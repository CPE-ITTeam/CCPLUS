<!-- components/ui/AuthShell.vue -->
<script setup>
import AppLogo from "@/components/ui/AppLogo.vue";

defineProps({
  errorMessage: { type: String, default: '' },
  successMessage: { type: String, default: '' }
});
</script>

<!-- components/ui/AuthShell.vue -->
<template>
  <!-- 💡 FIXED FOR MOBILITY: Swapped static width for fluid width + max-width limits!
       The 'w-100' lets it stretch, 'max-width' caps it on desktop, and 'px-4 px-sm-6' keeps margins clean on tiny phones. -->
  <v-card class="mx-auto px-4 px-sm-6 py-6 w-100" max-width="380">
    
    <div class="d-flex justify-center mb-5">
      <AppLogo />
    </div>

    <v-divider class="mb-6"></v-divider>

    <div class="auth-form-content">
      <slot></slot>
    </div>

    <div v-if="errorMessage || successMessage" class="mt-4">
      <v-divider class="mb-4"></v-divider>
      
      <v-alert v-if="errorMessage" type="error">
        {{ errorMessage }}
      </v-alert>

      <v-alert v-else-if="successMessage" type="success">
        {{ successMessage }}
      </v-alert>
    </div>
  </v-card>
</template>


<style scoped>
:deep(input:-webkit-autofill),
:deep(input:-webkit-autofill:hover), 
:deep(input:-webkit-autofill:focus),
:deep(input:-webkit-autofill:active) {
  -webkit-box-shadow: 0 0 0 1000px white inset !important;
  -webkit-text-fill-color: rgba(0, 0, 0, 0.87) !important;
  transition: background-color 5000s ease-in-out 0s;
}
</style>
