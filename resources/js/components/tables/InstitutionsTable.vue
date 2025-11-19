<!-- tables/Institutions.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="institutions" @updateConso="$emit('updateConso', $event)" />
</template>
