// resources/js/composables/table/useHarvestStatusPoll.JS

import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import api from '@/services/api.js';

export function useHarvestStatusPoll(dataset, queueStats, onRefreshRecords) {
  const liveStatusCounts = ref([]);
  const liveQueueTotal = ref(0);
  const lastChecked = ref('');
  const isPolling = ref(true);
  const workerStatus = ref({ harvester_running: 0, processor_running: 0, consortia: [] });
  let pollTimer = null;

  const isHarvestDataset = computed(() =>
    dataset.value === 'jobs' || dataset.value === 'harvests'
  );

  const harvestStageChips = computed(() =>
    liveStatusCounts.value.filter((item) => item.stage !== 'process')
  );

  const processStageChips = computed(() =>
    liveStatusCounts.value.filter((item) => item.stage === 'process')
  );

  watch(
    queueStats,
    (val) => {
      if (val && Array.isArray(val.status_counts)) {
        liveStatusCounts.value = val.status_counts;
        liveQueueTotal.value =
          val.queue_total ?? val.status_counts.reduce((sum, s) => sum + (s.count || 0), 0);
      }
    },
    { immediate: true, deep: true }
  );

  async function fetchWorkerStatus() {
    if (dataset.value !== 'jobs') return;
    try {
      const res = await api.get('/api/settings/workers');
      const data = res?.data !== undefined ? res.data : res;
      if (data?.result) {
        workerStatus.value = data;
      }
    } catch {
      // Silent background poll failure
    }
  }

  async function fetchStatusSummary() {
    if (!isHarvestDataset.value) return;
    if (dataset.value === 'jobs' && !isPolling.value) return;

    const endpoint = dataset.value === 'jobs' ? '/api/jobs/getItems' : '/api/harvests/getItems';
    try {
      const [res] = await Promise.all([
        api.post(endpoint, { summary_only: true }),
        dataset.value === 'jobs' ? fetchWorkerStatus() : Promise.resolve(),
      ]);
      const data = res?.data !== undefined ? res.data : res;
      if (data?.result) {
        const prevTotal = liveQueueTotal.value;
        const prevSignature = JSON.stringify(liveStatusCounts.value);
        liveStatusCounts.value = data.status_counts || [];
        liveQueueTotal.value = data.queue_total ?? 0;
        lastChecked.value = new Date().toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
        });

        if (
          dataset.value === 'jobs' &&
          prevSignature !== '[]' &&
          (prevTotal !== liveQueueTotal.value || prevSignature !== JSON.stringify(liveStatusCounts.value))
        ) {
          onRefreshRecords?.();
        }
      }
    } catch (e) {
      if (e?.response?.status === 401) {
        stopPolling();
      }
    }
  }

  function startPolling() {
    stopPolling();
    if (!isHarvestDataset.value) return;
    fetchStatusSummary();
    if (dataset.value === 'jobs') {
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

  watch(dataset, (ds) => {
    liveStatusCounts.value = [];
    liveQueueTotal.value = 0;
    if (ds === 'jobs' || ds === 'harvests') {
      startPolling();
    } else {
      stopPolling();
    }
  });

  onMounted(() => {
    if (isHarvestDataset.value) {
      startPolling();
    }
  });

  onBeforeUnmount(() => {
    stopPolling();
  });

  return {
    isHarvestDataset,
    liveStatusCounts,
    liveQueueTotal,
    harvestStageChips,
    processStageChips,
    lastChecked,
    isPolling,
    workerStatus,
    togglePolling,
  };
}
