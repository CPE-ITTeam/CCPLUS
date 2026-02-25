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
`  <div class="login-container">
    <v-card class="login-card" elevation="4" max-width="420">
      <!-- Logo -->
      <div class="text-center mb-6">
        <img src="/images/CC_Plus_Logo.png" alt="CC Plus" height="50" width="103" class="login-logo" />
      </div>
`
      <!-- Form -->
      <form method="POST" action="" @submit.prevent="formSubmit" class="login-form">

        <!-- Consortium -->
        <div class="mb-4">
          <template v-if="preset_conso.length > 0">
            <h5 class="text-center">Logging into {{ preset_conso }}</h5>
          </template>
          <template v-else>
          <v-select :items='consortia' v-model='consortium' label="Consortium" 
                    item-title="name" return-object autofocus density="compact" />
          </template>
        </div>

        <!-- Email -->
        <v-text-field id="email" type="text" name="email" label="Email" class="mb-4" required
              v-model="email" placeholder="Email address" density="compact" clearable />
        <!-- Password -->
        <v-text-field id="password" :type="pw_show ? 'text' : 'password'" name="password" label="Password"
                      :append-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append="pw_show = !pw_show"
                      v-model="password" placeholder="Password" density="compact" required  class="mb-6"/>
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

      <!-- Notices -->
      <div v-if="errorMessage" class="login-notices mt-4">
        <span class="redNotice">{{ errorMessage }}</span>
      </div>
      <div v-else-if="successMessage" class="login-notices mt-4">
        <span class="greenNotice">{{ successMessage }}</span>
      </div>

      <!-- Footer (added here) -->
      <v-footer class="login-footer mt-6" height="64">
        <div class="footer-links">
          <a href="https://www.countermetrics.org/" target="_blank" rel="noopener">
            <img src="https://www.countermetrics.org/wp-content/themes/counter/images/counter-logo-new.svg"
                 alt="countermetrics.org" title="countermetrics.org" />
          </a>
          <a href="https://registry.countermetrics.org/" target="_blank" rel="noopener">
            <img src="https://registry.countermetrics.org/favicon.ico" alt="COUNTER registry" title="COUNTER registry" />
          </a>
          <a href="https://github.com/CPE-ITTeam/CCPLUS" target="_blank" rel="noopener">
            <img src="https://github.githubassets.com/favicons/favicon.svg" alt="Github CC-PLUS" title="Github CC-PLUS" />
          </a>
        </div>
      </v-footer>
    </v-card>
  </div>
</template>
<style scoped>
.login-container {
  width: 100%;
  display: flex;
  justify-content: center;
  padding-top: var(--space-xl);
}
.login-card {
  padding: var(--space-lg);
  border-radius: var(--radius-md);
}
.login-logo {
  display: block;
  margin: 0 auto;
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
/* Footer styles */
.login-footer {
  background-color: #f8f9fa;
  border-top: 1px solid #e0e0e0;
  display: flex;
  justify-content: center;
  padding: 1rem 0;
}
.footer-links {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}
.footer-links img {
  height: 28px;
  width: auto;
  display: block;
}
</style>
