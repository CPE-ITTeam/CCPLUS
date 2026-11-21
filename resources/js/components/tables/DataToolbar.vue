<!-- components/tables/DataToolbar.vue -->
<template>
  <v-card class="pa-4 mb-4" border>
    <template v-if="showSearch">
      <div class="d-flex flex-wrap align-center ga-3 w-100 mb-1">
        <div style="width: 280px;" class="flex-shrink-0">
          <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
            {{ $t('tables.search') }}
          </span>
        </div>
      </div>

      <div class="d-flex flex-wrap align-center ga-3 w-100 mb-3">
        <div style="width: 280px;" class="flex-shrink-0">
          <SearchAndSelect :search="search" @update:search="$emit('update:search', $event)" />
        </div>
      </div>
    </template>

    <ToolbarFilterSection
      :filter-options="filter_options"
      :is-harvest-dataset="isHarvestDataset"
      :any-filter-set="anyFilterSet"
      :show-refresh-button="needsRefreshButton && consoKey !== ''"
      @setFilter="handleFilter"
      @clearAll="clearAllFilters"
      @refreshRecords="$emit('refreshRecords')"
    />

    <v-expand-transition>
      <div v-if="hasTier2Content">
        <v-divider class="my-3" />
        <div class="d-flex flex-wrap align-center justify-space-between ga-4 w-100 pt-1">
          <v-expand-x-transition>
            <div v-if="hasBulkActions" class="d-flex align-center ga-3">
              <BulkActions
                v-model="bulkOptionsModel"
                style="width: 280px;"
                @bulkAction="$emit('bulkAction', $event)"
              />
            </div>
          </v-expand-x-transition>

          <HarvestStatusBar
            v-if="isHarvestDataset"
            :dataset="props.dataset"
            :harvest-stage-chips="harvestStageChips"
            :process-stage-chips="processStageChips"
            :total-count="liveQueueTotal"
            :active-statuses="activeStatusFilters"
            :worker-status="workerStatus"
            :ccp-key="authStore.ccp_key"
            :is-server-admin="isServerAdmin"
            :is-polling="isPolling"
            :last-checked="lastChecked"
            @toggleStatus="toggleStatusChip"
            @resetStatuses="resetStatusChips"
            @togglePolling="togglePolling"
          />

          <div v-else-if="showExportImport" class="d-flex align-center flex-wrap ga-2 ms-auto">
            <ExportAndImport
              :showRefresh="props.dataset === 'platforms'"
              :exportOnly="props.dataset === 'audit'"
              :hideExport="props.hideExport"
              @export="$emit('export')"
              @import="importDialog = true"
              @add="$emit('add')"
              @refresh="$emit('bulkAction', { action: 'Full Refresh' })"
            />
          </div>
        </div>
      </div>
    </v-expand-transition>

    <v-dialog v-model="importDialog">
      <ImportDialog :dataset="props.dataset" @close="importDialog = false" @imported="handleImported" />
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, watch, toRef } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useHarvestStatusPoll } from '@/composables/useHarvestStatusPoll.js';
import SearchAndSelect from '@/components/ui/SearchAndSelect.vue';
import ToolbarFilterSection from './ToolbarFilterSection.vue';
import HarvestStatusBar from './HarvestStatusBar.vue';
import BulkActions from './BulkActions.vue';
import ExportAndImport from './ExportAndImport.vue';
import ImportDialog from '@/components/dialogs/ImportDialog.vue';

const filter_options = defineModel({ type: Array, required: true });

const props = defineProps({
  search: { type: String, required: true },
  showSelectedOnly: { type: Boolean, required: true },
  dataset: { type: String, required: true },
  selectedRows: { type: Array, required: true },
  bulkOptions: { type: Object, required: true },
  hideExport: { type: Boolean, required: true },
  queueStats: { type: Object, default: () => ({ status_counts: [], queue_total: 0 }) },
});

const emit = defineEmits([
  'updateConso',
  'update:search',
  'update:showSelectedOnly',
  'update:bulkOptions',
  'add',
  'export',
  'imported',
  'setFilter',
  'bulkAction',
  'refreshRecords',
]);

const authStore = useAuthStore();
const isServerAdmin = authStore.is_serveradmin;
const consoKey = ref(authStore.ccp_key);
const importDialog = ref(false);
const activeStatusFilters = ref([]);

const bulkOptionsModel = computed({
  get: () => props.bulkOptions,
  set: (val) => emit('update:bulkOptions', val),
});

const {
  isHarvestDataset,
  liveQueueTotal,
  harvestStageChips,
  processStageChips,
  lastChecked,
  isPolling,
  workerStatus,
  togglePolling,
} = useHarvestStatusPoll(
  toRef(props, 'dataset'),
  toRef(props, 'queueStats'),
  () => emit('refreshRecords')
);

watch(
  () => props.dataset,
  () => {
    activeStatusFilters.value = [];
  }
);

const consoOnly = computed(() => consoKey.value === '' && isServerAdmin);
const showSearch = computed(() => !consoOnly.value || isServerAdmin);
const needsRefreshButton = computed(() => props.dataset === 'audit');

const showExportImport = computed(
  () => props.dataset === 'consortia' || (!consoOnly.value && !isHarvestDataset.value)
);

const hasBulkActions = computed(
  () => props.selectedRows?.length > 0 && props.bulkOptions?.items?.length > 0
);

const hasTier2Content = computed(
  () => hasBulkActions.value || isHarvestDataset.value || showExportImport.value
);

const anyFilterSet = computed(() => {
  if (activeStatusFilters.value.length > 0) return true;

  return filter_options.value.some((row) =>
    Object.entries(row).some(([key, filter]) => {
      if (!filter.show) return false;
      if (Array.isArray(filter.value)) return filter.value.length > 0;
      if (key === 'is_conso') return filter.value === 'Active' || filter.value === 'NonConso';
      if (filter.type === 'toggle') return filter.value === 'Active';

      const val = filter.value;
      return (
        val !== null &&
        val !== undefined &&
        val !== '' &&
        val !== 0 &&
        val !== '0' &&
        val !== 'all' &&
        val !== 'All'
      );
    })
  );
});

function toggleStatusChip(label) {
  const idx = activeStatusFilters.value.indexOf(label);
  if (idx >= 0) {
    activeStatusFilters.value.splice(idx, 1);
  } else {
    activeStatusFilters.value.push(label);
  }
  const nextVal = [...activeStatusFilters.value];
  handleFilter({ key: 'statuses', value: nextVal });
  emit('setFilter', { key: 'statuses', value: nextVal, refresh: true });
}

function resetStatusChips() {
  activeStatusFilters.value = [];
  handleFilter({ key: 'statuses', value: [] });
  emit('setFilter', { key: 'statuses', value: [], refresh: true });
}

function clearAllFilters() {
  activeStatusFilters.value = [];
  emit('setFilter', { key: 'reset', refresh: true });
}

function handleFilter(filt) {
  for (const row of filter_options.value) {
    if (row[filt.key] !== undefined) {
      row[filt.key].value = filt.value;
      emit('setFilter', filt);
      break;
    }
  }
}

function handleImported(data) {
  emit('imported', data);
  importDialog.value = false;
}
</script>
