<!-- views/auth/ForgotPassword.vue -->
<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import { storeToRefs } from 'pinia';
import AuthShell from "@/components/ui/AuthShell.vue";

const preset_conso = ref('');
const consortium = ref('');
const email = ref('');

const authStore = useAuthStore();
const { authErrorMessage: errorMessage, authSuccessMessage: successMessage } = storeToRefs(authStore);
const { forgotPass } = authStore;

const { consortia } = storeToRefs(useCCPlusStore());

async function formSubmit() {
  await forgotPass({
    email: email.value,
    consortium: consortium.value,
  });
}
</script>

<template>
  <AuthShell :error-message="errorMessage" :success-message="successMessage">
    <v-form @submit.prevent="formSubmit">
      
      <div class="mb-4">
        <h5 v-if="preset_conso.length > 0" class="text-center">
          Logging into {{ preset_conso }}
        </h5>
        <v-select 
          v-model="consortium" 
          :items="consortia" 
          item-title="name" 
          item-value="ccp_key" 
          return-object
          label="Consortium" 
           
          density="comfortable" 
           
          autofocus 
        />
      </div>

      <v-text-field 
        id="email" 
        v-model="email" 
        type="email" 
        label="Email" 
        placeholder="Email address"
         
        density="comfortable" 
        clearable 
         
        class="mb-6" 
        required 
      />

      <div class="d-flex justify-center align-center">
        <v-btn type="submit">
          Send Reset Password Link
        </v-btn>
      </div>

    </v-form>
  </AuthShell>
</template>
