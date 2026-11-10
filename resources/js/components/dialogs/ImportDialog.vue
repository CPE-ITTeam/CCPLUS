<!-- components/dialogs/ImportDialog.vue (Full Script) -->
<script setup>
import { ref, watch } from 'vue';
import axios from 'axios'; // 💡 ADDED: Import axios directly to process the upload
import { useAuthStore } from '@/stores/useAuthStore.js';
import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';

const props = defineProps({
  dataset: { type: String, required: true },
});

const emit = defineEmits(['close', 'imported']);

// Access store state for the active bearer token
const authStore = useAuthStore(); 

const csv_upload = ref(null);
const failure = ref('');
const import_type = ref(null);

const requiredRule = (v) => !!v || 'This field is required';

const destructiveWarning = "Use this operation carefully! Imports carry the potential to degrade the CC-Plus configuration, harvesting and reporting functionality.";

const datasets = {
  'institutions': {
    'title': "Import Institutions",
    'intro': "Institution imports function exclusively as updates to prevent broken relational data trees.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institutions export FIRST as a backup, and then build an import CSV from the exported data.",
      destructiveWarning
    ],
    'import_types': ['Additions & Updates'],
  },
  'types': {
    'title': "Import Institution Types",
    'intro': "Institution Type imports can fully replace existing types or provide additions/updates.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institution Types export FIRST as a backup, and then build an import CSV from the exported data.",
      destructiveWarning
    ],
    'import_types': ['Full Replacement', 'Additions & Updates'],
  },
  'groups': {
    'title': "Import Institution Groups",
    'intro': "Institution Group imports can fully replace existing group definitions or supply additions/updates.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate an Institution Groups export FIRST as a backup, and then build an import CSV from the exported data.",
      destructiveWarning
    ],
    'import_types': ['Full Replacement', 'Additions & Updates'],
  },
  'users': {
    'title': "Import Users",
    'intro': "User imports function exclusively as Updates. No existing records will be deleted.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Users export FIRST as a backup, and then build an import CSV from the exported data.",
      destructiveWarning
    ],
    'import_types': ['Additions & Updates'],
  },
  'connections': {
    'title': "Import Connections",
    'intro': "Connection imports function exclusively as Updates. No existing records will be deleted.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Connections export FIRST as a backup, and then build an import CSV from the exported data.",
      "Connections assigned to Institution ID: 1 will be available, by convention, to all members of the consortium.",
      destructiveWarning
    ],
    'import_types': ['Additions & Updates'],
  },
  'credentials': {
    'title': "Import Credentials",
    'intro': "Credential imports function exclusively as Updates. No existing records will be deleted.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Credentials export FIRST as a backup, and then build an import CSV from the exported data.",
      destructiveWarning
    ],
    'import_types': ['Additions & Updates'],
  },
  'platforms': {
    'title': "Import Platforms",
    'intro': "Platform imports operate exclusively as Updates. No existing records will be deleted.",
    'rows': [
      "Detailed import instructions are embedded in the 'How To Import' tab within Export files. The recommended approach is to generate a Platforms export FIRST as a backup, and then build an import CSV from the exported data.",
      destructiveWarning
    ],
    'import_types': ['Additions & Updates'],
  },
};

// Automatically handle default replacement configurations when switching active tabs or datasets
watch(() => props.dataset, (newDataset) => {
  csv_upload.value = null;
  failure.value = '';
  
  if (datasets[newDataset]) {
    const types = datasets[newDataset].import_types;
    import_type.value = types.includes('Additions & Updates') ? 'Additions & Updates' : types;
  } else {
    import_type.value = null;
  }
}, { immediate: true });

async function runImport() {
  if (!csv_upload.value) {
    failure.value = 'A CSV import file is required';
    return;
  }
  if (!import_type.value) {
    failure.value = 'Please select an import configuration type';
    return;
  }
  
  failure.value = '';
  let formData = new FormData();
  formData.append('csvfile', csv_upload.value);
  formData.append('type', import_type.value);
  
  let importUrl = `/api/${props.dataset}/import`;
  
  // ───────────────────────────────────────────────────────────────────────
  // 🔬 DIAGNOSTIC DEBUG BLOCK (Inspects text structure before sending)
  // ───────────────────────────────────────────────────────────────────────
  console.group("🚀 CC+ IMPORT DIAGNOSTIC DEBUG");
  console.log("Target API Endpoint:", importUrl);
  console.log("Import Strategy Mode:", import_type.value);
  console.log("Raw File Info:", {
    name: csv_upload.value.name,
    size: `${(csv_upload.value.size / 1024).toFixed(2)} KB`,
    type: csv_upload.value.type
  });
  
  // Read and print out the raw first few lines of the text buffer
  // Find this block inside your ImportDialog.vue runImport function:
  const reader = new FileReader();
  reader.onload = (e) => {
    let text = e.target.result;
    
    // 💡 DEFENSIVE FIX: Automatically strip out Excel's invisible UTF-8 BOM if present
    if (text.charCodeAt(0) === 0xFEFF || text.startsWith('\uFEFF')) {
      console.log("⚠️ Invisible Excel BOM detected and stripped programmatically!");
      text = text.substring(1);
    }
    
    const lines = text.split("\n").slice(0, 5);
    console.log("Raw Spreadsheet Lines Flying to Server:\n", lines.join("\n"));
    console.groupEnd();
  };
  reader.readAsText(csv_upload.value);

  // ───────────────────────────────────────────────────────────────────────

  try {
    const token = authStore.token || localStorage.getItem('user-token');
    
    const response = await axios.post(importUrl, formData, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'multipart/form-data'
      },
      withCredentials: true,
    });
    
    // Log the absolute raw payload signature returned by Laravel
    console.log("⬇️ Raw Server Response Payload:", response.data);

    if (response.data && response.data.result) {
      emit('imported', response.data.msg);
    } else {
      failure.value = response.data?.msg || 'Import operation failed with an unexpected response layout format.';
    }
  } catch (err) {
    console.error("❌ Network Request Failed:", err.response || err);
    failure.value = err.response?.data?.message || err.response?.data?.msg || err.message || 'An unexpected network connection failure occurred.';
  }
}

</script>


<!-- components/dialogs/ImportDialog.vue (Template) -->
<template>
  <BaseDialogLayout @close="emit('close')">
    <template #title>
      {{ datasets[props.dataset]?.title }}
    </template>

    <v-form @submit.prevent="runImport">
      <v-container class="pa-0">
        
        <!-- Info Guidance Section -->
        <div v-if="datasets[props.dataset]?.intro" class="mb-3 text-body-1 font-weight-bold">
          {{ datasets[props.dataset].intro }}
        </div>

        <div 
          v-for="(row, idx) in datasets[props.dataset]?.rows" 
          :key="idx" 
          class="text-body-2 text-medium-emphasis mb-3"
        >
          {{ row }}
        </div>

        <v-divider class="my-4" />

        <!-- Input Configurations Row (Now uniformly displays the select field for all types) -->
        <v-row no-gutters class="ga-3">
          <v-col cols="12">
            <v-file-input 
              v-model="csv_upload" 
              show-size 
              label="CC+ Import File (CSV)" 
              accept="text/csv" 
              variant="outlined"
              density="compact"
              prepend-icon="mdi-file-delimited-outline"
              hide-details="auto"
              :rules="[requiredRule]" 
            />
          </v-col>

          <v-col cols="12">
            <v-select 
              v-model="import_type"
              :items="datasets[props.dataset]?.import_types || []" 
              label="Import Action / Replacement Strategy" 
              variant="outlined"
              density="compact"
              hide-details="auto"
              :readonly="datasets[props.dataset]?.import_types?.length === 1"
              :hint="datasets[props.dataset]?.import_types?.length === 1 ? 'Locked to safe update mode for cascading records' : ''"
              persistent-hint
              :rules="[requiredRule]" 
            />
          </v-col>
        </v-row>

        <!-- Dynamic Failure Alert Layout Context -->
        <v-alert 
          v-if="failure" 
          type="error" 
          variant="tonal"
          density="compact"
          class="mt-4"
          :text="failure" 
        />
        
      </v-container>
      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <template #actions>
      <v-spacer />
      <v-btn color="primary" variant="flat" @click="runImport">
        Run Import
      </v-btn>
      <v-btn variant="text" @click="emit('close')">
        Cancel
      </v-btn>
    </template>
  </BaseDialogLayout>
</template>
