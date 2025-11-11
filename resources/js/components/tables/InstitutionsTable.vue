<!-- tables/Institutions.vue -->
<script setup>
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const emit = defineEmits(['update:conso']);
</script>

<template>
  <DatasetViewer datasetKey="institutions" @update:conso="$emit('update:conso', $event)" />
</template>
