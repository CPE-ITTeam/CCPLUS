<!-- components/shared/DataForm.vue -->
<script setup>
  import { computed, ref } from 'vue';
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
  const formValues = ref({...props.initialValues});
  const requiredRule = (v) => !!v || 'This field is required';

  const fieldTypes = computed(() => {
    return props.schema.fields.filter(fld => fld.searchable).map(f => f.type);
  });

  const editableKeys = computed(() => {
    return props.schema.fields.filter(fld => fld.editable).map(f => f.type);
  });

  const fieldLabels = computed(() => {
    return props.schema.fields.map(f => f.label);
  });

  defineEmits(['submit', 'cancel']);

  function isToggleField(field) {
    return field.type === 'toggle' ||
      typeof formValues.value[field.name] === 'boolean' ||
      isComplexToggle(formValues.value[field.name]);
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

  const statusMap = {
    true: { icon: 'mdi-check-circle', color: 'green' },
    false: { icon: 'mdi-close-circle', color: 'red' },
    Active: { icon: 'mdi-toggle-switch', color: '#00dd00' },
    Inactive: { icon: 'mdi-toggle-switch-off', color: '#dd0000' },
  };

  function submitForm() {
    if (formRef.value?.validate()) {
      emit('submit', formValues.value);
    }
  }
</script>

<template>
  <div>
    <v-form v-if="props.schema.fields.length" ref="formRef" @submit.prevent="submitForm">
      <v-container>
        <v-row v-for="field in props.schema.fields" :key="field.name" class="mb-3">
          <v-col v-if="!field.static" cols="12">
            <!-- Read-only display -->
            <div v-if="!field.editable || field.renderAsText">
              <strong>{{ field.label }}:</strong>
              <div class="text-medium-emphasis">
                {{ formValues[field.name] }}
              </div>
            </div>

            <!-- ToggleIcon input -->
            <ToggleIcon v-else-if="isToggleField(field)" v-model="formValues[field.name]"
                        :toggleable="isToggleEditable(formValues[field.name])" :statusMap="statusMap"
                        :size="36" @update:modelValue="val => formValues[field.name] = val" />
                        
            <!-- Password input -->
            <v-text-field v-else-if="field.type==='password'" v-model="formValues[field.name]" :label="field.label"
                          :type="pw_show ? 'text' : 'password'" :append-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
                          @click:append="pw_show = !pw_show" variant="outlined" :hint="field.helperText"
                          persistent-hint :rules="field.required ? [requiredRule] : []" />

            <!-- Textarea -->
            <v-textarea v-else-if="field.type==='textarea'" v-model="formValues[field.name]" :label="field.label"
                        :rules="field.required ? [requiredRule] : []" variant="outlined" :prepend-icon="field.icon"
                        :hint="field.helperText" persistent-hint />

            <!-- Select inputs -->
            <v-select v-else-if="field.type==='select'" v-model="formValues[field.name]" :label="field.label"
                      :items="schema.options[field.name].items" item-title="name" item-value="id" variant="outlined"
                      :prepend-icon="field.icon" :hint="field.helperText" persistent-hint
                      :rules="field.required ? [requiredRule] : []" />
            <v-combobox v-else-if="field.type==='mselect'" v-model="formValues[field.name]" :label="field.label"
                        :items="schema.options[field.name].items" item-title="name" item-value="id" multiple
                        persistent-hint :prepend-icon="field.icon" :hint="field.helperText" variant="outlined"
                        :rules="field.required ? [requiredRule] : []" />
          
                      
            <!-- Fallback text input -->
            <v-text-field v-else v-model="formValues[field.name]" :label="field.label" variant="outlined"
                          :prepend-icon="field.icon" :hint="field.helperText" persistent-hint />
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" class="text-left">
            <v-btn color="primary" @click="submitForm">Save</v-btn>
            <v-btn variant="text" class="ml-2" @click="emit('cancel')"
              >Cancel</v-btn
            >
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
