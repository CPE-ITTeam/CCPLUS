<!-- components/tables/cells/HarvestErrorCell.vue -->

<template>
  <span v-if="item.error?.id > 0" class="d-flex align-center ga-1" :class="`text-${item.error.color || 'error'}`">
    {{ item.error.id }}
    <v-btn
      icon="mdi-dots-vertical"
      :color="item.error.color"
      :title="$t('tables.view_error_details')"
      :aria-label="$t('tables.view_error_details')"
      @click="$emit('viewDetails', item.error)"
    />
  </span>

  <v-icon v-else-if="item.status === 'Success'" color="success" size="small" :title="item.d_status">
    mdi-check-circle
  </v-icon>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
});

defineEmits(['viewDetails']);
</script>
