<script setup>
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/plugins/authStore.js';
import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
import { storeToRefs } from 'pinia';
import { ref, onMounted } from 'vue';
const router = useRouter();
const route = useRoute();
const pw_show = ref(false);
const preset_conso = ref('');
const consortium = ref();
const email = ref('');
const password = ref('');
// Pinia Datastores
const {
  authErrorMessage: errorMessage,
  authSuccessMessage: successMessage,
} = storeToRefs(useAuthStore());
const { login, clearLoginError, setConso } = useAuthStore();
const { consortia } = storeToRefs(useCCPlusStore());
const { getConsortia } = useCCPlusStore();
// Functions
const fetchConsortia = async () => {
  try {
    const result = await getConsortia();
    if (consortia.value.length == 1) {
      preset_conso.value = consortia.value[0].name;
      consortium.value = consortia.value[0];
      setConso(consortium.value['id'],consortium.value['ccp_key']);
    }
  } catch {}
}
async function formSubmit() {
  let consoKey = (typeof(consortium.value)!='undefined') ? consortium.value['ccp_key'] : '';
  let consoId = (typeof(consortium.value)!='undefined') ? consortium.value['id'] : 0;
  await login({
    email: email.value,
    consortium: consoKey,
    password: password.value,
    conso_id: consoId,
  });
}
function triggerApiCall(url) {
  router.push(url);
}
onMounted(() => {
  // Get consortia if store value is empty
  if (consortia.value.length == 0) fetchConsortia();

  // Clear any existing error(s)
  clearLoginError();

  // If no preset defined and the URI has a key, preset it in the form
  if (preset_conso.value == '') {
    Object.keys(route.query).forEach( (val) =>  {
      if (consortia.value.map(c => c.ccp_key).includes(val)) {
        preset_conso.value = c.name;
        consortium.value = val;
      }
    });
  }
  console.log('Login Component Mounted');
});

</script>

<template>
  <v-card class="login-card" elevation="4" max-width="420">
    <div class="text-center mb-4">
      <img src="/images/CC_Plus_Logo.png" alt="CC Plus" height="50" width="103" class="login-logo my-4" />
    </div>
    <div class="section-divider mb-6"></div>
    <form method="POST" action="" @submit.prevent="formSubmit" class="login-form">
      <div class="mb-4">
        <template v-if="preset_conso.length > 0">
          <h5 class="text-center">Logging into {{ preset_conso }}</h5>
        </template>
        <template v-else>
        <v-select v-model='consortium' :items='consortia' item-title="name" return-object label="Consortium" 
                  variant="outlined" density="comfortable" hide-details="auto" />
        </template>
      </div>
      <!-- Email -->
      <v-text-field id="email" v-model="email" type="text" name="email" label="Email" placeholder="Email address"
                    variant="outlined" density="comfortable" clearable hide-details="auto" class="mb-4" required />
      <!-- Password -->
      <v-text-field id="password" v-model="password" :type="pw_show ? 'text' : 'password'" name="password" label="Password"
                    placeholder="Password" variant="outlined" density="comfortable" clearable hide-details="auto"
                    :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append-inner="pw_show = !pw_show"
                    class="mb-6" required />
      <!-- Buttons -->
      <div class="d-flex justify-space-between">
        <v-btn size="small" class="login-primary" type="submit" color="primary" variant="elevated">
          Login
        </v-btn>
        <v-btn size="small" variant="text" @click="triggerApiCall('/forgotPassForm')">
          Forgot Your Password
        </v-btn>
      </div>
    </form>
    <div class="section-divider mt-6"></div>
    <div v-if="errorMessage" class="login-notices mt-4">
      <span class="redNotice">{{ errorMessage }}</span>
    </div>
    <div v-else-if="successMessage" class="login-notices mt-4">
      <span class="greenNotice">{{ successMessage }}</span>
    </div>
  </v-card>
</template>
<style scoped>
.login-card {
  padding: var(--space-lg);
  border-radius: var(--radius-md);
}
.login-logo {
  display: block;
  margin: 0 auto;
}
.section-divider {
  border-bottom: 1px solid #d3d3d3;
  width: 100%;
}
.login-notices {
  text-align: center;
}
.redNotice {
  color: var(--red);
}
.greenNotice {
  color: var(--green);
}
</style>
