<!-- components/ui/FlexCol.vue -->
<template>
  <v-col
    v-bind="$attrs"
    :cols="cols"
    :sm="sm"
    :md="md"
    :lg="lg"
    class="d-flex flex-column ga-5"
  >
    <slot />
  </v-col>
</template>

<script>
  export default {
    name: 'FlexCol',
    inheritAttrs: false,
    props: {
      cols: { type: [Number, String], default: 12 },
      sm: { type: [Number, String], default: 6 },
      md: { type: [Number, String], default: 4 },
      lg: { type: [Number, String], default: 3 }
    }
  }
</script>
