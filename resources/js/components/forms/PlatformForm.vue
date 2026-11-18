<!-- components/forms/PlatformForm.vue -->
<script setup>
// 1. CORE DEPENDENCIES IMPORT PIPELINE
import { ref, reactive, computed } from 'vue';
import api from '@/services/api.js';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import BaseDialogLayout from '@/components/dialogs/BaseDialogLayout.vue';
import Swal from 'sweetalert2';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

// 2. STABLE COMPONENT CONTRACT MAPPINGS
const props = defineProps({
  initialValues: {
    type: [String, Object],
    required: true,
    validator: (value) => {
      const isString = typeof value === 'string';
      const isObject = typeof value === 'object' && value !== null;
      if (!isString && !isObject) {
        console.warn('Invalid prop: "initialValues" must be a string or an object.');
      }
      return isString || isObject;
    }
  },
  schema: { type: Object, required: true },
});

const emit = defineEmits(['submit', 'cancel']);

// 3. REACTIVE ENVIRONMENT ENGINE SETUP
const formRef = ref();
const opType = ref(props.schema.type);
var warnConnectors = ref(false);
var dialog_error = ref('');
var dialog_success = ref('');

// STABILITY GUARD: Normalizes the raw data input payload into reactive maps cleanly
var initialSource = typeof props.initialValues === 'string'
  ? { value: props.initialValues }
  : { ...props.initialValues };

if (!initialSource.connector_state) initialSource.connector_state = {};
if (initialSource.day_of_month == null || initialSource.day_of_month === '') {
  initialSource.day_of_month = 15;
}
if (!initialSource.report_state) initialSource.report_state = {};
if (initialSource.cur_release != null) {
  initialSource.cur_release = String(initialSource.cur_release).trim();
}

var formValues = reactive(initialSource);

// AUTOMATED UI FAILSAFE: Ensures every schema field fallback configuration remains present in template memory
const formFields = computed(() => {
  var fields = {};
  props.schema.fields.forEach((fld) => {
    fields[fld.name] = { ...fld };
  });
  return fields;
});

// Combine platform's reg_releases with global schema releases for the Release selector
const releaseOptions = computed(() => {
  const itemReleases = Array.isArray(formValues['reg_releases']) ? formValues['reg_releases'] : [];
  const globalReleases = Array.isArray(props.schema.options?.['releases']) ? props.schema.options['releases'] : [];
  return [...new Set([...itemReleases, ...globalReleases].map((r) => String(r).trim()).filter(Boolean))];
});

// 4. SELECTION ACCESSIBILITY RULES MATRIX
const requiredRule = (v) => !!v || 'This field is required';
const dayRules = {
  required: (v) => !!v || 'Day is required',
  validDay: (v) => {
    const d = parseInt(v, 10);
    return (d >= 1 && d <= 31) || 'Day must be between 1 and 31';
  }
};

// 5. STANDARD EVENT MONITORING OPERATIONS
function changeConnector() {
  if (!formValues['connector_state']) return;
  Object.keys(formValues['connector_state']).forEach((cnx) => {
    if (formValues['connector_state'][cnx] == null) formValues['connector_state'][cnx] = false;
    if (props.initialValues['connector_state'] &&
      props.initialValues['connector_state'][cnx] &&
      !formValues['connector_state'][cnx]) {
      warnConnectors.value = true;
    }
  });
}

function changeReport() {
  if (!formValues['report_state']) return;
  Object.keys(formValues['report_state']).forEach((rpt) => {
    if (formValues['report_state'][rpt] == null) formValues['report_state'][rpt] = false;
  });
}

function changeRelease() {
  if (!formValues['registries']) return;
  let registry = formValues['registries'].find(r => r.release == formValues['cur_release']);
  if (typeof (registry) !== 'undefined') {
    formValues['connector_state'] = Object.assign({}, registry.connector_state);
    formValues['report_state'] = Object.assign({}, registry.report_state);
    formValues['is_selected'] = registry.is_selected;
    if (registry.service_url) {
      formValues['service_url'] = registry.service_url;
    }
  }
}

function changeSelected() {
  if (!formValues['registries']) return;
  formValues['registries'].forEach((reg) => {
    reg.is_selected = (reg.release == formValues['cur_release']) ? 'Active' : 'Inactive';
  });
}

// 6. PIPELINE CONTROLLER SUBMISSIONS
async function submitForm() {
  const isValid = await formRef.value?.validate();
  if (!isValid?.valid) return;

  if (props.schema.dataset == 'platforms' && warnConnectors.value) {
    let warning_html = "One or more required connectors has been marked as no longer required. The current " +
      " values defined for these connectors will be cleared THROUGH ALL INSTANCES from the " +
      " COUNTER API Credentials when the platform is saved.<br />";
    warning_html += "Having good exports of the COUNTER API credentials for all instances could be valuable if you find" +
      " you need to re-enable the modified connector field.";

    Swal.fire({
      title: 'Continue to save?',
      html: warning_html,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, proceed',
      customClass: { container: 'swal-container' }
    }).then((result) => {
      if (result.value) {
        emit('submit', formValues);
      }
    });
  } else {
    emit('submit', formValues);
  }
}

// REGISTRY REFRESH DATA BINDING
async function regRefresh() {
  dialog_error.value = '';
  dialog_success.value = '';
  var args = { ids: [formValues['id']], dialog: true };
  try {
    const response = await api.post('/api/platforms/refresh', args);

    const payload = (response && response.data && typeof response.data.result !== 'undefined')
      ? response.data
      : response;

    if (!payload || payload.result === false) {
      dialog_error.value = payload?.msg || "Refresh failed on the server.";
      return;
    }

    const updatedRecord = payload.record || (Array.isArray(payload.affectedItems) ? payload.affectedItems[0] : payload.affectedItems);

    if (updatedRecord) {
      Object.keys(formValues).forEach((key) => {
        if (typeof updatedRecord[key] !== 'undefined') {
          if (Array.isArray(updatedRecord[key])) {
            formValues[key] = [...updatedRecord[key]];
          } else {
            formValues[key] = updatedRecord[key];
          }
        }
      });
      dialog_success.value = payload.msg || "Selected platform successfully retrieved.";
      changeRelease();
    } else {
      dialog_error.value = "Server returned success but no platform records could be parsed.";
    }
  } catch (error) {
    console.error("Error running registry refresh:", error);
    dialog_error.value = "An error occurred while refreshing registry data.";
  }
}
</script>

<template>
  <!-- Pure form content block. No BaseDialogLayout, no outer v-card -->
  <v-form ref="formRef" @submit.prevent="submitForm">


    <v-row align="center">
      <v-col :sm="formFields['name'].cols" cols="12">
        <v-text-field v-model="formValues['name']" :label="formFields['name'].label" hide-details />
      </v-col>
      <v-col :sm="formFields['abbrev'].cols" cols="12">
        <v-text-field v-model="formValues['abbrev']" :label="formFields['abbrev'].label"
          :hint="formFields['abbrev'].helperText" persistent-hint />
      </v-col>
    </v-row>

    <!-- ROW 2: Release Selection Pipeline Cluster -->
    <v-row align="center" class="mt-2">
      <v-col cols="12">
        <v-row class="align-center ma-0 pa-0">
          <v-col cols="auto" class="pa-0">
            <!-- Add mode: clean text input -->
            <v-text-field v-if="opType == 'Add'" v-model="formValues['cur_release']"
              :label="formFields['cur_release'].label" :clearable="false" density="compact" style="width: 140px;"
              :rules="[requiredRule]" hide-details="auto" />

            <!-- Edit mode: native Vuetify v-select with centered selection & menu items -->
            <v-select v-else v-model="formValues['cur_release']" :items="releaseOptions"
              :label="formFields['cur_release'].label" :clearable="false" density="compact" style="width: 140px;"
              :rules="[requiredRule]" hide-details="auto" @update:modelValue="changeRelease">
              <template #selection>
                <span class="text-center d-inline-block" style="width: 84px;">
                  {{ formValues['cur_release'] }}
                </span>
              </template>
              <template #item="{ props: itemProps }">
                <v-list-item v-bind="itemProps" class="text-center pe-12" />
              </template>
            </v-select>


          </v-col>

          <v-col v-if="opType == 'Edit'" class="d-flex align-center py-0 ps-4 pe-0">
            <ToggleIcon v-model="formValues['is_selected']" :toggleable="formValues['is_selected'] == 'Inactive'"
              :size="36" @update:modelValue="changeSelected" />
            <strong class="text-body-2 ms-2">Currently Selected Release</strong>
          </v-col>
        </v-row>
      </v-col>
    </v-row>


    <!-- ROW 3: Endpoint Connectivity Routing & Content Provider -->
    <v-row class="mt-2">
      <v-col cols="12">
        <v-text-field v-model="formValues['service_url']" :label="formFields['service_url'].label"
          :hint="formFields['service_url'].helperText" :readonly="formValues['refreshable'] == 'Active'"
          persistent-hint />
      </v-col>
      <v-col cols="12" sm="6">
        <v-text-field v-model="formValues['platform_parm']" :label="formFields['platform_parm'].label"
          :hint="formFields['platform_parm'].helperText" persistent-hint />
      </v-col>
      <v-col cols="12" sm="6">
        <v-text-field v-model="formValues['content_provider']"
          :label="formFields['content_provider']?.label || 'Content Provider'" />
      </v-col>
    </v-row>


    <!-- ROW 4: Relational Matrix Configurations -->
    <v-row class="mt-2">
      <v-col cols="12" sm="6">
        <v-sheet class="border border-opacity-25 rounded bg-transparent pa-3 h-100">
          <div class="text-center pb-2">
            <strong class="text-subtitle-2 text-primary text-uppercase font-weight-bold">Connection Fields</strong>
          </div>
          <v-divider class="mb-2 border-opacity-25" />
          <div v-for="cnx in props.schema.options['all_connectors']" :key="cnx.name" class="ps-8">
            <v-checkbox v-model="formValues['connector_state'][cnx.name]" :label="cnx.label" density="compact"
              color="primary" hide-details @change="changeConnector" />
          </div>
        </v-sheet>
      </v-col>

      <v-col cols="12" sm="6">
        <v-sheet class="border border-opacity-25 rounded bg-transparent pa-3 h-100">
          <div class="text-center pb-2">
            <strong class="text-subtitle-2 text-primary text-uppercase font-weight-bold">Supported Reports</strong>
          </div>
          <v-divider class="mb-2 border-opacity-25" />
          <div v-for="rpt in props.schema.options['master_reports']" :key="rpt.name" class="ps-8">
            <v-checkbox v-model="formValues['report_state'][rpt.name]" :label="rpt.name" density="compact"
              color="primary" hide-details @change="changeReport" />
          </div>
        </v-sheet>
      </v-col>
    </v-row>


    <!-- ROW 5: Orchestration Toggles & Harvest Timing -->
    <v-row align="center" class="mt-4 ga-4">
      <v-col :sm="formFields['day_of_month'].cols" cols="12" class="d-flex align-center">
        <strong class="text-body-2 text-no-wrap text-medium-emphasis mr-2">Monthly harvest day:</strong>
        <v-number-input v-model.number="formValues['day_of_month']" :min="1" :max="28" :clearable="false"
          control-variant="split" density="compact" :rules="[dayRules.required, dayRules.validDay]"
          style="max-width: 135px;" hide-details="auto" />
      </v-col>


      <v-col cols="auto" class="d-flex align-center">
        <ToggleIcon v-model="formValues.status" toggleable :size="36" />
        <strong class="text-body-2 ms-2 text-medium-emphasis">{{ formFields.status.label }}</strong>
      </v-col>

      <v-col cols="auto" class="d-flex align-center">
        <ToggleIcon v-model="formValues['refreshable']" toggleable :size="36" />
        <strong class="text-body-2 ms-2 text-medium-emphasis">{{ formFields['refreshable'].label }}</strong>
      </v-col>
    </v-row>

    <!-- ROW 6: Registry Integration Execution -->
    <v-row v-if="formValues['refreshable'] == 'Active'" align="center" class="mt-2">
      <v-col cols="12">
        <v-row class="align-center ma-0 pa-0">
          <v-col class="flex-grow-1 pa-0">
            <v-text-field v-model="formValues['registry_id']" :label="formFields['registry_id'].label" hide-details />
          </v-col>
          <v-col cols="auto" class="py-0 ps-3 pe-0">
            <v-btn color="info" @click="regRefresh">Refresh</v-btn>
          </v-col>
        </v-row>
      </v-col>
    </v-row>

    <!-- ROW 7: In-line Notification Warnings -->
    <v-row v-if="dialog_success || dialog_error" class="mt-2">
      <v-col cols="12">
        <StatusAlerts v-model:success="dialog_success" v-model:failure="dialog_error" mode="modal" />
      </v-col>
    </v-row>

    <!-- Optional: Add submission controls inside the form if datasetviewer doesn't provide global actions -->
    <v-row class="mt-4">
      <v-col cols="12" class="d-flex justify-end align-center">
        <div v-if="formValues['updated']?.length > 0" class="text-caption text-disabled text-italic mr-auto">
          Last Updated: {{ formValues['updated'] }}
        </div>
        <v-btn variant="text" @click="emit('cancel')">Cancel</v-btn>
        <v-btn color="primary" type="submit" class="ml-2">Save</v-btn>
      </v-col>
    </v-row>


  </v-form>
</template>
<style scoped>
.centered-number :deep(input) {
  text-align: center;
}
</style>
