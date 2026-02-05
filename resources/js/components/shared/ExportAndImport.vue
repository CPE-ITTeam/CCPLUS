<!-- toolbar/ExportAndImport -->

<script setup>
  const props = defineProps({
    showAdd: { type: Boolean, required: false, default: false },
  });
  defineEmits(['export', 'import', 'add']);
</script>

<template>
  <!-- Add Button -->
  <v-col v-if="props.showAdd" cols="auto">
    <v-btn color="primary" @click="$emit('add')">
    <v-icon start>mdi-plus</v-icon>
      Add
    </v-btn>
  </v-col>
  <v-col cols="auto">
    <v-btn color="secondary" @click="$emit('export')">
    <v-icon start>mdi-export</v-icon>
      Export
    </v-btn>
  </v-col>
  <v-col cols="auto">
    <v-btn color="secondary" @click="$emit('import')">
    <v-icon start>mdi-upload</v-icon>
      Import
    </v-btn>
  </v-col>
</template>
