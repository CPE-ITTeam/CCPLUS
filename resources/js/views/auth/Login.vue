<!-- views/auth/Login.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import AuthShell from '@/components/ui/AuthShell.vue';

const route = useRoute();
const pw_show = ref(false);
const preset_conso = ref('');
const consortium = ref(null);
const email = ref('');
const password = ref('');

const authStore = useAuthStore();
const { authErrorMessage: errorMessage, authSuccessMessage: successMessage } = storeToRefs(authStore);
const { login, clearLoginError, setConso } = authStore;

const ccplusStore = useCCPlusStore();
const { consortia } = storeToRefs(ccplusStore);
const { getConsortia } = ccplusStore;

function selectConsortium(conso) {
  preset_conso.value = conso.name;
  consortium.value = conso;
  setConso(conso.id, conso.ccp_key);
}

onMounted(async () => {
  clearLoginError();
  await getConsortia();

  if (consortia.value.length === 1) {
    selectConsortium(consortia.value[0]);
  } else {
    const queryKeys = Object.keys(route.query);
    const matched = consortia.value.find((c) => queryKeys.includes(c.ccp_key));
    if (matched) selectConsortium(matched);
  }
});
</script>

<template>
  <AuthShell :error-message="errorMessage" :success-message="successMessage">
    <v-form
      class="w-100"
      @submit.prevent="login({
        email,
        consortium: consortium?.ccp_key || '',
        password,
        conso_id: consortium?.id || 0,
      })"
    >
      <!-- Consortium Selection Block -->
      <div class="mb-4">
        <h5 v-if="preset_conso" class="text-center text-wrap">
          {{ $t('auth.logging_into', { consortium: preset_conso }) }}
        </h5>
        <v-select
          v-else
          v-model="consortium"
          :items="consortia"
          item-title="name"
          return-object
          :label="$t('datasets.consortia.title')"
        />
      </div>

      <!-- Email Field Input -->
      <v-text-field
        v-model="email"
        type="email"
        name="email"
        autocomplete="username"
        :label="$t('auth.email')"
        class="mb-4"
        required
      />

      <!-- Password Field Input -->
      <v-text-field
        v-model="password"
        :type="pw_show ? 'text' : 'password'"
        name="password"
        autocomplete="current-password"
        :label="$t('fields.password')"
        :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
        class="mb-6"
        required
        @click:append-inner="pw_show = !pw_show"
      />

      <!-- Action Panel Buttons -->
      <div class="d-flex flex-wrap align-center justify-space-between ga-2 w-100">
        <v-btn type="submit" class="flex-grow-1 flex-sm-grow-0">
          {{ $t('auth.login') }}
        </v-btn>

        <v-btn
          to="/forgotPassForm"
          color="info"
          class="flex-grow-1 flex-sm-grow-0 text-none text-wrap text-center mw-100"
        >
          {{ $t('auth.forgot_password') }}
        </v-btn>
      </div>
    </v-form>
  </AuthShell>
</template>
