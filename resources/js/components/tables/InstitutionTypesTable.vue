<!-- tables/InstitutionTypes.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="institutionTypes" @updateConso="$emit('updateConso', $event)" />
</template>
