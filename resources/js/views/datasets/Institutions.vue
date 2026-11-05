<!-- views/datasets/Institutions.vue -->
<script setup>
  import DatasetViewer from '@/components/tables/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="institutions" @updateConso="$emit('updateConso', $event)" />
</template>
