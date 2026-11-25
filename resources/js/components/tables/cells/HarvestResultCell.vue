<!-- components/tables/cells/HarvestResultCell.vue -->
<template>
  <span
    v-if="item.error?.id > 0"
    class="d-inline-flex align-center justify-center ga-1"
    :class="`text-${item.error.color || 'error'}`"
  >
    {{ item.error.id }}
    <v-btn
      icon="mdi-dots-vertical"
      :color="item.error.color"
      :title="$t('tables.view_error_details')"
      :aria-label="$t('tables.view_error_details')"
      @click="$emit('viewDetails', item.error)"
    />
  </span>

  <v-icon
    v-else-if="item.status === 'Success' || item.result === 'Success'"
    color="success"
    size="small"
    :title="item.d_status || 'Success'"
  >
    mdi-check-circle
  </v-icon>

  <v-icon
    v-else-if="item.result === 'No Harvests'"
    color="medium-emphasis"
    size="small"
    :title="item.result"
  >
    mdi-minus-circle-outline
  </v-icon>

  <span
    v-else-if="item.result"
    :class="Number(item.result) > 0 ? 'text-error font-weight-medium' : ''"
  >
    {{ item.result }}
  </span>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
});

defineEmits(['viewDetails']);
</script>
