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
import { useDatasetFilters } from '@/composables/useDatasetFilters.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import { setAppLocale } from '@/plugins/i18n.js';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';
import DataToolbar from './DataToolbar.vue';
import DataTable from './DataTable.vue';
import DataForm from '@/components/tables/DataForm.vue';
import PlatformForm from '@/components/forms/PlatformForm.vue';
import CredentialsForm from '@/components/forms/CredentialsForm.vue';
import ReportToggle from '@/components/dialogs/ReportToggle.vue';
import { useI18n } from 'vue-i18n';

// ============================================================================
// PART 2: ACCOUNT LAYER INITIALIZATION & DECOUPLED BRIDGES
// ============================================================================
const authStore = useAuthStore();
const ccPlusStore = useCCPlusStore();
const is_admin = authStore.is_admin;
const is_conso_admin = authStore.is_conso_admin;
const { setConso } = authStore;
const { t, locale } = useI18n();

var dtLoading = ref(false);
var truncated = ref(false);
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
});

//===========================================================================
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
  Object.keys(filterOptions).forEach((key) => {
    if (filterOptions[key]?.type === 'ymrange') {
      const val = filterOptions[key].value || {};
      _filters.fromYM = val.fromYM || '';
      _filters.toYM = val.toYM || '';
    } else {
      _filters[key] = filterOptions[key]['value'];
    }
  });
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

  const buildFiscalYearOptions = () =>
    fyMonths.map((month, index) => {
      const fullMonthName = new Date(2000, index, 1).toLocaleString('en-US', { month: 'long' });
      return {
        id: Number(index + 1),
        name: month,
        label: month,
        fullName: fullMonthName,
        value: month,
        title: month,
      };
    });

  const buildHeaderEntry = (fld) => {
    const isCompactReportCol = ['PR', 'DR', 'TR', 'IR'].includes(fld.name);
    const isCompactCredCol = ['customer_id', 'requestor_id', 'api_key'].includes(fld.name);
    const isCompactCol = isCompactReportCol || isCompactCredCol;
    const colWidth = isCompactReportCol ? '52px' : isCompactCredCol ? '68px' : fld.width;

    let groupClass = '';
    if (isCompactReportCol) {
      groupClass = 'compact-col report-group-col';
      if (fld.name === 'PR') groupClass += ' group-border-start';
      if (fld.name === 'IR') groupClass += ' group-border-end';
    } else if (isCompactCredCol) {
      groupClass = 'compact-col cred-group-col';
      if (fld.name === 'api_key') groupClass += ' group-border-end';
    }

    const entry = {
      title: fld.shortLabel || fld.label,
      fullTitle: fld.label,
      key: fld.name,
      align: isCompactCol ? undefined : fld.header,
      type: fld.type,
      truncate: fld.truncate,
      ...(colWidth ? { width: colWidth, maxWidth: colWidth } : {}),
      ...(isCompactCol
        ? {
          headerProps: { class: `bg-info text-white ${groupClass}`, title: fld.label },
          cellProps: { class: groupClass },
        }
        : {}),
    };
    if (fld.name === 'error') {
      entry.sort = (a, b) => Number(a.id) - Number(b.id);
    }
    return entry;
  };

  const resolvePresentOptionItems = (rawOptions, fld) => {
    if (!Array.isArray(rawOptions) || allItems.length === 0) return [];
    const presentValues = new Set(
      allItems.flatMap((itm) => itm[fld.filterCol] ?? []).map((v) => String(v).trim())
    );
    return rawOptions
      .map((opt) => (typeof opt === 'string' ? opt.trim() : opt))
      .filter((opt) => {
        const optValue = fld.optVal && typeof opt === 'object' && opt !== null ? opt[fld.optVal] : opt;
        return presentValues.has(String(optValue).trim());
      });
  };

  headers.value = [{ title: '', key: '' }];

  config.fields.forEach((fld, idx) => {
    if (fld.name === 'fiscalYr') {
      allOptions.fiscalYr = buildFiscalYearOptions();
      fld.optVal = 'label';
      fld.optTxt = 'label';
    }
    if (fld.header !== null) {
      headers.value.push(buildHeaderEntry(fld));
    }

    if (fld.name === 'conso' && !is_conso_admin) return;
    config.fields[idx].static = config.static.includes(fld.name);

    const fallbackOptions = fld.options ?? [];
    const sourceOptions = allOptions[fld.name] ?? fallbackOptions;
    const isSelectLike = ['select', 'mselect', 'selectObj', 'toggle'].includes(fld.type);

    if (isSelectLike && (fld.options === 'fromURL' || fld.name === 'fiscalYr') && allOptions[fld.name] !== undefined) {
      const urlOptions = allOptions[fld.name];
      const isSinglePrimitive =
        Array.isArray(urlOptions) &&
        fld.type !== 'mselect' &&
        ((props.datasetKey === 'roles' && fld.name === 'role') ||
          fld.optTxt === undefined ||
          fld.optVal === undefined);

      let items = [];
      let show = false;

      if (refreshable.includes(datasetKey)) {
        items = [...urlOptions];
        show = true;
      } else if (fld.name === 'fiscalYr') {
        items = [...urlOptions];
        show = fld.isFilter;
      } else {
        items = resolvePresentOptionItems(urlOptions, fld);
        show = items.length === 0 ? false : fld.isFilter;
      }

      filterOptions[fld.name] = {
        name: fld.name,
        label: fld.label,
        type: isSinglePrimitive ? 'text' : fld.type,
        val: fld.optVal,
        txt: fld.optTxt,
        show: isSinglePrimitive ? fld.isFilter : show,
        col: fld.filterCol,
        items,
        value: fld.type === 'mselect' ? [] : null,
      };
    } else if (fld.type === 'toggle' && fld.isFilter && (sourceOptions.length > 0 || fld.name === 'is_conso')) {
      filterOptions[fld.name] = {
        name: fld.name,
        label: fld.label,
        type: 'toggle',
        show: fld.isFilter,
        col: fld.filterCol,
        items: [...sourceOptions],
        value: fld.name === 'is_conso' ? 'All' : 'Inactive',
      };

    } else if (fld.isFilter && (fld.type === 'text' || fld.type === 'mtext')) {
      const items =
        fld.options === 'fromURL' && Array.isArray(allOptions[fld.name])
          ? [...allOptions[fld.name]]
          : [...fld.options];

      filterOptions[fld.name] = {
        name: fld.name,
        label: fld.label,
        type: fld.type,
        show: true,
        col: fld.filterCol,
        value: fld.type === 'text' ? null : [],
        items,
      };
    } else if (fld.isFilter && fld.type === 'ymrange') {
      filterOptions[fld.name] = {
        name: fld.name,
        label: fld.label,
        type: 'ymrange',
        show: true,
        col: fld.filterCol,
        items: [],
        value: { fromYM: '', toYM: '' },
      };
    } else if (Array.isArray(fld.options)) {
      const items =
        allItems.length > 0
          ? fld.options.filter((opt) => allItems.flatMap((itm) => itm[fld.filterCol]).includes(opt[fld.optVal]))
          : fld.options;

      filterOptions[fld.name] = {
        name: fld.name,
        label: fld.label,
        type: fld.type,
        val: fld.optVal,
        txt: fld.optTxt,
        show: fld.isFilter,
        col: fld.filterCol,
        items: [...items],
        value: null,
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
watch(
  () => selectedRows.value.length,
  (len) => {
    if (len === 0 && showSelectedOnly.value) {
      showSelectedOnly.value = false;
    }
  }
);

async function handleChangeConso(conso) {
  consoKey.value = conso.ccp_key;
  await setConso(conso.id, conso.ccp_key);
  loadDataset(props.datasetKey);
  emit('updateConso', conso);
  dtKey.value++;
}
// 💡 FIXED: Make the hook async so we await the data reload before incrementing the DOM key
async function handleImported(data) {
  if (data && data.length > 0) {
    ccPlusStore.notifySuccess(data);
  }
  selectedRows.value = [];
  try {
    if (typeof loadDataset === 'function') {
      await loadDataset(props.datasetKey);
    }
  } catch (err) {
    console.error("Dataset re-sync failed after import:", err);
  } finally {
    dtKey.value++;
  }
}


function handleEdit(item) {
  const config = datasetConfig[props.datasetKey];
  formDialogType.value = "Edit";
  formDialogTitle.value = t('dialogs.edit_item', { item: config.title });

  // Clone the item right away to prevent mutating data table grid records inline!
  let localizedItem = { ...item };

  config.fields.forEach((fld) => {
    if (fld.type == 'select' && fld.name == 'types') {
      localizedItem['types'] = localizedItem.type_id;
      localizedItem['reset_type'] = false;
    }
    if (fld.type == 'select' && fld.name == 'type') {
      localizedItem['type'] = localizedItem.type_id;
    }
    if (fld.type == 'select' && fld.name == 'institutions') {
      localizedItem['institutions'] = localizedItem.inst_id;
    }
  });


  // ─── SAFE INSTITUTION-TO-GROUP RE-HYDRATION ───
  if (props.datasetKey === 'institutions') {
    if (localizedItem.group_ids) {
      localizedItem.groups = Array.isArray(localizedItem.group_ids)
        ? localizedItem.group_ids.map(Number)
        : String(localizedItem.group_ids).split(',').filter(Boolean).map(Number);
    } else {
      localizedItem.groups = [];
    }
  }

  // ─── SAFE GROUP-TO-INSTITUTION RE-HYDRATION ───
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

    const foundOption = allOptions['fiscalYr']?.find((opt, idx) =>
      opt.name.toLowerCase() === rawVal ||
      opt.fullName.toLowerCase() === rawVal ||
      String(idx + 1) === rawVal
    );

    if (foundOption) {
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
  formDialogTitle.value = t('dialogs.add_new_item', { item: config.title });
  editingItem.value = {};

  config.fields.forEach((fld) => {
    if (fld.type == 'toggle') {
      editingItem.value[fld.name] = fld.addValue ?? 'Inactive';
    } else if (fld.type == 'mselect' || fld.type == 'array') {
      editingItem.value[fld.name] = [];
    } else if (fld.name == 'report_state') {
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
    } else if (fld.name == 'fiscalYr') {
      const fyOptions = formSchema.value.options?.['fiscalYr'] || [];
      const defaultFy = formSchema.value.options?.defaultFiscalYr
        || formSchema.value.defaultFiscalYr
        || ccPlusStore?.fiscalYr
        || authStore?.fiscalYr
        || 1;
      const matchedOpt = fyOptions.find(
        (opt) => opt.id == defaultFy || opt.name === defaultFy
      );
      editingItem.value[fld.name] = matchedOpt ? matchedOpt.id : (fyOptions?.id ?? null);
    } else {
      editingItem.value[fld.name] = null;
    }
  });

  formDialogOpen.value = true;
}


function addConnection() {
  reptItem = { 'type': 'Add', 'id': null, 'rept': '', 'flags': emptyFlags, 'connections': [...allItems] };
  reptDialog.value = true;
}

async function handleFormSubmit(updatedValues) {
  ccPlusStore.clearAlerts();

  const formPayload = JSON.parse(JSON.stringify(updatedValues));

  if (typeof formPayload.fiscalYr === 'string') {
    const monthIndex = fyMonths.indexOf(formPayload.fiscalYr);
    if (monthIndex >= 0) {
      formPayload.fiscalYr = monthIndex + 1;
    }
  }

  const isEdit = formDialogType.value === 'Edit';
  const targetId = isEdit ? editingItem.value?.id : undefined;

  const result = await saveItem(formPayload, formDialogType.value, targetId);

  if (!result.success) {
    ccPlusStore.notifyError(result.msg);
    return;
  }

  ccPlusStore.notifySuccess(result.msg);
  formDialogOpen.value = false;

  if (isEdit) {
    const finalizedRecord = {
      ...editingItem.value,
      ...formPayload,
      ...(result.record || {}),
    };
    updateLocalArrayRecord(targetId, finalizedRecord);
    updateItems();
    dtKey.value++;

    if (props.datasetKey === 'consortia' && finalizedRecord.ccp_key === authStore.ccp_key) {
      if (finalizedRecord.theme) {
        authStore.setTheme(finalizedRecord.theme);
      }
      if (finalizedRecord.language && !authStore.user?.language) {
        setAppLocale(finalizedRecord.language);
      }
    }
  } else {
    await loadDataset(props.datasetKey);
    dtKey.value++;
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
  } else if (result.msg) {
    ccPlusStore.notifyError(result.msg);
  }
}

async function handleDelete(id) {
  const result = await deleteItem(id);
  if (result.success) {
    ccPlusStore.notifySuccess(result.msg);
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
    ccPlusStore.notifyError(result.msg);
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
  ccPlusStore.clearAlerts();

  const result = await executeBulkAction(data);

  if (result && result.success) {
    if (result.delegateGroupUi) {
      const { resData, data: originalData } = result;

      if (!allOptions.groups) {
        allOptions.groups = [];
      }

      const newGroupObj = { 'id': resData.group.id, 'name': resData.group.name };

      if (originalData.action === 'Create New Group') {
        if (filterOptions.groups && Array.isArray(filterOptions.groups.items)) {
          filterOptions.groups.items.push(newGroupObj);
        }

        if (Array.isArray(allOptions.groups)) {
          allOptions.groups.push(newGroupObj);
        }
      }

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
    ccPlusStore.notifySuccess(result.msg || t('messages.bulk_assign_success'));
  } else if (result && !result.handledByUi) {
    ccPlusStore.notifyError(result.msg || t('messages.bulk_assign_failed'));
  }

  dtLoading.value = false;
}

const {
  handleFilter,
  updateItems,
} = useDatasetFilters({
  datasetKey: () => props.datasetKey,
  filterOptions,
  allOptions,
  allItems,
  filteredItems,
  selectedRows,
  dtKey,
  refreshData,
});

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
        ccPlusStore.notifyError(resData.msg);
      }
    } catch (error) {
      console.error('Error updating report toggle', error);
      ccPlusStore.notifyError(t('messages.unexpected_server_error'));
    }
  }
  reptDialog.value = false;
}

async function handleReportToggle(id, rept) {
  let _idx = allItems.findIndex((ii) => ii.id == id);
  if (_idx < 0) return;

  const theItem = Object.assign({}, allItems[_idx]);

  if (props.datasetKey == 'connections') {
    reptItem = { type: 'Edit', id: id, rept: rept, flags: { ...theItem[rept] } };
    reptDialogSubtitle.value = typeof theItem.platform != 'undefined' ? theItem.platform : '';
    reptDialog.value = true;
    return;
  }

  if (props.datasetKey == 'credentials') {
    const currentFlag = theItem[rept];
    if (!currentFlag || !currentFlag.available || currentFlag.conso) return;

    const nextFlag = { ...currentFlag, requested: !currentFlag.requested };
    const optimisticItem = { ...theItem, [rept]: nextFlag };
    allItems.splice(_idx, 1, optimisticItem);
    const f_idx = filteredItems.findIndex((fi) => fi.id == id);
    if (f_idx >= 0) filteredItems.splice(f_idx, 1, optimisticItem);

    try {
      const response = await api.patch(`/api/credentials/update/${id}`, {
        id: id,
        report_only: true,
        [rept]: nextFlag,
      });
      const resData = response?.data !== undefined ? response.data : response;
      if (resData?.result && resData?.record) {
        const updated = { ...theItem, ...resData.record };
        allItems.splice(_idx, 1, updated);
        if (f_idx >= 0) filteredItems.splice(f_idx, 1, updated);
      } else {
        allItems.splice(_idx, 1, theItem);
        if (f_idx >= 0) filteredItems.splice(f_idx, 1, theItem);
        ccPlusStore.notifyError(resData?.msg || t('messages.server_rejected'));
      }
    } catch (error) {
      allItems.splice(_idx, 1, theItem);
      if (f_idx >= 0) filteredItems.splice(f_idx, 1, theItem);
      console.error('Error toggling credential report:', error);
      ccPlusStore.notifyError(t('messages.unexpected_server_error'));
    }
  }
}

function handleFormCancel() {
  formDialogOpen.value = false;
  editingItem.value = null;
  reptDialog.value = false;
  typeDialog.value = false;
  ccPlusStore.clearAlerts();
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

watch(locale, () => {
  const config = datasetConfig[props.datasetKey];
  if (!config) return;

  headers.value = [{ title: '', key: '' }];
  config.fields.forEach((fld) => {
    if (fld.header != null) {
      if (props.datasetKey == 'harvests' && fld.name == 'error') {
        headers.value.push({
          title: fld.label,
          key: fld.name,
          align: fld.header,
          type: fld.type,
          sort: (a, b) => Number(a.id) - Number(b.id),
        });
      } else {
        headers.value.push({
          title: fld.label,
          key: fld.name,
          align: fld.header,
          type: fld.type,
          truncate: fld.truncate,
        });
      }
    }
  });


  // Also refresh filter labels when locale changes
  Object.keys(filterOptions).forEach((key) => {
    const fld = config.fields.find((f) => f.name === key);
    if (fld) filterOptions[key].label = fld.label;
  });

  dtKey.value++;
});
</script>

<template>
  <div class="ma-2">
    <v-sheet class="bg-transparent">

      <!-- 1. Toolbar Filters Wrapper -->
      <DataToolbar v-model="toolbarFilters" :search="search" :dataset="props.datasetKey" :bulkOptions="bulkOptions"
        :selectedRows="selectedRows" :showSelectedOnly="showSelectedOnly" :hideExport="!exportable"
        @updateConso="handleChangeConso" @export="handleExport" @setFilter="handleFilter" @bulkAction="handleBulk"
        @add="handleAddItem" @update:search="search = $event" @update:showSelectedOnly="handleToggle"
        @refreshRecords="refreshData(props.datasetKey)" @imported="handleImported" />

      <!-- Live COUNTER Registry Refresh Progress Banner (WCAG Accessible) -->
      <v-alert v-if="refreshProgress.active" type="info" variant="tonal" class="my-3 rounded-lg" aria-live="polite">
        <div class="d-flex align-center justify-space-between mb-2">
          <span>
            {{ $t('tables.refreshing_registry') }}
            <strong>{{ refreshProgress.current }} / {{ refreshProgress.total || '...' }}</strong>
            <span v-if="refreshProgress.name" class="text-caption ml-2">({{ refreshProgress.name }})</span>
          </span>
          <v-btn size="small" color="error" variant="tonal" class="rounded-xl px-3"
            :disabled="refreshProgress.cancelling" @click="cancelRegistryRefresh">
            <v-icon start class="mr-1">mdi-close-circle-outline</v-icon>
            {{ refreshProgress.cancelling ? $t('common.stopping') : $t('common.cancel') }}
          </v-btn>
        </div>
        <v-progress-linear
          :model-value="refreshProgress.total ? (refreshProgress.current / refreshProgress.total) * 100 : 0"
          :indeterminate="!refreshProgress.total" color="primary" height="8" rounded role="progressbar"
          :aria-valuenow="refreshProgress.current" aria-valuemin="0" :aria-valuemax="refreshProgress.total || 100" />
      </v-alert>

      <!-- 2. Status Messages: Native alerts -->
      <StatusAlerts v-model:success="success" v-model:failure="failure" mode="page" />

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
            {{
              reptItem.type == 'Add'
                ? $t('dialogs.add_report_connections')
                : $t('dialogs.edit_report_connections', { report: reptItem.rept })
            }}
          </template>

          <template v-if="reptItem.type == 'Edit' && reptDialogSubtitle.length > 0" #subtitle>
            <div class="d-flex align-center justify-space-between w-100">
              <strong>{{ reptDialogSubtitle }}</strong>
              <span v-if="reptItem.id !== null" class="text-caption d-flex align-center">
                <v-icon size="small" class="mr-1">mdi-crosshairs-gps</v-icon> {{ $t('fields.id') }}: {{ reptItem.id }}
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
          <template #title>{{ $t('dialogs.update_institution_type') }}</template>

          <template #subtitle>
            <strong>{{ $t('dialogs.updating_type_for_count', { count: selectedRows.length }) }}</strong>
          </template>

          <v-autocomplete v-model="newType" :label="$t('fields.type')" :items="allOptions.type || []" item-title="name"
            item-value="id" density="compact" return-object />

          <template #actions>
            <v-btn @click="handleBulk({ action: 'Set Institution Type', type_id: newType.id })">{{ $t('common.save')
            }}</v-btn>
            <v-btn variant="text" class="ml-2" @click="handleFormCancel">{{ $t('common.cancel') }}</v-btn>
          </template>
        </BaseDialogLayout>
      </v-dialog>

    </v-sheet>
  </div>
</template>
