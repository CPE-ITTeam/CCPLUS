<!-- components/dialogs/BaseDialogLayout.vue -->
<template>
  <v-card class="d-flex flex-column" max-height="90vh">
    
    <!-- Fixed Header -->
    <v-card-item class="flex-shrink-0">
      <v-card-title>
        <slot name="title"></slot>
      </v-card-title>

      <template #append>
        <v-btn
          icon="mdi-close"
          aria-label="Close dialog"
          @click="$emit('close')"
        />
      </template>
    </v-card-item>

    <!-- Fixed Subtitle (if provided) -->
    <v-card-subtitle v-if="$slots.subtitle" class="flex-shrink-0">
      <slot name="subtitle"></slot>
    </v-card-subtitle>

    <!-- Scrollable Form / Content Body -->
    <v-card-text :class="['overflow-y-auto flex-grow-1', props.contentClass]">
      <slot></slot>
    </v-card-text>

    <!-- Fixed Footer Actions -->
    <template v-if="$slots.actions">
      <v-divider class="flex-shrink-0"></v-divider>
      <v-card-actions class="flex-shrink-0">
        <slot name="actions"></slot>
      </v-card-actions>
    </template>

  </v-card>
</template>

<script setup>
const props = defineProps({
  contentClass: { type: String, default: '' }
});

defineEmits(['close']);
</script>
