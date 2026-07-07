  <!-- Account Settings Panel -->
<script setup>
  import { ref, reactive } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  const authStore = useAuthStore();
  const { ccGet } = useAuthStore();
  var dataDialog = ref(false);
  var action = ref('');
  var title = ref('');
  var getRecords = ref('');
  const appUrl = import.meta.env.VITE_APP_URL;
  const ccpKey = authStore.ccp_key;
  const is_admin = authStore.is_admin;
  const is_serveradmin = authStore.is_serveradmin;
  async function getJson(dataset,arg='') {
    title.value = dataset;
    action.value = 'GET';
    let url = '/api/'+dataset.toLowerCase()+'/get';
    url += (arg=='') ? '' : '/'+arg;
    const { data } = await ccGet(url);
    getRecords.value = JSON.stringify(data.records,null,2);
    dataDialog.value = true;
  }
</script>
<template>
  <v-container class="account-container">
    <h2>CC-Plus API</h2>
    <v-row class="d-flex my-2" no-gutters>
      Access to CC-Plus database tables and related datasets is available to all authenticated
      users, subject to the role(s) assigned to their login credential.
    </v-row>
    <v-row class="d-flex mb-1" no-gutters>
      Links from API-aware applications should be consructed as:
    </v-row>
    <v-row class="d-flex mb-2" no-gutters>
      <v-col class="d-flex px-4" cols="12">
        {{ appUrl }}api/<-dataset->/<-method->/<-arguments> 
      </v-col>
      <v-col class="d-flex px-4" cols="12">(see examples below)</v-col>
    </v-row>
    <h4>Required Headers</h4>
    <v-row class="d-flex my-2" no-gutters>
      <v-col class="d-flex px-4" cols="12">
      API requests also require Headers with a "Bearer:" token and a valid "X-Tenant" value in the request(s):
      </v-col>
      <v-col class="d-flex px-8" cols="12">
        Bearer: <- Your_Personal_Access_Token -> &nbsp; &nbsp; (<strong>Managed in your Profile)</strong>
      </v-col>
      <v-col class="d-flex px-8" cols="12">X-Tenant: {{ ccpKey }}</v-col>
    </v-row>
    <h4>Example Links</h4>
    <v-list density="compact">
      <v-list-item v-if="is_serveradmin" @click="getJson('Consortia')">
        Consortia : (  {{ appUrl }}api/consortia/get  )
      </v-list-item>
      <v-list-item alt="Get Institutions" @click="getJson('Institutions',(is_admin ? 'admin' : 'viewer'))">
        <span v-if="is_admin">Institutions : (  {{ appUrl }}api/institutions/get/admin  )</span>
        <span v-else>Institutions : (  {{ appUrl }}api/institutions/get/viewer  )</span>
      </v-list-item>
      <v-list-item v-if="is_admin" @click="getJson('Groups')">
         Institution Groups : (  {{ appUrl }}api/groups/get  )
      </v-list-item>
      <v-list-item @click="getJson('Types')">
         Institution Types : (  {{ appUrl }}api/types/get  )
      </v-list-item>
      <v-list-item v-if="is_serveradmin" @click="getJson('Platforms')">
         Platforms : (  {{ appUrl }}api/platforms/get/admin  )
      </v-list-item>
      <v-list-item @click="getJson('Connections')">
         Connections : (  {{ appUrl }}api/connections/get  )
      </v-list-item>
      <v-list-item @click="getJson('Credentials')">
         Credentials : (  {{ appUrl }}api/credentials/get  )
      </v-list-item>
      <v-list-item v-if="is_admin" @click="getJson('Audit')">
         Credentials audit : (  {{ appUrl }}api/audit/get  )
      </v-list-item>
      <v-list-item @click="getJson('Users')">
         Users : (  {{ appUrl }}api/users/get  )
      </v-list-item>
      <v-list-item @click="getJson('Roles')">
         User Roles : (  {{ appUrl }}api/roles/get  )
      </v-list-item>
      <v-list-item @click="getJson('SavedReports')">
         Saved Reports : (  {{ appUrl }}api/savedreports/get  )
      </v-list-item>
      <v-list-item>
         Data for a Saved Report : (  {{ appUrl }}api/savedreports/execute/<-report_id->  )
      </v-list-item>
    </v-list>
  </v-container>
    <v-dialog v-model="dataDialog">
      <v-card>
        <v-card-title class="pa-6 d-flex justify-space-between align-center">
          <span>CC-Plus API Response for :<br />{{  action }} : {{ title }}</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="dataDialog=false">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-text>
          <v-container v-if="action=='GET'" grid-list-md>
            <pre>{{ getRecords }}</pre>
          </v-container>
        </v-card-text>
      </v-card>
    </v-dialog>
</template>
<style scoped>
</style>
