<!-- resources/js/components/panels/UserSettingsPanel.vue -->
<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useValidationRules } from '@/composables/useValidationRules.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import LockTip from '@/components/ui/LockTip.vue';

const props = defineProps({
  modelValue: { type: Object, required: true },
  title: { type: String, required: true },
  fyOptions: { type: Array, default: () => [] },
  languageOptions: { type: Array, default: () => [] },
  defaultFy: { type: Number, default: null },
  defaultLanguage: { type: String, default: '' },
  isAdmin: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  success: { type: String, default: '' },
  failure: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const { t } = useI18n();
const { required, emailRule } = useValidationRules();
const formValid = ref(true);
const pw_show = ref(false);
const pwc_show = ref(false);

const updateField = (key, value) => {
  const next = { ...props.modelValue, [key]: value };
  if (key === 'password' && !value) {
    next.confirm_pass = null;
  }
  emit('update:modelValue', next);
};

const passwordConfirmRules = computed(() => {
  if (!props.modelValue.password) return [];
  return [
    (v) => !!v || t('validation.field_required'),
    (v) => v === props.modelValue.password || t('validation.passwords_must_match'),
  ];
});

const defaultFyLabel = computed(() => {
  if (!props.defaultFy) return '';
  return props.fyOptions.find((o) => o.value === props.defaultFy)?.label || '';
});

const defaultLangLabel = computed(() => {
  if (!props.defaultLanguage) return '';
  return props.languageOptions.find((o) => o.id === props.defaultLanguage)?.name || props.defaultLanguage.toUpperCase();
});
</script>

<template>
  <SettingsPanelShell v-model:valid="formValid" :title="title" icon="mdi-account-cog-outline"
    :submit-label="$t('account.save_settings')" :loading="loading" :success="success" :failure="failure"
    @submit="$emit('submit')">
    <v-row class="mt-0">
      <!-- Column 1: Name, Email, Password -->
      <v-col cols="12" md="6" lg="4" class="pt-0">
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-card-account-details-outline</v-icon>
          {{ $t('account.profile_details') }}
        </SectionHeading>

        <v-text-field :model-value="modelValue.name" :label="$t('common.name')" :rules="[required]" class="mb-4"
          @update:model-value="updateField('name', $event)" />

        <v-text-field :model-value="modelValue.email" :label="$t('auth.email')" :rules="[required, emailRule]"
          class="mb-4" @update:model-value="updateField('email', $event)" />

        <v-text-field :model-value="modelValue.password" :label="$t('fields.password')"
          :type="pw_show ? 'text' : 'password'" autocomplete="new-password"
          :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
          @update:model-value="updateField('password', $event)" @click:append-inner="pw_show = !pw_show" />

        <v-text-field v-if="modelValue.password" :model-value="modelValue.confirm_pass"
          :label="$t('fields.confirm_password')" :type="pwc_show ? 'text' : 'password'" autocomplete="new-password"
          :append-inner-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'" :rules="passwordConfirmRules" class="mt-4"
          @update:model-value="updateField('confirm_pass', $event)" @click:append-inner="pwc_show = !pwc_show" />
      </v-col>

      <!-- Column 2: Home Institution, Roles & API Token -->
      <v-col cols="12" md="6" lg="4" class="pt-0">
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-shield-account-outline</v-icon>
          {{ $t('account.organization_and_roles') }}
        </SectionHeading>

        <v-text-field :model-value="modelValue.inst_name || $t('account.na')" :label="$t('fields.home_institution')"
          :clearable="false" readonly class="mb-4">
          <template #append-inner>
            <LockTip :text="$t('account.home_institution_hint')" />
          </template>
        </v-text-field>

        <div class="d-flex align-center justify-space-between mb-2">
          <span class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
            {{ $t('account.user_roles') }}
          </span>
          <router-link v-if="isAdmin" to="/users/roles"
            class="text-caption text-primary text-decoration-none font-weight-medium">
            {{ $t('account.manage_roles') }} →
          </router-link>
        </div>

        <v-list v-if="modelValue.roles && modelValue.roles.length > 0" class="mb-4">
          <template v-for="(role, idx) in modelValue.roles" :key="idx">
            <v-list-item>
              <template #prepend>
                <v-icon size="18" color="success" class="me-2">mdi-shield-check-outline</v-icon>
              </template>

              <v-list-item-title class="text-body-medium">
                <strong>{{ role.role?.name }}</strong> —
                <span v-if="role.inst_id == 1">{{ $t('account.consortium_wide') }}</span>
                <span v-else-if="role.inst_id > 1">{{ role.institution?.name }}</span>
                <span v-else-if="role.group_id > 0">{{ role.institutiongroup?.name }}</span>
              </v-list-item-title>
            </v-list-item>
            <v-divider v-if="idx < modelValue.roles.length - 1" />
          </template>
        </v-list>

        <CodeBlock v-else class="text-body-medium text-medium-emphasis mb-4">
          {{ $t('account.no_roles_assigned') }}
        </CodeBlock>
      </v-col>

      <!-- Column 3: Preferences (FY & Language) -->
      <v-col cols="12" md="12" lg="4" class="pt-0">
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-tune-variant</v-icon>
          {{ $t('account.preferences') }}
        </SectionHeading>

        <v-select :model-value="modelValue.fiscalYr" :label="$t('fields.fiscal_year_start')" :items="fyOptions"
          item-title="label" item-value="value" clearable
          :placeholder="defaultFyLabel" :persistent-placeholder="!!defaultFyLabel"
          :hint="modelValue.fiscalYr ? $t('account.override_active') : $t('account.using_inherited_default')"
          persistent-hint class="mb-4" @update:model-value="updateField('fiscalYr', $event)" />

        <v-select :model-value="modelValue.language" :label="$t('fields.default_language')" :items="languageOptions"
          item-title="name" item-value="id" clearable
          :placeholder="defaultLangLabel" :persistent-placeholder="!!defaultLangLabel"
          :hint="modelValue.language ? $t('account.override_active') : $t('account.using_inherited_default')"
          persistent-hint @update:model-value="updateField('language', $event)" />
      </v-col>
    </v-row>
  </SettingsPanelShell>
</template>
