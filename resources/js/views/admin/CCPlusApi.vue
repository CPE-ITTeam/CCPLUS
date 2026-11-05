<!-- views/admin/CCPlusApi.vue -->
<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';

const authStore = useAuthStore();
const { ccGet } = authStore;

const dataDialog = ref(false);
const action = ref('');
const title = ref('');
const getRecords = ref('');

const appUrl = import.meta.env.VITE_APP_URL || '/';
const ccpKey = authStore.ccp_key;
const is_admin = authStore.is_admin;
const is_serveradmin = authStore.is_serveradmin;

async function getJson(dataset, arg = '') {
  title.value = dataset;
  action.value = 'GET';
  let url = '/api/' + dataset.toLowerCase() + '/get';
  url += (arg == '') ? '' : '/' + arg;
  
  try {
    const response = await ccGet(url);
    if (response && response.records) {
      getRecords.value = JSON.stringify(response.records, null, 2);
    } else {
      getRecords.value = "No records returned or operation unauthorized.";
    }
    dataDialog.value = true;
  } catch (error) {
    getRecords.value = "API Retrieval Failed: " + error.message;
    dataDialog.value = true;
  }
}
</script>

<template>
  <!-- 💡 FIXED: A single unified parent div contains all layout templates safely -->
  <div>
    <v-container class="account-container pa-0">
      <h2>CC-Plus API</h2>
      <v-row class="d-flex my-2" no-gutters>
        Access to CC-Plus database tables and related datasets is available to all authenticated
        users, subject to the role(s) assigned to their login credential.
      </v-row>
      <v-row class="d-flex mb-1" no-gutters>
        Links from API-aware applications should be constructed as:
      </v-row>
      <v-row class="d-flex mb-2" no-gutters>
        <v-col class="d-flex px-4 font-weight-bold" cols="12">
          {{ appUrl }}api/&lt;-dataset-&gt;/&lt;-method-&gt;/&lt;-arguments&gt;
        </v-col>
        <v-col class="d-flex px-4 text-caption text-disabled" cols="12">(see examples below)</v-col>
      </v-row>
      
      <h4 class="mt-4">Required Headers</h4>
      <v-row class="d-flex my-2" no-gutters>
        <v-col class="d-flex px-4" cols="12">
          API requests also require Headers with a "Bearer:" token and a valid "X-Tenant" value in the request(s):
        </v-col>
        <v-col class="d-flex px-8 mt-1" cols="12">
          Bearer: &lt;- Your_Personal_Access_Token -&gt; &nbsp; &nbsp; (<strong>Managed in your Profile)</strong>
        </v-col>
        <v-col class="d-flex px-8" cols="12">X-Tenant: {{ ccpKey }}</v-col>
      </v-row>
      
      <h4 class="mt-4">Example Links</h4>
      <v-list density="compact" class="border rounded-lg mt-2">
        <v-list-item v-if="is_serveradmin" @click="getJson('Consortia')" prepend-icon="mdi-code-json">
          Consortia : ( {{ appUrl }}api/consortia/get )
        </v-list-item>
        <v-list-item @click="getJson('Institutions', (is_admin ? 'admin' : 'viewer'))" prepend-icon="mdi-code-json">
          <span v-if="is_admin">Institutions : ( {{ appUrl }}api/institutions/get/admin )</span>
          <span v-else>Institutions : ( {{ appUrl }}api/institutions/get/viewer )</span>
        </v-list-item>
        <v-list-item v-if="is_admin" @click="getJson('Groups')" prepend-icon="mdi-code-json">
           Institution Groups : ( {{ appUrl }}api/groups/get )
        </v-list-item>
        <v-list-item @click="getJson('Types')" prepend-icon="mdi-code-json">
           Institution Types : ( {{ appUrl }}api/types/get )
        </v-list-item>
        <v-list-item v-if="is_serveradmin" @click="getJson('Platforms')" prepend-icon="mdi-code-json">
           Platforms : ( {{ appUrl }}api/platforms/get )
        </v-list-item>
        <v-list-item @click="getJson('Connections')" prepend-icon="mdi-code-json">
           Connections : ( {{ appUrl }}api/connections/get )
        </v-list-item>
        <v-list-item @click="getJson('Credentials')" prepend-icon="mdi-code-json">
           Credentials : ( {{ appUrl }}api/credentials/get )
        </v-list-item>
        <v-list-item v-if="is_admin" @click="getJson('Audit')" prepend-icon="mdi-code-json">
           Credentials audit : ( {{ appUrl }}api/audit/get )
        </v-list-item>
        <v-list-item @click="getJson('Users')" prepend-icon="mdi-code-json">
           Users : ( {{ appUrl }}api/users/get )
        </v-list-item>
        <v-list-item @click="getJson('Roles')" prepend-icon="mdi-code-json">
           User Roles : ( {{ appUrl }}api/roles/get )
        </v-list-item>
        <v-list-item @click="getJson('SavedReports')" prepend-icon="mdi-code-json">
           Saved Reports : ( {{ appUrl }}api/savedreports/get )
        </v-list-item>
        <v-list-item prepend-icon="mdi-link-variant">
           Data for a Saved Report : ( {{ appUrl }}api/savedreports/execute/&lt;-report_id-&gt; )
        </v-list-item>
      </v-list>
    </v-container>

    <!-- 💡 FIXED: Dialog is now nested inside the parent div container, preventing compilation drops! -->
    <v-dialog v-model="dataDialog" max-width="800px">
      <v-card>
        <v-card-title class="pa-4 d-flex justify-space-between align-center">
          <span>CC-Plus API Response for :<br /><strong>{{ action }} : {{ title }}</strong></span>
          <v-btn icon variant="text" @click="dataDialog = false">
            <v-icon size="18">mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <v-container v-if="action=='GET'" class="bg-grey-darken-4 rounded pa-4 text-white">
            <pre style="white-space: pre-wrap; font-family: monospace; font-size: 0.85rem;">{{ getRecords }}</pre>
          </v-container>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>
