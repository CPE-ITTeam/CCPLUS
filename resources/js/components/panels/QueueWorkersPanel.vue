<!-- resources/js/components/panels/QueueWorkersPanel.vue -->
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

defineOptions({ name: 'QueueWorkersPanel', inheritAttrs: false });

const authStore = useAuthStore();

const workerInfo = ref({
  harvester_running: 0,
  processor_running: 0,
  harvest_queue_count: 0,
  process_queue_count: 0,
  consortia: [],
});
const workerBusy = ref(false);
const workerSuccess = ref('');
const workerFailure = ref('');
let pollTimer = null;

async function fetchWorkerStatus() {
  try {
    const res = await api.get('/api/settings/workers');
    const data = res?.data ?? res;
    if (data?.result) workerInfo.value = data;
  } catch (e) {
    console.error('Error fetching worker status:', e.message);
  }
}

async function startWorker(worker, targetConso = null) {
  workerBusy.value = true;
  workerSuccess.value = '';
  workerFailure.value = '';
  try {
    const res = await api.post('/api/settings/workers/run', {
      worker,
      consortium: targetConso || authStore.ccp_key,
    });
    const data = res?.data ?? res;
    if (data?.result) {
      workerSuccess.value = data.msg;
      setTimeout(fetchWorkerStatus, 800);
    } else {
      workerFailure.value = data?.msg || 'Failed to start worker.';
    }
  } catch (e) {
    workerFailure.value = 'Failed to start worker.';
  } finally {
    workerBusy.value = false;
  }
}

onMounted(() => {
  fetchWorkerStatus();
  pollTimer = setInterval(fetchWorkerStatus, 5000);
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
  <SettingsPanelShell
    class="mt-4"
    :title="$t('server.queue_workers', 'Queue & Background Workers')"
    icon="mdi-cog-sync-outline"
    :show-submit="false"
  >
    <div class="d-flex align-center justify-end mb-4">
      <v-btn size="small" variant="text" prepend-icon="mdi-refresh" @click="fetchWorkerStatus">
        {{ $t('common.refresh', 'Refresh') }}
      </v-btn>
    </div>

    <!-- Global Harvester Worker Banner -->
    <v-sheet border rounded="lg" class="pa-4 mb-4 d-flex flex-wrap align-center justify-space-between ga-3">
      <div>
        <div class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
          <span>Global Harvester Worker (ccplus:harvester)</span>
          <v-chip size="x-small" :color="workerInfo.harvester_running > 0 ? 'success' : 'secondary'" variant="tonal">
            {{ workerInfo.harvester_running > 0 ? `${workerInfo.harvester_running} Running` : 'Idle' }}
          </v-chip>
        </div>
        <div class="text-body-2 text-medium-emphasis">
          Total across all consortia: <strong>{{ workerInfo.harvest_queue_count }}</strong> in Harvest Queue ·
          <strong>{{ workerInfo.process_queue_count }}</strong> in Process Queue
        </div>
      </div>
      <v-btn
        color="primary"
        variant="tonal"
        prepend-icon="mdi-play"
        :loading="workerBusy"
        :disabled="workerInfo.harvester_running > 0"
        @click="startWorker('harvester')"
      >
        Run Harvester
      </v-btn>
    </v-sheet>

    <!-- Worker Feedback Alerts -->
    <StatusAlerts
      v-model:success="workerSuccess"
      v-model:failure="workerFailure"
      mode="modal"
      container-class="mb-4"
    />

    <!-- Per-Consortium Report Processor Card -->
    <v-sheet v-if="workerInfo.consortia?.length > 0" border rounded="lg" class="overflow-hidden">
      <v-table density="comfortable">
        <thead style="background-color: rgba(var(--v-theme-secondary), 0.12);">
          <tr>
            <th>Consortium</th>
            <th class="text-end">Harvest Queue</th>
            <th class="text-end">Process Queue</th>
            <th class="text-center">Report Processor</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="con in workerInfo.consortia" :key="con.ccp_key">
            <td>
              <div class="font-weight-medium">{{ con.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ con.ccp_key }}</div>
            </td>
            <td class="text-end">{{ con.harvest_queue_count }}</td>
            <td class="text-end font-weight-bold">{{ con.process_queue_count }}</td>
            <td class="text-center">
              <v-chip size="x-small" :color="con.processor_running > 0 ? 'success' : 'secondary'" variant="tonal">
                {{ con.processor_running > 0 ? `${con.processor_running} Running` : 'Idle' }}
              </v-chip>
            </td>
            <td class="text-end">
              <v-btn
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="mdi-play"
                :loading="workerBusy"
                @click="startWorker('reportprocessor', con.ccp_key)"
              >
                Run Processor
              </v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-sheet>
  </SettingsPanelShell>
</template>
