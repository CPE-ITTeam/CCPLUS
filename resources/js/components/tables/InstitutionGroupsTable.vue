<!-- tables/InstitutionGroups.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="institutionGroups" @updateConso="$emit('updateConso', $event)" />
</template>
