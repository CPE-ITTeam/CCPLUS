<!-- views/admin/AccountSettings.vue -->
<script setup>
import { ref, watch, onMounted } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useValidationRules } from '@/composables/useValidationRules.js';
import { fyMonths } from '@/stores/useCCPlusStore.js';
import DatasetViewer from '@/components/tables/DatasetViewer.vue';

// Initialize data store proxies cleanly to prevent desynchronization drops
const authStore = useAuthStore();
const { ccGet, ccPost, ccPatch, ccDestroy } = authStore;

const is_serveradmin = authStore.is_serveradmin;
const { required } = useValidationRules();

const success = ref('');
const failure = ref('');
const acctSettings = ref({
  name: '',
  email: '',
  fiscalYr: null,
  password: null,
  confirm_pass: null,
  access_token: null,
  token_expires: null,
  inst_name: '',
  roles: []
});

const formRef = ref();
const pw_show = ref(false);
const pwc_show = ref(false);
const pat_show = ref(false);
const requiredRule = (v) => !!v || 'This field is required';

// Get account settings for current user
const getSettings = async () => {
  if (!authStore.user?.id) return;
  try {
    const response = await ccGet("/api/users/settings/" + authStore.user.id);
    if (response && response.records) {
      acctSettings.value = { ...response.records };
    }
  } catch (error) {
    console.error('Error fetching settings: ' + error.message);
  }
};

async function userSubmit() {
  if (formRef.value) {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    success.value = '';
    failure.value = '';
    try {
      // 💡 FIXED: Stripped out the undefined local 'urlRoot' and resolved straight to the API route!
      const url = '/api/users/update/' + authStore.user.id;
      const response = await ccPatch(url, acctSettings.value);
      if (response && response.result) {
        success.value = response.msg || "Account configuration saved successfully.";
      } else {
        failure.value = response?.msg || "Failed to update profile settings.";
      }
    } catch (error) {
      console.error('Error updating profile configuration:', error);
      failure.value = "An unhandled server execution exception occurred on save.";
    }
  }
}

// Create personal access token for the current user
const makeToken = async () => {
  try {
    const response = await ccPost("/api/tokens/store");
    if (response && response.result) {
      acctSettings.value.access_token = response.access_token;
      acctSettings.value.token_expires = response.token_expires;
    } else {
      failure.value = response?.msg || "Failed to create security key token.";
    }
  } catch (error) {
    console.error('Error creating token: ' + error.message);
  }
};

// Delete personal access tokens for the current user
const destroyToken = async () => {
  try {
    const response = await ccDestroy("/api/tokens/destroy");
    if (response && response.result) {
      acctSettings.value.access_token = null;
      acctSettings.value.token_expires = null;
    } else {
      failure.value = response?.msg || "Failed to terminate credentials.";
    }
  } catch (error) {
    console.error('Error destroying token: ' + error.message);
  }
};

// Watch for changes in settings to clear status messages
watch(acctSettings, () => { 
  success.value = '';
  failure.value = '';
}, { deep: true });

onMounted(() => {
  getSettings();
});
</script>

<template>
  <v-container v-if="authStore.user" class="account-container pa-0">
    <h2 class="centered-text mb-4">{{ authStore.user.name }} Account Settings</h2>
    
    <v-form ref="formRef" @submit.prevent="userSubmit">
      
      <!-- Status Feedback Row -->
      <v-row v-if="success || failure" class="mb-4" no-gutters>
        <v-col cols="12">
          <v-alert v-if="success" type="success" variant="tonal" density="compact" :text="success" />
          <v-alert v-else-if="failure" type="error" variant="tonal" density="compact" :text="failure" />
        </v-col>
      </v-row>   

      <v-row no-gutters class="ga-y-2">
        <v-col cols="12" sm="4" class="d-flex px-2">
          <v-text-field v-model="acctSettings.name" label="Name" density="compact" variant="outlined" />
        </v-col>
        <v-col cols="12" sm="4" class="d-flex px-2">
          <v-text-field v-model="acctSettings.email" label="Email" density="compact" variant="outlined" required />
        </v-col>
        <v-col cols="12" sm="4" class="d-flex px-2">
          <v-select label="Fiscal Year Start" :items="fyMonths" v-model="acctSettings.fiscalYr" 
                    :rules="[required]" item-title="label" item-value="value" density="compact" variant="outlined" />
        </v-col>
      </v-row>

      <div v-if="is_serveradmin" class="mt-4">
        <v-alert type="info" variant="tonal" class="rounded-lg">
          <strong>ServerAdmin credentials are managed using the Artisan command-line interface.</strong><br>
          For example: <em># php artisan ccplus:resetadminpw</em><br>
          Refer to the CCPlus installation documentation for more information.
        </v-alert>
      </div>

      <div v-else class="mt-2">
        <v-row no-gutters>
          <v-col cols="12" sm="6" class="d-flex px-2">
            <v-text-field v-model="acctSettings.password" label="Password" :type="pw_show ? 'text' : 'password'"
                          :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append-inner="pw_show = !pw_show"
                          density="compact" variant="outlined" />
          </v-col>
          <v-col cols="12" sm="6" class="d-flex px-2">
            <v-text-field v-model="acctSettings.confirm_pass" label="Confirm Password" :type="pwc_show ? 'text' : 'password'"
                          :append-inner-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append-inner="pwc_show = !pwc_show" 
                          :rules="(acctSettings.password != null && acctSettings.password !== '') ? [requiredRule] : []" 
                          density="compact" variant="outlined" />
          </v-col>
        </v-row>   

        <v-row v-if="acctSettings.access_token == null" no-gutters class="mt-2">
          <v-col cols="12" class="px-2">
            <v-btn color="secondary" @click="makeToken">Create Access Token</v-btn>
          </v-col>
        </v-row>
        
        <v-row v-else no-gutters class="mt-2 align-center ga-y-2">
          <v-col cols="12" sm="5" class="d-flex px-2">
            <v-text-field v-model="acctSettings.access_token" label="Access Token" readonly
                          :type="pat_show ? 'text' : 'password'" :append-inner-icon="pat_show ? 'mdi-eye' : 'mdi-eye-off'"
                          @click:append-inner="pat_show = !pat_show" density="compact" variant="outlined" hide-details />
          </v-col>
          <v-col cols="12" sm="4" class="d-flex px-2 text-body-2 text-medium-emphasis">
            Expires: {{ acctSettings.token_expires ? new Date(acctSettings.token_expires).toLocaleString() : 'N/A' }}
          </v-col>
          <v-col cols="12" sm="3" class="d-flex px-2 justify-sm-end">
            <v-btn color="error" variant="tonal" @click="destroyToken">Destroy Token</v-btn>
          </v-col>
        </v-row>

        <v-row no-gutters class="mt-4">
          <v-col class="px-2 text-subtitle-1 font-weight-medium">
            Home Institution: <span class="text-primary">{{ acctSettings.inst_name || 'N/A' }}</span>
          </v-col>
        </v-row>

        <v-row no-gutters class="mt-2">
          <v-col cols="12" class="px-2">
            <div class="text-subtitle-1 font-weight-medium mb-1">User Role(s):</div>
            <v-card variant="flat" border class="pa-3 bg-grey-lighten-5">
              <ul class="roles-list ma-0 pa-0" style="list-style-type: none;">  
                <li v-for="(role, idx) in acctSettings.roles" :key="idx" class="py-1 text-body-2 d-flex align-center">
                  <v-icon start size="16" color="success">mdi-shield-check-outline</v-icon>
                  <span v-if="role.inst_id == 1"><strong>{{ role.role?.name }}</strong> : Consortium-Wide</span>
                  <span v-else-if="role.inst_id > 1"><strong>{{ role.role?.name }}</strong> : {{ role.institution?.name }}</span>
                  <span v-else-if="role.inst_id == null && role.group_id > 0"><strong>{{ role.role?.name }}</strong> : {{ role.institutiongroup?.name }}</span>
                </li>
              </ul>
            </v-card>
          </v-col>
        </v-row>
      </div>

      <!-- Form Submission Panel Row -->
      <v-row class="d-flex align-center mt-6 px-2" no-gutters>
        <v-btn color="primary" type="submit" class="px-6">Save Settings</v-btn>
      </v-row>
    </v-form>

    <v-divider class="my-6" />

    <!-- Secondary Reports Context Panel Group -->
    <h3 class="mb-4">{{ acctSettings.email || 'User' }} — Saved Reports</h3>
    <DatasetViewer datasetKey="savedreports" />
  </v-container>
</template>
