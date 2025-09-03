<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/plugins/authStore.js';
import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
import { storeToRefs } from 'pinia';
const preset_conso = ref('');
const consortium = ref('');
const email = ref('');
// Pinia Datastores
const {
  authErrorMessage: errorMessage,
  authSuccessMessage: successMessage,
} = storeToRefs(useAuthStore());
const { forgotPass } = useAuthStore();
const { consortia } = storeToRefs(useCCPlusStore());
// Functions
async function formSubmit() {
  await forgotPass({
    email: email.value,
    consortium: consortium.value,
  });
};
onMounted(() => {
  console.log('ForgotPassword Component Mounted');
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
                  item-title="name" item-value="ccp_key" autofocus density="compact"
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
    <v-row class="d-flex mt-4 align-center" no-gutters>
      <v-col class="d-flex justify-center">
        <v-btn small class="btn login-primary" type="submit">Send Reset Password Link</v-btn>
      </v-col>
    </v-row>
  </form>
  <div v-if="errorMessage" class="login-notices" no-gutters>
    <span class="d-flex mx-1 fail">{{ errorMessage }}</span>
  </div>
  <div v-else-if="successMessage" class="login-notices" no-gutters>
    <span class="d-flex mx-1 good">{{ successMessage }}</span>
  </div>
</template>
