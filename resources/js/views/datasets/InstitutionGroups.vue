<!-- views/datasets/InstitutionGroups.vue -->
<script setup>
  import DatasetViewer from '@/components/tables/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="groups" @updateConso="$emit('updateConso', $event)" />
</template>
