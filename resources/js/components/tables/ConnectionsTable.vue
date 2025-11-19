<!-- views/ConnectionsTable.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="connections" @updateConso="$emit('updateConso', $event)" />
</template>
