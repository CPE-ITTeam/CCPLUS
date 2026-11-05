<!-- components/ui/FlexCol.vue -->
<template>
  <v-col
    :cols="cols"
    :sm="sm"
    :md="md"
    :lg="lg"
  >
    <slot />
  </v-col>
</template>

<script>
  export default {
    name: 'FlexCol',
    inheritAttrs: false, // 👈 ADD THIS LINE TO PREVENT UNTRACKED ATTRIBUTES FROM FALLING THROUGH
    props: {
      cols: { type: [Number, String], default: 12 },
      sm: { type: [Number, String], default: 6 },
      md: { type: [Number, String], default: 4 },
      lg: { type: [Number, String], default: 3 }
    }
  }
</script>
