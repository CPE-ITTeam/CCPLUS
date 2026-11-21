<!-- components/tables/toolbar/HarvestStatusBar.vue -->

<template>
  <div class="d-flex flex-wrap align-center ga-2 ms-auto">
    <v-chip
      v-for="item in harvestStageChips"
      :key="item.status"
      size="small"
      :variant="isSelected(item.label) ? 'flat' : 'outlined'"
      :color="item.color || 'secondary'"
      :prepend-icon="isSelected(item.label) ? 'mdi-check' : 'mdi-filter-outline'"
      class="font-weight-medium cursor-pointer"
      :title="`Toggle ${item.label} filter`"
      @click="$emit('toggleStatus', item.label)"
    >
      {{ item.label }}: <strong class="ms-1">{{ item.count }}</strong>
    </v-chip>

    <v-divider
      v-if="dataset === 'jobs' && harvestStageChips.length > 0 && processStageChips.length > 0"
      vertical
      class="mx-1"
    />

    <v-chip
      v-for="item in processStageChips"
      :key="item.status"
      size="small"
      :variant="isSelected(item.label) ? 'flat' : 'outlined'"
      :color="item.color || 'secondary'"
      :prepend-icon="isSelected(item.label) ? 'mdi-check' : 'mdi-filter-outline'"
      class="font-weight-medium cursor-pointer"
      :title="`Toggle ${item.label} filter`"
      @click="$emit('toggleStatus', item.label)"
    >
      {{ item.label }}: <strong class="ms-1">{{ item.count }}</strong>
    </v-chip>

    <span v-if="totalCount === 0 && harvestStageChips.length === 0 && processStageChips.length === 0" class="text-caption text-medium-emphasis px-1">
      {{ dataset === 'jobs' ? 'Queue empty' : 'No records' }}
    </span>

    <v-divider v-if="harvestStageChips.length > 0 || processStageChips.length > 0" vertical class="mx-1" />

    <v-chip
      size="small"
      :variant="activeStatuses.length === 0 ? 'flat' : 'tonal'"
      color="info"
      class="font-weight-bold cursor-pointer"
      title="Show all statuses"
      @click="$emit('resetStatuses')"
    >
      Total: {{ totalCount }}
    </v-chip>

    <template v-if="dataset === 'jobs'">
      <v-divider vertical class="mx-1" />

      <v-chip
        size="x-small"
        variant="tonal"
        :color="workerStatus.harvester_running > 0 ? 'success' : 'secondary'"
        :prepend-icon="workerStatus.harvester_running > 0 ? 'mdi-cog-play' : 'mdi-cog-off-outline'"
        :class="isServerAdmin ? 'cursor-pointer' : ''"
        :title="isServerAdmin ? 'Open Queue Workers' : 'Global Harvester Worker status'"
        @click="isServerAdmin && router.push({ name: 'QueueWorkers' })"
      >
        Harvester: <strong class="ms-1">{{ workerStatus.harvester_running > 0 ? 'Running' : 'Idle' }}</strong>
      </v-chip>

      <v-chip
        size="x-small"
        variant="tonal"
        :color="currentConsoProcessorRunning > 0 ? 'success' : 'secondary'"
        :prepend-icon="currentConsoProcessorRunning > 0 ? 'mdi-cog-play' : 'mdi-cog-off-outline'"
        :class="isServerAdmin ? 'cursor-pointer' : ''"
        :title="isServerAdmin ? 'Open Queue Workers' : 'Report Processor status for this consortium'"
        @click="isServerAdmin && router.push({ name: 'QueueWorkers' })"
      >
        Processor: <strong class="ms-1">{{ currentConsoProcessorRunning > 0 ? 'Running' : 'Idle' }}</strong>
      </v-chip>

      <v-divider vertical class="mx-1" />

      <v-btn
        size="x-small"
        variant="tonal"
        :color="isPolling ? 'success' : 'secondary'"
        :prepend-icon="isPolling ? 'mdi-broadcast' : 'mdi-pause-circle-outline'"
        :title="isPolling ? 'Click to pause live updates' : 'Click to resume live updates'"
        @click="$emit('togglePolling')"
      >
        {{ isPolling ? 'Live' : 'Paused' }}
        <span v-if="lastChecked" class="ms-1 opacity-80">· {{ lastChecked }}</span>
      </v-btn>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({
  dataset: { type: String, required: true },
  harvestStageChips: { type: Array, default: () => [] },
  processStageChips: { type: Array, default: () => [] },
  totalCount: { type: Number, default: 0 },
  activeStatuses: { type: Array, default: () => [] },
  workerStatus: { type: Object, required: true },
  ccpKey: { type: String, default: '' },
  isServerAdmin: { type: Boolean, default: false },
  isPolling: { type: Boolean, default: true },
  lastChecked: { type: String, default: '' },
});

defineEmits(['toggleStatus', 'resetStatuses', 'togglePolling']);

const router = useRouter();

const currentConsoProcessorRunning = computed(() => {
  const match = props.workerStatus.consortia?.find((c) => c.ccp_key === props.ccpKey);
  return match ? match.processor_running : props.workerStatus.processor_running;
});

function isSelected(label) {
  return props.activeStatuses.includes(label);
}
</script>
