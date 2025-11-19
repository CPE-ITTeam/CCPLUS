<!-- tables/RolesTable.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="roles" @updateConso="$emit('updateConso', $event)" />
</template>
