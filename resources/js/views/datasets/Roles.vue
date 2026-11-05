<!-- views/datasets/RolesTable.vue -->
<script setup>
  import DatasetViewer from '@/components/tables/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="roles" @updateConso="$emit('updateConso', $event)" />
</template>
