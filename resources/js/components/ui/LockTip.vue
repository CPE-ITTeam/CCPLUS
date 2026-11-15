<!-- components/ui/LockTip.vue -->
<script setup>
defineProps({
  text: { type: String, default: '' },
});
</script>

<template>
  <v-tooltip :text="text || $t('common.field_read_only')">
    <template #activator="{ props: tooltipProps }">
      <v-icon
        v-bind="tooltipProps"
        size="small"
        color="medium-emphasis"
        style="pointer-events: auto; cursor: help;"
      >
        mdi-lock-outline
      </v-icon>
    </template>
  </v-tooltip>
</template>
