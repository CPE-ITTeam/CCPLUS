<!-- views/datasets/ConnectionsTable.vue -->
<script setup>
  import DatasetViewer from '@/components/tables/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="connections" @updateConso="$emit('updateConso', $event)" />
</template>
