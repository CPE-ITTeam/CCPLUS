<!-- components/ui/AuthShell.vue -->
<script setup>
import AppLogo from '@/components/ui/AppLogo.vue';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

defineProps({
  errorMessage: { type: String, default: '' },
  successMessage: { type: String, default: '' },
});

defineEmits(['update:errorMessage', 'update:successMessage']);
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

      <StatusAlerts
        mode="page"
        container-class="mb-0"
        :success="successMessage"
        :failure="errorMessage"
        @update:success="$emit('update:successMessage', '')"
        @update:failure="$emit('update:errorMessage', '')"
      />
    </div>
  </v-card>
</template>
