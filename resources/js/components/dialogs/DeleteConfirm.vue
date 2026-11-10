<!-- components/dialogs/DeleteConfirm.vue -->
<script setup>
import BaseDialogLayout from './BaseDialogLayout.vue';

const props = defineProps({
  dataset: { type: String, required: true },
  item: { type: Object, required: true },
});

defineEmits(['close', 'confirm']);
</script>

<template>
  <!-- Keep your custom form submit and card-specific style overrides on the layout if needed -->
  <BaseDialogLayout 
    content-class="pt-6 pb-2 text-center text-sm-left"
    elevation="3" 
    style="--v-shadow-color: var(--v-theme-info);"
    @close="$emit('close')"
  >
    <!-- Header Title Slot -->
    <template #title>
      Confirm Delete
    </template>

    <!-- Content Slot (Wrapped in form for native submission) -->
    <v-form @submit.prevent="$emit('confirm')">
      <div v-if="props.dataset === 'connections'" class="text-subtitle-1 mb-4">
        You are about to delete <strong class="text-error text-uppercase">all connections</strong> to <strong>{{ item.platform }}</strong>
      </div>
      <div v-else class="text-subtitle-1 mb-4">
        You are about to delete <strong>{{ item.name }}</strong> from <strong>{{ props.dataset }}</strong>
      </div>

      <h3 class="text-h6 font-weight-bold mb-4">Are you sure?</h3>
      
      <!-- Hidden submit button so hitting 'Enter' inside the form still triggers submit -->
      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <!-- Footer Actions Slot -->
    <template #actions>
      <div class="px-4 pb-4 w-100 d-flex justify-start">
        <v-btn 
          color="error" 
          variant="flat"
          @click="$emit('confirm')"
        >
          Yes, Delete it
        </v-btn>
        <v-btn 
          variant="text" 
          class="ml-2" 
          @click="$emit('close')"
        >
          Cancel
        </v-btn>
      </div>
    </template>
  </BaseDialogLayout>
</template>
