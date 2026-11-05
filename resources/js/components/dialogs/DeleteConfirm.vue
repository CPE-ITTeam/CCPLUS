<!-- components/dialogs/DeleteConfirm.vue -->
<script setup>
  const props = defineProps({
    dataset: { type: String, required: true },
    item: { type: Object, required: true },
  });
  const emit = defineEmits(['close', 'confirm']);
</script>

<template>
  <!-- REMOVED hardcoded width props so it centers horizontally again -->
  <v-card 
    elevation="3" 
    style="--v-shadow-color: var(--v-theme-info);"
  >
    <v-form @submit.prevent="$emit('confirm')">
      
      <!-- Unified header layout -->
      <v-card-item class="bg-primary rounded-t pa-3" title="Confirm Delete">
        <template #append>
          <v-btn 
            icon="mdi-close" 
            variant="text" 
            color="white" 
            density="comfortable" 
            @click="$emit('close')" 
          />
        </template>
      </v-card-item>

      <!-- Dialog Confirmation Content -->
      <v-card-text class="pt-6 pb-2 text-center text-sm-left">
        <div v-if="props.dataset == 'connections'" class="text-subtitle-1 mb-4">
          You are about to delete <strong class="text-error text-uppercase">all connections</strong> to <strong>{{ item.platform }}</strong>
        </div>
        <div v-else class="text-subtitle-1 mb-4">
          You are about to delete <strong>{{ item.name }}</strong> from <strong>{{ props.dataset }}</strong>
        </div>

        <h3 class="text-h6 font-weight-bold mb-4">Are you sure?</h3>
      </v-card-text>

      <!-- Action Buttons Row -->
      <v-card-actions class="px-6 pb-6 justify-start">
        <v-btn 
          type="submit"
          color="error" 
          variant="flat"
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
      </v-card-actions>
      
    </v-form>
  </v-card>
</template>
