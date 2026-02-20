<!-- toolbar/ExportAndImport -->

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
  <v-col v-if='props.showRefresh && !props.exportOnly' cols="auto">
    <v-btn color="primary" @click="$emit('refresh')">Full Registry Refresh</v-btn>
  </v-col>
  <!-- Add Button -->
  <v-col v-if='!props.exportOnly' cols="auto">
    <v-btn color="primary" @click="$emit('add')">
    <v-icon start>mdi-plus</v-icon>
      Add
    </v-btn>
  </v-col>
  <v-col v-if='!props.hideExport' cols="auto">
    <v-btn color="secondary" @click="$emit('export')">
    <v-icon start>mdi-export</v-icon>
      Export
    </v-btn>
  </v-col>
  <!-- hideExport also hides import -->
  <v-col v-if='!props.exportOnly && !props.hideExport' cols="auto">
    <v-btn color="secondary" @click="$emit('import')">
    <v-icon start>mdi-upload</v-icon>
      Import
    </v-btn>
  </v-col>
</template>
