<!-- components/shared/DataForm.vue -->
<script setup>
  import { computed, ref, reactive, onMounted } from 'vue';
  import ToggleIcon from './ToggleIcon.vue';

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
  const formRef = ref();
  var pw_show = ref(false);
  var pwc_show = ref(false);
  const requiredRule = (v) => !!v || 'This field is required';
  const opType = ref(props.schema.type);
  var consoFlag = ref(false);
  // Set m_schema as a local, mutable copy of the input schema prop
  var m_schema = reactive(JSON.parse(JSON.stringify(props.schema)));
  var formValues = reactive({...props.initialValues});

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
    if ( Object.keys(formValues).includes('password')) {
      if (formValues['password']!==null) {
        return (formValues['password'].length > 0);
      }
    }
    return false;
  });

  const emit = defineEmits(['submit', 'cancel']);

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

  // toggle-specific changes
  function toggleChanged(fieldName) {
    // Show/hide institution/group fields when conso toggle changes
    if (fieldName=='conso') {
      consoFlag.value = !consoFlag.value;
      let _idx = m_schema.fields.findIndex(f => f.name=='institution') ;
      if (_idx >= 0) m_schema.fields[_idx]['visible'] = !consoFlag.value;
      if (typeof(formValues['institution']) != 'undefined') formValues['institution'] = (consoFlag.value) ? 1 : null;
      _idx = m_schema.fields.findIndex(f => f.name=='group') ;
      if (_idx >= 0) m_schema.fields[_idx]['visible'] = !consoFlag.value;
      if (typeof(formValues['group']) != 'undefined') formValues['group'] = null;
    // update CREDENTIAL report field value (CONNECTIONS not handled here... they update via ReportToggle)
    } else if (fieldName=='PR' || fieldName=='DR' || fieldName=='TR' || fieldName=='IR') {
      // Ignore clicks on conso-icons ... conso is updated in connections
      if (typeof(formValues[fieldName]) == 'undefined') return;
      if (formValues[fieldName]['conso']) return;
      // Update insts array for the report
      if (typeof(formValues['inst_id']) != 'undefined') {
        if (typeof(formValues[fieldName]['insts']) != 'undefined' &&
            typeof(formValues[fieldName]['requested']) != 'undefined') {
          var ridx = formValues[fieldName]['insts'].findIndex(ii => ii.id==formValues['inst_id']);
          // Disable inst for the report
          if (ridx >= 0) {
            formValues[fieldName]['insts'].splice(ridx,1);
          // Enable inst disabled report
          } else {
            formValues[fieldName]['insts'].push(formValues['inst_id']);
          }
          formValues[fieldName]['requested'] = !formValues[fieldName]['requested'];
        }
      }
    }
  }

  // select-specific changes
  function selectChanged(fieldName) {
    if (fieldName=='institution') {
      let _idx = m_schema.fields.findIndex(f => f.name=='group');
      if (_idx >= 0) m_schema.fields[_idx]['visible'] = false;
      if (typeof(formValues['group']) != 'undefined') formValues['group'] = null;
    } else if (fieldName=='institutions') {
      // If UNSET exists in options, limit platforms based on chosen inst
      if (m_schema.unset.length>0) {
        // get all UNSET platforms for the selected inst
        m_schema.options.platforms = m_schema.options.platforms.filter( plat =>
          m_schema.unset.filter( opt => opt.inst_id == formValues['institutions'])
                        .map( p => p.plat_id).includes(plat.id)
        );
      }
      let _idx = m_schema.fields.findIndex(f => f.name=='inst_id');
      if (_idx >= 0) formValues['inst_id'] = formValues['institutions'];
    } else if (fieldName=='platforms') {
      // If UNSET exists in options, limit institutions based on chosen platform
      if (m_schema.unset.length>0) {
        m_schema.options.institutions = m_schema.options.institutions.filter( inst =>
          m_schema.unset.filter( opt => opt.plat_id == formValues['platforms'])
                        .map( p => p.inst_id).includes(inst.id)
        );
      }
      let _idx = m_schema.fields.findIndex(f => f.name=='prov_id');
      if (_idx >= 0) formValues['prov_id'] = formValues['platforms'];
    } else if (fieldName=='group') {
      let _idx = m_schema.fields.findIndex(f => f.name=='institution');
      if (_idx >= 0) m_schema.fields[_idx]['visible'] = false;
      if (typeof(formValues['institution']) != 'undefined') formValues['institution'] = null;
    // update any fields named fieldName+"_id" if they exist as a schema field
    // (see: type and type_id in DataTableConfig.js for institutions and groups)
    } else {
      let _cf = fieldName+'_id';
      let _ci = m_schema.fields.findIndex(f => f.name==_cf);
      if (_ci >= 0) formValues[_cf] = formValues[fieldName];
    }
  }

  function submitForm() {
    // apply values of institutions and platforms to inst_id and prov_id if present
    let i_idx = m_schema.fields.findIndex(f => f.name=='inst_id');
    if (i_idx>0 && typeof(formValues['institutions'])!='undefined' && !Array.isArray(formValues['institutions'])) {
      formValues['inst_id'] = formValues['institutions'];
    }
    let p_idx = m_schema.fields.findIndex(f => f.name=='prov_id');
    if (p_idx>0 && typeof(formValues['platforms'])!='undefined' && !Array.isArray(formValues['platforms'])) {
      formValues['prov_id'] = formValues['platforms'];
    }
    if (formRef.value?.validate()) {
      emit('submit', formValues);
    }
  }
onMounted(() => {
  // Preset and/or limit specific select fields
  m_schema.fields.forEach( (fld) => {
    if (fld.type == 'select' && typeof(m_schema.options[fld.name])!='undefined' &&
        typeof(formValues[fld.name])!='undefined') {
      if (Array.isArray(m_schema.options[fld.name]) && m_schema.unset.length>0) {
        // Limit options for insitutions and platforms using unset pairs
        if (fld.name == 'institutions') {
          m_schema.options[fld.name] = props.schema.options[fld.name].filter(
            inst => m_schema.unset.map( p => p.inst_id).includes(inst.id));
        }
        if (fld.name == 'platforms') {
          m_schema.options[fld.name] = props.schema.options[fld.name].filter(
            plat => m_schema.unset.map( p => p.plat_id).includes(plat.id));
        }
        // Preset values if there's only one item in options
        if (m_schema.options[fld.name].length == 1) {
          formValues[fld.name] = m_schema.options[fld.name][0][fld.optVal];
          // Set inst_id and prov_id explictly for institutions and platforms
          if (fld.name == 'institutions' && typeof(formValues['inst_id'])!='undefined') {
            formValues['inst_id'] = formValues['institutions'];
          }
          if (fld.name == 'platforms' && typeof(formValues['prov_id'])!='undefined') {
            formValues['prov_id'] = formValues['platforms'];
          }
        }
      }
    } 
  });
});
</script>

<template>
  <div>
    <v-form v-if="m_schema.fields.length" ref="formRef" @submit.prevent="submitForm">
      <v-container>
        <v-row v-for="field in m_schema.fields" :key="field.name" class="mb-3">
          <v-col v-if="field.visible && (!field.static && (
                                         (opType=='Edit' && (field.editable || !field.isFilter)) ||
                                         (opType=='Add'  && m_schema.requiredKeys.includes(field.name))))"
                 cols="12" class="d-flex ma-0 pa-0">
            <!-- Read-only display -->
            <div v-if="opType!='Add' && (!field.editable || field.renderAsText)">
              <strong>{{ field.label }}:</strong> &nbsp;
              <span class="text-medium-emphasis">{{ formValues[field.name] }}</span>
            </div>

            <!-- ToggleIcon input -->
            <div v-else-if="isToggleField(field)">
              <strong>{{ field.label }} : </strong> &nbsp;
              <ToggleIcon v-model="formValues[field.name]" :toggleable="isToggleEditable(formValues[field.name])"
                          :size="36" @update:modelValue="toggleChanged(field.name)" />
            </div>
            <!-- Password inputs -->
            <v-text-field v-else-if="field.type==='password'" v-model="formValues[field.name]" :label="field.label"
                          :type="pw_show ? 'text' : 'password'" :append-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
                          @click:append="pw_show = !pw_show" variant="outlined" :hint="field.helperText"
                          persistent-hint :rules="field.required ? [requiredRule] : []" density="compact"/>
            <v-text-field v-else-if="field.type==='passconf' && showConfirm" :label="field.label" density="compact"
                          v-model="formValues[field.name]" :hint="field.helperText" variant="outlined" persistent-hint
                          :type="pwc_show ? 'text' : 'password'" :append-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'"
                          @click:append="pwc_show=!pwc_show" :rules="field.required ? [requiredRule] : []"/>

            <!-- Textarea -->
            <v-textarea v-else-if="field.type==='textarea'" v-model="formValues[field.name]"
                        :label="field.label" :rules="field.required ? [requiredRule] : []" variant="outlined"
                        :prepend-icon="field.icon" :hint="field.helperText" persistent-hint density="compact"/>

            <!-- Select inputs -->
            <v-select v-else-if="field.type==='select'" v-model="formValues[field.name]" :label="field.label"
                      :items="m_schema.options[field.name]" :item-title="field.optTxt" :item-value="field.optVal" 
                      :prepend-icon="field.icon" :hint="field.helperText" persistent-hint variant="outlined"
                      :rules="field.required ? [requiredRule] : []" density="compact"
                      @update:modelValue="selectChanged(field.name)" />
            <v-select v-else-if="field.type==='selectObj'" v-model="formValues[field.name]" :label="field.label"
                      :items="m_schema.options[field.name]" :item-title="field.optTxt" :item-value="field.optVal" 
                      :prepend-icon="field.icon" :hint="field.helperText" persistent-hint variant="outlined"
                      return-object :rules="field.required ? [requiredRule] : []" density="compact"/>
            <v-combobox v-else-if="field.type==='mselect'" v-model="formValues[field.name]" :label="field.label"
                      :items="m_schema.options[field.name]" :item-title="field.optTxt" :item-value="field.optVal"
                      :prepend-icon="field.icon" :hint="field.helperText" persistent-hint variant="outlined" multiple
                      :rules="field.required ? [requiredRule] : []" density="compact"/>

            <!-- Fallback text input -->
            <v-text-field v-else-if="field.type!='passconf'" v-model="formValues[field.name]" :label="field.label"
                          variant="outlined":prepend-icon="field.icon" :hint="field.helperText"
                          persistent-hint density="compact"/>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" class="text-left">
            <v-btn color="primary" @click="submitForm">Save</v-btn>
            <v-btn variant="text" class="ml-2" @click="emit('cancel')">Cancel</v-btn>
          </v-col>
        </v-row>
      </v-container>
    </v-form>

    <div v-else class="pa-4 text-medium-emphasis">
      No editable fields available.
    </div>
  </div>
</template>

<style scoped>
  .text-medium-emphasis {
    color: rgba(0, 0, 0, 0.6);
  }
  .error-highlight {
    border: 1px solid red;
  }
</style>
