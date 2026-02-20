<!-- tables/CredentialsAudit.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="audit" @updateConso="$emit('updateConso', $event)" />
</template>
