<!-- components/tables/DatasetViewer.vue -->
<script setup>
// ============================================================================
// PART 1: MODULES, REUSABLE COMPOSABLES & UI DIALOG IMPORTS
// ============================================================================
// 1. Core Frameworks & External Packages
import { computed, onBeforeMount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

// 2. Stores, Configs & Services
import { tableSetup } from '@/config/dataTableConfig.js';
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';

// 3. Composables
import { useDatasetActions } from '@/composables/dataset/useDatasetActions.js';
import { useDatasetBulk } from '@/composables/dataset/useDatasetBulk.js';
import { useDatasetExporter } from '@/composables/dataset/useDatasetExporter.js';
import { useDatasetFilters } from '@/composables/dataset/useDatasetFilters.js';
import { useDatasetForm } from '@/composables/dataset/useDatasetForm.js';
import { useDatasetLoader } from '@/composables/dataset/useDatasetLoader.js';
import { useDatasetMutations } from '@/composables/dataset/useDatasetMutations.js';
import { useDatasetSchema } from '@/composables/dataset/useDatasetSchema.js';
import { useReportToggle } from '@/composables/dataset/useReportToggle.js';
import { useRegistryProgress } from '@/composables/useRegistryProgress.js';

// 4. Child & UI Components
import DataTable from './DataTable.vue';
import DataToolbar from './DataToolbar.vue';
import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';
import ManageConnectionsDialog from '@/components/dialogs/ManageConnectionsDialog.vue';
import CredentialsForm from '@/components/forms/CredentialsForm.vue';
import DataForm from '@/components/forms/DataForm.vue';
import PlatformForm from '@/components/forms/PlatformForm.vue';
import RoleForm from '@/components/forms/RoleForm.vue';
import { fyMonths } from '@/components/ui/YmInput.vue';

// ============================================================================
// PART 2: ACCOUNT LAYER INITIALIZATION & DECOUPLED BRIDGES
// ============================================================================
const authStore = useAuthStore();
const ccPlusStore = useCCPlusStore();
const is_admin = authStore.is_admin;
const is_conso_admin = authStore.is_conso_admin;
const { setConso } = authStore;
const { t, locale } = useI18n();

const dtLoading = ref(false);
const truncated = ref(false);
const consoKey = ref(authStore.ccp_key);
const props = defineProps({
  datasetKey: { type: String, required: true },
  loadingColor: { type: String, default: undefined },
});
const emit = defineEmits(['updateConso', 'setFilter']);
const datasetConfig = { ...tableSetup };

// ============================================================================
// PART 3: REACTIVE INSTANCE STATE MANAGEMENT & BASE INITIALIZATIONS
// ============================================================================
const dtKey = ref(0);
const allItems = reactive([]);
const filteredItems = reactive([]);
let newType = reactive({ id: null, name: '' });
const platformExportItems = reactive([]);
const allOptions = reactive({});
const bulkOptions = ref({ dataset: props.datasetKey, items: [] });
const filterOptions = reactive({});
const headers = ref([]);
const searchFields = ref([]);
const search = ref('');
const selectedRows = ref([]);
const showSelectedOnly = ref(false);
const typeDialog = ref(false);
const editableFields = ref([]);
const urlRoot = ref('');
const success = ref('');
const failure = ref('');

// ─── 1. PURE PRESENTATIONAL COMPUTED STRINGS ───
const capDataset = computed(() =>
  props.datasetKey ? props.datasetKey.charAt(0).toUpperCase() + props.datasetKey.slice(1) : ''
);

const AUDIT_INST_COLS = new Set(['inst_name', 'inst_valid', 'json_inst']);
const AUDIT_PLAT_COLS = new Set(['plat_name', 'plat_valid', 'json_item', 'json_host']);

const visibleHeaders = computed(() => {
  if (props.datasetKey !== 'audit') return headers.value;
  const vt = filterOptions.valid_types?.value;
  const isNotValOnly = Array.isArray(vt)
    ? vt.includes('Not Validated') && !vt.includes('Validated')
    : vt === 'Not Validated';
  if (!isNotValOnly) return headers.value;

  const target = filterOptions.valid_target?.value || 'All';
  if (target === 'Platform') {
    return headers.value.filter((h) => !AUDIT_INST_COLS.has(h.key));
  }
  if (target === 'Institution') {
    return headers.value.filter((h) => !AUDIT_PLAT_COLS.has(h.key));
  }
  return headers.value;
});

const formComponent = computed(() => {
  if (props.datasetKey === 'platforms') return PlatformForm;
  if (props.datasetKey === 'credentials') return CredentialsForm;
  if (props.datasetKey === 'roles') return RoleForm;
  return DataForm;
});


const formSchema = computed(() => {
  const config = datasetConfig[props.datasetKey];
  return {
    dataset: props.datasetKey,
    type: formDialogType.value,
    fields: [...config.fields],
    requiredKeys: [...config.required],
    options: allOptions,
  };
});

// ─── 2. COMPOSABLE INTERFACE WIRING ───
const { applySchema, syncLocaleLabels } = useDatasetSchema({
  datasetKey: () => props.datasetKey,
  datasetConfig,
  allItems,
  allOptions,
  filterOptions,
  headers,
  searchFields,
  editableFields,
  bulkOptions,
  urlRoot,
  isConsoAdmin: is_conso_admin,
});

const { loadDataset, refreshData } = useDatasetLoader({
  datasetConfig,
  consoKey,
  isAdmin: is_admin,
  dtLoading,
  truncated,
  allItems,
  filteredItems,
  allOptions,
  filterOptions,
  selectedRows,
  applySchema,
  updateItems: () => {
    updateItems();
    dtKey.value++;
  },
});

const { handleFilter, updateItems } = useDatasetFilters({
  datasetKey: () => props.datasetKey,
  filterOptions,
  allOptions,
  allItems,
  filteredItems,
  selectedRows,
  dtKey,
  refreshData,
});

watch(
  [() => props.datasetKey, () => filterOptions.is_conso],
  ([ds, isConsoFilter]) => {
    if (ds === 'platforms' && isConsoFilter) {
      isConsoFilter.useConnected = true;
    }
  },
  { immediate: true }
);

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
  fyMonths,
});

const { isProcessing, saveItem, toggleStatusField, deleteItem } = useDatasetActions(urlRoot);

const {
  refreshProgress,
  startRegistryProgress,
  stopRegistryProgress,
  cancelRegistryRefresh,
} = useRegistryProgress();

const { executeBulkAction, isBulkProcessing } = useDatasetBulk({
  urlRoot,
  allItems,
  filteredItems,
  selectedRows,
  typeDialog,
  newType,
  dtKey,
  updateItems: () => updateItems(),
  startRegistryProgress,
  stopRegistryProgress,
});

const {
  reptDialog,
  reptItem,
  reptDialogSubtitle,
  addConnection,
  reportToggleSubmit,
  handleReportToggle,
  resetReportDialog,
} = useReportToggle({
  datasetKey: () => props.datasetKey,
  allItems,
  filteredItems,
  dtKey,
  ccPlusStore,
  t,
});

const {
  formDialogOpen,
  formDialogType,
  formDialogTitle,
  editingItem,
  handleEdit,
  handleAddItem,
  handleFormSubmit,
  resetFormDialog,
} = useDatasetForm({
  datasetKey: () => props.datasetKey,
  datasetConfig,
  allItems,
  filteredItems,
  allOptions,
  formSchema,
  saveItem,
  loadDataset,
  updateItems,
  dtKey,
  authStore,
  ccPlusStore,
  t,
  onAddConnection: addConnection,
});

const {
  handleToggleUpdate,
  handleDelete,
  handleBulk,
} = useDatasetMutations({
  datasetKey: () => props.datasetKey,
  allItems,
  filteredItems,
  allOptions,
  filterOptions,
  dtLoading,
  dtKey,
  toggleStatusField,
  deleteItem,
  executeBulkAction,
  updateItems,
  ccPlusStore,
  t,
});

// ============================================================================
// PART 4: COMPONENT VIEW LAYOUT COMPUTED EXPRESSIONS ONLY
// ============================================================================
const isEditable = computed(() => {
  const config = datasetConfig[props.datasetKey];
  return editingItem.value && config && editableFields.value.length > 0;
});

const selectableRows = computed(() => {
  const hasBulkActions = Array.isArray(bulkOptions.value.items) && bulkOptions.value.items.length > 0;
  return hasBulkActions || exportable.value;
});

const effectiveBulkOptions = computed(() => {
  const base = bulkOptions.value;
  if (props.datasetKey !== 'audit' || !Array.isArray(base?.items)) {
    return base;
  }

  const vt = filterOptions.valid_types?.value;
  const isNotValOnly = Array.isArray(vt)
    ? vt.includes('Not Validated') && !vt.includes('Validated')
    : vt === 'Not Validated';
  const target = filterOptions.valid_target?.value;

  if (isNotValOnly && target === 'Platform') {
    return {
      ...base,
      items: base.items.filter((opt) => opt !== 'Set Institution Validated'),
    };
  }

  if (isNotValOnly && target === 'Institution') {
    return {
      ...base,
      items: base.items.filter((opt) => opt !== 'Set Platform Validated'),
    };
  }

  return base;
});



const toolbarFilters = computed(() => {
  const rows = [];
  const keys = Object.keys(filterOptions).filter((key) => filterOptions[key].show);
  for (let i = 0; i < keys.length; i += 4) {
    const row = {};
    const rKeys = keys.slice(i, i + 4);
    rKeys.forEach((key) => {
      row[key] = { ...filterOptions[key] };
    });
    rows.push({ ...row });
  }
  return rows;
});

const exportable = computed(() => {
  const config = datasetConfig[props.datasetKey];
  if (!config || !config.exportFields) return false;
  return config.exportFields.length > 0;
});


// ============================================================================
// PART 5: CORE COMPONENT DATA LIFECYCLES & EVENT BRIDGES
// ============================================================================
async function handleExport() {
  const config = datasetConfig[props.datasetKey];
  if (props.datasetKey === 'platforms') {
    try {
      const { data } = await api.get('/api/platforms/exportData');
      platformExportItems.splice(0, platformExportItems.length, ...(data.records || []));
    } catch (error) {
      console.error('Platform pre-fetch error:', error);
    }
  }
  await triggerExcelExport(config.title);
}

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
  await loadDataset(props.datasetKey);
  emit('updateConso', conso);
  dtKey.value++;
}

async function handleImported(data) {
  if (data && data.length > 0) {
    ccPlusStore.notifySuccess(data);
  }
  selectedRows.value = [];
  try {
    await loadDataset(props.datasetKey);
  } catch (err) {
    console.error('Dataset re-sync failed after import:', err);
  } finally {
    dtKey.value++;
  }
}

function handleFormCancel() {
  resetFormDialog();
  resetReportDialog();
  typeDialog.value = false;
  ccPlusStore.clearAlerts();
}

// ============================================================================
// PART 6: LIFECYCLE WATCHERS & TRIGGERS
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
  syncLocaleLabels(props.datasetKey);
  dtKey.value++;
});
</script>

<template>
  <div class="ma-2">
    <v-sheet class="bg-transparent">

      <!-- 1. Toolbar Filters Wrapper -->
      <DataToolbar :model-value="toolbarFilters" :search="search" :dataset="props.datasetKey" :items="allItems"
        :bulkOptions="effectiveBulkOptions" :selectedRows="selectedRows" :showSelectedOnly="showSelectedOnly"
        :hideExport="!exportable" @updateConso="handleChangeConso" @export="handleExport" @setFilter="handleFilter"
        @bulkAction="handleBulk" @add="handleAddItem" @update:search="search = $event"
        @update:showSelectedOnly="handleToggle" @refreshRecords="refreshData(props.datasetKey)"
        @imported="handleImported" />


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
      <v-alert v-if="success" type="success" class="my-3 mx-4" :text="success" />
      <v-alert v-else-if="failure" type="error" class="my-3 mx-4" :text="failure" />

      <!-- 3. Primary Data Table Grid -->
      <DataTable v-if="consoKey != '' || props.datasetKey == 'consortia'" :items="filteredItems" :key="dtKey"
        :search="search" :headers="visibleHeaders" :isLoading="dtLoading && !refreshProgress.active"
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

          <ManageConnectionsDialog :item="reptItem" :options="allOptions" @submit="reportToggleSubmit"
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
