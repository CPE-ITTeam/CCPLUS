<!-- components/ui/AuthShell.vue -->
<script setup>
import AppLogo from '@/components/ui/AppLogo.vue';

defineProps({
  errorMessage: { type: String, default: '' },
  successMessage: { type: String, default: '' },
});
</script>

<template>
  <v-card class="mx-auto px-4 px-sm-6 py-6 w-100" max-width="380">
    <div class="d-flex justify-center mb-5">
      <AppLogo />
    </div>

    <v-divider class="mb-6" />

    <div class="auth-form-content">
      <slot />
    </div>

    <div v-if="errorMessage || successMessage" class="mt-4">
      <v-divider class="mb-4" />

      <v-alert v-if="errorMessage" type="error">
        {{ errorMessage }}
      </v-alert>

      <v-alert v-else-if="successMessage" type="success">
        {{ successMessage }}
      </v-alert>
    </div>
  </v-card>
</template>
