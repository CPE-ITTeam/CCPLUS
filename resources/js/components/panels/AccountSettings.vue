  <!-- Account Settings Panel -->
<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useAuthStore } from '@/plugins/authStore.js';
  import { useValidationRules } from '@/composables/useValidationRules.js';
  import { fyMonths } from '@/plugins/CCPlusStore.js';
  import DatasetViewer from '../shared/DatasetViewer.vue';
  const { ccGet, ccPatch, user } = useAuthStore();
  const authStore = useAuthStore();
  const is_serveradmin = authStore.is_serveradmin;
  const { required, numberRule, booleanRule, yearmon } = useValidationRules()
  const success = ref('');
  const failure = ref('');
  const acctSettings = ref([]);
  const formRef = ref();
  var pw_show = ref(false);
  var pwc_show = ref(false);
  const requiredRule = (v) => !!v || 'This field is required';

  // Get account settings for current user
  const getSettings = async () => {
    try {
      const { data } = await ccGet("/api/users/settings/"+user.id);
      acctSettings.value = {...data.records};
    } catch (error) {
      console.log('Error fetching settings: '+error.message);
    }
  }
  async function userSubmit() {
    if (formRef.value?.validate()) {
      success.value = '';
      failure.value = '';
      try {
        let url = urlRoot.value+'api/users/update/'+user.id;
        const response = await ccPatch(url, acctSettings);
        if (response.result) {
          success.value = response.msg;
        } else {
          failure.value = response.msg;
        }
      } catch (error) {
        console.error('Error updating:', error);
      }
    }
  }
  // Watch for changes in settings to clear messages
  watch(acctSettings, () =>
    { success.value = '';
      failure.value = '';
    }, { deep: true }
  );
  onMounted(() => {
    getSettings();
  });
</script>
<template>
  <v-container class="account-container">
    <h2 class="centered-text">{{ authStore.user.name }} Account Settings</h2>
    <p>&nbsp;</p>
    <v-form @submit.prevent="userSubmit" ref="formRef">
      <v-row v-if="success || failure" class="status-message" no-gutters>
        <span v-if="success"      class="good" v-text="success"></span>
        <span v-else-if="failure" class="fail" v-text="failure"></span>
      </v-row>   
      <v-row no-gutters>
        <v-col cols="4" class="d-flex px-2 align-middle">
          <v-text-field v-model="acctSettings.name" label="Name" outlined></v-text-field>
        </v-col>
        <v-col cols="4" class="d-flex px-2 align-middle">
          <v-text-field outlined required label="Email" v-model="acctSettings.email"></v-text-field>
        </v-col>
        <v-col cols="2" class="d-flex px-2">
          <v-select label="Fiscal Year Start" :items="fyMonths" v-model="acctSettings.fiscalYr" variant="outlined"
                    :rules="[required]" item-title="label" item-value="value" density="compact"/>
        </v-col>
      </v-row>   
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
      <v-row v-if="!is_serveradmin" no-gutters>
        <v-col cols="4" class="d-flex px-2">AccountSettings
          Home Institution: {{ acctSettings.inst_name }}
        </v-col>
        <v-col cols="8" class="d-flex px-2">
          User Role(s)
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
      <v-row class="d-flex justify-center mt-4" no-gutters>
        <v-btn small color="primary" type="submit">Save Settings</v-btn>
        <!-- <v-btn small type="button" @click="hideForm">Cancel</v-btn> -->
        <div class="status-message" v-if="success || failure">
          <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </div>
      </v-row>
    </v-form>
    <p>&nbsp;</p>
    <hr>
    <p>&nbsp;</p>
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
