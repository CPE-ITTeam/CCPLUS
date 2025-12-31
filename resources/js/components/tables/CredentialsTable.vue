<!-- views/CredentialsTable.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="credentials" @updateConso="$emit('updateConso', $event)" />
</template>
