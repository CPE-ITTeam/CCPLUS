<!-- components/dialogs/ImportDialog.vue -->
<script setup>
  import { ref } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';

  const props = defineProps({
    dataset: { type:String, required: true },
  });
  const { ccPost } = useAuthStore();
  const requiredRule = (v) => !!v || 'This field is required';

  var csv_upload = ref(null);
  var failure = ref('');
  var import_type = ref(null);
  const import_types = ['Full Replacement', 'New Additions'];
  const emit = defineEmits(['close','imported']);
  
  const datasets = {
    'institutions' : {
      'title': "Import Institutions",
      'intro': "Institution imports function exclusively as Updates. No existing institution records will be deleted.",
      'rows': [
          "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institutions export FIRST as a backup, and then build an import CSV from the exported data.",
          "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
      ],
      'import_types': [],
    },
    'types' : {
      'title': "Import Institution Types",
      'intro': "Institution Type imports can fully replace existing types or provide additions/updates.",
      'rows': [
          "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institution Types export FIRST as a backup, and then build an import CSV from the exported data.",
          "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
      ],
      'import_types': ['Full Replacement', 'New Additions'],
    },
    'groups' : {
      'title': "Import Institution Groups",
      'intro': "Institution Group imports function exclusively as Updates. No existing records will be deleted.",
      'rows': [
          "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institution Groups export FIRST as a backup, and then build an import CSV from the exported data.",
          "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
      ],
      'import_types': [],
    },
    'users' : {
      'title': "Import Users",
      'intro': "User imports function exclusively as Updates. No existing records will be deleted.",
      'rows': [
          "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Users export FIRST as a backup, and then build an import CSV from the exported data.",
          "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
      ],
      'import_types': [],
    },
    'connections' : {
      'title': "Import Connections",
      'intro': "Connection imports function exclusively as Updates. No existing records will be deleted.",
      'rows': [
          "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Connections export FIRST as a backup, and then build an import CSV from the exported data.",
          "Connections assigned to  Institution ID: 1 will be available, by convention, to all members of the consortium.",
          "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
      ],
      'import_types': [],
    },
    'credentials' : {
      'title': "Import Credentials",
      'intro': "Credential imports function exclusively as Updates. No existing records will be deleted.",
      'rows': [
          "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Credentials export FIRST as a backup, and then build an import CSV from the exported data.",
          "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
      ],
      'import_types': [],
    },
  };

  async function runImport() {
      if (csv_upload.value==null) {
          failure.value = 'A CSV import file is required';
          return;
      }
      failure.value = '';
      let formData = new FormData();
      formData.append('csvfile', csv_upload.value);
      if (datasets[props.dataset]['import_types'].length>0) {
        formData.append('type', import_type.value);
      }
      let importUrl = '/api/'+props.dataset+'/import';
      const response = await ccPost(importUrl, formData);
      if (response.result) {
        // pass success message via emit
        emit('imported',response.msg);
      } else {
        failure.value = response.msg;
      }
  }
  </script>
<template>
  <v-card>
    <v-card-title class="d-flex justify-space-between align-center">
      <span>{{ datasets[props.dataset].title }}</span>
      <v-tooltip text="Cancel" location="bottom">
        <template #activator="{ props }">
          <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="emit('close')">
            <v-icon size="18">mdi-close</v-icon>
          </v-btn>
        </template>
      </v-tooltip>
    </v-card-title>
    <v-card-text>
      <v-row v-if="datasets[props.dataset].intro.length>0" class="my-1 pa-0" no-gutters>
        <v-col class="d-flex ma-0 pa-0"><strong>{{ datasets[props.dataset].intro }}</strong></v-col>
      </v-row>
      <v-row v-for="(row, idx) in datasets[props.dataset].rows" :key="idx" class="mb-2 pa-0" no-gutters>
        <v-col class="d-flex ma-0 pa-0">{{ row }}</v-col>
      </v-row>
      <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv"
                    outlined :rules="[requiredRule]"
      ></v-file-input>
      <v-row v-if="datasets[props.dataset]['import_types'].length>0" class="my-2 pa-0" no-gutters>
        <v-col class="d-flex ma-0 pa-0">
          <v-select :items="datasets[props.dataset]['import_types']" v-model="import_type" label="Import Type"
                    outlined :rules="[requiredRule]"
          ></v-select>
        </v-col>
      </v-row>
      <div v-if="failure" class="status-message">
        <span class="fail" v-text="failure"></span>
      </div>
      <p>&nbsp;</p>
      <v-row class="my-1 pa-0 " no-gutters>
        <v-col class="d-flex">
          <v-btn color="primary" @click="runImport">Run Import</v-btn>
        </v-col>
        <v-col class="d-flex">
          <v-btn variant="text" class="ml-2" @click="emit('close')">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>
