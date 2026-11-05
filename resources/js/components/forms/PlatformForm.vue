<!-- components/forms/PlatformForm.vue -->
<script setup>
// 1. CORE DEPENDENCIES IMPORT PIPELINE
import { ref, reactive, computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import Swal from 'sweetalert2';

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
const { ccPost } = useAuthStore();
const formRef = ref();

const opType = ref(props.schema.type);
var warnConnectors = ref(false);
var dialog_error = ref('');
var dialog_success = ref('');

// STABILITY GUARD: Normalizes the raw data input payload into reactive maps cleanly
var initialSource = typeof props.initialValues === 'string'
  ? { value: props.initialValues }
  : { ...props.initialValues };

var formValues = reactive(initialSource);

// AUTOMATED UI FAILSAFE: Ensures every schema field fallback configuration remains present in template memory
const formFields = computed(() => {
  var fields = {};
  // First, baseline populate every single schema field so hardcoded template lookups don't crash
  props.schema.fields.forEach((fld) => {
    fields[fld.name] = { ...fld };
  });
  return fields;
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

async function regRefresh() {
  dialog_error.value = '';
  dialog_success.value = '';
  var args = { ids: [formValues['id']], dialog: true };
  try {
    const response = await ccPost('/api/platforms/refresh', args);
    if (!response.result) {
      dialog_error.value = response.msg;
    } else if (response.affectedItems && typeof (response.affectedItems[0] !== 'undefined')) {
      Object.keys(formValues).forEach((key) => {
        if (typeof (response.affectedItems[0][key]) !== 'undefined') {
          if (Array.isArray(response.affectedItems[0][key])) {
            formValues[key] = [...response.affectedItems[0][key]];
          } else {
            formValues[key] = response.affectedItems[0][key];
          }
        }
      });
      dialog_success.value = "Selected platform successfully retrieved.";
    }
  } catch (error) {
    console.error("Error running hardware registry refresh:", error);
    dialog_error.value = "An error occurred while refreshing registry data.";
  }
}
</script>

<template>
  <div>
    <v-form ref="formRef" @submit.prevent="submitForm">
      <!-- 
        NATIVE VUETIFY UTILITIES:
        Uses native layout padding (pa-4) and vertical gap row utility configurations (d-flex flex-column ga-4).
        This isolates component spacing and leverages your theme settings perfectly.
      -->
      <div class="d-flex flex-column ga-4 pa-4">

        <!-- Status & System ID Header Meta -->
        <div class="d-flex justify-space-between align-center">
          <div class="d-flex align-center">
            <strong class="text-body-1 mr-2">{{ formFields['status'].label }}:</strong>
            <ToggleIcon v-model="formValues['status']" toggleable :size="36" />
          </div>
          <v-chip size="small" variant="tonal" class="text-caption">
            <v-icon start size="small">mdi-web</v-icon>
            ID: {{ formValues['id'] }}
          </v-chip>
        </div>

        <!-- Name Input -->
        <v-text-field v-model="formValues['name']" :label="formFields['name'].label" 
          density="compact"  />

        <!-- Abbreviation Input -->
        <v-text-field v-model="formValues['abbrev']" :label="formFields['abbrev'].label" 
          density="compact" :hint="formFields['abbrev'].helperText" persistent-hint  />

        <!-- Release Selection Pipeline Cluster -->
        <v-row no-gutters class="ga-3 align-center">
          <v-col v-if="opType == 'Add'" cols="12">
            <v-text-field v-model="formValues['cur_release']" :label="formFields['cur_release'].label"
               density="compact" :rules="[requiredRule]"  />
          </v-col>
          <v-col v-else-if="formFields['releases'].options.length <= 1" class="flex-grow-1">
            <v-text-field v-model="formValues['cur_release']" :value="formFields['releases'].options"
              :label="formFields['cur_release'].label"  density="compact" :rules="[requiredRule]"
               />
          </v-col>
          <v-col v-else-if="formFields['releases'].options.length > 1" class="flex-grow-1">
            <v-select v-model="formValues['cur_release']" :items="formValues['reg_releases']"
              :label="formFields['cur_release'].label"  density="compact" :rules="[requiredRule]"
               @update:modelValue="changeRelease" />
          </v-col>

          <v-col v-if="opType == 'Edit'" class="d-flex align-center flex-grow-1">
            <ToggleIcon v-model="formValues['is_selected']" :toggleable="formValues['is_selected'] == 'Inactive'"
              :size="36" @update:modelValue="changeSelected" />
            <strong class="text-body-2 ml-2">Currently Selected Release</strong>
          </v-col>
        </v-row>

        <!-- Service URL Input -->
        <v-text-field v-model="formValues['service_url']" :label="formFields['service_url'].label" 
          density="compact" :hint="formFields['service_url'].helperText" persistent-hint
          :readonly="formValues['refreshable'] == 'Active'"  />

        <!-- Relational Configuration Lists Layout -->
        <v-row no-gutters class="ga-4">
          <v-col class="pa-0">
            <v-list density="compact" class="bg-transparent pa-0">
              <v-list-item class="px-0 py-1"><strong class="text-subtitle-2">Connection Fields</strong></v-list-item>
              <v-list-item v-for="cnx in props.schema.options['all_connectors']" :key="cnx.name" class="px-0 py-0">
                <v-checkbox v-model="formValues['connector_state'][cnx.name]" :label="cnx.label" density="compact"
                  hide-details @change="changeConnector" />
              </v-list-item>
            </v-list>
          </v-col>
          <v-col class="pa-0">
            <v-list density="compact" class="bg-transparent pa-0">
              <v-list-item class="px-0 py-1"><strong class="text-subtitle-2">Supported Reports</strong></v-list-item>
              <v-list-item v-for="rpt in props.schema.options['master_reports']" :key="rpt.name" class="px-0 py-0">
                <v-checkbox v-model="formValues['report_state'][rpt.name]" :label="rpt.name" density="compact"
                  hide-details @change="changeReport" />
              </v-list-item>
            </v-list>
          </v-col>
        </v-row>

        <!-- Platform Parameter Input -->
        <v-text-field v-model="formValues['platform_parm']" :label="formFields['platform_parm'].label"
           density="compact" :hint="formFields['platform_parm'].helperText" persistent-hint
           />

        <!-- Day of Month Metric Input -->
        <div class="d-flex align-center ga-3">
          <strong class="text-body-1 text-no-wrap">Run Harvests Monthly on day:</strong>
          <!-- 
            NATIVE VUETIFY INPUT MAPPING:
            Swapped custom max-width and centered rules to use native classes: 
            - 'w-25' limits the form element size natively.
            - Injecting the custom configuration layout prop ':class="\'text-center\'"' maps the text direction safely.
          -->
          <v-text-field v-model="formValues['day_of_month']" :label="formFields['day_of_month'].label" class="w-25"
            input-class="text-center"  density="compact" type="number"
            :rules="[dayRules.required, dayRules.validDay]"  />
        </div>

        <!-- Refreshable Settings Toggle Block -->
        <div class="d-flex align-center">
          <strong class="text-body-1 mr-2">{{ formFields['refreshable'].label }}:</strong>
          <ToggleIcon v-model="formValues['refreshable']" toggleable :size="36" />
        </div>

        <!-- Registry ID Input Block -->
        <v-row v-if="formValues['refreshable'] == 'Active'" no-gutters class="ga-2 align-center">
          <v-col class="flex-grow-1">
            <v-text-field v-model="formValues['registry_id']" :label="formFields['registry_id'].label"
               density="compact"  />
          </v-col>
          <v-col cols="auto">
            <v-btn @click="regRefresh">Refresh</v-btn>
          </v-col>
        </v-row>

        <!-- Dynamic Status Message Components: Using standard tonal alerts -->
        <v-alert v-if="dialog_success" type="success" variant="tonal" density="compact" :text="dialog_success" />
        <v-alert v-if="dialog_error" type="error" variant="tonal" density="compact" :text="dialog_error" />

        <!-- Submissions Controls Row -->
        <div class="d-flex align-center pt-2">
          <v-btn @click="submitForm">Save</v-btn>
          <v-btn class="ml-2" @click="emit('cancel')">Cancel</v-btn>
        </div>

        <!-- Timestamp indicator baseline -->
        <div v-if="typeof (formValues['updated']) !== 'undefined' && formValues['updated'].length > 0"
          class="text-center text-caption text-disabled text-italic mt-1">
          Last Updated: {{ formValues['updated'] }}
        </div>

      </div>
    </v-form>
  </div>
</template>
