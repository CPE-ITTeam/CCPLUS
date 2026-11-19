<!-- components/tables/DataTable.vue -->
<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import DeleteConfirm from '@/components/dialogs/DeleteConfirm.vue';
import ErrorDetails from '@/components/dialogs/ErrorDetails.vue';
import { useDate } from 'vuetify';
import { tableSetup } from '@/config/dataTableConfig.js';
import { sortDatasetItems } from '@/utils/datasetSort.js';
import { useExternalLinks } from '@/composables/useExternalLinks.js';

const { openUrl, openRegistryPlatform } = useExternalLinks();
const { t } = useI18n();
const authStore = useAuthStore();
const dateInstance = useDate();

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
  selectableRows: { type: Boolean, required: true },
});

const showActions = computed(() => {
  if (props.items.length > 0) {
    return props.items.some((itm) => typeof itm.can_edit != 'undefined' || typeof itm.can_delete != 'undefined');
  }
  return false;
});


// ==========================================
// 1. Your Computed Headers (Guarantees unique key mapping)
// ==========================================
const computedHeaders = computed(() => {
  const rawHeaders = props.headers.filter((h) => {
    const keyName = h.key || h.name;

    if (keyName === 'data-table-select' || keyName === 'id') {
      return false;
    }

    if (h.header === null || h.headerProps?.header === null) {
      return false;
    }

    const titleText = h.title || h.label || '';
    if (titleText.trim() === '') {
      return false;
    }

    return true;
  });

  const hdrs = rawHeaders.map((header) => {
    const isCentered = header.align === 'center' || header.header === 'center';
    return {
      ...header,
      key: header.key || header.name,
      title: header.title || header.label,
      align: isCentered ? 'center' : (header.align || 'start'),
      headerProps: {
        class: isCentered
          ? 'font-weight-bold bg-info text-white ps-10'
          : 'font-weight-bold bg-info text-white',
      },
    };
  });

  if (showActions.value) {
    hdrs.push({
      title: t('tables.actions'),
      key: 'actions',
      align: 'end',
      headerProps: { class: 'font-weight-bold bg-info text-white' },
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

  return new Intl.DateTimeFormat('en-CA').format(d);
}

const internalSelectedRows = computed({
  get: () => props.selectedRows,
  set: (val) => emit('update:selectedRows', val),
});
const selectedIds = computed(() => internalSelectedRows.value.map((row) => row.id));
const hasStatusColumn = computed(() => props.headers.some((h) => h.key === 'status'));
const hasConnectedColumn = computed(() => props.headers.some((h) => h.key === 'connected'));
const hasIncludeZerosColumn = computed(() => props.headers.some((h) => h.key === 'includeZeros'));
const hasInstValidColumn = computed(() => props.headers.some((h) => h.key === 'inst_valid'));
const hasPlatValidColumn = computed(() => props.headers.some((h) => h.key === 'plat_valid'));

const filteredItems = computed(() => {
  const searchTerm = (props.search ?? '').trim().toLowerCase();
  let list = props.items;

  if (props.showSelectedOnly) {
    list = list.filter((item) => selectedIds.value.includes(item.id));
  } else if (searchTerm) {
    list = list.filter((item) =>
      props.searchFields.some((field) => {
        const value = item[field];
        return typeof value === 'string' && value.toLowerCase().includes(searchTerm);
      })
    );
  }

  return sortDatasetItems(list, tableSetup[props.dataset]?.sortby);
});


const deleteDialog = ref(false);
const curItem = ref({});
const errorDialog = ref(false);
const current_error = ref({ id: null, message: '', explanation: '', detail: '', process_step: '', help_url: '' });

const emit = defineEmits(['update:selectedRows', 'update:toggle', 'update:report', 'edit', 'delete']);
const { ccRawJson } = useAuthStore();

const reportSort = (a, b) => {
  return a.sortval - b.sortval;
};

const customKeySort = {
  DR: (a, b) => reportSort(a, b),
  PR: (a, b) => reportSort(a, b),
  TR: (a, b) => reportSort(a, b),
  IR: (a, b) => reportSort(a, b),
};

const refreshDataset = computed(() => {
  return props.dataset == 'harvests' || props.dataset == 'jobs' || props.dataset == 'audit';
});

const noDataMessage = computed(() => {
  if (!refreshDataset.value) return t('tables.no_data_default');
  return props.dataset == 'jobs'
    ? t('tables.no_data_jobs')
    : t('tables.no_data_refresh');
});

function formatTruncatedDisplay(value, limit) {
  if (value == null || value === '' || value === '-required-') return value ?? '';
  const str = String(value);
  return str.length > limit ? `${str.slice(0, limit)}...` : str;
}


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

</script>

<!-- components/tables/DataTable.vue -->
<template>
  <div class="w-100">

    <!-- Top Table Header Utility Bar Area -->
    <div class="d-flex flex-column ga-2 mb-4">
      <v-alert v-if="truncated" type="warning" variant="tonal" density="compact" :text="$t('tables.truncated_warning')"
        class="font-weight-bold" />

      <!-- Selection count indicator chip & selection filter toggle button -->
      <div v-if="props.selectableRows && selectedRows.length > 0" class="align-self-start ms-4">
        <v-tooltip
          :text="props.showSelectedOnly ? $t('tables.show_all') : $t('tables.show_selected_only')"
          location="top"
        >
          <template #activator="{ props: tooltipProps }">
            <v-chip
              v-bind="tooltipProps"
              color="primary"
              :variant="props.showSelectedOnly ? 'flat' : 'tonal'"
              prepend-icon="mdi-check-circle"
              :append-icon="props.showSelectedOnly ? 'mdi-filter-off' : 'mdi-filter'"
              class="font-weight-bold cursor-pointer"
              :aria-label="props.showSelectedOnly ? $t('tables.show_all') : $t('tables.show_selected_only')"
              @click="$emit('update:toggleSelectedOnly', !props.showSelectedOnly)"
            >
              {{ $t('tables.items_selected', { count: selectedRows.length }, selectedRows.length) }}
            </v-chip>
          </template>
        </v-tooltip>
      </div>

    </div>

    <!-- Core Interactive Data Grid Matrix -->
    <v-data-table v-model="internalSelectedRows" :headers="computedHeaders"
      :header-props="{ class: 'bg-info text-white' }" :items="filteredItems" item-key="id" item-value="id"
      :show-select="props.selectableRows" return-object :loading="props.isLoading" :custom-key-sort="customKeySort"
      class="elevation-4 bg-surface rounded-lg overflow-hidden">


      <template #no-data>
        <v-alert type="info" variant="tonal" density="compact" icon="mdi-alert-circle-outline" class="ma-4"
          :text="noDataMessage" />
      </template>

      <!-- UNIFIED SELECTION CELL ARCHITECTURE -->
      <template #header.data-table-select="{ allSelected, selectAll, someSelected }">
        <v-checkbox-btn :indeterminate="someSelected && !allSelected" :model-value="allSelected" color="white"
          base-color="white" @update:model-value="selectAll" />
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
        <ToggleIcon v-model="item[report]" :toggleable="true" :size="24"
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
            {<v-btn size="small" density="compact" class="text-primary text-none px-0" :title="item.harvest_id"
              @click="ccRawJson(item.harvest_id)">{{ $t('tables.download_json') }}</v-btn>}
          </span>
          <span v-else>{{ item.harvest_id }}</span>

          <v-btn icon="mdi-barley" variant="text" size="small" density="comfortable" color="info"
            :title="$t('tables.manual_retry_title')" :aria-label="$t('tables.manual_retry_aria')"
            @click="openUrl(item.retryUrl)" />
        </div>
      </template>

      <!-- Error Validation Matrix Column -->
      <template #item.error="{ item }">
        <span v-if="item.error?.id > 0" class="d-flex align-center ga-1">
          {{ item.error.id }}
          <v-btn icon="mdi-dots-vertical" variant="text" size="small" density="comfortable" :color="item.error.color"
            :title="$t('tables.view_error_details')" :aria-label="$t('tables.view_error_details')"
            @click="enableErrorDialog(item.error)" />
        </span>
      </template>

      <!-- Dynamic Date Interceptor Loop -->
      <template v-for="header in computedHeaders.filter(h => h.type === 'date')" :key="header.key"
        #[`item.${header.key}`]="{ value }">
        {{ formatDateDisplay(value) }}
      </template>

      <!-- Dynamic Truncated Interceptor Loop -->
      <template v-for="header in computedHeaders.filter(h => h.truncate > 0)" :key="header.key"
        #[`item.${header.key}`]="{ value }">
        <span v-if="value === '-required-'" class="text-warning font-italic">{{ value }}</span>
        <span v-else>{{ formatTruncatedDisplay(value, header.truncate) }}</span>
      </template>


      <!-- Row Level Operations Action Matrix Column -->
      <template #item.actions="{ item }">
        <div v-if="showActions || props.dataset === 'platforms'" class="d-flex ga-1 justify-end">

          <!-- Condition A: Platform refresh action configurations -->
          <v-tooltip
            v-if="props.dataset === 'platforms' && (item.refresh_result === 'orphan' || item.refresh_result == null)"
            :text="item.refresh_text">
            <template #activator="{ props: tooltipProps }">
              <v-btn :icon="item.refresh_icon" :color="item.refresh_color" variant="text" size="small"
                density="comfortable" v-bind="tooltipProps" :aria-label="$t('tables.platform_refresh_state_aria')" />
            </template>
          </v-tooltip>
        <!-- Condition B: Platform refresh targeting registry index records -->
        <v-tooltip v-else-if="item.refresh_result != null" :text="item.refresh_text">
          <template #activator="{ props: tooltipProps }">
            <v-btn :icon="item.refresh_icon" :color="item.refresh_color" variant="text" size="small"
              density="comfortable" v-bind="tooltipProps" :aria-label="$t('tables.platform_refresh_state_aria')"
              @click="openRegistryPlatform(item.registry_id)" />
          </template>
        </v-tooltip>


          <!-- Edit Action -->
          <v-btn v-if="item.can_edit" icon="mdi-pencil" variant="text" size="small" density="comfortable"
            color="medium-emphasis" :aria-label="$t('tables.edit_item_aria')" @click="$emit('edit', item)" />

          <!-- Delete Action -->
          <v-btn icon="mdi-delete" variant="text" size="small" density="comfortable" :disabled="!item.can_delete"
            :color="item.can_delete ? 'error' : 'medium-emphasis'"
            :aria-label="item.can_delete ? $t('tables.delete_item_aria') : $t('tables.delete_action_disabled')"
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
