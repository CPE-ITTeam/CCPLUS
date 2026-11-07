<!-- components/tables/DatasetViewer.vue -->
<script setup>
// 1. MODULE & REUSABLE ELEMENT IMPORTS
import { ref, reactive, watch, onBeforeMount, computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { fyMonths } from '@/components/ui/YmInput.vue';
import { tableSetup } from '@/config/dataTableConfig.js';
import api from '@/services/api.js'; // 👈 IMPORT THE DECOUPLED INTERCEPTOR SERVICE

//import { exportDatasetToExcel } from '@/utils/excelExporter.js'; 
import { exportDatasetToExcel } from '@/utils/excelJsExporter.js';


import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue'
import DataToolbar from './DataToolbar.vue';
import DataTable from './DataTable.vue';
import DataForm from '@/components/tables/DataForm.vue';
import PlatformForm from '@/components/forms/PlatformForm.vue';
import CredentialsForm from '@/components/forms/CredentialsForm.vue';
import ReportToggle from '@/components/dialogs/ReportToggle.vue';

import Swal from 'sweetalert2';

// 2. ROOT ACCOUNT ENVIRONMENT INITIALIZATION
const authStore = useAuthStore();
const is_admin = authStore.is_admin;
const is_conso_admin = authStore.is_conso_admin;

// Map the old signatures locally onto the clean api layout so downstream code is safe!
// 🔄 UPDATE TO THIS (Clean direct pass-throughs):
const { setConso } = authStore;
const ccGet = (url) => api.get(url);
const ccPost = (url, content) => api.post(url, content);
const ccPatch = (url, content) => api.patch(url, content);
const ccDestroy = (url) => api.delete(url);

var consoKey = ref(authStore.ccp_key);

const props = defineProps({
  datasetKey: { type: String, required: true }
});
const emit = defineEmits(['updateConso', 'setFilter']);

const datasetConfig = { ...tableSetup };

// 3. REACTIVE TRACKING ENVIRONMENT STATE
var dtKey = ref(0);
var formDialogType = ref('');
var formDialogTitle = ref('Item');
var reptDialogSubtitle = ref('Item');
var allItems = reactive([]);
var filteredItems = reactive([]);
var reptItem = reactive({});
var newType = reactive({ id: null, name: '' });
var platformExportItems = reactive([]);
var allOptions = {};
var bulkOptions = ref([]);
const filterOptions = reactive({});
const headers = ref([]);
const searchFields = ref([]);
const search = ref('');
const selectedRows = ref([]);
const showSelectedOnly = ref(false);
const formDialogOpen = ref(false);
const editingItem = ref(null);
const reptDialog = ref(false);
const typeDialog = ref(false);
const editableFields = ref([]);
const urlRoot = ref('');
const emptyFlags = {
  'available': true, 'conso': false, 'groups': [], 'insts': [],
  'requested': false, 'sortval': 1
};
const refreshable = ['harvests', 'jobs', 'audit'];
var dtLoading = ref(false);
var truncated = ref(false);
var success = ref('');
var failure = ref('');


// 4. COMPUTED EXPRESSION HOOKS
const isEditable = computed(() => {
  const config = datasetConfig[props.datasetKey];
  return (editingItem.value && config && editableFields.value.length > 0);
});

const selectableRows = computed(() => {
  if (typeof (bulkOptions.value.items) == 'undefined') return false;
  return (bulkOptions.value.items.length > 0);
});

const toolbarFilters = computed(() => {
  var rows = [];
  const keys = Object.keys(filterOptions).filter(key => filterOptions[key]['show']);
  for (let i = 0; i < keys.length; i += 4) {
    const row = {};
    const rKeys = keys.slice(i, i + 4);
    rKeys.forEach(key => { row[key] = { ...filterOptions[key] }; });
    rows.push({ ...row });
  }
  return rows;
});

const formSchema = computed(() => {
  const config = datasetConfig[props.datasetKey];
  return {
    dataset: props.datasetKey,
    type: formDialogType.value,
    fields: [...config.fields],
    requiredKeys: [...config.required],
    options: { ...allOptions },
  };
});

// CENTRAL ARCHITECTURAL IMPROVEMENT: Resolve form card instances dynamically
const formComponent = computed(() => {
  if (props.datasetKey === 'platforms') return PlatformForm;
  if (props.datasetKey === 'credentials') return CredentialsForm;
  return DataForm;
});

const capDataset = computed(() => {
  return (props.datasetKey) ? props.datasetKey.charAt(0).toUpperCase() + props.datasetKey.slice(1) : "";
});

// Inside resources/js/components/shared/DatasetViewer.vue
const exportable = computed(() => {
  const config = datasetConfig[props.datasetKey];

  // FAILSAFE LAYER: 
  // If 'exportFields' does not exist in the config layout matrix, 
  // safely evaluate to false instead of crashing on .length!
  if (!config || !config.exportFields) return false;

  return (config.exportFields.length > 0);
});

// 5. ASYNCHRONOUS EXPORT & DATA WRITERS
const exportInstScope = computed(() => {
  if (typeof (filterOptions.institutions) == 'undefined' && typeof (filterOptions.groups) == 'undefined') return "";
  if ((props.datasetKey == 'groups')) {
    let _scope = "_AllGroups";
    if (typeof (filterOptions.types) != 'undefined') {
      var ftype = filterOptions.types.items.find(t => t.id == filterOptions.types['value']);
      if (typeof (ftype) != 'undefined') _scope = "_" + ftype.name;
    }
    return _scope;
  }
  if (typeof (filterOptions.groups) != 'undefined') {
    let group_name = "";
    if (filterOptions.groups['value'].length == 0 && typeof (filterOptions.institutions) == 'undefined') {
      group_name = (props.datasetKey == 'institutions') ? "_AllInstitutions" : "_AllGroups";
    } else if (filterOptions.groups['value'].length == 1) {
      var _grp = filterOptions.groups.items.find(g => g.id == filterOptions.groups['value'][0]);
      if (typeof (_grp) != 'undefined') group_name = "_" + _grp.name;
    } else if (filterOptions.groups['value'].length > 1) {
      group_name = "_SomeGroups";
    }
    if (group_name != "") return group_name;
  }
  let inst_name = "";
  if (typeof (filterOptions.institutions) != 'undefined') {
    if (Array.isArray(filterOptions.institutions['value'])) {
      if (filterOptions.institutions['value'].length == 0) {
        inst_name = "_AllInstitutions";
      } else if (filterOptions.institutions['value'].length == 1) {
        var _inst = filterOptions.institutions.items.find(g => g.id == filterOptions.institutions['value'][0]);
        if (typeof (_inst) != 'undefined') inst_name = "_" + _inst.name;
      } else if (filterOptions.institutions['value'].length > 1) {
        inst_name = "_SomeInstitutions";
      }
    } else {
      if (filterOptions.institutions['value'] == '' || filterOptions.institutions['value'] == null) {
        inst_name = "_AllInstitutions";
      } else {
        var _inst = filterOptions.institutions.items.find(g => g.id == filterOptions.institutions['value']);
        inst_name = (typeof (_inst) != 'undefined') ? "_" + _inst.name : "_" + filterOptions.institutions['value'];
      }
    }
  }
  return inst_name;
});

const exportPlatScope = computed(() => {
  if (typeof (filterOptions.platforms) == 'undefined') return '';
  let plat_name = "";
  if (Array.isArray(filterOptions.platforms['value'])) {
    if (filterOptions.platforms['value'].length == 0) {
      plat_name = "_AllPlatforms";
    } else if (filterOptions.platforms['value'].length == 1) {
      var _plat = filterOptions.platforms.items.find(g => g.id == filterOptions.platforms['value'][0]);
      if (typeof (_plat) != 'undefined') plat_name = _plat.name;
    } else {
      plat_name = "_SomePlatforms";
    }
  } else {
    plat_name = (filterOptions.platforms['value'] != '' && filterOptions.platforms['value'] != null)
      ? "_" + filterOptions.platforms['value'] : "_AllPlatforms";
  }
  return plat_name;
});

const exportStatus = computed(() => {
  if (typeof (filterOptions.statuses) == 'undefined') return '';
  let stat_name = "";
  if (Array.isArray(filterOptions.statuses['value'])) {
    if (filterOptions.statuses['value'].length <= 1) {
      stat_name = (filterOptions.statuses['value'].length == 1) ? "_" + filterOptions.statuses['value'][0] : "";
    } else {
      stat_name = "_SomeStatuses";
    }
  } else {
    if (filterOptions.statuses['value'] != '' && filterOptions.statuses['value'] != null) {
      stat_name = "_" + filterOptions.statuses['value'];
    }
  }
  return stat_name;
});
const exportDataRows = computed(() => {
  const config = datasetConfig[props.datasetKey];

  // Safely returns an empty array if exportFields doesn't exist
  if (!config || !config.exportFields || config.exportFields.length == 0) return [];

  let exportItems = (config.title == 'Platform') ? [...platformExportItems] : [...filteredItems];

  // ─── DEFENSIVE SELECTION FILTER ───
  if (selectedRows.value.length > 0) {
    exportItems = exportItems.filter(itm => {
      return selectedRows.value.some(row => {
        // 1. Direct ID comparison
        if (row.id !== undefined && itm.id !== undefined && row.id == itm.id) return true;
        
        // 2. Platform-specific key fallback (e.g., checking platform_id or matching by name string)
        if (config.title == 'Platform') {
          const rowPlatId = row.platform_id || row.id;
          const itmPlatId = itm.platform_id || itm.id;
          if (rowPlatId !== undefined && itmPlatId !== undefined && rowPlatId == itmPlatId) return true;
          
          // 3. Absolute safety fallback: Match by unique name if platform IDs are missing from the raw export
          if (row.name && itm.name && row.name === itm.name) return true;
        }
        
        return false;
      });
    });
  }

  // ─── MAP TO SPREADSHEET HEADERS ───
  return exportItems.map(row => {
    return config.exportFields.reduce((newRow, col) => {
      let fld = config.fields.find(f => f.name == col);
      let label = (typeof (fld) != 'undefined') ? fld.label : col;
      if (col in row) {
        if (Array.isArray(row[col])) {
          newRow[label] = row[col].join();
        } else {
          newRow[label] = row[col];
        }
      }
      return newRow;
    }, {});
  });
});

// ============================================================================
// PART 6: PIPELINE CONTROLLER METHODS
// ============================================================================

// Load/Refresh dataset items with filters applied (uses POST route)
const refreshData = async (datasetKey) => {
  const config = datasetConfig[datasetKey];
  dtLoading.value = true;
  let _filters = {};
  Object.keys(filterOptions).forEach(key => {
    _filters[key] = filterOptions[key]['value'];
  });
  try {
    if (consoKey.value == '' && datasetKey != 'consortia') return;

    // Detect if this is one of your global database layer routes
    const isGlobalTable = config.urlRoot.includes('/global') ||
      ['platforms', 'publishers', 'providers', 'registries', 'titles', 'databases', 'items', 'datahosts', 'errors'].includes(datasetKey);

    let response;
    if (isGlobalTable) {
      // Global tier tables match base routes directly (no /getItems) and expect a GET request
      const axiosResponse = await api.get(config.urlRoot, { params: { filters: _filters, type: datasetKey } });
      response = axiosResponse.data;
    } else {
      // Standard tenant/consortium datasets use the regular backend route and POST method
      let itemsUrl = config.urlRoot + '/getItems';
      response = await ccPost(itemsUrl, { filters: _filters, type: datasetKey });
    }

    if (response && response.result) {
      truncated.value = response.truncated || false;

      // ✅ NATIVE VUE 3 SOLUTION: Mutate the reactive array proxies in-place
      allItems.splice(0, allItems.length, ...response.records);
      filteredItems.splice(0, filteredItems.length, ...response.records);

      selectedRows.value = [];

      // Run your standard internal filter/sorting mapping
      if (typeof updateItems === 'function') {
        updateItems();
      }

      dtKey.value++;
    }



  } catch (error) {
    console.log('Error refreshing records for ' + datasetKey + ' : ', error);
  }
  dtLoading.value = false;
};

// Load base dataset parameters and layout matrices on folder changes
const loadDataset = async (datasetKey) => {
  const config = datasetConfig[datasetKey];
  try {
    if (consoKey.value == '' && datasetKey != 'consortia') return;
    dtLoading.value = true;
    let itemsUrl = config.urlRoot + '/get';
    if (datasetKey == 'institutions') {
      itemsUrl += (is_admin) ? '/admin' : '/viewer';
    }
    const { data } = await ccGet(itemsUrl);
    truncated.value = (data.truncated) ? data.truncated : false;
    allItems = [...data.records];
    filteredItems = [...data.records];
    allOptions = { ...data.options };
  } catch (error) {
    console.log('Error fetching records for ' + datasetKey + ' : ', error);
  }
  // Standard initialization framework setup
  bulkOptions.value = { 'dataset': props.datasetKey, 'items': [] };

  // AUTOMATED ARCHITECTURAL SAFEGUARD:
  // Verifies that bulkOptions exists and is a valid array structure before cloning it.
  // This completely stops undefined or empty configuration parameters from crashing the compiler!
  if (config.bulkOptions && Array.isArray(config.bulkOptions) && config.bulkOptions.length > 0) {
    bulkOptions.value.items = [...config.bulkOptions];
  } else {
    bulkOptions.value.items = [];
  }

  // Safe institutional grouping check allocations
  if (props.datasetKey == 'institutions' && typeof (allOptions.groups) !== 'undefined') {
    bulkOptions.value['groups'] = [...allOptions.groups];
  }


  headers.value = [{ title: "", key: "" }];
  config.fields.forEach((fld, idx) => {
    if (fld.header != null) {
      if (datasetKey == 'harvests' && fld.name == 'error') {
        headers.value.push({
          title: fld.label, key: fld.name, align: fld.header,
          type: fld.type, // 💡 Forwards 'date' type designator
          sort: (a, b) => { return Number(a.id) - Number(b.id); }
        });
      } else {
        headers.value.push({ 
          title: fld.label, 
          key: fld.name, 
          align: fld.header,
          type: fld.type // 💡 Forwards 'date' type designator
        });
      }
    }

    if (fld.name == 'conso' && !is_conso_admin) return;
    config.fields[idx].static = config.static.includes(fld.name);
    const _options = (typeof (fld.options) != 'undefined') ? fld.options : [];
    var f_options = (typeof (allOptions[fld.name]) != 'undefined') ? allOptions[fld.name] : _options;

    if ((fld.type == 'select' || fld.type == 'mselect' || fld.type == 'selectObj' || fld.type == 'toggle') &&
      fld.options == 'fromURL' && typeof (allOptions[fld.name]) != 'undefined') {
      if (Array.isArray(allOptions[fld.name]) && ((props.datasetKey == 'roles' && fld.name == 'role') ||
        typeof (fld.optTxt) == 'undefined' || typeof (fld.optVal) == 'undefined')) {
        if (allItems.length > 0) {
          f_options = allOptions[fld.name].filter(
            opt => allItems.flatMap(itm => itm[fld.filterCol]).includes(opt[fld.optVal])
          );
        }
        filterOptions[fld.name] = {
          'name': fld.name, 'label': fld.label, 'type': 'text', 'show': fld.isFilter, 'col': fld.filterCol,
          'items': [...f_options], 'value': null
        };
      } else {
        let f_options = [];
        let _showIt = false;
        let initVal = (fld.type == 'mselect') ? [] : null;
        if (refreshable.includes(datasetKey)) {
          f_options = [...allOptions[fld.name]];
          _showIt = true;
        } else if (allItems.length > 0 && Array.isArray(allOptions[fld.name])) {
          f_options = allOptions[fld.name].filter(
            opt => allItems.flatMap(itm => itm[fld.filterCol]).includes(opt[fld.optVal])
          );
          _showIt = (f_options.length == 0 && !refreshable.includes(datasetKey)) ? false : fld.isFilter;
        }
        filterOptions[fld.name] = {
          'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'show': _showIt, 'col': fld.filterCol, 'items': [...f_options], 'value': initVal
        };
      }
    } else if (fld.type == 'toggle' && fld.isFilter && f_options.length > 0) {
      filterOptions[fld.name] = {
        'name': fld.name, 'label': fld.label, 'type': 'toggle', 'show': fld.isFilter, 'col': fld.filterCol,
        'items': [...f_options], 'value': f_options
      };
    } else if (fld.isFilter && (fld.type == 'text' || fld.type == 'mtext')) {
      filterOptions[fld.name] = {
        'name': fld.name, 'label': fld.label, 'type': fld.type, 'show': true, 'col': fld.filterCol
      };
      filterOptions[fld.name]['value'] = (fld.type == 'text') ? null : [];
      if (allItems.length > 0 && Array.isArray(allOptions[fld.name])) {
        f_options = allOptions[fld.name].filter(
          opt => allItems.flatMap(itm => itm[fld.filterCol]).includes(opt[fld.optVal])
        );
      }
      filterOptions[fld.name]['items'] = (fld.options == 'fromURL' && Array.isArray(allOptions[fld.name]))
        ? [...allOptions[fld.name]] : [...fld.options];
    } else if (Array.isArray(fld.options)) {
      if (allItems.length > 0) {
        f_options = fld.options.filter(
          opt => allItems.flatMap(itm => itm[fld.filterCol]).includes(opt[fld.optVal])
        );
      }
      filterOptions[fld.name] = {
        'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
        'show': fld.isFilter, 'col': fld.filterCol, 'items': [...f_options], 'value': null
      };
    } else if (fld.name == 'fiscalYr') {
      allOptions['fiscalYr'] = [...fyMonths];
    }
  });
  searchFields.value = config.fields.filter(fld => fld.searchable).map(f => f.name);
  editableFields.value = config.fields.filter(fld => fld.editable).map(f => f.name);
  urlRoot.value = config.urlRoot;
  dtLoading.value = false;
};

async function handleExport() {
  const config = datasetConfig[props.datasetKey];

  // ─── RESTORED ORIGINAL LIFECYCLE ORDER ───
  // Re-fetch the platform dataset records first so the reactive exportDataRows 
  // computed filter properties have time to recalculate exactly like the Git version!
  if (config.title == 'Platform') {
    try {
      const { data } = await ccGet('/api/platforms/exportData');
      // Splice in-place to preserve Vue's reactive proxy boundaries safely
      platformExportItems.splice(0, platformExportItems.length, ...data.records);
    } catch (error) {
      console.error("Platform pre-fetch network error before export:", error);
    }
  }

  // Now exportDataRows.value is fully calculated, filtered, and safe to evaluate lazily
  await exportDatasetToExcel({
    datasetKey: props.datasetKey,
    title: config.title,
    consoKey: consoKey.value,
    capDataset: capDataset.value,
    exportDataRows: exportDataRows.value, // Fully filtered array matrix
    filterOptions: filterOptions,
    exportInstScope: exportInstScope.value,
    exportPlatScope: exportPlatScope.value,
    exportStatus: exportStatus.value
  });
}


function handleToggle(value) {
  showSelectedOnly.value = value;
  if (value) search.value = '';
}

async function handleChangeConso(conso) {
  consoKey.value = conso.ccp_key;
  await setConso(conso.id, conso.ccp_key);
  loadDataset(props.datasetKey);
  emit('updateConso', conso);
  dtKey.value++;
}

function handleImported(data) {
  if (data.length > 0) success.value = data;
  loadDataset(props.datasetKey);
  dtKey.value++;
}

function handleEdit(item) {
  const config = datasetConfig[props.datasetKey];
  formDialogType.value = "Edit";
  formDialogTitle.value = "Edit " + config.title;
  config.fields.forEach((fld, idx) => {
    if (props.datasetKey == 'institutions' && fld.name == 'creds') {
      config.fields[idx]['visible'] = false;
    } else {
      config.fields[idx]['visible'] = true;
    }
    if (fld.type == 'select' && fld.name == 'types') {
      item['types'] = item.type_id;
      item['reset_type'] = false;
    }
    if (fld.type == 'select' && fld.name == 'institutions') item['institutions'] = item.inst_id;
  });
  editingItem.value = { ...item };
  formDialogOpen.value = true;
}
// ============================================================================
// PART 5 (Continued): DIALOG AND RECONCILIATION METHODS
// ============================================================================

function handleAddItem() {
  const config = datasetConfig[props.datasetKey];
  if (props.datasetKey == 'connections') {
    addConnection();
    return;
  }
  formDialogType.value = "Add";
  formDialogTitle.value = "Add New " + config.title;
  editingItem.value = {};
  config.fields.forEach((fld, idx) => {
    if (!config.required.includes(fld.name)) return;
    if (fld.type == 'toggle') {
      editingItem.value[fld.name] = fld.addValue;
    } else {
      if (fld.name == 'report_state') {
        if (typeof (formSchema.value.options['master_reports']) != 'undefined') {
          let _state = {};
          formSchema.value.options['master_reports'].forEach(rpt => _state[rpt.name] = false);
          editingItem.value[fld.name] = _state;
        } else {
          editingItem.value[fld.name] = { PR: false, DR: false, TR: false, IR: false };
        }
      } else if (fld.name == 'connector_state') {
        if (typeof (formSchema.value.options['all_connectors']) != 'undefined') {
          let _state = {};
          formSchema.value.options['all_connectors'].forEach(cnx => _state[cnx.name] = false);
          editingItem.value[fld.name] = _state;
        } else {
          editingItem.value[fld.name] = { customer_id: false, requestor_id: false, api_key: false, extra_args: false };
        }
      } else {
        editingItem.value[fld.name] = null;
      }
    }
    config.fields[idx]['visible'] = true;
  });
  formDialogOpen.value = true;
}

function addConnection() {
  reptItem = { 'type': 'Add', 'id': null, 'rept': '', 'flags': emptyFlags, 'connections': [...allItems] };
  reptDialog.value = true;
}
const refreshProgress = ref({ active: false, current: 0, total: 0, name: '', cancelling: false });
let registryPollTimer = null;

function startRegistryProgress() {
  stopRegistryProgress();
  refreshProgress.value = { active: true, current: 0, total: 0, name: 'Fetching registry list...', cancelling: false };
  registryPollTimer = setTimeout(pollRegistryProgress, 1200);
}


async function pollRegistryProgress() {
  if (!refreshProgress.value.active) return;
  try {
    const { data } = await api.get('/api/global/registries/progress');
    if (!refreshProgress.value.active) return;

    if (data.status === 'running') {
      refreshProgress.value.current = data.current || 0;
      refreshProgress.value.total = data.total || 0;
      refreshProgress.value.name = data.name || '';
    } else if (data.status === 'done' || data.status === 'cancelled') {
      stopRegistryProgress();
      return;
    }
  } catch { }

  // Schedule the next poll ONLY after the current one completes
  if (refreshProgress.value.active) {
    registryPollTimer = setTimeout(pollRegistryProgress, 1500);
  }
}

function stopRegistryProgress() {
  if (registryPollTimer) clearTimeout(registryPollTimer);
  registryPollTimer = null;
  refreshProgress.value.active = false;
}

async function cancelRegistryRefresh() {
  refreshProgress.value.cancelling = true;
  try {
    await api.get('/api/global/registries/cancel');
  } catch { }
  stopRegistryProgress();
}



async function handleBulk(data) {
  if (data.action == 'Assign Type') {
    newType = { id: null, name: '' };
    typeDialog.value = true;
    return;
  }

  const isRegistryRefresh = (data.action == 'Refresh Registry' || data.action == 'Full Refresh');
  var bulkUrl = urlRoot.value + (isRegistryRefresh ? '/refresh' : '/bulk');
  var args = (data.action != 'Full Refresh') ? { ids: selectedRows.value.map(ii => ii.id) } : { ids: 'ALL' };
  Object.keys(data).forEach((key) => { args[key] = data[key]; });

  if (isRegistryRefresh) startRegistryProgress();

  try {
    dtLoading.value = true;
    const response = await ccPost(bulkUrl, args);

    if (response.result && allItems.length > 0) {
      if (data.action == 'Set Active' || data.action == 'Set Inactive') {
        if (response.affectedIds.length > 0) {
          allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach(itm => {
            if (typeof (itm.is_active) != 'undefined') itm.is_active = (data.action == 'Set Active') ? 1 : 0;
            if (typeof (itm.status) != 'undefined') itm.status = (data.action == 'Set Active') ? 'Active' : 'Inactive';
          });
        }
      } else if (data.action == 'Create New Group' || data.action == 'Add to Existing Group') {
        if (response.affectedIds.length > 0 && typeof (allItems['group_string']) != 'undefined') {
          if (data.action == 'Create New Group') {
            filterOptions.groups.items.push({ 'id': response.group.id, 'name': response.group.name });
          }
          allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach(itm => {
            itm.group_string += (itm.group_string.length > 0) ? ',' : '';
            itm.group_string += response.group.name;
            if (Array.isArray(itm.group_ids)) itm.group_ids.push(response.group.id);
          });
        }
        updateItems();
        dtKey.value++;
      } else if (data.action == 'Enable') {
        if (response.affectedItems.length > 0 && typeof (allItems['status']) != 'undefined') {
          response.affectedItems.forEach(ritm => {
            var idx = allItems.findIndex(itm => itm.id == ritm.id);
            if (idx >= 0) allItems[idx]['status'] = ritm.status;
          });
        }
      } else if (data.action == 'Disable') {
        if (response.affectedIds.length > 0 && typeof (allItems['status']) != 'undefined') {
          allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach(itm => { itm.status = 'Disabled'; });
        }
      } else if (data.action == 'Delete' || data.action == 'Kill') {
        if (response.affectedIds.length > 0) {
          response.affectedIds.forEach(itemId => {
            let aIdx = allItems.findIndex(ii => ii.id == itemId);
            if (aIdx >= 0) allItems.splice(aIdx, 1);
            let fIdx = filteredItems.findIndex(ii => ii.id == itemId);
            if (fIdx >= 0) filteredItems.splice(fIdx, 1);
          });
          dtKey.value++;
        }
      } else if (data.action == 'Refresh Registry' || data.action == 'Full Refresh') {
        if (response.affectedItems.length > 0) {
          let new_platforms = false;
          response.affectedItems.forEach(plat => {
            let _idx = allItems.findIndex(itm => itm.id == plat.id);
            if (_idx < 0) {
              allItems.push(plat);
              new_platforms = true;
            } else {
              allItems.splice(_idx, 1, plat);

              // 🚀 NATIVE DOM INJECTION: Find and update the table row cell instantly
              const targetDate = plat.updated || (plat.updated_at ? plat.updated_at.substring(0, 16).replace('T', ' ') : '');
              if (targetDate) {
                // Find all table rows matching this specific platform ID
                const rows = document.querySelectorAll('tr');
                rows.forEach(row => {
                  // Check if the row belongs to the current platform row item
                  if (row.innerText.includes(plat.name)) {
                    // Find cells containing date-like strings or an 'updated' column class
                    const cells = row.querySelectorAll('td');
                    cells.forEach(cell => {
                      if (cell.innerText.includes('-') && cell.innerText.includes(':')) {
                        cell.innerText = targetDate;
                      }
                    });
                  }
                });
              }
            }
          });
          if (response.summary != "") {
            Swal.fire({
              title: 'Refresh Results', html: response.summary, icon: 'info', confirmButtonText: 'Close'
            });
          }
          if (new_platforms) {
            allItems.sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()));
          }
        }

        if (typeof updateItems === 'function') {
          updateItems();
        }
        dtKey.value++;

      } else if (data.action == 'Set Institution Type') {
        if (response.affectedIds.length > 0 && newType.id != null) {
          allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach(itm => {
            itm.type = newType.name;
            itm.type_id = newType.id;
          });
          typeDialog.value = false;
        }
      } else if (data.action == 'Restart' || data.action == 'Pause') {
        response.affectedItems.forEach(newItem => {
          let idx = allItems.findIndex(ii => ii.id == newItem.id);
          if (idx >= 0) allItems.splice(idx, 1, newItem);
        });
        updateItems();
        dtKey.value++;
      } else if (data.action.includes(' Validat')) {
        allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach(itm => {
          Object.entries(response.data).forEach(([col, value]) => {
            itm[col] = (value == null) ? 'Inactive' : 'Active';
          });
        });
      }
      success.value = response.msg;
    } else {
      failure.value = response.msg;
    }
  } catch (error) {
    console.log('Error processing bulk request: ', error);
  } finally {
    dtLoading.value = false;
    stopRegistryProgress();
  }
}


function handleFilter(filt) {
  if (filt.key == 'reset') {
    selectedRows.value = [];
    for (const key of Object.keys(filterOptions)) {
      if (key.includes('institution')) {
        if (typeof (filterOptions.group) != 'undefined') filterOptions.group.show = true;
        if (typeof (filterOptions.groups) != 'undefined') filterOptions.groups.show = true;
      }
      if (key.includes('group')) {
        if (typeof (filterOptions.institution) != 'undefined') filterOptions.institution.show = true;
        if (typeof (filterOptions.institutions) != 'undefined') filterOptions.institutions.show = true;
      }
      if (filterOptions[key]['items'].length == 0) filterOptions[key]['show'] = false;
      if (!filterOptions[key]['show']) continue;
      if (Array.isArray(filterOptions[key]['value'])) {
        filterOptions[key]['value'] = [];
      } else if (filterOptions[key]['value']) {
        filterOptions[key]['value'] = (filterOptions[key]['type'] == 'toggle') ? 'Inactive' : null;
      }
    }
    updateItems();
    dtKey.value++;
  } else if (typeof (filterOptions[filt.key]['value']) != 'undefined') {
    filterOptions[filt.key]['value'] = filt.value;
    if (filt.key.includes('institution') || filt.key.includes('group')) {
      let cleared = Array.isArray(filterOptions[filt.key]['value'])
        ? filterOptions[filt.key]['value'].length == 0
        : (filterOptions[filt.key]['value'] == null || filterOptions[filt.key]['value'] == "");
      if (filt.key.includes('institution')) {
        if (typeof (filterOptions.group) != 'undefined') filterOptions.group.show = cleared;
        if (typeof (filterOptions.groups) != 'undefined') filterOptions.groups.show = cleared;
      }
      if (filt.key.includes('group')) {
        if (typeof (filterOptions.institution) != 'undefined') filterOptions.institution.show = cleared;
        if (typeof (filterOptions.institutions) != 'undefined') filterOptions.institutions.show = cleared;
      }
    }
    updateItems();
    dtKey.value++;
  }
}

function updateItems() {
  if (allItems.length == 0) return;
  if (props.datasetKey == 'harvests') {
    filteredItems = [];
    return;
  }
  var filterResult = [...allItems];
  for (const key of Object.keys(filterOptions)) {
    const filter = filterOptions[key];
    if (!filter.show) continue;
    if (filterResult.length == 0) break;
    if (Array.isArray(filter.value)) {
      if (filter.value.length == 0) continue;
    } else if (!filter.value) {
      continue;
    }

    if (filter.type == 'select' || filter.type == 'text') {
      if (Array.isArray(allItems[filter.col])) {
        filterResult = filterResult.filter(item => item[filter.col].some(f => f == filter.value));
      } else {
        filterResult = filterResult.filter(item => item[filter.col] == filter.value);
      }
    } else if (filter.type == 'toggle') {
      if (filter.value == 'Active') {
        filterResult = filterResult.filter(item => (item[filter.col]));
      } if (filter.col == 'is_conso' && typeof (filterOptions.platforms) != 'undefined') {
        filterOptions.platforms.items = (filter.value == 'Active')
          ? filterOptions.platforms.items.filter(itm => itm.is_conso) : [...allOptions.platforms];
      }
    } else if (filter.type == 'mselect' || filter.type == 'mtext') {
      if (Array.isArray(allItems[filter.col])) {
        filterResult = filterResult.filter(item => item[filter.col].some(f => filter.value.includes(f)));
      } else {
        filterResult = filterResult.filter(item => filter.value.includes(item[filter.col]));
      }
    }
  }
  filteredItems = [...filterResult];
}

async function handleDelete(id) {
  let destroyUrl = urlRoot.value + '/delete/' + id;
  try {
    const response = await ccDestroy(destroyUrl);
    if (response.result) {
      let a_idx = allItems.findIndex(ii => ii.id == id);
      let f_idx = filteredItems.findIndex(ii => ii.id == id);
      if (props.datasetKey == 'connections') {
        let cnx = { ...allItems[a_idx] };
        cnx.can_delete = false;
        allOptions['reports'].forEach(rpt => {
          let _key = rpt.name.toLowerCase() + '_insts';
          cnx[_key] = [];
          _key = rpt.name.toLowerCase() + '_groups';
          cnx[_key] = [];
          cnx[rpt.name]['conso'] = false;
          cnx[rpt.name]['insts'] = [];
          cnx[rpt.name]['groups'] = [];
          cnx[rpt.name]['requested'] = false;
        });
        allItems.splice(a_idx, 1, cnx);
        filteredItems.splice(f_idx, 1, cnx);
      } else {
        allItems.splice(a_idx, 1);
        filteredItems.splice(f_idx, 1);
      }
      success.value = response.msg;
      dtKey.value++;
    } else {
      failure.value = response.msg;
    }
  } catch (error) {
    console.log('Error deleting:', error);
  }
}

async function handleToggleUpdate(id, field, value) {
  let _idx = allItems.findIndex(ii => ii.id == id);
  if (_idx >= 0) {
    let _item = Object.assign({}, allItems[_idx]);
    if (field == 'status') {
      _item['is_active'] = (value == 'Active') ? 1 : 0;
      try {
        let url = urlRoot.value + '/update/' + id;
        const response = await ccPatch(url, { 'is_active': _item['is_active'] });
        if (response.result) {
          _item['status'] = value;
          allItems.splice(_idx, 1, _item);
          let fIdx = filteredItems.findIndex(ii => ii.id == id);
          if (fIdx >= 0) filteredItems.splice(fIdx, 1, _item);
          dtKey.value++;
        } else {
          failure.value = response.msg;
        }
      } catch (error) {
        console.log('Error updating status', error);
      }
    }
    if (field == 'inst_valid' || field == 'plat_valid') {
      let _arg = {};
      _arg[field] = value;
      try {
        let url = urlRoot.value + '/update/' + id;
        const response = await ccPatch(url, _arg);
        if (response.result) {
          _item[field] = value;
          if (_item['inst_valid'] == 'Active' && _item['plat_valid'] == 'Active') {
            _item['valid_state'] = ['Fully Validated'];
          } else {
            _item['valid_state'] = [];
            if (_item['inst_valid'] == 'Inactive') _item['valid_state'].push('Institution Not Validated');
            if (_item['plat_valid'] == 'Inactive') _item['valid_state'].push('Platform Not Validated');
            if (_item['inst_valid'] == 'Inactive' && _item['plat_valid'] == 'Inactive') {
              _item['valid_state'].push('Neither Validated');
            }
          }
          allItems.splice(_idx, 1, _item);
          let fIdx = filteredItems.findIndex(ii => ii.id == id);
          if (fIdx >= 0) filteredItems.splice(fIdx, 1, _item);
          dtKey.value++;
        } else {
          failure.value = response.msg;
        }
      } catch (error) {
        console.log('Error updating status', error);
      }
    }
  }
}

async function reportToggleSubmit(data) {
  let a_idx = allItems.findIndex(ii => ii.id == data.id);
  if (a_idx >= 0) {
    let _item = Object.assign({}, allItems[a_idx]);
    try {
      const response = await ccPost("/api/connections/access", { id: data.id, rept: data.rept, flags: data.flags });
      if (response.result) {
        _item[data.rept] = { ...response.record };
        reptItem.flags = { ...data.flags };
        allItems.splice(a_idx, 1, _item);
        let f_idx = filteredItems.findIndex(fi => fi.id == data.id);
        if (f_idx >= 0) filteredItems.splice(f_idx, 1, _item);
        dtKey.value++;
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.log('Error updating report toggle', error);
    }
  }
  reptDialog.value = false;
}

function handleReportToggle(id, rept) {
  let _idx = allItems.findIndex(ii => ii.id == id);
  if (_idx >= 0) {
    var theItem = Object.assign({}, allItems[_idx]);
    if (props.datasetKey == 'connections') {
      reptItem = { 'type': 'Edit', 'id': id, 'rept': rept, 'flags': { ...theItem[rept] } };
      reptDialogSubtitle.value = (typeof (theItem.platform) != 'undefined') ? theItem.platform : "";
      reptDialog.value = true;
    } else if (props.datasetKey == 'credentials') {
      handleEdit(theItem);
    }
  }
}

async function handleFormSubmit(updatedValues) {
  if (formDialogType.value == 'Edit') {
    try {
      let url = urlRoot.value + '/update/' + editingItem.value.id;
      const response = await ccPatch(url, updatedValues);
      if (response.result) {
        let a_idx = allItems.findIndex(ai => ai.id == editingItem.value.id);
        if (a_idx >= 0) allItems.splice(a_idx, 1, response.record);
        let f_idx = filteredItems.findIndex(fi => fi.id == editingItem.value.id);
        if (f_idx >= 0) filteredItems.splice(f_idx, 1, response.record);
        success.value = response.msg;
        updateItems();
        dtKey.value++;
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.log('Error updating:', error);
    }
  } else if (formDialogType.value == 'Add') {
    try {
      let url = urlRoot.value + '/store';
      const response = await ccPost(url, updatedValues);
      if (response.result) {
        allItems.push(response.record);
        success.value = response.msg;
        updateItems();
        dtKey.value++;
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.log('Error adding:', error);
    }
  }
  formDialogOpen.value = false;
  editingItem.value = null;
}

function handleFormCancel() {
  formDialogOpen.value = false;
  editingItem.value = null;
  reptDialog.value = false;
  typeDialog.value = false;
}
// ============================================================================
// PART 7: REGISTRY SWITCHES & COMPONENT LIFECYCLE WATCHERS
// ============================================================================

// Component Mounting Lifecycle Setup
onBeforeMount(() => {
  loadDataset(props.datasetKey);
});

// Active Route Parameter Watcher
watch(
  () => props.datasetKey,
  (newKey) => {
    loadDataset(newKey);
  }
);


</script>

<!-- components/shared/DatasetViewer.vue -->
<template>
  <div class="ma-2">
    <v-sheet class="bg-transparent">

      <!-- 1. Toolbar Filters Wrapper (💡 FIXED: Restored all missing event pipelines) -->
      <DataToolbar v-model="toolbarFilters" :search="search" :dataset="props.datasetKey" :bulkOptions="bulkOptions"
        :selectedRows="selectedRows" :showSelectedOnly="showSelectedOnly"
        :hideExport="!exportable || filteredItems.length == 0" @updateConso="handleChangeConso" @export="handleExport"
        @setFilter="handleFilter" @bulkAction="handleBulk" @add="handleAddItem" @update:search="search = $event"
        @update:showSelectedOnly="handleToggle" @refreshRecords="refreshData(props.datasetKey)"
        @imported="handleImported" />

      <!-- Live COUNTER Registry Refresh Progress Banner (WCAG Accessible) -->
      <v-alert v-if="refreshProgress.active" type="info" variant="tonal" class="my-3 rounded-lg" aria-live="polite">
        <div class="d-flex align-center justify-space-between mb-2">
          <span>
            Refreshing COUNTER Registry:
            <strong>{{ refreshProgress.current }} / {{ refreshProgress.total || '...' }}</strong>
            <span v-if="refreshProgress.name" class="text-caption ml-2">({{ refreshProgress.name }})</span>
          </span>
          <v-btn size="small" color="error" variant="tonal" class="rounded-xl px-3"
            :disabled="refreshProgress.cancelling" aria-label="Cancel current COUNTER registry refresh process"
            @click="cancelRegistryRefresh">
            <v-icon start class="mr-1">mdi-close-circle-outline</v-icon>
            {{ refreshProgress.cancelling ? 'Stopping...' : 'Cancel' }}
          </v-btn>
        </div>
        <v-progress-linear
          :model-value="refreshProgress.total ? (refreshProgress.current / refreshProgress.total) * 100 : 0"
          :indeterminate="!refreshProgress.total" color="primary" height="8" rounded role="progressbar"
          :aria-valuenow="refreshProgress.current" aria-valuemin="0" :aria-valuemax="refreshProgress.total || 100" />
      </v-alert>


      <!-- 2. Status Messages: Native alerts -->
      <v-alert v-if="success" type="success" class="my-3 mx-4" :text="success" />
      <v-alert v-else-if="failure" type="error" class="my-3 mx-4" :text="failure" />

      <!-- 3. Primary Data Table Grid -->
      <DataTable v-if="consoKey != '' || props.datasetKey == 'consortia'" :items="filteredItems" :key="dtKey"
        :search="search" :headers="headers" :isLoading="dtLoading && !refreshProgress.active" :loadingColor="props.loadingColor"
        :dataset="props.datasetKey" :showSelectedOnly="showSelectedOnly" :editableFields="editableFields"
        :truncated="truncated" :searchFields="searchFields" :selectedRows="selectedRows"
        :selectableRows="selectableRows" @edit="handleEdit" @delete="handleDelete" @update:report="handleReportToggle"
        @update:selectedRows="selectedRows = $event" @update:toggle="handleToggleUpdate"
        @update:toggleSelectedOnly="handleToggle" />


      <!-- 4. Dialog One: Core Item Forms -->
      <v-dialog v-if="editingItem && isEditable" v-model="formDialogOpen">
        <BaseDialogLayout @close="handleFormCancel">
          <template #title>{{ formDialogTitle }}</template>

          <component :is="formComponent" :schema="formSchema" :initialValues="editingItem" @submit="handleFormSubmit"
            @cancel="handleFormCancel" />
        </BaseDialogLayout>
      </v-dialog>

      <!-- 5. Dialog Two: Report Connections -->
      <v-dialog v-model="reptDialog">
        <BaseDialogLayout @close="handleFormCancel">
          <template #title>
            {{ reptItem.type == 'Add' ? 'Add Report Connection(s)' : `${reptItem.rept} Report Connection(s)` }}
          </template>

          <!-- 💡 maps cleanly into the subtitle layout zone -->
          <template v-if="reptItem.type == 'Edit' && reptDialogSubtitle.length > 0" #subtitle>
            <div class="d-flex align-center justify-space-between w-100">
              <strong>{{ reptDialogSubtitle }}</strong>
              <span v-if="reptItem.id !== null" class="text-caption d-flex align-center">
                <v-icon size="small" class="mr-1">mdi-crosshairs-gps</v-icon> ID: {{ reptItem.id }}
              </span>
            </div>
          </template>

          <ReportToggle :item="reptItem" :options="allOptions" @submit="reportToggleSubmit"
            @cancel="handleFormCancel" />
        </BaseDialogLayout>
      </v-dialog>

      <!-- 6. Dialog Three: Bulk Action Type Selection -->
      <v-dialog v-model="typeDialog" max-width="500">
        <BaseDialogLayout content-class="pb-2" @close="typeDialog = false">
          <template #title>Update Institution Type</template>

          <template #subtitle>
            <strong>Updating Type For {{ selectedRows.length }} Institutions</strong>
          </template>

          <v-autocomplete v-model="newType" label="Type" :items="allOptions.type || []" item-title="name"
            item-value="id" density="compact" return-object />

          <template #actions>
            <v-btn @click="handleBulk({ action: 'Set Institution Type', type_id: newType.id })">Save</v-btn>
            <v-btn variant="text" class="ml-2" @click="handleFormCancel">Cancel</v-btn>
          </template>
        </BaseDialogLayout>
      </v-dialog>


    </v-sheet>
  </div>
</template>
