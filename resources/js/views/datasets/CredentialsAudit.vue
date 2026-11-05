<!-- views/datasets/CredentialsAudit.vue -->
<script setup>
  import DatasetViewer from '@/components/tables/DatasetViewer.vue';
  const emit = defineEmits(['updateConso']);
</script>

<template>
  <DatasetViewer datasetKey="audit" @updateConso="$emit('updateConso', $event)" />
</template>
