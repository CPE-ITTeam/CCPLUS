<!-- components/tables/cells/HarvestIdCell.vue -->

<template>
  <div class="d-flex align-center justify-end ga-1">
    <span>{{ item.harvest_id }}</span>

    <v-btn
      v-if="item.rawfile != null"
      icon="mdi-code-json"
      color="primary"
      :title="$t('tables.download_json')"
      :aria-label="$t('tables.download_json')"
      @click="$emit('download', item.harvest_id)"
    />

    <v-btn
      icon="mdi-barley"
      color="info"
      :title="$t('tables.manual_retry_title')"
      :aria-label="$t('tables.manual_retry_aria')"
      @click="$emit('retry', item.retryUrl)"
    />
  </div>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
});

defineEmits(['download', 'retry']);
</script>
