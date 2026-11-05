<!-- components/dialogs/BaseDialogLayout.vue -->
<template>
  <v-card>
    
    <!-- Unified Header Line -->
    <!-- 💡 FIXED: Added 'dialog-header-strip' to target the internal layout grid -->
    <v-card-item class="dialog-header-strip">
      <v-card-title class="d-flex justify-space-between align-center w-100 pa-0">
        <div class="text-white">
          <slot name="title"></slot>
        </div>
        <v-btn 
          icon="mdi-close" 
          variant="text" 
          color="white" 
          density="comfortable" 
          aria-label="Close dialog"
          @click="$emit('close')" 
        />
      </v-card-title>
    </v-card-item>

    <v-card-subtitle v-if="$slots.subtitle" class="pt-3 px-4">
      <slot name="subtitle"></slot>
    </v-card-subtitle>

    <!-- Content Area -->
    <v-card-text :class="props.contentClass">
      <slot></slot>
    </v-card-text>

    <!-- Optional Footer Actions Layout Row -->
    <template v-if="$slots.actions">
      <v-divider></v-divider>
      <v-card-actions>
        <slot name="actions"></slot>
      </v-card-actions>
    </template>
    
  </v-card>
</template>

<script setup>
const props = defineProps({
  contentClass: { type: String, default: 'pt-4' }
});
defineEmits(['close']);
</script>

<!-- 💡 NEW SCOPED OVERRIDE: Targets the internal title element box 
     and forcefully strips out the global pt-4 default space inside popups! -->
<style scoped>
:deep(.dialog-header-strip .v-card-title) {
  padding-top: 0px !important;
  margin-top: 0px !important;
  line-height: 1.25rem !important;
}
</style>
