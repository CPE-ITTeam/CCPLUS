<!-- components/dialogs/PlatformDialog.vue -->
<script setup>
  import { ref,reactive, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import ToggleIcon from '../shared/ToggleIcon.vue';
  import Swal from 'sweetalert2';

  const props = defineProps({
    initialValues: {
      validator: (value) => { // validator allows initialValues to be string or object
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
  const { ccPost } = useAuthStore();
  const formRef = ref();
  var formValues = reactive({...props.initialValues});
  const opType = ref(props.schema.type);
  var warnConnectors = ref(false);
  var dialog_error = ref('');
  var dialog_success = ref('');
  // Put fields in an by-name object based on the input schema prop
  const formFields = computed(() => {
    var fields = {};
    props.schema.fields.forEach ( (fld) => {
      if ( (opType.value=='Add' && props.schema.requiredKeys.includes(fld.name)) ||
           (opType.value == 'Edit' && !fld.static) ) {
        fields[fld.name] = {...fld};
      }
    })
    return fields;
  });
  const requiredRule = (v) => !!v || 'This field is required';
  const dayRules = {
    required: (v) => !!v || 'Day is required',
    validDay: (v) => {
      const d = parseInt(v, 10)
      return (d >= 1 && d <= 31) || 'Day must be between 1 and 31'
    }
  }
  const emit = defineEmits(['submit', 'cancel']);

  // Set flag if we need to warn about connectors being turned off (happens when saving)
  function changeConnector() {     
    Object.keys(formValues['connector_state']).forEach( (cnx) => {
      if (formValues['connector_state'][cnx]==null) formValues['connector_state'][cnx] = false;
      if (props.initialValues['connector_state'][cnx] && !formValues['connector_state'][cnx]) {
          warnConnectors.value = true;
      } 
    });
  }
  // Force changes to the report_state checkboxes to hold false, instead of null, when cleared
  function changeReport() {
    Object.keys(formValues['report_state']).forEach( (rpt) => {
      if (formValues['report_state'][rpt] == null) formValues['report_state'][rpt] = false;
    });     
  }
  // Change selected COUNTER Release means updating form fields
  function changeRelease() {
      let registry = formValues['registries'].find(r => r.release == formValues['cur_release']);
      if (typeof(registry)!='undefined') {
        let initial_connector_state = (typeof(registry)!='undefined') ? Object.assign({},registry.connector_state) : {};
        formValues['connector_state'] = Object.assign({},initial_connector_state);
        let initial_report_state = (typeof(registry)!='undefined') ? Object.assign({},registry.report_state) : {};
        formValues['report_state'] = Object.assign({},initial_report_state);
        formValues['is_selected'] = registry.is_selected;
      }
  }
  function changeSelected() {
    formValues['registries'].forEach( (reg) => {
      reg.is_selected = (reg.release == formValues['cur_release']) ? 'Active' : 'Inactive';
    });
  }

  function submitForm() {
    // If a required connector in platforms form was turned off, popup a warning 
    if (props.schema.dataset=='platforms' && warnConnectors.value) {
      let warning_html = "One or more required connectors has been marked as no longer required. The current "+
                         " values defined for these connectors will be cleared THROUGH ALL INSTANCES from the "+
                         " COUNTER API Credentials when the platform is saved.<br />";
      warning_html += "Having good exports of the COUNTER API credentials for all instances could be valuable if you find"
      warning_html += " you need to re-enable the modified connector field.";
      Swal.fire({
        title: 'Continue to save?', html: warning_html, icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed',
          customClass: { container: 'swal-container' }
      }).then((result) => {
        // If confirmed, validate and emit
        if (result.value && formRef.value?.validate()) {
          emit('submit', formValues);
        }
      });
    } else if (formRef.value?.validate()) {
      emit('submit', formValues);
    }
  }

  async function regRefresh() {
    dialog_error.value = '';
    dialog_success.value = '';
    var args = {ids: [ formValues['id'] ], dialog: true };
    const response = await ccPost('/api/platforms/refresh', args);
    if (response.result && typeof(response.affectedItems[0] != 'undefined')) {
      Object.keys(formValues).forEach( (key) => {
        if (typeof(response.affectedItems[0][key]) != 'undefined') {
          formValues[key] = response.affectedItems[0][key];
        }
      });
      dialog_success.value = "Selected platform successfully retrieved.";
    }
  }
</script>
<template>
  <div>
    <v-form ref="formRef" @submit.prevent="submitForm">
      <v-container>
        <v-row class="ma-0 py-1" no-gutters>
          <v-col class="d-flex ma-0 pa-0" cols="10">
            <strong>{{  formFields['status'].label }}: </strong> &nbsp;
            <ToggleIcon v-model="formValues['status']" toggleable :size="36" />
          </v-col>
        </v-row>
        <v-row class="ma-0 py-1" no-gutters>
          <v-col class="d-flex ma-0 pa-0" cols="10">
            <v-text-field v-model="formValues['name']" :label="formFields['name'].label" variant="outlined"
                          density="compact" />
          </v-col>       
          <v-col class="d-flex idbox px-2" cols="2">
            <v-icon title="CC+ ID">mdi-web</v-icon>&nbsp; {{ formValues['id'] }}
          </v-col>
        </v-row>
        <v-row class="ma-0 py-1" no-gutters>
          <v-col class="d-flex ma-0 pa-0">
            <v-text-field v-model="formValues['abbrev']" :label="formFields['abbrev'].label" density="compact" 
                          variant="outlined" :hint="formFields['abbrev'].helperText" persistent-hint />
          </v-col>
        </v-row>
        <v-row class="ma-0 py-1" no-gutters>
          <v-col v-if="opType=='Add'" class="d-flex ma-0 pa-0" cols="12">
            <v-text-field v-model="formValues['cur_release']" :label="formFields['cur_release'].label" density="compact"
                           variant="outlined" :rules="[requiredRule]"
            ></v-text-field>
          </v-col>
          <v-col v-else-if="formFields['releases'].options.length<=1" class="d-flex ma-0 pa-0" cols="4">
            <v-text-field v-model="formValues['cur_release']" :value="formFields['releases'].options[0]"
                          density="compact" :label="formFields['cur_release'].label" variant="outlined" :rules="[requiredRule]"
            ></v-text-field>
          </v-col>
          <v-col v-else-if="formFields['releases'].options.length>1" class="d-flex ma-0 pa-0" cols="4">
            <v-select v-model="formValues['cur_release']" :items="formValues['reg_releases']" density="compact"
                      :label="formFields['cur_release'].label" variant="outlined" :rules="[requiredRule]"
                      @update:modelValue="changeRelease()"
            ></v-select>
          </v-col>
          <v-col v-if="opType=='Edit'" class="d-flex ma-0 pa-0" cols="8">
            <ToggleIcon v-model="formValues['is_selected']" :toggleable="formValues['is_selected']=='Inactive'" :size="36"
                        @update:modelValue="changeSelected()"/>
            &nbsp; <strong>Currently Selected Release </strong>
          </v-col>
        </v-row>
        <v-row class="ma-0 pa-0" no-gutters>
          <v-col class="d-flex ma-0 pa-0" cols="12">
            <v-text-field v-model="formValues['service_url']" :label="formFields['service_url'].label" density="compact" 
                          variant="outlined" :hint="formFields['service_url'].helperText" persistent-hint
                          :readonly="formValues['refreshable']=='Active'" />
          </v-col>
        </v-row>
        <v-row class="my-1 pa-0" no-gutters>
          <v-col class="d-flex ma-0 pa-0" cols="6">
            <v-list density="compact">
              <v-list-item class="verydense"><strong>Connection Fields</strong></v-list-item>
              <v-list-item v-for="cnx in props.schema.options['all_connectors']" :key="cnx.name" class="verydense">
                <v-checkbox v-model="formValues['connector_state'][cnx.name]" :key="cnx.name" :label="cnx.label" hide-details
                             density="compact" @change="changeConnector()">
                </v-checkbox>
              </v-list-item>
            </v-list>
          </v-col>
          <v-col class="d-flex ma-0 pa-0" cols="6">
            <v-list density="compact">
              <v-list-item class="verydense"><strong>Supported Reports</strong></v-list-item>
              <v-list-item v-for="rpt in props.schema.options['master_reports']" :key="rpt.name" class="verydense">
                <v-checkbox v-model="formValues['report_state'][rpt.name]" :key="rpt.name" :label="rpt.name" hide-details
                             density="compact" @change="changeReport()">
                </v-checkbox>
              </v-list-item>
            </v-list>
          </v-col>
        </v-row>
        <v-row class="ma-0 py-1" no-gutters>
          <v-col class="d-flex ma-0 pa-0">
            <v-text-field v-model="formValues['platform_parm']" :label="formFields['platform_parm'].label" density="compact" 
                          variant="outlined" :hint="formFields['platform_parm'].helperText" persistent-hint />
          </v-col>
        </v-row>
        <v-row class="d-flex mt-2 pa-0" no-gutters>
          <v-col class="d-flex pa-0 align-center" cols="7"><strong>Run Harvests Monthly on day: </strong></v-col>
          <v-col class="d-flex pa-0" cols="3">
            <v-text-field v-model="formValues['day_of_month']" :label="formFields['day_of_month'].label" class="centered-input"
                          density="compact" type="number" :rules="[dayRules.required, dayRules.validDay]" 
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row class="d-flex my-1 pa-0" no-gutters>
          <v-col class="d-flex ma-0 pa-0" cols="12">
            <strong>{{  formFields['refreshable'].label }} : </strong> &nbsp;
            <ToggleIcon v-model="formValues['refreshable']" toggleable :size="36" />
          </v-col>
        </v-row>
        <v-row v-if="formValues['refreshable']=='Active'" class="d-flex my-1" no-gutters>
          <v-col cols="9" class="d-flex ma-0 pa-0">
            <v-text-field v-model="formValues['registry_id']" :label="formFields['registry_id'].label" variant="outlined"
                          density="compact" />
          </v-col>
          <v-col cols="3" class="d-flex ma-0 pa-0">
            <v-btn color="primary" @click="regRefresh">Refresh</v-btn>
          </v-col>
        </v-row>
        <v-row class="d-flex mt-2" no-gutters>
          <v-col cols="12" class="text-left">
            <v-btn color="primary" @click="submitForm">Save</v-btn>
            <v-btn variant="text" class="ml-2" @click="emit('cancel')">Cancel</v-btn>
          </v-col>
        </v-row>
        <v-row v-if="dialog_error || dialog_success" class="d-flex my-1 status-message" no-gutters>
          <span v-if="dialog_success" class="good" role="alert" v-text="dialog_success"></span>
          <span v-if="dialog_error" class="fail" role="alert" v-text="dialog_error"></span>
        </v-row>
        <v-row v-if="(typeof(formValues['updated']) != 'undefined')" class="d-flex mt-2" no-gutters>
          <v-col v-if="formValues['updated'].length>0" class="d-flex justify-center">
            <em>Last Updated: {{ formValues['updated'] }}</em>
          </v-col>
        </v-row>
      </v-container>
    </v-form>
  </div>
</template>

<style scoped>
  .text-medium-emphasis {
    color: rgba(0, 0, 0, 0.6);
  }
  .error-highlight {
    border: 1px solid red;
  }
  .verydense {
    max-height: 18px;
    padding: 0px;
  }
  .centered-input >>> input {
    text-align: center
  }
</style>
