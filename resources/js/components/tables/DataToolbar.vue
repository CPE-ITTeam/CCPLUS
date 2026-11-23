<!-- components/tables/DataToolbar.vue -->
<template>
  <v-card class="pa-4 mb-4" border>
    <v-row v-if="showSearch" dense class="mb-2">
      <v-col cols="12" sm="6" lg="3">
        <div class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis mb-1">
          {{ $t('tables.search') }}
        </div>
        <SearchAndSelect :search="search" class="w-100" @update:search="$emit('update:search', $event)" />
      </v-col>
    </v-row>

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
import { ref, computed, toRef } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useHarvestStatusPoll } from '@/composables/table/useHarvestStatusPoll.js';
import { useToolbarFilters } from '@/composables/table/useToolbarFilters.js';
import SearchAndSelect from '@/components/ui/SearchAndSelect.vue';
import ImportDialog from '@/components/dialogs/ImportDialog.vue';

import BulkActions from '@/components/tables/toolbar/BulkActions.vue';
import ExportAndImport from '@/components/tables/toolbar/ExportAndImport.vue';
import HarvestStatusBar from '@/components/tables/toolbar/HarvestStatusBar.vue';
import ToolbarFilterSection from '@/components/tables/toolbar/ToolbarFilterSection.vue';


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

const {
  activeStatusFilters,
  anyFilterSet,
  handleFilter,
  toggleStatusChip,
  resetStatusChips,
  clearAllFilters,
} = useToolbarFilters(filter_options, toRef(props, 'dataset'), emit);

const consoOnly = computed(() => consoKey.value === '' && isServerAdmin);
const showSearch = computed(() => !consoOnly.value || isServerAdmin);
//const needsRefreshButton = computed(() => props.dataset === 'audit');

const showExportImport = computed(
  () => props.dataset === 'consortia' || (!consoOnly.value && !isHarvestDataset.value)
);

const hasBulkActions = computed(
  () => props.selectedRows?.length > 0 && props.bulkOptions?.items?.length > 0
);

const hasTier2Content = computed(
  () => hasBulkActions.value || isHarvestDataset.value || showExportImport.value
);

function handleImported(data) {
  emit('imported', data);
  importDialog.value = false;
}
</script>
