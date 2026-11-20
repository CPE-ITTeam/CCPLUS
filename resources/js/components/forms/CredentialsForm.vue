<!-- components/forms/CredentialsForm.vue -->
<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api.js';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

const { t } = useI18n();

const props = defineProps({
  initialValues: {
    validator: (value) => {
      const isString = typeof value === 'string';
      const isObject = typeof value === 'object' && value !== null;
      return isString || isObject;
    },
  },
  schema: { type: Object, required: true },
});

const emit = defineEmits(['submit', 'cancel']);

const formRef = ref(null);
const opType = ref(props.schema.type);
const formValues = reactive(
  typeof props.initialValues === 'object' && props.initialValues !== null
    ? { ...props.initialValues }
    : {}
);

const instOptions = ref([]);
const platOptions = ref([]);
const unsetPlatIds = ref(null);
const unsetInstIds = ref(null);
const loadingUnset = ref(false);

const success = ref('');
const failure = ref('');
const showTest = ref(false);
const testingType = ref('');
const testData = ref([]);
const testStatus = ref('');
const testPassed = ref(null);

const allConnectors = computed(() => props.schema.options?.all_connectors || []);
const allPlatforms = computed(() => props.schema.options?.platforms || []);
const allInstitutions = computed(() => props.schema.options?.institutions || []);

const curPlatform = computed(() => {
  return formValues.prov_id > 0
    ? allPlatforms.value.find((p) => Number(p.id) === Number(formValues.prov_id)) || null
    : null;
});

const requiredCnx = computed(() => {
  const map = {};
  allConnectors.value.forEach((cnx) => {
    map[cnx.name] = false;
  });
  if (!curPlatform.value || !curPlatform.value.connectors) return map;
  return { ...map, ...curPlatform.value.connectors };
});

const visibleConnectors = computed(() => {
  return allConnectors.value.filter((cnx) => Boolean(requiredCnx.value[cnx.name]));
});


const filteredInstOptions = computed(() => {
  if (opType.value === 'Edit' || !Array.isArray(unsetInstIds.value)) {
    return instOptions.value;
  }
  const allowed = new Set(unsetInstIds.value.map(Number));
  return instOptions.value.filter((i) => allowed.has(Number(i.id)));
});

const filteredPlatOptions = computed(() => {
  if (opType.value === 'Edit' || !Array.isArray(unsetPlatIds.value)) {
    return platOptions.value;
  }
  const allowed = new Set(unsetPlatIds.value.map(Number));
  return platOptions.value.filter((p) => allowed.has(Number(p.id)));
});

const requiredRule = (v) => {
  const val = String(v ?? '').trim();
  return (val !== '' && val !== '-required-') || t('validation.required', 'This field is required');
};

function clearPlaceholderMarker(fieldName) {
  if (formValues[fieldName] === '-required-') {
    formValues[fieldName] = '';
  }
}

watch(
  () => formValues.inst_id,
  async (newInstId) => {
    if (opType.value !== 'Add') return;
    if (!newInstId) {
      unsetPlatIds.value = null;
      return;
    }
    loadingUnset.value = true;
    try {
      const res = await api.post('/api/credentials/unset', { inst_id: newInstId });
      const data = res?.data ?? res;
      if (data?.result && Array.isArray(data.unset_ids)) {
        unsetPlatIds.value = data.unset_ids;
      }
    } catch (e) {
      console.error('Error fetching unset platforms:', e);
    } finally {
      loadingUnset.value = false;
    }
  }
);

watch(
  () => formValues.prov_id,
  async (newProvId) => {
    // Initialize missing connector keys so v-model binds cleanly in Add mode
    allConnectors.value.forEach((cnx) => {
      if (formValues[cnx.name] === undefined) {
        formValues[cnx.name] = '';
      }
    });

    if (opType.value !== 'Add') return;
    if (!newProvId) {
      unsetInstIds.value = null;
      return;
    }
    loadingUnset.value = true;
    try {
      const res = await api.post('/api/credentials/unset', { plat_id: newProvId });
      const data = res?.data ?? res;
      if (data?.result && Array.isArray(data.unset_ids)) {
        unsetInstIds.value = data.unset_ids;
      }
    } catch (e) {
      console.error('Error fetching unset institutions:', e);
    } finally {
      loadingUnset.value = false;
    }
  }
);

async function testSettings(type) {
  failure.value = '';
  success.value = '';
  testData.value = [];
  testStatus.value = '';
  testPassed.value = null;
  testingType.value = type;
  showTest.value = true;

  const testArgs = { type, prov_id: formValues.prov_id };
  allConnectors.value.forEach((cnx) => {
    if (requiredCnx.value[cnx.name]) {
      testArgs[cnx.name] = formValues[cnx.name] === '-required-' ? '' : formValues[cnx.name];
    }
  });


  try {
    const res = await api.post('/api/credentials/test', testArgs);
    const data = res?.data ?? res;
    testPassed.value = Boolean(data?.result);
    testStatus.value = data?.msg || '';
    const rows = Array.isArray(data?.rows)
      ? data.rows
      : data?.rows
        ? [String(data.rows)]
        : [];
    testData.value = rows.length > 0 ? rows : [data?.result ? 'OK' : 'Test Failed'];
  } catch (e) {
    testPassed.value = false;
    testStatus.value = t('credentials.status_request_failed', 'Test request failed');
    testData.value = [e?.response?.data?.msg || e.message || 'Test Failed'];
  } finally {
    testingType.value = '';
  }
}

async function submitForm() {
  const { valid } = (await formRef.value?.validate()) || { valid: true };
  if (!valid) return;
  emit('submit', { ...formValues });
}

onMounted(() => {
  allConnectors.value.forEach((cnx) => {
    if (formValues[cnx.name] === undefined) {
      formValues[cnx.name] = '';
    }
  });

  if (opType.value === 'Edit') {
    instOptions.value = allInstitutions.value.filter(
      (inst) => Number(inst.id) === Number(formValues.inst_id)
    );
    platOptions.value = allPlatforms.value.filter(
      (plat) => Number(plat.id) === Number(formValues.prov_id)
    );
  } else {
    instOptions.value = [...allInstitutions.value];
    platOptions.value = [...allPlatforms.value];
  }
});
</script>

<template>
  <div>
    <v-form ref="formRef" @submit.prevent="submitForm">
      <!-- Institution & Platform Selection / Header -->
      <v-row class="align-center mb-2">
        <v-col cols="12" sm="6">
          <v-sheet v-if="opType === 'Edit' || instOptions.length === 1" border rounded="lg"
            class="pa-3 bg-grey-lighten-5">
            <div class="text-caption text-medium-emphasis">
              {{ $t('fields.institution') }}
            </div>
            <div class="text-subtitle-1 font-weight-semibold">
              {{ instOptions[0]?.name || formValues.institution }}
            </div>
          </v-sheet>
          <v-autocomplete v-else v-model="formValues.inst_id" :items="filteredInstOptions"
            :label="$t('fields.institution')" :loading="loadingUnset" :rules="[requiredRule]" item-title="name"
            item-value="id" />
        </v-col>

        <v-col cols="12" sm="6">
          <v-sheet v-if="opType === 'Edit' || platOptions.length === 1" border rounded="lg"
            class="pa-3 bg-grey-lighten-5">
            <div class="text-caption text-medium-emphasis">
              {{ $t('fields.platform') }}
            </div>
            <div class="text-subtitle-1 font-weight-semibold">
              {{ platOptions[0]?.name || formValues.platform }}
            </div>
          </v-sheet>
          <v-autocomplete v-else v-model="formValues.prov_id" :items="filteredPlatOptions"
            :label="$t('fields.platform')" :loading="loadingUnset" :rules="[requiredRule]" item-title="name"
            item-value="id" />
        </v-col>
      </v-row>

      <!-- Platform Service URL Hint -->
      <div v-if="curPlatform?.service_url || formValues.service_url"
        class="text-caption text-medium-emphasis mb-3 d-flex align-center ga-1">
        <v-icon size="16" color="primary">mdi-link-variant</v-icon>
        <span class="text-truncate">{{ curPlatform?.service_url || formValues.service_url }}</span>
      </div>

      <!-- Dynamic Platform Connector Fields -->
      <template v-if="formValues.prov_id > 0">
        <v-row v-if="visibleConnectors.length > 0">
          <v-col
            v-for="cnx in visibleConnectors"
            :key="cnx.id || cnx.name"
            cols="12"
          >
            <v-text-field
              :id="cnx.name"
              v-model="formValues[cnx.name]"
              :label="cnx.label"
              :placeholder="$t('credentials.leave_blank_placeholder', 'Leave blank to save as Incomplete')"
              persistent-placeholder
              clearable
              @focus="clearPlaceholderMarker(cnx.name)"
            />
          </v-col>
        </v-row>

        <v-alert v-else type="info" variant="tonal" density="comfortable" class="mb-3">
          {{ $t('credentials.no_connectors_required', 'This platform does not require authentication parameters.') }}
        </v-alert>
      </template>

      <StatusAlerts v-model:success="success" v-model:failure="failure" mode="modal" container-class="mb-3" />
      <!-- Test Output Panel -->
      <v-sheet v-if="showTest" border rounded="lg" class="pa-3 mb-4 bg-grey-lighten-5">
        <div class="d-flex align-center justify-space-between mb-2">
          <div class="d-flex align-center ga-2">
            <v-progress-circular v-if="testingType !== ''" indeterminate size="18" width="2" color="primary" />
            <v-icon v-else-if="testPassed === true" color="success" size="20">
              mdi-check-circle-outline
            </v-icon>
            <v-icon v-else-if="testPassed === false" color="error" size="20">
              mdi-alert-circle-outline
            </v-icon>
            <span class="text-subtitle-2 font-weight-semibold">
              {{ testingType !== '' ? $t('common.working', 'Working...') : testStatus }}
            </span>
          </div>
          <v-btn icon="mdi-close" size="x-small" variant="text" @click="showTest = false" />
        </div>

        <pre v-if="testData.length > 0" class="text-caption overflow-auto pa-2 rounded bg-surface border"
          style="max-height: 220px; white-space: pre-wrap; word-break: break-all;">{{ testData.join('\n') }}</pre>
      </v-sheet>

      <div class="d-flex flex-wrap align-center justify-space-between ga-2 pt-2">
        <div class="d-flex align-center ga-2">
          <v-btn color="primary" variant="tonal" type="submit">
            {{ $t('common.save', 'Save') }}
          </v-btn>
          <v-btn color="secondary" variant="text" @click="emit('cancel')">
            {{ $t('common.cancel', 'Cancel') }}
          </v-btn>
        </div>

        <div v-if="formValues.inst_id != null && formValues.prov_id != null" class="d-flex align-center ga-2">
          <v-btn color="info" variant="tonal" prepend-icon="mdi-heart-pulse" :loading="testingType === 'status'"
            :disabled="testingType !== '' && testingType !== 'status'" @click="testSettings('status')">
            {{ $t('credentials.service_status', 'Service Status') }}
          </v-btn>
          <v-btn color="info" variant="tonal" prepend-icon="mdi-connection" :loading="testingType === 'test'"
            :disabled="testingType !== '' && testingType !== 'test'" @click="testSettings('test')">
            {{ $t('credentials.test_credentials', 'Test Credentials') }}
          </v-btn>
        </div>
      </div>
    </v-form>
  </div>
</template>
