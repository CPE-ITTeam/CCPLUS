<!-- toolbar/ExportAndImport -->

<script setup>
  defineEmits(['export', 'import']);
</script>

<template>
  <v-btn color="secondary" @click="$emit('export')">
    <v-icon start>mdi-export</v-icon>
    Export
  </v-btn>

  <v-btn color="secondary" @click="$emit('import')">
    <v-icon start>mdi-upload</v-icon>
    Import
  </v-btn>
</template>
