<!-- tables/HarvestLog.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="harvests" @updateConso="$emit('updateConso', $event)" />
</template>
