<!-- toolbar/FiltersAndActions -->
<script setup>
  const props = defineProps({ dataset: { type: String, required: true } });

  const emit = defineEmits(['customAction', 'add']);
  const roles = ['Admin', 'Editor', 'Viewer'];
  const emitAction = (type, payload) => {
    emit('customAction', { type, payload });
  };
</script>

<template>
  <!-- Example: dataset-specific filters -->
  <v-col v-if="dataset === 'users'">
    <v-select label="Role" :items="roles" @change="emitAction('filterRole', $event)" />
  </v-col>

  <!-- Add Button -->
  <v-col cols="auto">
    <v-btn color="primary" @click="$emit('add')">
      <v-icon start>mdi-plus</v-icon>
      Add
    </v-btn>
  </v-col>
</template>