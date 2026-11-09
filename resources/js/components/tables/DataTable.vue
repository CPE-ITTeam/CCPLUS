<!-- components/tables/DataTable.vue -->
<script setup>
import { ref, computed } from 'vue'; // Both ref and computed are handled here
import { useAuthStore } from '@/stores/useAuthStore.js';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import DeleteConfirm from '@/components/dialogs/DeleteConfirm.vue';
import ErrorDetails from '@/components/dialogs/ErrorDetails.vue';
import { useDate } from 'vuetify';

// Initialize your store and date composable inside the setup scope
const authStore = useAuthStore();
const dateInstance = useDate();
//const dateKeys = ['date', 'created_at', 'updated', 'last_login', 'deleted_at'];

const props = defineProps({
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  search: { type: String, required: true },
  dataset: { type: String, required: true },
  showSelectedOnly: { type: Boolean, required: true },
  searchFields: { type: Array, required: true },
  selectedRows: { type: Array, required: true },
  editableFields: { type: Array, required: true },
  isLoading: { type: Boolean, required: true },
  loadingColor: { type: String, default: 'info' },
  truncated: { type: Boolean, required: true },
  selectableRows: { type: Boolean, required: true }
});
// (Your computedHeaders and showActions logic goes right below this)

const showActions = computed(() => {
  if (props.items.length > 0) {
    return (props.items.some((itm) => (typeof (itm.can_edit) != 'undefined' ||
      typeof (itm.can_delete) != 'undefined')));
  }
  return false;
});

// ==========================================
// 1. Your Computed Headers (Guarantees unique key mapping)
// ==========================================
const computedHeaders = computed(() => {
  let rawHeaders = props.headers.filter(h => h.key !== 'data-table-select' && h.name !== 'data-table-select');

  const hdrs = rawHeaders.map(header => {
    const isCentered = header.align === 'center';
    return {
      ...header,            // 💡 1. THIS IS MANDATORY - Copies 'type: "date"' from your matrix config
      key: header.key || header.name, // 💡 2. THIS IS MANDATORY - Bridges name to key
      headerProps: {
        class: isCentered
          ? 'font-weight-bold bg-info text-white ps-10'
          : 'font-weight-bold bg-info text-white'
      }
    };
  });

  if (showActions.value) {
    hdrs.push({
      title: 'Actions',
      key: 'actions',
      align: 'end',
      headerProps: { class: 'font-weight-bold bg-info text-white' }
    });
  }

  return hdrs;
});

// ==========================================
// 2. Your Date Formatting Helper 
// ==========================================
function formatDateDisplay(dateVal) {
  if (!dateVal) return '';
  const d = new Date(dateVal);
  if (isNaN(d.getTime())) return dateVal;

  // Natively outputs yyyy-mm-dd format on screen
  return new Intl.DateTimeFormat('en-CA').format(d);
}

const internalSelectedRows = computed({
  get: () => props.selectedRows,
  set: (val) => emit('update:selectedRows', val),
});
const selectedIds = computed(() => internalSelectedRows.value.map(row => row.id));
const hasStatusColumn = computed(() => props.headers.some(h => h.key === 'status'));
const hasConnectedColumn = computed(() => props.headers.some(h => h.key === 'connected'));
const hasIncludeZerosColumn = computed(() => props.headers.some(h => h.key === 'includeZeros'));
const hasInstValidColumn = computed(() => props.headers.some(h => h.key === 'inst_valid'));
const hasPlatValidColumn = computed(() => props.headers.some(h => h.key === 'plat_valid'));
const filteredItems = computed(() => {
  const searchTerm = (props.search ?? '').trim().toLowerCase();
  if (props.showSelectedOnly) {
    return props.items.filter(item => selectedIds.value.includes(item.id));
  }
  if (!searchTerm) {
    return props.items;
  }
  return props.items.filter(item =>
    props.searchFields.some(field => {
      const value = item[field];
      return typeof value === 'string' && value.toLowerCase().includes(searchTerm);
    })
  );
});
const deleteDialog = ref(false);
var curItem = ref({});
const errorDialog = ref(false);
var current_error = ref({ id: null, message: '', explanation: '', detail: '', process_step: '', help_url: '' });

const emit = defineEmits(['update:selectedRows', 'update:toggle', 'update:report', 'edit', 'delete']);
const { ccRawJson } = useAuthStore();

// reportSort compares 'sortval' values in items
const reportSort = (a, b) => {
  return a.sortval - b.sortval;
};

// Define custom sort functions for specific keys
// (columns not included here sort as-usual)
const customKeySort = {
  DR: (a, b) => reportSort(a, b),
  PR: (a, b) => reportSort(a, b),
  TR: (a, b) => reportSort(a, b),
  IR: (a, b) => reportSort(a, b),
};

const refreshDataset = computed(() => {
  return (props.dataset == 'harvests' || props.dataset == 'jobs' || props.dataset == 'audit');
});

const noDataMessage = computed(() => {
  if (!refreshDataset) return "No data or matching records available";
  return (props.dataset == 'jobs') ? "No Matching Records or Harvest Queue is Empty"
    : "Adjust Filters or Click Refresh Records to Load Records";
});
function enableDeleteDialog(item) {
  curItem.value = { ...item };
  deleteDialog.value = true;
}
function closeDeleteDialog() {
  curItem.value = {};
  deleteDialog.value = false;
}
function enableErrorDialog(error) {
  current_error.value = { ...error };
  errorDialog.value = true;
}
function closeErrorDialog() {
  current_error.value = { id: null, message: '', explanation: '', detail: '', process_step: '', help_url: '' };
  errorDialog.value = false;
}
function openUrl(url) {
  window.open(url, "_blank");
}
</script>

<!-- components/shared/DataTable.vue -->
<template>
  <div class="w-100">

    <!-- Top Table Header Utility Bar Area -->
    <div class="d-flex flex-column ga-2 mb-4">
      <v-alert v-if="truncated" type="warning" variant="tonal" density="compact"
        text="Available records truncated to 500 items" class="font-weight-bold" />

      <!-- 
        NATIVE GROUPING WRAPPER:
        Aligns the items-selected count indicator chip right next to your 
        interactive selection grid filter toggle button dynamically.
      -->
      <div v-if="props.selectableRows && selectedRows.length > 0" class="d-flex align-center ga-3 align-self-start">
        <v-chip color="primary" variant="tonal" prepend-icon="mdi-check-circle" class="font-weight-bold">
          {{ selectedRows.length }} {{ selectedRows.length === 1 ? 'item' : 'items' }} selected
        </v-chip>

        <!-- Migrated Toggle Button grouped with the count -->
        <v-tooltip :text="props.showSelectedOnly ? 'Show All' : 'Show Selected Only'" location="top">
          <template #activator="{ props: tooltipProps }">
            <v-btn v-bind="tooltipProps" icon color="primary" size="small"
              :variant="props.showSelectedOnly ? 'flat' : 'tonal'"
              @click="$emit('update:toggleSelectedOnly', !props.showSelectedOnly)">
              <v-icon size="small">
                {{ props.showSelectedOnly ? 'mdi-filter-off' : 'mdi-filter' }}
              </v-icon>
            </v-btn>
          </template>
        </v-tooltip>
      </div>
    </div>

    <!-- Core Interactive Data Grid Matrix -->
    <v-data-table v-model="internalSelectedRows" :headers="computedHeaders" :items="filteredItems" item-key="id"
      item-value="id" :show-select="props.selectableRows" return-object :loading="props.isLoading"
      :custom-key-sort="customKeySort" class="elevation-4 bg-surface rounded-lg overflow-hidden">
      <template #no-data>
        <v-alert type="info" variant="tonal" density="compact" icon="mdi-alert-circle-outline" class="ma-4"
          :text="noDataMessage" />
      </template>

      <!-- UNIFIED SELECTION CELL ARCHITECTURE -->
      <template #header.data-table-select="{ allSelected, selectAll, someSelected }">
        <div class="d-flex align-center justify-center h-100 bg-info text-white rounded-tl-lg ps-4"
          style="box-shadow: inset 0 0 0 10px var(--v-theme-info); min-height: 56px;">
          <v-checkbox-btn :indeterminate="someSelected && !allSelected" :model-value="allSelected" color="white"
            hide-details density="compact" @update:model-value="selectAll" />
        </div>
      </template>

      <!-- FAILSAFE SLOT OVERLAY FOR ROOT DATASETS -->
      <template v-if="props.dataset === 'consortia' || props.dataset === 'consortium'"
        #header.id="{ allSelected, selectAll, someSelected }">
        <div class="d-flex align-center justify-center h-100 bg-info text-white rounded-tl-lg ps-4"
          style="box-shadow: inset 0 0 0 10px var(--v-theme-info); min-height: 56px;">
          <v-checkbox-btn
            :indeterminate="internalSelectedRows.length > 0 && internalSelectedRows.length < filteredItems.length"
            :model-value="internalSelectedRows.length === filteredItems.length && filteredItems.length > 0"
            color="white" hide-details density="compact" @update:model-value="selectAll(!allSelected)" />
        </div>
      </template>


      <!-- 1. REUSABLE TOGGLE ENGINE LOOP -->
      <template v-for="col in ['status', 'connected', 'includeZeros', 'inst_valid', 'plat_valid']"
        #[`item.${col}`]="{ item }">
        <ToggleIcon v-model="item[col]" :toggleable="col === 'status' ? (props.dataset !== 'audit') : true"
          @update:modelValue="$emit('update:toggle', item.id, col, $event)" />
      </template>


      <!-- 2. COUNTER REPORT SLOT ENGINE LOOP -->
      <template v-for="report in ['PR', 'DR', 'TR', 'IR']" #[`item.${report}`]="{ item }">
        <ToggleIcon v-model="item[report]" :toggleable="true"
          @update:modelValue="$emit('update:report', item.id, report)" />
      </template>

      <!-- 3. ACCESSIBILITY INPUT VALIDATOR LOOP -->
      <template v-for="field in ['customer_id', 'requestor_id', 'api_key']" #[`item.${field}`]="{ item }">
        <span :class="{ 'text-warning font-italic': item[field] === '-required-' }">
          {{ item[field] }}
        </span>
      </template>

      <!-- 4. INACTIVE RECORD ENTRIES LOOP -->
      <template v-for="key in ['platform', 'institution']" #[`item.${key}`]="{ item }">
        <span :class="{ 'text-disabled font-italic': item[key === 'platform' ? 'plat_active' : 'inst_active'] === 0 }">
          {{ item[key] }}
        </span>
      </template>

      <!-- statusDot Key Indicator -->
      <template #item.statusDot="{ item }">
        <v-icon :title="item.d_status" :color="item.error?.color || 'disabled'">mdi-record</v-icon>
      </template>

      <!-- Harvest ID Data Field Control Box -->
      <template #item.harvest_id="{ item }">
        <div class="d-flex align-center ga-1">
          <span v-if="item.rawfile != null">
            {<v-btn size="small" density="compact" class="text-primary text-none px-0"
              @click="ccRawJson(item.harvest_id)" :title="item.harvest_id">Download JSON</v-btn>}
          </span>
          <span v-else>{{ item.harvest_id }}</span>

          <v-btn icon="mdi-barley" variant="text" size="small" density="comfortable" color="info"
            title="Manual Retry/Confirm Link" aria-label="Manual Retry or Confirm Link"
            @click="openUrl(item.retryUrl)" />
        </div>
      </template>

      <!-- Error Validation Matrix Column -->
      <template #item.error="{ item }">
        <span v-if="item.error?.id > 0" class="d-flex align-center ga-1">
          {{ item.error.id }}
          <v-btn icon="mdi-dots-vertical" variant="text" size="small" density="comfortable" :color="item.error.color"
            title="View Error Details" aria-label="View Error Details" @click="enableErrorDialog(item.error)" />
        </span>
      </template>

      <!-- Dynamic Interceptor Loop -->
      <template v-for="header in computedHeaders.filter(h => h.type === 'date')" :key="header.key"
        v-slot:[`item.${header.key}`]="{ value }">
        {{ formatDateDisplay(value) }}
      </template>

      <!-- Row Level Operations Action Matrix Column -->
      <template #item.actions="{ item }">
        <div v-if="showActions || props.dataset === 'platforms'" class="d-flex ga-1 justify-end">

          <!-- Condition A: Platform refresh action configurations (💡 Kept Tooltip, Removed location baseline) -->
          <v-tooltip
            v-if="props.dataset === 'platforms' && (item.refresh_result === 'orphan' || item.refresh_result == null)"
            :text="item.refresh_text">
            <template #activator="{ props: tooltipProps }">
              <v-btn :icon="item.refresh_icon" :color="item.refresh_color" variant="text" size="small"
                density="comfortable" v-bind="tooltipProps" aria-label="Platform Refresh State" />
            </template>
          </v-tooltip>

          <!-- Condition B: Platform refresh targeting registry index records (💡 Kept Tooltip, Removed location baseline) -->
          <v-tooltip v-else-if="item.refresh_result != null" :text="item.refresh_text">
            <template #activator="{ props: tooltipProps }">
              <v-btn :icon="item.refresh_icon" :color="item.refresh_color" variant="text" size="small"
                density="comfortable" v-bind="tooltipProps"
                @click="openUrl('https://countermetrics.org' + item.registry_id)" />
            </template>
          </v-tooltip>

          <!-- 💡 Edit Action: Removed Tooltip wrapper entirely, added descriptive aria-label for WCAG compliance -->
          <v-btn v-if="item.can_edit" icon="mdi-pencil" variant="text" size="small" density="comfortable"
            color="medium-emphasis" aria-label="Edit item" @click="$emit('edit', item)" />

          <!-- 💡 Delete Action: Removed Tooltip wrapper entirely, added adaptive accessibility string modifiers -->
          <v-btn icon="mdi-delete" variant="text" size="small" density="comfortable" :disabled="!item.can_delete"
            :color="item.can_delete ? 'error' : 'medium-emphasis'"
            :aria-label="item.can_delete ? 'Delete item' : 'Delete action disabled'"
            @click="enableDeleteDialog(item)" />
        </div>
      </template>
    </v-data-table>


    <!-- Modal Overlays -->
    <v-dialog v-model="deleteDialog" width="auto">
      <DeleteConfirm :item="curItem" :dataset="props.dataset" @confirm="$emit('delete', curItem.id)"
        @close="closeDeleteDialog" />
    </v-dialog>

    <v-dialog v-model="errorDialog" width="auto">
      <ErrorDetails :error="current_error" @close="closeErrorDialog" />
    </v-dialog>
  </div>
</template>

<!-- Add this to resources/js/components/tables/DataTable.vue -->
<style scoped>
/* 
  FIXED VIA DEEP COMBINATOR: 
  Wrapping the selector string inside :deep() bypasses Vue's scoped asset compiler boundaries.
  This allows your authoritative info theme blue color to bind directly to the internal 
  table cell elements natively, overriding the default layout lines completely.
*/
:deep(.v-data-table__td.v-data-table-column--no-padding.v-data-table-column--align-start.v-data-table__th) {
  border: 10px solid var(--v-theme-info) !important;
  padding: 0;
}
</style>

