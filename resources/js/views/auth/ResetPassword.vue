<!-- views/auth/ResetPassword.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import AuthShell from '@/components/ui/AuthShell.vue';

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
    token: route.query.token,
  });
}

onMounted(() => {
  clearLoginError();
  clearSuccessMessage();
});
</script>

<template>
  <AuthShell :error-message="errorMessage" :success-message="successMessage">
    <v-form class="w-100" @submit.prevent="formSubmit">
      <v-text-field
        v-model="email"
        type="email"
        name="email"
        autocomplete="email"
        :label="$t('auth.email')"
        clearable
        class="mb-4"
        required
      />

      <v-text-field
        v-model="pass"
        :type="pw_show ? 'text' : 'password'"
        name="password"
        autocomplete="new-password"
        :label="$t('fields.password')"
        :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
        class="mb-4"
        required
        @click:append-inner="pw_show = !pw_show"
      />

      <v-text-field
        v-model="pass_confirm"
        :type="pwc_show ? 'text' : 'password'"
        name="password_confirmation"
        autocomplete="new-password"
        :label="$t('fields.confirm_password')"
        :append-inner-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'"
        class="mb-6"
        required
        @click:append-inner="pwc_show = !pwc_show"
      />

      <div class="d-flex justify-center align-center">
        <v-btn type="submit">
          {{ $t('auth.reset_password') }}
        </v-btn>
      </div>
    </v-form>
  </AuthShell>
</template>
