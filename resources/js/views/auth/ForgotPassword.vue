<!-- views/auth/ForgotPassword.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import AuthShell from '@/components/ui/AuthShell.vue';

const preset_conso = ref('');
const consortium = ref(null);
const email = ref('');

const authStore = useAuthStore();
const { authErrorMessage: errorMessage, authSuccessMessage: successMessage } = storeToRefs(authStore);
const { forgotPass, clearLoginError } = authStore;

const ccplusStore = useCCPlusStore();
const { consortia } = storeToRefs(ccplusStore);
const { getConsortia } = ccplusStore;

const activeConsortia = computed(() =>
  consortia.value.filter((c) => Boolean(c.is_active) && c.status !== 'Inactive')
);

onMounted(async () => {
  clearLoginError();
  if (!consortia.value.length) {
    await getConsortia();
  }
  if (activeConsortia.value.length === 1) {
    preset_conso.value = activeConsortia.value[0].name;
    consortium.value = activeConsortia.value[0];
  }
});

async function formSubmit() {
  await forgotPass({
    email: email.value,
    consortium: consortium.value,
  });
}
</script>

<template>
  <AuthShell :error-message="errorMessage" :success-message="successMessage">
    <v-form class="w-100" @submit.prevent="formSubmit">
      <div class="mb-4">
        <h5 v-if="preset_conso" class="text-center text-wrap">
          {{ $t('auth.logging_into', { consortium: preset_conso }) }}
        </h5>
        <v-select
          v-else
          v-model="consortium"
          :items="activeConsortia"
          item-title="name"
          item-value="ccp_key"
          return-object
          :label="$t('datasets.consortia.title')"
          autofocus
        />
      </div>

      <v-text-field
        v-model="email"
        type="email"
        name="email"
        autocomplete="email"
        :label="$t('auth.email')"
        class="mb-6"
        required
      />

      <div class="d-flex justify-center align-center">
        <v-btn type="submit">
          {{ $t('auth.send_reset_link') }}
        </v-btn>
      </div>
    </v-form>
  </AuthShell>
</template>
