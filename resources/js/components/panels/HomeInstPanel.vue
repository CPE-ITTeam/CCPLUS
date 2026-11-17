<!-- resources/js/components/panels/HomeInstPanel.vue -->
<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useValidationRules } from '@/composables/useValidationRules.js';
import { resolvePanelFieldState } from '@/utils/panelPermissions.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import LockTip from '@/components/ui/LockTip.vue';
import { CONSORTIUM_THEMES } from '@/plugins/vuetify.js';

const props = defineProps({
  dataset: { type: Object, required: true },
  title: { type: String, required: true },
  icon: { type: String, default: 'mdi-domain' },
  submitLabel: { type: String, default: '' },
  modelValue: { type: Object, required: true },
  optionsMap: { type: Object, default: () => ({}) },
  isServerAdmin: { type: Boolean, default: false },
  isConsoAdmin: { type: Boolean, default: false },
  canEditOrg: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  success: { type: String, default: '' },
  failure: { type: String, default: '' },
});

const themeOptions = computed(() =>
  CONSORTIUM_THEMES.map((item) => ({
    id: item.id,
    name: t(`account.theme_${item.id}`),
    primary: item.primary,
    info: item.info,
  }))
);
const getThemeColor = (id) =>
  CONSORTIUM_THEMES.find((tItem) => tItem.id === id)?.primary || '#0066a1';

const emit = defineEmits(['update:modelValue', 'submit']);

const { t } = useI18n();
const { required, emailRule } = useValidationRules();
const formValid = ref(true);

const isConsortiumPanel = computed(() => props.dataset?.dataset === 'consortia');

const fieldMap = computed(() => {
  const map = {};
  (props.dataset?.fields || []).forEach((field) => {
    map[field.name] = {
      ...field,
      state: resolvePanelFieldState(field, {
        isServerAdmin: props.isServerAdmin,
        isConsoAdmin: props.isConsoAdmin,
        canEditOrg: props.canEditOrg,
      }),
    };
  });
  return map;
});

const resolvedFields = computed(() =>
  Object.values(fieldMap.value).filter((field) => field.state.visible)
);

const updateField = (name, value) => {
  emit('update:modelValue', { ...props.modelValue, [name]: value });
};

const getFieldRules = (field) => {
  if (!field || !field.state.editable) return [];
  const rules = [];
  if (props.dataset?.required?.includes(field.name) && typeof required === 'function') {
    rules.push(required);
  }
  if (field.type === 'email' && typeof emailRule === 'function') {
    rules.push(emailRule);
  }
  return rules;
};


const formatLines = (field) => {
  const val = props.modelValue[field.name];
  if (Array.isArray(val) && val.length > 0) {
    return val
      .map((item) => {
        if (typeof item === 'object' && item !== null) {
          return item[field.optTxt || 'name'] || '';
        }
        const match = (props.optionsMap[field.name] || []).find(
          (opt) => opt[field.optVal || 'id'] === item
        );
        return match ? match[field.optTxt || 'name'] : item;
      })
      .filter(Boolean)
      .join('\n');
  }
  const str = props.modelValue.group_string || '';
  return str
    ? str
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .join('\n')
    : '';
};

const resolveSelectDisplay = (field) => {
  const val = props.modelValue[field.name];
  if (typeof val === 'object' && val !== null) {
    return val[field.optTxt || 'name'] || '';
  }
  const options = props.optionsMap[field.name] || [];
  const match = options.find((opt) => opt[field.optVal || 'id'] === val);
  if (match) return match[field.optTxt || 'name'];
  return props.modelValue[`${field.name}_name`] || val || '';
};
</script>

<template>
  <SettingsPanelShell v-model:valid="formValid" :title="title" :icon="icon"
    :submit-label="canEditOrg ? submitLabel : ''" :loading="loading" :success="success" :failure="failure"
    @submit="$emit('submit', modelValue)">
    <!-- 3-Column Treatment for Consortium Panel -->
    <v-row v-if="isConsortiumPanel" class="mt-0">
      <!-- Column 1: Conso Name, Key + Status on one line -->
      <v-col cols="12" md="6" lg="4" class="pt-0">
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-lan</v-icon>
          {{ $t('account.conso_identity') }}
        </SectionHeading>

        <v-text-field :model-value="modelValue.name" :label="fieldMap.name?.label"
          :readonly="!fieldMap.name?.state.editable" :rules="getFieldRules(fieldMap.name)" class="mb-4"
          @update:model-value="updateField('name', $event)" />

        <v-row align="center" density="compact">
          <v-col cols="7">
            <v-text-field :model-value="modelValue.ccp_key" :label="fieldMap.ccp_key?.label" readonly
              hide-details="auto">
              <template #append-inner>
                <LockTip :text="$t('server.readonly_env_hint')" />
              </template>
            </v-text-field>
          </v-col>

          <v-col cols="5" class="d-flex align-center justify-end">
            <v-switch :model-value="modelValue.status" :label="fieldMap.status?.label"
              :readonly="!fieldMap.status?.state.editable" :disabled="!fieldMap.status?.state.editable"
              true-value="Active" false-value="Inactive" color="primary" hide-details="auto"
              @update:model-value="updateField('status', $event)" />
            <LockTip v-if="!fieldMap.status?.state.editable" :text="$t('account.readonly_field_hint')" class="ms-1" />
          </v-col>
        </v-row>
      </v-col>

      <!-- Column 2: Admin Name & Email -->
      <v-col cols="12" md="6" lg="4" class="pt-0">
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-account-tie-outline</v-icon>
          {{ $t('account.admin_contact') }}
        </SectionHeading>

        <v-text-field :model-value="modelValue.admin_name" :label="fieldMap.admin_name?.label"
          :readonly="!fieldMap.admin_name?.state.editable" :rules="getFieldRules(fieldMap.admin_name)" class="mb-4"
          @update:model-value="updateField('admin_name', $event)" />

        <v-text-field :model-value="modelValue.email" :label="fieldMap.email?.label" type="email"
          :readonly="!fieldMap.email?.state.editable" :rules="getFieldRules(fieldMap.email)"
          @update:model-value="updateField('email', $event)" />
      </v-col>

      <!-- Column 3: Consortium Preferences (FY & Language) -->
      <v-col cols="12" md="12" lg="4" class="pt-0">
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-tune-variant</v-icon>
          {{ $t('account.preferences') }}
        </SectionHeading>

        <v-select :model-value="modelValue.fiscalYr" :label="fieldMap.fiscalYr?.label"
          :items="optionsMap.fiscalYr || []" item-value="id" item-title="name"
          :readonly="!fieldMap.fiscalYr?.state.editable" :rules="getFieldRules(fieldMap.fiscalYr)" class="mb-4"
          @update:model-value="updateField('fiscalYr', $event)" />

        <v-select :model-value="modelValue.language" :label="fieldMap.language?.label"
          :items="optionsMap.language || []" item-value="id" item-title="name"
          :readonly="!fieldMap.language?.state.editable" :rules="getFieldRules(fieldMap.language)" class="mb-4"
          @update:model-value="updateField('language', $event)" />

        <v-select
          :model-value="modelValue.theme || 'light'"
          :label="$t('account.color_theme')"
          :items="themeOptions"
          item-value="id"
          item-title="name"
          :readonly="!canEditOrg"
          :clearable="false"
          @update:model-value="updateField('theme', $event)"
        >
          <template #selection="{ item }">
            <span
              class="px-3 py-1 rounded text-body-small font-weight-medium"
              :style="{
                backgroundColor: item?.raw?.primary || getThemeColor(modelValue.theme || 'light'),
                color: '#ffffff',
              }"
            >
              {{ item?.title || $t(`account.theme_${modelValue.theme || 'light'}`) }}
            </span>
          </template>

          <template #item="{ item, props: itemProps }">
            <v-list-item v-bind="itemProps" :title="undefined">
              <span
                class="px-3 py-1 rounded text-body-small font-weight-medium d-inline-block my-1"
                :style="{
                  backgroundColor: item?.raw?.primary || getThemeColor(itemProps?.value ?? item?.value),
                  color: '#ffffff',
                }"
              >
                {{ item?.title || itemProps?.title }}
              </span>
            </v-list-item>
          </template>
        </v-select>


      </v-col>
    </v-row>

    <!-- Schema-Driven Grid for Home Institution Panel -->
    <v-row v-else class="mt-0">
      <v-col v-for="field in resolvedFields" :key="field.name" cols="12" :md="field.cols || 12" class="pt-0">
        <!-- Toggle / Switch (status) -->
        <div v-if="field.type === 'toggle'" class="d-flex align-center">
          <v-switch :model-value="modelValue[field.name]" :label="field.label" :readonly="!field.state.editable"
            :disabled="!field.state.editable" true-value="Active" false-value="Inactive" color="primary"
            hide-details="auto" @update:model-value="updateField(field.name, $event)" />
          <LockTip v-if="!field.state.editable" :text="t('account.readonly_field_hint')" class="ms-2" />
        </div>

        <!-- Multi-select: read-only lines box vs editable multi-select -->
        <template v-else-if="field.type === 'mselect'">
          <v-textarea v-if="!field.state.editable && field.readOnlyFormat === 'lines'" :model-value="formatLines(field)"
            :label="field.label" readonly rows="3" auto-grow hide-details="auto" />
          <v-select v-else :model-value="modelValue[field.name]" :label="field.label"
            :items="optionsMap[field.name] || []" :item-value="field.optVal || 'id'"
            :item-title="field.optTxt || 'name'" :readonly="!field.state.editable" multiple chips hide-details="auto"
            @update:model-value="updateField(field.name, $event)" />
        </template>
        <!-- Single Select (type) -->
        <template v-else-if="field.type === 'select'">
          <v-text-field v-if="!field.state.editable" :model-value="resolveSelectDisplay(field)" :label="field.label"
            readonly hide-details="auto" />
          <v-select v-else :model-value="modelValue[field.name]" :label="field.label"
            :items="optionsMap[field.name] || []" :item-value="field.optVal || 'id'"
            :item-title="field.optTxt || 'name'" :rules="getFieldRules(field)" hide-details="auto"
            @update:model-value="updateField(field.name, $event)" />
        </template>

        <!-- Textarea (notes) -->
        <v-textarea v-else-if="field.type === 'textarea'" :model-value="modelValue[field.name]" :label="field.label"
          :readonly="!field.state.editable" :rules="getFieldRules(field)" rows="3" auto-grow hide-details="auto"
          @update:model-value="updateField(field.name, $event)" />

        <!-- Standard Text / Number / Email Input -->
        <v-text-field v-else :model-value="modelValue[field.name]" :label="field.label"
          :type="field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : 'text'"
          :readonly="!field.state.editable" :rules="getFieldRules(field)" hide-details="auto"
          @update:model-value="updateField(field.name, $event)" />
      </v-col>
    </v-row>
  </SettingsPanelShell>
</template>
