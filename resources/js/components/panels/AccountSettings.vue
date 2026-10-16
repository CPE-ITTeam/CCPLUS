  <!-- Account Settings Panel -->
<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useAuthStore } from '@/plugins/authStore.js';
  import { useValidationRules } from '@/composables/useValidationRules.js';
  import { fyMonths } from '@/plugins/CCPlusStore.js';
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const { ccGet, ccPost, ccPatch, ccDestroy, user } = useAuthStore();
  const authStore = useAuthStore();
  const is_serveradmin = authStore.is_serveradmin;
  const is_conso_admin = authStore.is_conso_admin;
  const admin_insts = authStore.admin_insts;
  const { required } = useValidationRules()
  const a_success = ref('');
  const a_failure = ref('');
  const i_success = ref('');
  const i_failure = ref('');
  const acctSettings = ref([]);
  const instSettings = ref([]);
  const acctFormRef = ref();
  const instFormRef = ref();
  const is_home_admin = ref(false);
  var home_status = '';
  var home_label = ref('');
  var pw_show = ref(false);
  var pwc_show = ref(false);
  var pat_show = ref(false);
  const requiredRule = (v) => !!v || 'This field is required';

  // Get account settings for current user
  const getSettings = async () => {
    try {
      const { data } = await ccGet("/api/users/settings/"+user.id);
      acctSettings.value = {...data.acct};
      is_home_admin.value = (admin_insts.includes(user.inst_id));
      if (is_home_admin.value) {
        instSettings.value = {...data.inst};
        // institutions update() wants status as a string, not a boolean
        home_status = (data.inst.is_active == 1) ? 'Active' : 'Inactive';
        home_label.value = (is_conso_admin) ? 'Consortium Home' : 'Home';
      }
    } catch (error) {
      console.log('Error fetching settings: '+error.message);
    }
  }
  async function acctSubmit() {
    if (acctFormRef.value?.validate()) {
      a_success.value = '';
      a_failure.value = '';
      try {
        let url = '/api/users/update/'+user.id;
        const response = await ccPatch(url, acctSettings);
        if (response.result) {
          a_success.value = response.msg;
        } else {
          a_failure.value = response.msg;
        }
      } catch (error) {
        console.error('Error saving:', error);
      }
    }
  }
  async function instSubmit() {
    if (instFormRef.value?.validate()) {
      i_success.value = '';
      i_failure.value = '';
      try {
        let url = '/api/institutions/update/'+user.inst_id;
        const response = await ccPatch(url,
          { id: user.inst_id, name: instSettings.value.name, status: home_status,  fte: instSettings.value.fte,
            notes: instSettings.value.notes }
        );
        if (response.result) {
          i_success.value = response.msg;
        } else {
          i_failure.value = response.msg;
        }
      } catch (error) {
        console.error('Error updating:', error);
      }
    }
  }
  // Create personal access token for the current user
  const makeToken = async () => {
    try {
      const response = await ccPost("/api/tokens/store");
      if (response.result) {
        acctSettings.value.access_token = response.access_token;
        acctSettings.value.token_expires = response.token_expires;
      } else {
        failure.value = response.msg
      }
      // formKey.value++;
    } catch (error) {
      console.log('Error creating token: '+error.message);
    }
  }
  // Delete personal access tokens (Pat_USer_##) for the current user
  const destroyToken = async () => {
    try {
      const response = await ccDestroy("/api/tokens/destroy");
      if (response.result) {
        acctSettings.value.access_token = null;
        acctSettings.value.token_expires = null;
      } else {
        a_failure.value = response.msg
      }
      // formKey.value++;
    } catch (error) {
      console.log('Error destroying token: '+error.message);
    }
  }

  // Watch for changes in acct-settings to clear messages
  watch(acctSettings, () =>
    { a_success.value = '';
      a_failure.value = '';
    }, { deep: true }
  );
  // Watch for changes in inst-settings to clear messages
  watch(instSettings, () =>
    { i_success.value = '';
      i_failure.value = '';
    }, { deep: true }
  );
  onMounted(() => {
    getSettings();
  });
</script>
<template>
  <v-container v-if="authStore.user" class="account-container">
    <div v-if="is_home_admin">
      <h2 class="centered-text">
        <span v-if="is_conso_admin">Consortium Master-Institution Settings</span>
        <span v-else="is_conso_admin">Home Institution Settings</span>
      </h2>
      <p>&nbsp;</p>
      <v-form @submit.prevent="instSubmit" ref="instFormRef">
        <v-row class="align-start" no-gutters>
          <v-col cols="6" class="d-flex px-2" no-gutters>
            <v-col cols="8" class="d-flex px-2">
              <v-text-field v-model="instSettings.name" :label="home_label" variant="outlined" density="compact"/>
            </v-col>
            <v-col cols="4" class="d-flex px-2">
              <v-text-field v-model="instSettings.fte" label="FTE" variant="outlined" density="compact"/>
            </v-col>
          </v-col>
          <v-col cols="6" class="d-flex px-2">
            <v-textarea v-model="instSettings.notes" label="Notes" variant="outlined" rows="2"/>
          </v-col>
        </v-row>
        <v-row class="d-flex justify-center mt-4" no-gutters>
          <v-btn size="small" color="primary" type="submit">Update Institution</v-btn>
          <div class="status-message" v-if="i_success || i_failure">
            <span v-if="i_success" class="good" role="alert" v-text="i_success"></span>
            <span v-if="i_failure" class="fail" role="alert" v-text="i_failure"></span>
          </div>
        </v-row>
      </v-form>
    </div>
    <p>&nbsp;</p>
    <hr>
    <h2 class="centered-text">{{ authStore.user.name }} Account Settings</h2>
    <p>&nbsp;</p>
    <v-form @submit.prevent="acctSubmit" ref="acctFormRef">
      <v-row no-gutters>
        <v-col cols="4" class="d-flex px-2 align-middle">
          <v-text-field v-model="acctSettings.name" label="Name" variant="outlined" density="compact"></v-text-field>
        </v-col>
        <v-col cols="4" class="d-flex px-2 align-middle">
          <v-text-field v-model="acctSettings.email" label="Email" variant="outlined" density="compact"
                        required
          ></v-text-field>
        </v-col>
        <v-col cols="2" class="d-flex px-2">
          <v-select label="Fiscal Year Start" :items="fyMonths" v-model="acctSettings.fiscalYr" variant="outlined"
                    :rules="[required]" item-title="label" item-value="value" density="compact"/>
        </v-col>
      </v-row>
      <div v-if="is_serveradmin">
        <blockquote>
            <strong>ServerAdmin credentials are managed using the Artisan command-line interface.</strong><br>
            For example: <em> # php artisan ccplus:resetadminpw</em><br>
            Refer to the CCPlus <a href="https://github.com/CPE-ITTeam/CCPLUS/blob/main/docs/installation.markdown" target="_blank">
            installation documentation</a> for more information.
        </blockquote>
      </div>
      <div v-else>
        <v-row no-gutters>
          <v-col cols="4" class="d-flex px-2">
            <v-text-field v-model="acctSettings.password" label="Password" :type="pw_show ? 'text' : 'password'"
                          :append-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append="pw_show = !pw_show"
                          variant="outlined" density="compact"/>
          </v-col>
          <v-col cols="4" class="d-flex px-2">
            <v-text-field v-model="acctSettings.confirm_pass" label="Confirm Password" :type="pwc_show ? 'text' : 'password'"
                          :append-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append="pwc_show=!pwc_show" 
                          variant="outlined" :rules="(acctSettings.password!=null) ? [requiredRule] : []" density="compact"/>
          </v-col>
        </v-row>   
        <v-row v-if="acctSettings.access_token ==             null" no-gutters>
          <v-col cols="4" class="d-flex px-2">
            <v-btn color="primary" @click="makeToken">Create Access Token</v-btn>
          </v-col>
        </v-row>
        <v-row v-else no-gutters>
          <v-col cols="4" class="d-flex px-2">
            <v-text-field v-model="acctSettings.access_token" label="Access Token" readonly
                          :type="pat_show ? 'text' : 'password'" :append-icon="pat_show ? 'mdi-eye' : 'mdi-eye-off'"
                          @click:append="pat_show = !pat_show" variant="outlined" density="compact"/>
          </v-col>
          <v-col cols="4" class="d-flex px-2">
            Expires {{  acctSettings.token_expires ? new Date(acctSettings.token_expires).toLocaleString() : 'N/A' }}
          </v-col>
          <v-col cols="4" class="d-flex px-2">
            <v-btn color="primary" @click="destroyToken">Destroy</v-btn>
          </v-col>
        </v-row>
        <v-row class="d-flex my-2" no-gutters>
          <v-col cols="4" class="d-flex px-2">
            <span v-if="!is_home_admin"><h3>Home Institution: {{ acctSettings.inst_name }}</h3></span>
            <span v-else>&nbsp;</span>
          </v-col>
          <v-col cols="8" class="d-flex px-2">
            <h3>User Role(s)</h3>
            <div>
              <ul class="roles-list">  
                <li class="verydense" v-for="(role,idx) in acctSettings.roles" :key="idx">
                  <span class="d-flex pl-4" v-if="role.inst_id==1">{{ role.role.name }} : Consortium-Wide</span>
                  <span class="d-flex pl-4" v-if="role.inst_id>1">{{ role.role.name }} : {{ role.institution.name }}</span>
                  <span class="d-flex pl-4" v-if="role.inst_id==null && role.group_id>0">
                    {{ role.role.name }} :  {{ role.institutiongroup.name }}
                  </span>
                </li>
              </ul>
            </div>
          </v-col>
        </v-row>
      </div>
      <v-row class="d-flex justify-center mt-4" no-gutters>
        <v-btn size="small" color="primary" type="submit">Save User Settings</v-btn>
        <div class="status-message" v-if="a_success || a_failure">
          <span v-if="a_success" class="good" role="alert" v-text="a_success"></span>
          <span v-if="a_failure" class="fail" role="alert" v-text="a_failure"></span>
        </div>
      </v-row>
    </v-form>
    <p>&nbsp;</p>
    <hr>
    <h2 class="centered-text">{{ acctSettings.email }} : Saved Reports</h2>
    <DatasetViewer datasetKey="savedreports" />
  </v-container>
</template>
<style scoped>
 .redNotice { color: #ee0000; }
 .greenNotice { color: #339933; }
 .account-container {
   width: 80% !important;
   margin-left: auto !important;
   margin-right: auto !important;
 }
 .roles-list {
   padding-left: 30px;
 }
</style>
