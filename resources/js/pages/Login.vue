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
    <form method="POST" action="" @submit.prevent="formSubmit" class="login-form">
      <div class="img-top" no-gutters>
        <img src="/images/CC_Plus_Logo.png" alt="CC plus" height="50px" width="103px" />
      </div>
      <v-row class="d-flex mt-4" no-gutters>
        <v-col v-if="preset_conso.length>0" class="d-flex pa-0 justify-center" cols="12">
          <h5>Logging into {{ preset_conso }}</h5>
        </v-col>
        <v-col v-else class="d-flex pa-0 justify-start" cols="12">
          <v-select :items='consortia' v-model='consortium' label="Consortium" 
                    item-title="name" return-object autofocus density="compact"
          ></v-select>
        </v-col>
      </v-row>

      <v-row class="d-flex mt-4" no-gutters>
        <v-col class="d-flex pa-2 justify-start" cols="12">
          <v-text-field id="email" type="text" name="email" label="Email" required
                        v-model="email" placeholder="Email address" density="compact" clearable
          ></v-text-field>
        </v-col>
      </v-row>

      <v-row class="d-flex mt-4" no-gutters>
        <v-col class="d-flex pa-2 justify-start" cols="12">
          <v-text-field id="password" :type="pw_show ? 'text' : 'password'" name="password" label="Password"
                        :append-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append="pw_show = !pw_show"
                        v-model="password" placeholder="Password" required density="compact"
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex mt-4 align-center" no-gutters>
        <v-col class="d-flex justify-space-between">
          <v-btn size="small" class="btn login-primary" type="submit">Login</v-btn>
        </v-col>
        <v-col class="d-flex justify-space-between">
          <v-btn size="small" class="btn" title="Forgot Password Link" @click="triggerApiCall('/forgotPassForm')">
            Forgot Your Password
          </v-btn>
        </v-col>
      </v-row>
    </form>
    <div v-if="errorMessage" class="login-notices" no-gutters>
      <span class="d-flex mx-1 redNotice">{{ errorMessage }}</span>
    </div>
    <div v-else-if="successMessage" class="login-notices" no-gutters>
    <span class="d-flex mx-1 greenNotice">{{ successMessage }}</span>
  </div>
</template>
<style scoped>
 .redNotice { color: #ee0000; }
 .greenNotice { color: #339933; }
</style>
