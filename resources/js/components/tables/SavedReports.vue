<!-- tables/SavedReports.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="savedreports" @updateConso="$emit('updateConso', $event)" />
</template>
