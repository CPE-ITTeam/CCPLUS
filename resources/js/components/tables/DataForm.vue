<!-- components/tables/DataForm.vue -->
<script setup>
// 1. GLOBAL MODULE DEPENDENCIES IMPORT HOOKS
import { computed, ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';

const ccPlusStore = useCCPlusStore();

// 2. COMPONENT PROPERTIES & EMITS INTERCHANGE CONTRACTS
const props = defineProps({
  initialValues: {
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

const pw_show = ref(false);
const pwc_show = ref(false);
const requiredRule = (v) => !!v || 'This field is required';
const opType = ref(props.schema.type);
const consoFlag = ref(false);

// Scoped UI Alert Strings
var dialog_error = ref('');
var dialog_success = ref('');

// Set m_schema as a local, mutable copy of the input schema prop
const m_schema = reactive(JSON.parse(JSON.stringify(props.schema)));

// PRE-INITIALIZATION GUARD: Pre-flattens any relational objects before wrapping in reactive proxies
const normalizedSource = typeof props.initialValues === 'string'
  ? { value: props.initialValues }
  : { ...props.initialValues };

Object.keys(normalizedSource).forEach(key => {
  const propertyPayload = normalizedSource[key];
  if (propertyPayload && typeof propertyPayload === 'object' && 'id' in propertyPayload && !Array.isArray(propertyPayload)) {
    normalizedSource[key] = propertyPayload.id;
  }
});

const formValues = reactive(normalizedSource);

// 4. PERFORMANCE COMPUTED DATA MAPPINGS
const fieldTypes = computed(() => {
  return m_schema.fields.filter(fld => fld.searchable).map(f => f.type);
});

const editableKeys = computed(() => {
  return m_schema.fields.filter(fld => fld.editable).map(f => f.type);
});

const fieldLabels = computed(() => {
  return m_schema.fields.map(f => f.label);
});

const showConfirm = computed(() => {
  const passwordKey = Object.keys(formValues).find(key => key === 'password' || key === 'admin_pass');
  if (passwordKey && formValues[passwordKey] !== null) {
    return formValues[passwordKey].length > 0;
  }
  return false;
});

// 5. STANDARD FUNCTIONAL LOGIC OPERATORS
function isToggleField(field) {
  return field.type === 'toggle' ||
    typeof formValues[field.name] === 'boolean' ||
    isComplexToggle(formValues[field.name]);
}

function isComplexToggle(value) {
  return (typeof value === 'object' && value !== null &&
    'available' in value && 'requested' in value && 'conso' in value);
}

function isToggleEditable(value) {
  if (isComplexToggle(value)) {
    return (!value.conso && value.available);
  }
  return true;
}

function toggleChanged(fieldName) {
  if (fieldName == 'conso') {
    consoFlag.value = !consoFlag.value;
    let _idx = m_schema.fields.findIndex(f => f.name == 'institution');
    if (_idx >= 0) m_schema.fields[_idx]['visible'] = !consoFlag.value;
    if (typeof (formValues['institution']) != 'undefined') formValues['institution'] = (consoFlag.value) ? 1 : null;
    _idx = m_schema.fields.findIndex(f => f.name == 'group');
    if (_idx >= 0) m_schema.fields[_idx]['visible'] = !consoFlag.value;
    if (typeof (formValues['group']) != 'undefined') formValues['group'] = null;
  } else if (fieldName == 'PR' || fieldName == 'DR' || fieldName == 'TR' || fieldName == 'IR') {
    if (typeof (formValues[fieldName]) == 'undefined') return;
    if (formValues[fieldName]['conso']) return;
    if (typeof (formValues['inst_id']) != 'undefined') {
      if (typeof (formValues[fieldName]['insts']) != 'undefined' &&
        typeof (formValues[fieldName]['requested']) != 'undefined') {
        let ridx = formValues[fieldName]['insts'].findIndex(ii => ii.id == formValues['inst_id']);
        if (ridx >= 0) {
          formValues[fieldName]['insts'].splice(ridx, 1);
        } else {
          formValues[fieldName]['insts'].push(formValues['inst_id']);
        }
        formValues[fieldName]['requested'] = !formValues[fieldName]['requested'];
      }
    }
  }
}

async function selectChanged(fieldName) {
  if (m_schema.dataset == 'credentials') {
    if (fieldName == 'institutions' || fieldName == 'platforms') {
      let optionsKey = (fieldName == 'platforms') ? 'institutions' : 'platforms';
      let arg = (fieldName == 'platforms') ? { 'plat_id': formValues['platforms'] }
        : { 'inst_id': formValues['institutions'] };
      const response = await ccPost("/api/credentials/unset", arg);
      if (response.result) {
        m_schema.options[optionsKey] = props.schema.options[optionsKey].filter(
          opt => response.unset_ids.includes(opt.id)
        );
      }
    }
  }
  if (fieldName == 'institution') {
    let _idx = m_schema.fields.findIndex(f => f.name == 'group');
    if (_idx >= 0) m_schema.fields[_idx]['visible'] = false;
    if (typeof (formValues['group']) != 'undefined') formValues['group'] = null;
  } else if (fieldName == 'institutions') {
    let _idx = m_schema.fields.findIndex(f => f.name == 'inst_id');
    if (_idx >= 0) formValues['inst_id'] = formValues['institutions'];
  } else if (fieldName == 'platforms') {
    let _idx = m_schema.fields.findIndex(f => f.name == 'prov_id');
    if (_idx >= 0) formValues['prov_id'] = formValues['platforms'];
  } else if (fieldName == 'group') {
    let _idx = m_schema.fields.findIndex(f => f.name == 'institution');
    if (_idx >= 0) m_schema.fields[_idx]['visible'] = false;
    if (typeof (formValues['institution']) != 'undefined') formValues['institution'] = null;
  } else {
    let _cf = fieldName + '_id';
    let _ci = m_schema.fields.findIndex(f => f.name == _cf);
    if (_ci >= 0) {
      let emittedValue = formValues[fieldName];
      if (emittedValue && typeof emittedValue === 'object' && 'id' in emittedValue) {
        formValues[_cf] = emittedValue.id;
      } else {
        formValues[_cf] = emittedValue;
      }
    }
  }
}

// 💡 FIXED FOR VUETIFY 3: Converted to an async execution action to properly await 
// the asynchronous validation state check before allowing form submissions!
async function submitForm() {
  let i_idx = m_schema.fields.findIndex(f => f.name == 'inst_id');
  if (i_idx > 0 && typeof (formValues['institutions']) != 'undefined' && !Array.isArray(formValues['institutions'])) {
    formValues['inst_id'] = formValues['institutions'];
  }
  let p_idx = m_schema.fields.findIndex(f => f.name == 'prov_id');
  if (p_idx > 0 && typeof (formValues['platforms']) != 'undefined' && !Array.isArray(formValues['platforms'])) {
    formValues['prov_id'] = formValues['platforms'];
  }
  
  if (formRef.value) {
    const { valid } = await formRef.value.validate();
    if (valid) {
      emit('submit', formValues);
    }
  }
}

onMounted(() => {
  m_schema.fields.forEach((fld) => {
    if (fld.type == 'select' && typeof (m_schema.options[fld.name]) != 'undefined' &&
      typeof (formValues[fld.name]) != 'undefined') {
      if (fld.name == 'role') {
        let _options = m_schema.options.role.filter(opt => !(opt.includes('Consortium')));
        m_schema.options.role = [..._options];
      }
      if (m_schema.options[fld.name].length == 1) {
        formValues[fld.name] = m_schema.options[fld.name][0][fld.optVal];
        if (fld.name == 'institutions' && typeof (formValues['inst_id']) != 'undefined') {
          formValues['inst_id'] = formValues['institutions'];
        }
        if (fld.name == 'platforms' && typeof (formValues['prov_id']) != 'undefined') {
          formValues['prov_id'] = formValues['platforms'];
        }
        if (fld.name == 'types' && typeof (formValues['type_id']) != 'undefined') {
          formValues['types'] = formValues['type_id'];
        }
      }
    }
  });
});
</script>


<template>
  <div>
    <v-form v-if="m_schema.fields.length" ref="formRef" @submit.prevent="submitForm">
      
      <!-- 🌟 FIXED GUTTER BLOCKS via ma-n2 pa-0 -->
      <v-row class="ma-n2 pa-0">

        <!-- Header Info Row (ID Box) Layout -->
        <v-col v-if="typeof (formValues['id']) !== 'undefined'" cols="12" class="d-flex justify-end align-center px-2 mb-2">
          <v-chip size="small" variant="tonal" class="text-caption">
            <v-icon start size="small">mdi-web</v-icon>
            ID: {{ formValues['id'] }}
          </v-chip>
        </v-col>

        <!-- Dynamic Form Fields Loop -->
        <template v-for="field in m_schema.fields" :key="field.name">
          
          <!-- FORCE LINE BREAK: Steps down a row cleanly if requested by your schema configs -->
          <v-col v-if="field.breakBefore" cols="12" class="pa-0" />

          <!-- SMART FALLBACK: Handles responsive sm-breakpoint wrapping cleanly -->
          <v-col 
            v-if="field.visible && (!field.static && (
              (opType == 'Edit' && (field.editable || !field.isFilter)) ||
              (opType == 'Add' && m_schema.requiredKeys.includes(field.name))))"
            cols="12"
            :sm="field.cols || 12"
            class="px-2 py-1"
          >
            <!-- Read-only text mode styling with inline un-wrapping guard logic -->
            <div v-if="opType !== 'Add' && (!field.editable || field.renderAsText)" class="text-body-1 py-1">
              <strong>{{ field.label }}:</strong> &nbsp;
              <span class="text-medium-emphasis">
                {{
                  formValues[field.name] && typeof formValues[field.name] === 'object'
                    ? (formValues[field.name][field.optTxt] || formValues[field.name].name || formValues[field.name])
                    : formValues[field.name]
                }}
              </span>
            </div>

            <div v-else-if="opType == 'Add' && m_schema.dataset == 'credentials' &&
              (field.name == 'institutions' || field.name == 'platforms') &&
              m_schema.options[field.name].length == 0" class="text-body-1 py-1">
              <strong>{{ field.label }}:</strong> &nbsp;
              <span class="text-medium-emphasis">No {{ field.name }} can be added</span>
            </div>

            <!-- ToggleIcon inputs layout -->
            <div v-else-if="isToggleField(field)" class="d-flex align-center h-100 py-1">
              <strong class="mr-2">{{ field.label }} : </strong>
              <ToggleIcon v-model="formValues[field.name]" :toggleable="isToggleEditable(formValues[field.name])"
                :size="36" @update:modelValue="toggleChanged(field.name)" />
            </div>

            <!-- Password Fields -->
            <v-text-field v-else-if="field.type === 'password'" v-model="formValues[field.name]" :label="field.label"
              :type="pw_show ? 'text' : 'password'" :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
              @click:append-inner="pw_show = !pw_show" :hint="field.helperText" 
              :rules="field.required ? [requiredRule] : []" density="compact" error-messages="" variant="outlined" hide-details="auto" />

            <v-text-field v-else-if="field.type === 'passconf' && showConfirm" v-model="formValues[field.name]"
              :label="field.label" :hint="field.helperText" :type="pwc_show ? 'text' : 'password'"
              :append-inner-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append-inner="pwc_show = !pwc_show"
              :rules="field.required ? [requiredRule] : []" density="compact" error-messages="" variant="outlined" hide-details="auto" />

            <!-- Textareas -->
            <v-textarea v-else-if="field.type === 'textarea'" v-model="formValues[field.name]" :label="field.label"
              :rules="field.required ? [requiredRule] : []" :prepend-inner-icon="field.icon"
              :hint="field.helperText" density="compact" error-messages="" variant="outlined" hide-details="auto" />

            <!-- Standard Select Fields -->
            <v-select v-else-if="field.type === 'select'" v-model="formValues[field.name]" :label="field.label"
              :items="m_schema.options[field.name]" :item-title="field.optTxt" :item-value="field.optVal"
              :prepend-inner-icon="field.icon" :hint="field.helperText" 
              :rules="field.required ? [requiredRule] : []" density="compact"
              @update:modelValue="selectChanged(field.name)" error-messages="" variant="outlined" hide-details="auto" />

            <!-- Rich Object Select Strings -->
            <v-select v-else-if="field.type === 'selectObj'" v-model="formValues[field.name]" :label="field.label"
              :items="m_schema.options[field.name]" :item-title="field.optTxt" :item-value="field.optVal"
              :prepend-inner-icon="field.icon" :hint="field.helperText" 
              return-object :rules="field.required ? [requiredRule] : []" density="compact" error-messages="" variant="outlined" hide-details="auto" />

            <!-- Token Tag Selectors -->
            <v-combobox v-else-if="field.type === 'mselect'" v-model="formValues[field.name]" :label="field.label"
              :items="m_schema.options[field.name]" :item-title="field.optTxt" :item-value="field.optVal"
              :prepend-inner-icon="field.icon" :hint="field.helperText" multiple 
              :rules="field.required ? [requiredRule] : []" density="compact" error-messages="" variant="outlined" hide-details="auto" />

            <!-- Universal Fallback text inputs -->
            <v-text-field v-else-if="field.type != 'passconf'" v-model="formValues[field.name]" :label="field.label"
              :prepend-inner-icon="field.icon" :hint="field.helperText" 
              density="compact" :rules="field.required ? [requiredRule] : []" error-messages="" variant="outlined" hide-details="auto" />
          </v-col>

          <!-- Group Switch Configuration Block -->
          <v-col 
            v-if="m_schema.dataset == 'groups' && field.name == 'count' && opType == 'Edit' && formValues['types'] != null"
            cols="12"
            class="px-2 py-1"
          >
            <v-switch v-model="formValues['reset_type']" label="Clear Restriction" color="primary" density="compact" hide-details="auto" />
          </v-col>
        </template>

        <!-- Form Status Alerts -->
        <v-col cols="12" class="px-2 mt-2">
          <v-alert 
            v-if="dialog_success || ccPlusStore?.tableSuccessMessage" 
            type="success" 
            variant="tonal" 
            density="compact" 
            class="mb-2"
            :text="dialog_success || ccPlusStore.tableSuccessMessage" 
          />
          
          <v-alert 
            v-if="dialog_error || ccPlusStore?.tableErrorMessage" 
            type="error" 
            variant="tonal" 
            density="compact" 
            :text="dialog_error || ccPlusStore.tableErrorMessage" 
          />
        </v-col>

        <!-- Submissions Controls Row -->
        <v-col cols="12" class="d-flex align-center px-2 pt-2">
          <v-btn @click="submitForm">Save</v-btn>
          <v-btn class="ml-2" @click="emit('cancel')">Cancel</v-btn>
        </v-col>

        <!-- Metadata indicator tracking -->
        <v-col v-if="typeof (formValues['updated']) !== 'undefined' && formValues['updated'].length > 0" cols="12" class="text-center text-caption text-disabled mt-2">
          Last Updated: {{ formValues['updated'] }}
        </v-col>

      </v-row>
    </v-form>

    <div v-else class="text-center py-6 text-medium-emphasis">
      No editable fields available.
    </div>
  </div>
</template>
