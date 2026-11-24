<!-- components/dialogs/ImportDialog.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuthStore } from '@/stores/useAuthStore.js';
import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

const props = defineProps({
  dataset: { type: String, required: true },
});

const emit = defineEmits(['close', 'imported']);

const { t } = useI18n();
const authStore = useAuthStore();

const csv_upload = ref(null);
const failure = ref('');
const import_type = ref(null);

const requiredRule = (v) => !!v || t('validation.field_required');

const datasets = computed(() => ({
  institutions: {
    title: t('imports.institutions_title'),
    intro: t('imports.institutions_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.institutions') }),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
  types: {
    title: t('imports.types_title'),
    intro: t('imports.types_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.institution_types') }),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.full_replacement'), value: 'Full Replacement' },
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
  groups: {
    title: t('imports.groups_title'),
    intro: t('imports.groups_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.institution_groups') }),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.full_replacement'), value: 'Full Replacement' },
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
  users: {
    title: t('imports.users_title'),
    intro: t('imports.users_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.users') }),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
  connections: {
    title: t('imports.connections_title'),
    intro: t('imports.connections_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.connections') }),
      t('imports.connections_consortium_note'),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
  credentials: {
    title: t('imports.credentials_title'),
    intro: t('imports.credentials_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.credentials') }),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
  platforms: {
    title: t('imports.platforms_title'),
    intro: t('imports.platforms_intro'),
    rows: [
      t('imports.backup_instruction', { dataset: t('routes.platforms') }),
      t('imports.destructive_warning'),
    ],
    import_types: [
      { title: t('imports.additions_and_updates'), value: 'Additions & Updates' },
    ],
  },
}));
// Automatically handle default replacement configurations when switching active tabs or datasets
watch(
  () => props.dataset,
  (newDataset) => {
    csv_upload.value = null;
    failure.value = '';

    const activeDataset = datasets.value[newDataset];
    if (activeDataset) {
      const types = activeDataset.import_types;
      const defaultOpt = types.find((opt) => opt.value === 'Additions & Updates') || types[0];
      import_type.value = defaultOpt ? defaultOpt.value : null;
    } else {
      import_type.value = null;
    }
  },
  { immediate: true }
);

async function runImport() {
  if (!csv_upload.value) {
    failure.value = t('imports.csv_file_required');
    return;
  }
  if (!import_type.value) {
    failure.value = t('imports.import_type_required');
    return;
  }

  failure.value = '';
  const formData = new FormData();
  formData.append('csvfile', csv_upload.value);
  formData.append('type', import_type.value);

  const importUrl = `/api/${props.dataset}/import`;

  // Diagnostic debug block (inspects text structure before sending)
  console.group('🚀 CC+ IMPORT DIAGNOSTIC DEBUG');
  console.log('Target API Endpoint:', importUrl);
  console.log('Import Strategy Mode:', import_type.value);
  console.log('Raw File Info:', {
    name: csv_upload.value.name,
    size: `${(csv_upload.value.size / 1024).toFixed(2)} KB`,
    type: csv_upload.value.type,
  });

  const reader = new FileReader();
  reader.onload = (e) => {
    let text = e.target.result;

    if (text.charCodeAt(0) === 0xfeff || text.startsWith('\uFEFF')) {
      console.log('⚠️ Invisible Excel BOM detected and stripped programmatically!');
      text = text.substring(1);
    }

    const lines = text.split('\n').slice(0, 5);
    console.log('Raw Spreadsheet Lines Flying to Server:\n', lines.join('\n'));
    console.groupEnd();
  };
  reader.readAsText(csv_upload.value);

  try {
    const token = authStore.token || localStorage.getItem('user-token');

    const response = await axios.post(importUrl, formData, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'Content-Type': 'multipart/form-data',
      },
      withCredentials: true,
    });

    console.log('⬇️ Raw Server Response Payload:', response.data);

    if (response.data && response.data.result) {
      emit('imported', response.data.msg);
    } else {
      failure.value = response.data?.msg || t('imports.import_failed');
    }
  } catch (err) {
    console.error('❌ Network Request Failed:', err.response || err);
    failure.value =
      err.response?.data?.message ||
      err.response?.data?.msg ||
      err.message ||
      t('imports.network_failure');
  }
}
</script>

<!-- components/dialogs/ImportDialog.vue -->
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

        <div v-for="(row, idx) in datasets[props.dataset]?.rows" :key="idx"
          class="text-body-2 text-medium-emphasis mb-3">
          {{ row }}
        </div>

        <v-divider class="my-4" />

        <!-- Input Configurations Row -->
        <v-row no-gutters class="ga-3">
          <v-col cols="12">
            <v-file-input v-model="csv_upload" show-size :label="$t('imports.csv_file_label')" accept="text/csv"
              prepend-icon="mdi-file-delimited-outline" 
              :rules="[requiredRule]" />
          </v-col>

          <v-col cols="12">
            <v-select v-model="import_type" :items="datasets[props.dataset]?.import_types || []" item-title="title"
              item-value="value" :label="$t('imports.import_strategy_label')" 
              :readonly="datasets[props.dataset]?.import_types?.length === 1"
              :hint="datasets[props.dataset]?.import_types?.length === 1 ? $t('imports.locked_safe_update_hint') : ''"
              persistent-hint :rules="[requiredRule]" />
          </v-col>
        </v-row>

        <!-- Dynamic Failure Alert Layout Context -->
        <StatusAlerts v-model:failure="failure" mode="modal" />


      </v-container>
      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <template #actions>
      <v-spacer />
      <v-btn color="primary" variant="flat" @click="runImport">
        {{ $t('imports.run_import') }}
      </v-btn>
      <v-btn variant="text" @click="emit('close')">
        {{ $t('common.cancel') }}
      </v-btn>
    </template>
  </BaseDialogLayout>
</template>
