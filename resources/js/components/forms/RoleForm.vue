<!-- components/forms/RoleForm.vue -->
<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import InstitutionScopeSwitch from '@/components/ui/InstitutionScopeSwitch.vue';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

const { t } = useI18n();
const authStore = useAuthStore();
const ccPlusStore = useCCPlusStore();

const props = defineProps({
  initialValues: { type: Object, default: () => ({}) },
  schema: { type: Object, required: true },
});

const emit = defineEmits(['submit', 'cancel']);

const formRef = ref();
const dialog_error = ref('');
const dialog_success = ref('');

const isEdit = computed(() => props.schema.type === 'Edit');
const isConsoAdmin = computed(() => Boolean(authStore.is_conso_admin));

const userOptions = computed(() => props.schema.options?.users || props.schema.options?.user || []);
const roleOptions = computed(() => props.schema.options?.user_role || ['Admin', 'Viewer']);
const institutionOptions = computed(() => props.schema.options?.institutions || props.schema.options?.institution || []);
const groupOptions = computed(() => props.schema.options?.groups || props.schema.options?.group || []);

const useInstGroups = ref(Boolean(props.initialValues?.group_id));

const state = reactive({
  id: props.initialValues?.id ?? null,
  users: props.initialValues?.user_id ? [Number(props.initialValues.user_id)] : [],
  user_role: props.initialValues?.role_string
    ? String(props.initialValues.role_string).replace(/^Consortium\s+/i, '')
    : (props.initialValues?.user_role || 'Viewer'),
  conso: Number(props.initialValues?.inst_id) === 1,
  institutions:
    props.initialValues?.inst_id && Number(props.initialValues.inst_id) !== 1
      ? [Number(props.initialValues.inst_id)]
      : [],
  groups: props.initialValues?.group_id ? [Number(props.initialValues.group_id)] : [],
});

const isServerAdmin = computed(() => state.user_role === 'ServerAdmin');

watch(
  () => state.conso,
  (isConso) => {
    if (isConso) {
      state.institutions = [];
      state.groups = [];
    }
  }
);

watch(useInstGroups, (isGroups) => {
  if (isGroups) {
    state.institutions = [];
  } else {
    state.groups = [];
  }
});

// On Edit, a single UserRole row targets 1 Institution or 1 Group
watch(
  () => state.institutions,
  (vals) => {
    if (isEdit.value && vals.length > 1) {
      state.institutions = [vals[vals.length - 1]];
    }
  }
);

watch(
  () => state.groups,
  (vals) => {
    if (isEdit.value && vals.length > 1) {
      state.groups = [vals[vals.length - 1]];
    }
  }
);

const scopeValid = computed(() => {
  if (isServerAdmin.value) return true;
  if (state.conso) return true;
  return useInstGroups.value ? state.groups.length > 0 : state.institutions.length > 0;
});

const requiredRule = (v) => !!v || t('validation.field_required');

function clearAlerts() {
  dialog_error.value = '';
  dialog_success.value = '';
  if (ccPlusStore) {
    ccPlusStore.tableErrorMessage = '';
    ccPlusStore.tableSuccessMessage = '';
  }
}

async function submitForm() {
  clearAlerts();
  if (formRef.value) {
    const { valid } = await formRef.value.validate();
    if (!valid) return;
  }
  if (!isEdit.value && state.users.length === 0) {
    dialog_error.value = t('validation.field_required');
    return;
  }
  if (!scopeValid.value) {
    dialog_error.value = t(
      'users.role_scope_required',
      'Please select Entire Consortium or at least one Institution or Institution Group.'
    );
    return;
  }
  const instId = isServerAdmin.value
    ? null
    : (state.conso ? 1 : (!useInstGroups.value ? (state.institutions[0] ?? null) : null));
  const groupId = isServerAdmin.value || state.conso || !useInstGroups.value
    ? null
    : (state.groups[0] ?? null);


  emit('submit', {
    id: state.id,
    users: [...state.users],
    user: state.users ?? null,
    user_role: state.user_role,
    conso: state.conso ? 'Active' : 'Inactive',
    institutions: isServerAdmin.value || state.conso || useInstGroups.value ? [] : [...state.institutions],
    groups: isServerAdmin.value || state.conso || !useInstGroups.value ? [] : [...state.groups],
    institution: instId,
    group: groupId,
  });
}
</script>

<template>
  <v-form ref="formRef" @submit.prevent="submitForm">
    <v-row>
      <v-col v-if="isEdit && state.id" cols="12" class="d-flex justify-end align-center pb-0">
        <v-chip size="small" variant="tonal" prepend-icon="mdi-web">
          {{ $t('fields.id') }}: {{ state.id }}
        </v-chip>
      </v-col>

      <!-- User Selection (Read-only on Edit, MultiSelectCombobox on Add) -->
      <v-col cols="12" sm="6">
        <div v-if="isEdit" class="text-body-1 py-2">
          <strong>{{ $t('fields.user') }}:</strong>&nbsp;
          <span class="text-medium-emphasis">{{ initialValues?.name }} ({{ initialValues?.email }})</span>
        </div>
        <MultiSelectCombobox
          v-else
          v-model="state.users"
          :label="$t('fields.users')"
          :items="userOptions"
          item-title="name"
          item-value="id"
        />
      </v-col>

      <!-- Role Selection -->
      <v-col cols="12" sm="6">
        <v-select
          v-model="state.user_role"
          :label="$t('fields.role')"
          :items="roleOptions"
          :rules="[requiredRule]"
        />
      </v-col>
      <!-- Scope Section (Hidden for standalone ServerAdmin) -->
      <template v-if="!isServerAdmin">
        <v-col cols="12">
          <v-divider class="mb-3" />
          <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-2">
            <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('fields.scope') }}
            </span>

            <div class="d-flex flex-wrap align-center ga-4">
              <v-switch
                v-if="isConsoAdmin"
                v-model="state.conso"
                :label="$t('fields.entire_consortium')"
                color="primary"
                hide-details
              />

              <InstitutionScopeSwitch
                v-if="!state.conso && groupOptions.length > 0"
                v-model="useInstGroups"
              />
            </div>
          </div>
        </v-col>

        <template v-if="!state.conso">
          <v-col v-if="!useInstGroups || groupOptions.length === 0" cols="12">
            <MultiSelectCombobox
              v-model="state.institutions"
              :label="$t('fields.institutions')"
              :items="institutionOptions"
              item-title="name"
              item-value="id"
            />
          </v-col>

          <v-col v-else cols="12">
            <MultiSelectCombobox
              v-model="state.groups"
              :label="$t('fields.institution_groups')"
              :items="groupOptions"
              item-title="name"
              item-value="id"
            />
          </v-col>
        </template>
      </template>

      <v-col
        v-if="dialog_success || ccPlusStore?.tableSuccessMessage || dialog_error || ccPlusStore?.tableErrorMessage"
        cols="12"
      >
        <StatusAlerts v-model:success="dialog_success" v-model:failure="dialog_error" mode="modal" />
      </v-col>

      <v-col cols="12" class="d-flex align-center">
        <v-btn type="submit">{{ $t('common.save') }}</v-btn>
        <v-btn variant="text" class="ml-2" @click="emit('cancel')">{{ $t('common.cancel') }}</v-btn>
      </v-col>
    </v-row>
  </v-form>
</template>
