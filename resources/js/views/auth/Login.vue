<!-- views/auth/Login.vue -->
<script setup>
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import { storeToRefs } from 'pinia';
import { ref, onMounted } from 'vue';
import AuthShell from "@/components/ui/AuthShell.vue";

const route = useRoute();
const pw_show = ref(false);
const preset_conso = ref('');
const consortium = ref();
const email = ref('');
const password = ref('');

const authStore = useAuthStore();
const { authErrorMessage: errorMessage, authSuccessMessage: successMessage } = storeToRefs(authStore);
const { login, clearLoginError, setConso } = authStore;

const ccplusStore = useCCPlusStore();
const { consortia } = storeToRefs(ccplusStore);
const { getConsortia } = ccplusStore;

const fetchConsortia = async () => {
  try {
    await getConsortia();
    // 💡 FIXED: Accurately maps array tracking pointers so it does not evaluate undefined bounds!
    if (consortia.value.length === 1) {
      preset_conso.value = consortia.value[0].name;
      consortium.value = consortia.value[0];
      setConso(consortium.value['id'], consortium.value['ccp_key']);
    } else {
      // Clear preset strings if multi-conso databases load into view layers
      preset_conso.value = '';
    }
  } catch { }
};

onMounted(async () => {
  // 💡 FIXED: Bypasses sessionStorage locks to always fetch fresh active datasets on mount!
  await fetchConsortia();
  clearLoginError();

  if (preset_conso.value === '') {
    Object.keys(route.query).forEach((val) => {
      const matchedConso = consortia.value.find(c => c.ccp_key === val);
      if (matchedConso) {
        preset_conso.value = matchedConso.name;
        consortium.value = matchedConso;
      }
    });
  }
});
</script>

<template>
  <AuthShell :error-message="errorMessage" :success-message="successMessage">
    <v-form @submit.prevent="login({
      email: email,
      consortium: consortium?.ccp_key || '',
      password: password,
      conso_id: consortium?.id || 0
    })" class="w-100">
      
      <!-- Consortium Selection Block -->
      <div class="mb-4">
        <h5 v-if="preset_conso.length > 0" class="text-center text-wrap">
          Logging into {{ preset_conso }}
        </h5>
        <v-select 
          v-else 
          v-model="consortium" 
          :items="consortia" 
          item-title="name" 
          return-object 
          label="Consortium"
        />
      </div>

      <!-- Email Field Input -->
      <v-text-field 
        id="email" 
        v-model="email" 
        type="email" 
        name="email" 
        label="Email" 
        placeholder="Email address"
        class="mb-4" 
        required 
      />

      <!-- Password Field Input -->
      <v-text-field 
        id="password" 
        v-model="password" 
        :type="pw_show ? 'text' : 'password'" 
        name="password"
        label="Password" 
        placeholder="Password" 
        :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" 
        @click:append-inner="pw_show = !pw_show" 
        class="mb-6"
        required 
      />

      <!-- Action Panel Buttons -->
      <div class="d-flex flex-wrap align-center justify-space-between ga-2 w-100">
        <v-btn type="submit" class="flex-grow-1 flex-sm-grow-0">
          Login
        </v-btn>
        
        <v-btn to="/forgotPassForm" color="info" class="flex-grow-1 flex-sm-grow-0 text-none text-wrap text-center" style="font-size: 0.85rem; max-width: 100%;">
          Forgot Your Password
        </v-btn>
      </div>

    </v-form>
  </AuthShell>
</template>
