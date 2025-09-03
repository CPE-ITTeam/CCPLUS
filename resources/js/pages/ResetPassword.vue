<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/plugins/authStore.js';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
const route = useRoute();
const email = ref('');
const pw_show = ref(false);
const pwc_show = ref(false);
const pass = ref('');
const pass_confirm = ref('');
// const route_key = route.query.key;
// const route_token = route.query.token;
// Pinia Datastores
const {
  authErrorMessage: errorMessage,
  authSuccessMessage: successMessage,
} = storeToRefs(useAuthStore());
const { resetPass, clearLoginError, clearSuccessMessage } = useAuthStore();
// Functions
async function formSubmit() {
  await resetPass({
    email: email.value,
    password: pass.value,
    password_confirmation: pass_confirm.value,
    consortium: route.query.key,
    token: route.query.token
  });
};
onMounted(() => {
  // Clear any existing errors/messages
  clearLoginError();
  clearSuccessMessage();
  console.log('ResetPassword Component Mounted');
});

</script>

<template>
  <div>
    <form method="POST" action="" @submit.prevent="formSubmit" class="login-form">
    <div class="img-top" no-gutters>
      <img src="/images/CC_Plus_Logo.png" alt="CC plus" height="50px" width="103px" />
    </div>
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
                        v-model="pass" placeholder="Password" required density="compact"
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex mt-4" no-gutters>
        <v-col class="d-flex pa-2 justify-start" cols="12">
          <v-text-field id="password_confirmation" :type="pwc_show ? 'text' : 'password'" name="password_confirmation"
                        label="Confirm Password" v-model="pass_confirm" placeholder="Confirm Password" required density="compact"
                        :append-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append="pwc_show = !pwc_show"
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex mt-4 align-center" no-gutters>
        <v-col class="d-flex justify-center">
          <v-btn small class="btn login-primary" type="submit">Reset Password</v-btn>
        </v-col>
      </v-row>
    </form>
    <div v-if="errorMessage" class="login-notices" no-gutters>
      <span class="d-flex mx-1 fail">{{ errorMessage }}</span>
    </div>
    <div v-else-if="successMessage" class="login-notices" no-gutters>
      <span class="d-flex mx-1 good">{{ successMessage }}</span>
    </div>
  </div>
</template>
