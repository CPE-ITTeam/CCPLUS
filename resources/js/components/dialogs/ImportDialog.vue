<!-- components/dialogs/ImportDialog.vue -->
<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';

const props = defineProps({
  dataset: { type: String, required: true },
});

const { ccPost } = useAuthStore();
const requiredRule = (v) => !!v || 'This field is required';

var csv_upload = ref(null);
var failure = ref('');
var import_type = ref(null);
const emit = defineEmits(['close', 'imported']);

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
    'import_types': ['Full Replacement', 'Additions & Updates'],
  },
  'groups' : {
    'title': "Import Institution Groups",
    'intro': "Institution Group imports can fully replace existing group definitions or supply additions/updates.",
    'rows': [
        "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institution Groups export FIRST as a backup, and then build an import CSV from the exported data.",
        "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
    ],
    'import_types': ['Full Replacement', 'Additions & Updates'],
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
  'platforms' : {
    'title': "Import Platforms",
    'intro': "Platform imports operate exclusively as Updates. No existing records will be deleted.",
    'rows': [
        "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Platforms export FIRST as a backup, and then build an import CSV from the exported data.",
        "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality."
    ],
    'import_types': [],
  },
};

async function runImport() {
    if (csv_upload.value == null) {
        failure.value = 'A CSV import file is required';
        return;
    }
    failure.value = '';
    let formData = new FormData();
    formData.append('csvfile', csv_upload.value);
    if (datasets[props.dataset]['import_types'].length > 0) {
      formData.append('type', import_type.value);
    }
    let importUrl = '/api/' + props.dataset + '/import';
    const response = await ccPost(importUrl, formData);
    if (response.result) {
      emit('imported', response.msg);
    } else {
      failure.value = response.msg;
    }
}
</script>

<template>
  <BaseDialogLayout @close="emit('close')">
    
    <template #title>
      {{ datasets[props.dataset].title }}
    </template>

    <div v-if="datasets[props.dataset].intro.length > 0">
      <strong>{{ datasets[props.dataset].intro }}</strong>
    </div>

    <div v-for="(row, idx) in datasets[props.dataset].rows" :key="idx" class="text-body-2 text-medium-emphasis">
      {{ row }}
    </div>

    <v-file-input 
      v-model="csv_upload" 
      show-size 
      label="CC+ Import File (CSV)" 
      accept="text/csv" 
      :rules="[requiredRule]" 
    />

    <v-select 
      v-if="datasets[props.dataset]['import_types'].length > 0" 
      v-model="import_type"
      :items="datasets[props.dataset]['import_types']" 
      label="Import Type" 
      :rules="[requiredRule]" 
    />

    <v-alert 
      v-if="failure" 
      type="error" 
      :text="failure" 
    />

    <template #actions>
      <v-btn @click="runImport">
        Run Import
      </v-btn>
      <v-btn @click="emit('close')">
        Cancel
      </v-btn>
    </template>

  </BaseDialogLayout>
</template>
