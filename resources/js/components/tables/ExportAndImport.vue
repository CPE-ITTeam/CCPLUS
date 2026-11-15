<!-- components/tables/ExportAndImport.vue -->
<script setup>
const props = defineProps({
  showRefresh: { type: Boolean, required: true },
  exportOnly: { type: Boolean, required: true },
  hideExport: { type: Boolean, required: true },
});

defineEmits(['export', 'import', 'add', 'refresh']);
</script>

<template>
  <!-- Refresh Button -->
  <v-col v-if="props.showRefresh && !props.exportOnly" cols="auto">
    <v-btn @click="$emit('refresh')">{{ $t('tables.full_registry_refresh') }}</v-btn>
  </v-col>

  <!-- Add Button -->
  <v-col v-if="!props.exportOnly" cols="auto">
    <v-btn @click="$emit('add')">
      <v-icon start>mdi-plus</v-icon>
      {{ $t('tables.add') }}
    </v-btn>
  </v-col>

  <!-- Export Button -->
  <v-col v-if="!props.hideExport" cols="auto">
    <v-btn color="info" @click="$emit('export')">
      <v-icon start>mdi-export</v-icon>
      {{ $t('tables.export') }}
    </v-btn>
  </v-col>

  <!-- Import Button (hideExport also hides import) -->
  <v-col v-if="!props.exportOnly && !props.hideExport" cols="auto">
    <v-btn color="info" @click="$emit('import')">
      <v-icon start>mdi-upload</v-icon>
      {{ $t('tables.import') }}
    </v-btn>
  </v-col>
</template>
