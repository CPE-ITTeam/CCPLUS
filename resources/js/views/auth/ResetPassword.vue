<!-- views/auth/ResetPassword.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import AuthShell from "@/components/ui/AuthShell.vue";

const route = useRoute();
const email = ref('');
const pw_show = ref(false);
const pwc_show = ref(false);
const pass = ref('');
const pass_confirm = ref('');

const authStore = useAuthStore();
const { authErrorMessage: errorMessage, authSuccessMessage: successMessage } = storeToRefs(authStore);
const { resetPass, clearLoginError, clearSuccessMessage } = authStore;

async function formSubmit() {
  await resetPass({
    email: email.value,
    password: pass.value,
    password_confirmation: pass_confirm.value,
    consortium: route.query.key,
    token: route.query.token
  });
}

onMounted(() => {
  clearLoginError();
  clearSuccessMessage();
});
</script>

<template>
  <AuthShell :error-message="errorMessage" :success-message="successMessage">
    <v-form @submit.prevent="formSubmit">
      
      <v-text-field 
        id="email" 
        v-model="email" 
        type="email" 
        label="Email" 
        placeholder="Email address"
         
        density="comfortable" 
        clearable 
         
        class="mb-4" 
        required 
      />

      <v-text-field 
        id="password" 
        v-model="pass" 
        :type="pw_show ? 'text' : 'password'" 
        label="Password" 
        placeholder="Password" 
         
        density="comfortable" 
        
        :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" 
        @click:append-inner="pw_show = !pw_show" 
        class="mb-4"
        required 
      />

      <v-text-field 
        id="password_confirmation" 
        v-model="pass_confirm" 
        :type="pwc_show ? 'text' : 'password'" 
        label="Confirm Password" 
        placeholder="Confirm Password" 
         
        density="comfortable" 
        
        :append-inner-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'" 
        @click:append-inner="pwc_show = !pwc_show" 
        class="mb-6"
        required 
      />

      <div class="d-flex justify-center align-center">
        <v-btn type="submit">
          Reset Password
        </v-btn>
      </div>

    </v-form>
  </AuthShell>
</template>
