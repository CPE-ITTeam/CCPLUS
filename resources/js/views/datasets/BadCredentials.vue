<!-- views/datasets/BadCredentials.vue -->
<script setup>
  import DatasetViewer from '@/components/tables/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="badCredentials" @updateConso="$emit('updateConso', $event)" />
</template>
