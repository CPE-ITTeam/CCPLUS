<!-- components/tables/DatasetViewer.vue -->
<script setup>
// ============================================================================
// PART 1: MODULES, REUSABLE COMPOSABLES & UI DIALOG IMPORTS
// ============================================================================
import { ref, reactive, watch, onBeforeMount, computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { fyMonths } from '@/components/ui/YmInput.vue';
import { tableSetup } from '@/config/dataTableConfig.js';
import api from '@/services/api.js';
import { useDatasetActions } from '@/composables/useDatasetActions.js';
import { useRegistryProgress } from '@/composables/useRegistryProgress.js';
import { useDatasetExporter } from '@/composables/useDatasetExporter.js';
import { useDatasetBulk } from '@/composables/useDatasetBulk.js';

import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';
import DataToolbar from './DataToolbar.vue';
import DataTable from './DataTable.vue';
import DataForm from '@/components/tables/DataForm.vue';
import PlatformForm from '@/components/forms/PlatformForm.vue';
import CredentialsForm from '@/components/forms/CredentialsForm.vue';
import ReportToggle from '@/components/dialogs/ReportToggle.vue';
import Swal from 'sweetalert2';

// ============================================================================
// PART 2: ACCOUNT LAYER INITIALIZATION & DECOUPLED BRIDGES
// ============================================================================
const authStore = useAuthStore();
const is_admin = authStore.is_admin;
const is_conso_admin = authStore.is_conso_admin;
const { setConso } = authStore;

const ccGet = (url) => api.get(url);
const ccPost = (url, content) => api.post(url, content);

var consoKey = ref(authStore.ccp_key);
const props = defineProps({ datasetKey: { type: String, required: true } });
const emit = defineEmits(['updateConso', 'setFilter']);
const datasetConfig = { ...tableSetup };

// ============================================================================
// PART 3: REACTIVE INSTANCE STATE MANAGEMENT & BASE INITIALIZATIONS
// ============================================================================
var dtKey = ref(0);
var formDialogType = ref('');
var formDialogTitle = ref('Item');
var reptDialogSubtitle = ref('Item');
const allItems = reactive([]);
const filteredItems = reactive([]);
var reptItem = reactive({});
var newType = reactive({ id: null, name: '' });
const platformExportItems = reactive([]);
var allOptions = reactive({});
var bulkOptions = ref({ dataset: props.datasetKey, items: [] });
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
const emptyFlags = { 'available': true, 'conso': false, 'groups': [], 'insts': [], 'requested': false, 'sortval': 1 };
const refreshable = ['harvests', 'jobs', 'audit'];
var dtLoading = ref(false);
var truncated = ref(false);
var success = ref('');
var failure = ref('');

// ─── 1. PURE PRESENTATIONAL COMPUTED STRINGS (Moved Up First) ───
const capDataset = computed(() => {
  return (props.datasetKey) ? props.datasetKey.charAt(0).toUpperCase() + props.datasetKey.slice(1) : "";
});

const formComponent = computed(() => {
  if (props.datasetKey === 'platforms') return PlatformForm;
  if (props.datasetKey === 'credentials') return CredentialsForm;
  return DataForm;
});

// ─── 2. COMPOSABLE INTERFACE WIRING (Safe execution order) ───
const { triggerExcelExport } = useDatasetExporter({
  props,
  datasetConfig,
  filterOptions,
  allItems,
  filteredItems,
  platformExportItems,
  selectedRows,
  consoKey,
  capDataset,
  fyMonths
});

const { isProcessing, saveItem, toggleStatusField, deleteItem } = useDatasetActions(urlRoot);

const {
  refreshProgress,
  startRegistryProgress,
  stopRegistryProgress,
  cancelRegistryRefresh
} = useRegistryProgress();

const { executeBulkAction, isBulkProcessing } = useDatasetBulk({
  urlRoot,
  allItems,
  filteredItems,
  selectedRows,
  typeDialog,
  newType,
  dtKey,
  updateItems: () => updateItems(), // 👈 FIXED: Lazily wrapped to prevent TDZ crash!
  startRegistryProgress,
  stopRegistryProgress
});// ============================================================================
// PART 4: COMPONENT VIEW LAYOUT COMPUTED EXPRESSIONS ONLY
// ============================================================================
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
    options: allOptions, // 👈 Direct reactive connection preserved for polymorphic forms
  };
});

const exportable = computed(() => {
  const config = datasetConfig[props.datasetKey];
  if (!config || !config.exportFields) return false;
  return (config.exportFields.length > 0);
});


// ============================================================================
// PART 5: CORE COMPONENT DATA LIFECYCLES (Pillar 4)
// ============================================================================
const refreshData = async (datasetKey) => {
  const config = datasetConfig[datasetKey];
  dtLoading.value = true;
  let _filters = {};
  Object.keys(filterOptions).forEach(key => { _filters[key] = filterOptions[key]['value']; });
  try {
    if (consoKey.value == '' && datasetKey != 'consortia') return;
    const isGlobalTable = config.urlRoot.includes('/global') ||
      ['platforms', 'publishers', 'providers', 'registries', 'titles', 'databases', 'items', 'datahosts', 'errors'].includes(datasetKey);
    let response;
    if (isGlobalTable) {
      const axiosResponse = await api.get(config.urlRoot, { params: { filters: _filters, type: datasetKey } });
      response = axiosResponse.data;
    } else {
      let itemsUrl = config.urlRoot + '/getItems';
      const axiosResponse = await api.post(itemsUrl, { filters: _filters, type: datasetKey });
      response = axiosResponse.data || axiosResponse;
    }
    if (response && response.result) {
      truncated.value = response.truncated || false;
      allItems.splice(0, allItems.length, ...response.records);
      filteredItems.splice(0, filteredItems.length, ...response.records);
      selectedRows.value = [];
      updateItems();
      dtKey.value++;
    }
  } catch (error) {
    console.error('Error refreshing records for ' + datasetKey + ' : ', error);
  }
  dtLoading.value = false;
};

const loadDataset = async (datasetKey) => {
  const config = datasetConfig[datasetKey];
  try {
    if (consoKey.value == '' && datasetKey != 'consortia') return;
    dtLoading.value = true;
    let itemsUrl = config.urlRoot + '/get';
    if (datasetKey == 'institutions') {
      itemsUrl += (is_admin) ? '/admin' : '/viewer';
    }
    const { data } = await api.get(itemsUrl);
    truncated.value = (data.truncated) ? data.truncated : false;
    allItems.splice(0, allItems.length, ...data.records);
    filteredItems.splice(0, filteredItems.length, ...data.records);

    // ─── SAFE RE-ASSIGNMENT PATHWAY ───
    if (data && data.options) {
      Object.keys(allOptions).forEach(key => {
        if (!(key in data.options)) {
          delete allOptions[key];
        }
      });
      Object.assign(allOptions, data.options);

      // ─── MASTER DROPDOWN SYNC ENGINE ───
      Object.keys(data.options).forEach(key => {
        if (Array.isArray(data.options[key])) {
          allOptions[key] = [...data.options[key]];
        }
      });
    }

    // ─── BACKGROUND MASTER GROUP FETCH SAFEGUARD ───
    // If the active dataset is institutions, bypass the database query optimization limits
    // by making a silent secondary call to fetch EVERY group in existence (like Group C!)
    if (datasetKey === 'institutions') {
      try {
        const groupsResponse = await api.get('/api/groups/get');
        if (groupsResponse && groupsResponse.data && groupsResponse.data.records) {
          const masterGroupsList = groupsResponse.data.records.map(g => ({
            id: Number(g.id),
            name: g.name
          }));
          // Overwrite the limited option cache with the absolute master dataset records
          allOptions['groups'] = [...masterGroupsList];
        }
      } catch (groupFetchError) {
        console.error('[DatasetViewer] Background master group pre-fetch failed:', groupFetchError);
      }
    }

  } catch (error) {
    console.error('Error fetching records for ' + datasetKey + ' : ', error);
  }

  bulkOptions.value = { 'dataset': props.datasetKey, 'items': [] };
  if (config.bulkOptions && Array.isArray(config.bulkOptions) && config.bulkOptions.length > 0) {
    bulkOptions.value.items = [...config.bulkOptions];
  } else {
    bulkOptions.value.items = [];
  }
  if (props.datasetKey == 'institutions' && typeof (allOptions.groups) !== 'undefined') {
    bulkOptions.value['groups'] = [...allOptions.groups];
  }

  headers.value = [{ title: "", key: "" }];
  config.fields.forEach((fld, idx) => {
    if (fld.name == 'fiscalYr') {
      allOptions['fiscalYr'] = fyMonths.map((month, index) => {
        const dateObj = new Date(2000, index, 1);
        const fullMonthName = dateObj.toLocaleString('en-US', { month: 'long' });

        return {
          id: Number(index + 1),
          name: month, 
          label: month, 
          fullName: fullMonthName, 
          value: month,
          title: month
        };
      });

      fld.optVal = 'label';
      fld.optTxt = 'label';
    }

    if (fld.header != null) {
      if (datasetKey == 'harvests' && fld.name == 'error') {
        headers.value.push({
          title: fld.label, key: fld.name, align: fld.header, type: fld.type,
          sort: (a, b) => { return Number(a.id) - Number(b.id); }
        });
      } else {
        headers.value.push({ title: fld.label, key: fld.name, align: fld.header, type: fld.type });
      }
    }
    if (fld.name == 'conso' && !is_conso_admin) return;
    config.fields[idx].static = config.static.includes(fld.name);
    const _options = (typeof (fld.options) != 'undefined') ? fld.options : [];
    var f_options = (typeof (allOptions[fld.name]) != 'undefined') ? allOptions[fld.name] : _options;

    if ((fld.type == 'select' || fld.type == 'mselect' || fld.type == 'selectObj' || fld.type == 'toggle') &&
      (fld.options == 'fromURL' || fld.name == 'fiscalYr') && typeof (allOptions[fld.name]) != 'undefined') {
      if (Array.isArray(allOptions[fld.name]) && ((props.datasetKey == 'roles' && fld.name == 'role') ||
        typeof (fld.optTxt) == 'undefined' || typeof (fld.optVal) == 'undefined')) {
        if (allItems.length > 0 && fld.name != 'fiscalYr') {
          f_options = allOptions[fld.name].filter(
            opt => allItems.flatMap(itm => itm[fld.filterCol]).includes(opt[fld.optVal])
          );
        } else {
          f_options = [...allOptions[fld.name]];
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
        } else if (fld.name == 'fiscalYr') {
          f_options = [...allOptions[fld.name]];
          _showIt = fld.isFilter;
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
      filterOptions[fld.name] = { 'name': fld.name, 'label': fld.label, 'type': fld.type, 'show': true, 'col': fld.filterCol };
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
    }
  });

  searchFields.value = config.fields.filter(fld => fld.searchable).map(f => f.name);
  editableFields.value = config.fields.filter(fld => fld.editable).map(f => f.name);
  urlRoot.value = config.urlRoot;
  dtLoading.value = false;
};


async function handleExport() {
  const config = datasetConfig[props.datasetKey];
  if (config.title == 'Platform') {
    try {
      const { data } = await api.get('/api/platforms/exportData');
      platformExportItems.splice(0, platformExportItems.length, ...data.records);
    } catch (error) {
      console.error("Platform pre-fetch error:", error);
    }
  }
  await triggerExcelExport(config.title);
}

// ============================================================================
// PART 6: PRESENTATIONAL BOUNDARIES & FILTER EVENT BRIDGES (Pillar 1)
// ============================================================================
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

  // Clone the item right away to prevent mutating data table grid records inline!
  let localizedItem = { ...item };

  config.fields.forEach((fld, idx) => {
    if (props.datasetKey == 'institutions' && fld.name == 'creds') {
      config.fields[idx]['visible'] = false;
    } else {
      config.fields[idx]['visible'] = true;
    }

    if (fld.type == 'select' && fld.name == 'types') {
      localizedItem['types'] = localizedItem.type_id;
      localizedItem['reset_type'] = false;
    }
    if (fld.type == 'select' && fld.name == 'institutions') {
      localizedItem['institutions'] = localizedItem.inst_id;
    }
  });

  // ─── SAFE GROUP-TO-INSTITUTION RE-HYDRATION ───
  // When editing an Institution Group, DataForm looks for the 'institutions' field name 
  // value. Map it directly from member_ids to ensure your polymorphic checkboxes stay bound!
  if (props.datasetKey === 'groups') {
    if (localizedItem.member_ids) {
      localizedItem.institutions = Array.isArray(localizedItem.member_ids)
        ? localizedItem.member_ids.map(Number)
        : String(localizedItem.member_ids).split(',').filter(Boolean).map(Number);
    } else {
      localizedItem.institutions = [];
    }
  }

  // Hydration Safeguard for Fiscal Year Start dropdown matching rules
  if (localizedItem.fiscalYr !== undefined && localizedItem.fiscalYr !== null) {
    const rawVal = String(localizedItem.fiscalYr).trim().toLowerCase();

    // Find the option item by looking across short names, full names, or indices
    const foundOption = allOptions['fiscalYr']?.find((opt, idx) =>
      opt.name.toLowerCase() === rawVal ||
      opt.fullName.toLowerCase() === rawVal ||
      String(idx + 1) === rawVal
    );

    if (foundOption) {
      // Set it to the abbreviation string matching optVal: 'label' configuration!
      localizedItem.fiscalYr = foundOption.label;
    }
  }

  editingItem.value = localizedItem;
  formDialogOpen.value = true;
}


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

async function handleFormSubmit(updatedValues) {
  failure.value = '';
  success.value = '';

  const formPayload = JSON.parse(JSON.stringify(updatedValues));

  if (formPayload.fiscalYr && typeof formPayload.fiscalYr === 'string') {
    // Look up index positions across both raw short arrays or your options list
    const monthIndex = fyMonths.indexOf(formPayload.fiscalYr);
    if (monthIndex >= 0) {
      // Send raw numeric integer (1-12) to satisfy the database model requirements
      formPayload.fiscalYr = Number(monthIndex + 1);
    }
  }

  // Dispatch data cleanly out to your isolated action engine
  const result = await saveItem(formPayload, formDialogType.value, editingItem.value?.id);

  if (result.success) {
    success.value = result.msg;

    // Combine fields safely within client grid states 
    const finalizedRecord = {
      ...editingItem.value,
      ...formPayload,
      ...(result.record || {})
    };

    if (formDialogType.value === "Edit") {
      updateLocalArrayRecord(editingItem.value.id, finalizedRecord);
    } else {
      allItems.push(finalizedRecord);
    }

    updateItems();
    dtKey.value++;
    formDialogOpen.value = false;
  } else {
    failure.value = result.msg;
  }
}
async function handleToggleUpdate(id, field, value) {
  const currentItem = allItems.find(item => item.id === id);
  if (!currentItem) return;

  const result = await toggleStatusField(id, field, value, currentItem);
  if (result.success) {
    updateLocalArrayRecord(id, result.record);
    updateItems();
    dtKey.value++;
  } else {
    if (result.msg) failure.value = result.msg;
  }
}

async function handleDelete(id) {
  const result = await deleteItem(id);
  if (result.success) {
    success.value = result.msg;
    if (props.datasetKey === "connections") {
      let idx = allItems.findIndex(ii => ii.id == id);
      if (idx >= 0) allItems[idx].can_delete = false;
    } else {
      let aIdx = allItems.findIndex(ii => ii.id == id);
      if (aIdx >= 0) allItems.splice(aIdx, 1);
    }
    updateItems();
    dtKey.value++;
  } else {
    failure.value = result.msg;
  }
}

function updateLocalArrayRecord(id, updatedRecord) {
  // 1. Sync the primary memory pool safely
  let aIdx = allItems.findIndex(ai => ai.id == id);
  if (aIdx >= 0) {
    // Avoid blanket spreading of object state trees; 
    // update only the parameters that changed to prevent tracking key duplication warnings!
    Object.keys(updatedRecord).forEach(key => {
      if (key !== 'id') { // Absolute rule: Never modify or duplicate the row primary identifier
        allItems[aIdx][key] = updatedRecord[key];
      }
    });
  }

  // 2. Sync the filtered view layout pool safely
  let fIdx = filteredItems.findIndex(fi => fi.id == id);
  if (fIdx >= 0) {
    Object.keys(updatedRecord).forEach(key => {
      if (key !== 'id') {
        filteredItems[fIdx][key] = updatedRecord[key];
      }
    });
  }
}

// ============================================================================
// PART 7: BULK LOGIC ENGINE & LOCAL FILTERS
// ============================================================================
async function handleBulk(data) {
  dtLoading.value = true;
  success.value = '';
  failure.value = '';

  const result = await executeBulkAction(data);

  if (result && result.success) {
    // ─── DELEGATED INSTITUTION GROUPS LAYER ───
    // Inside handleBulk(data) method in DatasetViewer.vue:
    if (result.delegateGroupUi) {
      const { resData, data: originalData } = result;

      // 1. Ensure the groups object array exists inside your raw option references
      if (!allOptions.groups) {
        allOptions.groups = [];
      }

      const newGroupObj = { 'id': resData.group.id, 'name': resData.group.name };

      // 2. Inject the new group into your core layout definitions safely
      if (originalData.action === 'Create New Group') {
        // Push to the toolbar filter array layout
        if (filterOptions.groups && Array.isArray(filterOptions.groups.items)) {
          filterOptions.groups.items.push(newGroupObj);
        }

        // Push to the primary options pool so loadDataset routines keep it synced
        if (Array.isArray(allOptions.groups)) {
          allOptions.groups.push(newGroupObj);
        }
      }

      // 3. Update row assignments immutably
      allItems.filter(aitm => resData.affectedIds.includes(aitm.id)).forEach(itm => {
        let currentGroups = itm.group_string ? String(itm.group_string).split(',') : [];
        if (!currentGroups.includes(resData.group.name)) {
          currentGroups.push(resData.group.name);
        }
        itm.group_string = currentGroups.join(',');

        if (Array.isArray(itm.group_ids)) {
          if (!itm.group_ids.includes(resData.group.id)) {
            itm.group_ids.push(resData.group.id);
          }
        } else {
          itm.group_ids = [resData.group.id];
        }
      });

      updateItems();
      dtKey.value++;
    }
    success.value = result.msg || "Bulk assignment completed successfully.";
  } else if (result && !result.handledByUi) {
    failure.value = result.msg || "Bulk configuration parameters failed.";
  }

  dtLoading.value = false;
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
    filteredItems.splice(0, filteredItems.length);
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
        filterResult = filterResult.filter(item => item[filter.col] && item[filter.col].some(f => f == filter.value));
      } else {
        filterResult = filterResult.filter(item => item[filter.col] == filter.value);
      }
    } else if (filter.type == 'toggle') {
      if (filter.value == 'Active') {
        filterResult = filterResult.filter(item => (item[filter.col]));
      } 
      if (filter.col == 'is_conso' && typeof (filterOptions.platforms) != 'undefined') {
        filterOptions.platforms.items = (filter.value == 'Active')
          ? filterOptions.platforms.items.filter(itm => itm.is_conso) : [...allOptions.platforms];
      }
    } else if (filter.type == 'mselect' || filter.type == 'mtext') {
      // ─── DEFENSIVE MULTI-SELECT FILTERING ───
      const targetFilterValues = Array.isArray(filter.value) 
        ? filter.value.map(v => String(v).trim().toLowerCase()) 
        : [String(filter.value).trim().toLowerCase()];

      filterResult = filterResult.filter(item => {
        let itemValues = [];
        
        // 1. Unpack arrays or strings safely into strings to handle all column shapes
        if (Array.isArray(item[filter.col])) {
          itemValues = item[filter.col].map(v => String(v).trim().toLowerCase());
        } else if (item[filter.col] !== undefined && item[filter.col] !== null) {
          itemValues = String(item[filter.col]).split(',').map(v => v.trim().toLowerCase());
        }

        // 2. Failsafe intersection for cross-relational columns like group_ids / group_string
        if (filter.col === 'group_string' || filter.col === 'group_ids') {
          let fallbackIds = Array.isArray(item.group_ids)
            ? item.group_ids.map(v => String(v).trim())
            : (item.group_ids ? String(item.group_ids).split(',').map(v => v.trim()) : []);
          
          if (fallbackIds.some(id => targetFilterValues.includes(id))) return true;
        }

        // 3. Return true if there's any matching value between selected filters and the row item
        return itemValues.some(val => targetFilterValues.includes(val));
      });
    }
  }

  // Mutate the final visual pool array securely in place
  filteredItems.splice(0, filteredItems.length, ...filterResult);
}

async function reportToggleSubmit(data) {
  let a_idx = allItems.findIndex(ii => ii.id == data.id);
  if (a_idx >= 0) {
    let _item = Object.assign({}, allItems[a_idx]);
    try {
      const response = await api.post("/api/connections/access", { id: data.id, rept: data.rept, flags: data.flags });
      const resData = response.data || response;
      if (resData.result) {
        _item[data.rept] = { ...resData.record };
        reptItem.flags = { ...data.flags };
        allItems.splice(a_idx, 1, _item);
        let f_idx = filteredItems.findIndex(fi => fi.id == data.id);
        if (f_idx >= 0) filteredItems.splice(f_idx, 1, _item);
        dtKey.value++;
      } else {
        failure.value = resData.msg;
      }
    } catch (error) {
      console.error('Error updating report toggle', error);
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

function handleFormCancel() {
  formDialogOpen.value = false;
  editingItem.value = null;
  reptDialog.value = false;
  typeDialog.value = false;
  failure.value = '';
  success.value = '';
}
// ============================================================================
// PART 8: LIFECYCLE WATCHERS & TRIGGERS
// ============================================================================
onBeforeMount(() => {
  loadDataset(props.datasetKey);
});

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
        :search="search" :headers="headers" :isLoading="dtLoading && !refreshProgress.active"
        :loadingColor="props.loadingColor" :dataset="props.datasetKey" :showSelectedOnly="showSelectedOnly"
        :editableFields="editableFields" :truncated="truncated" :searchFields="searchFields"
        :selectedRows="selectedRows" :selectableRows="selectableRows" @edit="handleEdit" @delete="handleDelete"
        @update:report="handleReportToggle" @update:selectedRows="selectedRows = $event"
        @update:toggle="handleToggleUpdate" @update:toggleSelectedOnly="handleToggle" />


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
