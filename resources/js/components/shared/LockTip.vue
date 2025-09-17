<!-- components/LockTip.vue -->
<script>
  export default {
    name: 'LockTip',
    props: {
      text: { type: [String], default: 'Field is Read-Only' },
    }
  }
</script>

<template>
  <v-tooltip :text="text">
    <template v-slot:activator="{ props }">
      <v-icon v-bind="props">mdi-lock-outline</v-icon>
    </template>
  </v-tooltip>
</template>
