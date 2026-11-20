<!-- components/tables/DataToolbar.vue -->
<template>
  <v-card class="pa-4 mb-4" border>
    <!-- TIER 0: LABELS -->
    <div class="d-flex flex-wrap align-center ga-3 w-100 mb-1">
      <div v-if="!consoOnly || is_serveradmin" style="width: 280px;" class="flex-shrink-0">
        <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
          {{ $t('tables.search') }}
        </span>
      </div>
    </div>

    <!-- TIER 1: CORE ROW -->
    <div class="d-flex flex-wrap align-center ga-3 w-100 mb-3">
      <div v-if="!consoOnly || is_serveradmin" style="width: 280px;" class="flex-shrink-0">
        <SearchAndSelect :search="search" @update:search="$emit('update:search', $event)" />
      </div>
    </div>

    <!-- TIER 1.5: FILTERS BLOCK -->
    <div v-if="filter_options && filter_options.length > 0" class="w-100">
      <div v-for="(row, index) in filter_options" :key="index" class="d-flex flex-column ga-1 flex-grow-1">
        <div v-if="index === 0" class="d-flex flex-wrap align-center ga-3 mb-1">
          <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
            {{ $t('tables.filter', visibleFilterCount) }}
          </span>

          <v-switch
            v-if="flat_filter_options.is_conso"
            :model-value="consoSwitchState.modelValue"
            :indeterminate="consoSwitchState.indeterminate"
            :color="consoSwitchState.color"
            :label="consoSwitchState.label"
              inset
            true-icon="mdi-star-circle"
            false-icon="mdi-home-circle"
            class="flex-grow-0 my-n2"
            style="transform: scale(0.85); transform-origin: left center;"
            @click.prevent="cycleConsoSwitch"
          />

          <v-chip
            v-if="anyFilterSet"
            size="small"
            color="error"
            variant="tonal"
            prepend-icon="mdi-close-circle-outline"
            class="cursor-pointer font-weight-medium"
            @click="clearAllFilters"
          >
            {{ $t('tables.clear_all') }}
          </v-chip>

          <v-btn
            v-if="needsRefreshButton && consoKey != ''"
            size="small"
            variant="tonal"
            prepend-icon="mdi-refresh"
            class="ms-2"
            @click="$emit('refreshRecords')"
          >
            {{ $t('tables.refresh_records') }}
          </v-btn>
        </div>

        <ToolbarFilters :filters="visibleRowFilters(row)" @setFilter="handleFilter($event)" />
      </div>
    </div>

    <!-- TIER 2: ACTIONS PANEL (collapses with smooth vertical ease when empty) -->
    <v-expand-transition>
      <div v-if="(props.selectedRows && props.selectedRows.length > 0 && props.bulkOptions?.items?.length > 0) || isHarvestDataset || showExportImport">
        <v-divider class="my-3" />
        <div class="d-flex flex-wrap align-center justify-space-between ga-4 w-100 pt-1">
          <v-expand-x-transition>
            <div
              v-if="props.selectedRows && props.selectedRows.length > 0 && props.bulkOptions?.items?.length > 0"
              class="d-flex align-center ga-3"
            >
              <BulkActions
                v-model="props.bulkOptions"
                style="width: 280px;"
                @bulkAction="$emit('bulkAction', $event)"
              />
            </div>
          </v-expand-x-transition>

          <!-- LIVE STATUS FILTER BAR (Harvest Queue & Harvest Log, right-aligned in TIER 2) -->
          <div v-if="isHarvestDataset" class="d-flex flex-wrap align-center ga-2 ms-auto">
            <!-- Harvest Stage Chips (or Log Outcome Chips on harvests) -->
            <v-chip v-for="item in harvestStageChips" :key="item.status" size="small"
              :variant="isStatusSelected(item.label) ? 'flat' : 'outlined'" :color="item.color || 'secondary'"
              :prepend-icon="isStatusSelected(item.label) ? 'mdi-check' : 'mdi-filter-outline'"
              class="font-weight-medium cursor-pointer" :title="`Toggle ${item.label} filter`"
              @click="toggleStatusChip(item.label)">
              {{ item.label }}: <strong class="ms-1">{{ item.count }}</strong>
            </v-chip>

            <!-- Visual Divider Between Harvest Stage and Process Stage (Harvest Queue only) -->
            <v-divider v-if="props.dataset === 'jobs' && harvestStageChips.length > 0 && processStageChips.length > 0"
              vertical class="mx-1" />

            <!-- Process Stage Chips (Process Queue & Processing) -->
            <v-chip v-for="item in processStageChips" :key="item.status" size="small"
              :variant="isStatusSelected(item.label) ? 'flat' : 'outlined'" :color="item.color || 'secondary'"
              :prepend-icon="isStatusSelected(item.label) ? 'mdi-check' : 'mdi-filter-outline'"
              class="font-weight-medium cursor-pointer" :title="`Toggle ${item.label} filter`"
              @click="toggleStatusChip(item.label)">
              {{ item.label }}: <strong class="ms-1">{{ item.count }}</strong>
            </v-chip>

            <span v-if="liveStatusCounts.length === 0" class="text-caption text-medium-emphasis px-1">
              {{ props.dataset === 'jobs' ? 'Queue empty' : 'No records' }}
            </span>

            <v-divider v-if="liveStatusCounts.length > 0" vertical class="mx-1" />

            <v-chip size="small" :variant="activeStatusFilters.length === 0 ? 'flat' : 'tonal'" color="info"
              class="font-weight-bold cursor-pointer" title="Show all statuses" @click="resetStatusChips">
              Total: {{ liveQueueTotal }}
            </v-chip>

            <template v-if="props.dataset === 'jobs'">
              <v-divider vertical class="mx-1" />

              <v-chip
                size="x-small"
                variant="tonal"
                :color="workerStatus.harvester_running > 0 ? 'success' : 'secondary'"
                :prepend-icon="workerStatus.harvester_running > 0 ? 'mdi-cog-play' : 'mdi-cog-off-outline'"
                :class="authStore.is_serveradmin ? 'cursor-pointer' : ''"
                :title="authStore.is_serveradmin ? 'Open Queue Workers' : 'Global Harvester Worker status'"
                @click="authStore.is_serveradmin && router.push({ name: 'QueueWorkers' })"
              >
                Harvester: <strong class="ms-1">{{ workerStatus.harvester_running > 0 ? 'Running' : 'Idle' }}</strong>
              </v-chip>

              <v-chip
                size="x-small"
                variant="tonal"
                :color="currentConsoProcessorRunning > 0 ? 'success' : 'secondary'"
                :prepend-icon="currentConsoProcessorRunning > 0 ? 'mdi-cog-play' : 'mdi-cog-off-outline'"
                :class="authStore.is_serveradmin ? 'cursor-pointer' : ''"
                :title="authStore.is_serveradmin ? 'Open Queue Workers' : 'Report Processor status for this consortium'"
                @click="authStore.is_serveradmin && router.push({ name: 'QueueWorkers' })"
              >
                Processor: <strong class="ms-1">{{ currentConsoProcessorRunning > 0 ? 'Running' : 'Idle' }}</strong>
              </v-chip>

              <v-divider vertical class="mx-1" />

              <v-btn size="x-small" variant="tonal" :color="isPolling ? 'success' : 'secondary'"
                :prepend-icon="isPolling ? 'mdi-broadcast' : 'mdi-pause-circle-outline'"
                :title="isPolling ? 'Click to pause live updates' : 'Click to resume live updates'" @click="togglePolling">
                {{ isPolling ? 'Live' : 'Paused' }}
                <span v-if="lastChecked" class="ms-1 opacity-80">· {{ lastChecked }}</span>
              </v-btn>
            </template>
          </div>

          <div v-else-if="showExportImport" class="d-flex align-center flex-wrap ga-2 ms-auto">
            <ExportAndImport :showRefresh="props.dataset == 'platforms'"
              :exportOnly="props.dataset == 'audit'" :hideExport="props.hideExport" @export="$emit('export')"
              @import="importDialog = true" @add="$emit('add')"
              @refresh="$emit('bulkAction', { action: 'Full Refresh' })" />
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
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import api from '@/services/api.js';
import SearchAndSelect from '@/components/ui/SearchAndSelect.vue';
import ToolbarFilters from './ToolbarFilters.vue';
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
const router = useRouter();
const { t } = useI18n();
const authStore = useAuthStore();
const { consortia } = storeToRefs(useCCPlusStore());
const is_serveradmin = authStore.is_serveradmin;
const consoKey = ref(authStore.ccp_key);
const importDialog = ref(false);

const liveStatusCounts = ref([]);
const liveQueueTotal = ref(0);
const lastChecked = ref('');
const isPolling = ref(true);
const activeStatusFilters = ref([]);
const workerStatus = ref({ harvester_running: 0, processor_running: 0, consortia: [] });
let pollTimer = null;

const isHarvestDataset = computed(() =>
  props.dataset === 'jobs' || props.dataset === 'harvests'
);

const harvestStageChips = computed(() =>
  liveStatusCounts.value.filter((item) => item.stage !== 'process')
);

const processStageChips = computed(() =>
  liveStatusCounts.value.filter((item) => item.stage === 'process')
);

const currentConsoProcessorRunning = computed(() => {
  const match = workerStatus.value.consortia?.find((c) => c.ccp_key === authStore.ccp_key);
  return match ? match.processor_running : workerStatus.value.processor_running;
});

watch(
  () => props.queueStats,
  (val) => {
    if (val && Array.isArray(val.status_counts)) {
      liveStatusCounts.value = val.status_counts;
      liveQueueTotal.value = val.queue_total ?? val.status_counts.reduce((sum, s) => sum + (s.count || 0), 0);
    }
  },
  { immediate: true, deep: true }
);

function visibleRowFilters(row) {
  const copy = { ...row };
  delete copy.is_conso;
  if (isHarvestDataset.value) {
    delete copy.statuses;
  }
  return copy;
}

const visibleFilterCount = computed(() =>
  filter_options.value.flatMap((r) => Object.keys(visibleRowFilters(r))).length
);

function isStatusSelected(label) {
  return activeStatusFilters.value.includes(label);
}

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

async function fetchWorkerStatus() {
  if (props.dataset !== 'jobs') return;
  try {
    const res = await api.get('/api/settings/workers');
    const data = res?.data !== undefined ? res.data : res;
    if (data?.result) {
      workerStatus.value = data;
    }
  } catch (e) {
    // Silent background poll failure
  }
}

async function fetchStatusSummary() {
  if (!isHarvestDataset.value) return;
  if (props.dataset === 'jobs' && !isPolling.value) return;

  const endpoint = props.dataset === 'jobs' ? '/api/jobs/getItems' : '/api/harvests/getItems';
  try {
    const [res] = await Promise.all([
      api.post(endpoint, { summary_only: true }),
      props.dataset === 'jobs' ? fetchWorkerStatus() : Promise.resolve(),
    ]);
    const data = res?.data !== undefined ? res.data : res;
    if (data?.result) {
      const prevTotal = liveQueueTotal.value;
      const prevSignature = JSON.stringify(liveStatusCounts.value);
      liveStatusCounts.value = data.status_counts || [];
      liveQueueTotal.value = data.queue_total ?? 0;
      lastChecked.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

      if (
        props.dataset === 'jobs' &&
        prevSignature !== '[]' &&
        (prevTotal !== liveQueueTotal.value || prevSignature !== JSON.stringify(liveStatusCounts.value))
      ) {
        emit('refreshRecords');
      }
    }
  } catch (e) {
    // Silent background poll failure
  }
}

function startPolling() {
  stopPolling();
  if (!isHarvestDataset.value) return;
  fetchStatusSummary();
  if (props.dataset === 'jobs') {
    pollTimer = setInterval(fetchStatusSummary, 5000);
  }
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function togglePolling() {
  isPolling.value = !isPolling.value;
  if (isPolling.value) {
    startPolling();
  } else {
    stopPolling();
  }
}

watch(
  () => props.dataset,
  (ds) => {
    activeStatusFilters.value = [];
    liveStatusCounts.value = [];
    liveQueueTotal.value = 0;
    if (ds === 'jobs' || ds === 'harvests') {
      startPolling();
    } else {
      stopPolling();
    }
  }
);

onMounted(() => {
  if (isHarvestDataset.value) {
    startPolling();
  }
});

onBeforeUnmount(() => {
  stopPolling();
});

const consoOnly = computed(() => {
  return consoKey.value == '' && is_serveradmin;
});

const needsRefreshButton = computed(() => {
  return props.dataset == 'audit';
});

const showExportImport = computed(() => {
  return props.dataset == 'consortia' || (!consoOnly.value && props.dataset != 'harvests' && props.dataset != 'jobs');
});

const flat_filter_options = computed(() => {
  const _options = {};
  filter_options.value.forEach((row, idx) => {
    for (const key of Object.keys(row)) {
      _options[key] = { ...filter_options.value[idx][key] };
    }
  });
  return _options;
});

const consoSwitchState = computed(() => {
  const val = flat_filter_options.value.is_conso?.value || 'All';
  if (val === 'Active') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'primary',
      label: t('fields.limit_to_consortial', 'Limit to Consortial'),
    };
  }
  if (val === 'NonConso') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'secondary',
      label: t('fields.exclude_consortial', 'Exclude Consortial'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'success',
    label: t('fields.all_platforms', 'All Platforms'),
  };
});


function cycleConsoSwitch() {
  const current = flat_filter_options.value.is_conso?.value || 'All';
  const next = current === 'All' ? 'Active' : current === 'Active' ? 'NonConso' : 'All';
  emit('setFilter', { key: 'is_conso', value: next });
}

const anyFilterSet = computed(() => {
  if (activeStatusFilters.value.length > 0) return true;
  let is_set = false;
  for (const key of Object.keys(flat_filter_options.value)) {
    const filter = flat_filter_options.value[key];
    if (!filter.show) continue;

    if (Array.isArray(filter.value)) {
      if (filter.value.length > 0) is_set = true;
    } else if (key === 'is_conso') {
      is_set = filter.value === 'Active' || filter.value === 'NonConso';
    } else if (filter.type == 'toggle') {
      is_set = filter.value == 'Active';
    } else {
      const val = filter.value;
      if (val !== null && val !== undefined && val !== '' && val !== 0 && val !== '0' && val !== 'all' && val !== 'All') {
        is_set = true;
      }
    }
    if (is_set) break;
  }
  return is_set;
});

function handleFilter(filt) {
  let updated = false;
  filter_options.value.forEach((row, idx) => {
    if (updated || typeof filter_options.value[idx][filt.key] == 'undefined') return;
    filter_options.value[idx][filt.key]['value'] = filt.value;
    updated = true;
  });
  if (updated) emit('setFilter', filt);
}

function handleImported(data) {
  emit('imported', data);
  importDialog.value = false;
}

const emit = defineEmits([
  'updateConso',
  'update:search',
  'update:showSelectedOnly',
  'add',
  'export',
  'imported',
  'setFilter',
  'bulkAction',
  'refreshRecords',
]);
</script>
