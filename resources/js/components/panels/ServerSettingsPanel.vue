<!-- resources/js/components/panels/ServerSettingsPanel.vue -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { setAppLocale } from '@/plugins/i18n.js';
import api from '@/services/api.js';
import { useValidationRules } from '@/composables/useValidationRules.js';
import { fyMonths, timeZones } from '@/stores/useCCPlusStore.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import LockTip from '@/components/ui/LockTip.vue';
import YmInput from '@/components/ui/YmInput.vue';
import NumberStepper from '@/components/ui/NumberStepper.vue';
import { formatDurationMinutes } from '@/utils/datasetHelpers.js';


defineOptions({ name: 'ServerSettingsPanel', inheritAttrs: false });

const { t, locale } = useI18n();
const { required, numberRule, yearmon } = useValidationRules();
const authStore = useAuthStore();

const success = ref('');
const failure = ref('');
const configSettings = ref({
  default_locale: 'en',
  root_url: '',
  reports_path: '',
  time_zone: '',
  first_yearmon_51: '',
  fiscalYr: '',
  max_harvest_retries: 5,
  cookie_life: 30,
  session_timeout: 120,
  log_login_fails: '0',
});
const formValid = ref(true);

const languageOptions = [
  { title: 'English', value: 'en' },
  { title: 'Suomi', value: 'fi' },
  { title: 'Español', value: 'es' },
  { title: 'Français', value: 'fr' },
  { title: 'Deutsch', value: 'de' },
  { title: 'Magyar', value: 'hu' },
];

const localizedFyMonths = computed(() => {
  const formatter = new Intl.DateTimeFormat(locale.value || 'en', { month: 'short' });
  return fyMonths.map((item, idx) => {
    const val = typeof item === 'object' ? item.value : item;
    const formatted = formatter.format(new Date(2024, idx, 1));
    return {
      label: formatted.charAt(0).toUpperCase() + formatted.slice(1).replace(/\.$/, ''),
      value: val,
    };
  });
});

const getSettings = async () => {
  try {
    const response = await api.get('/api/settings/get/config');
    const records = response?.data?.records || response?.records || {};
    configSettings.value = {
      default_locale: 'en',
      ...records,
      max_harvest_retries: records.max_harvest_retries !== undefined ? Number(records.max_harvest_retries) : 5,
      cookie_life: records.cookie_life !== undefined ? Number(records.cookie_life) : 30,
      session_timeout: records.session_timeout !== undefined ? Number(records.session_timeout) : 120,
      log_login_fails: String(records.log_login_fails ?? '0'),
    };
  } catch (error) {
    console.error('Error fetching settings:', error.message);
  }
};

async function formSubmit() {
  try {
    const response = await api.post('/api/settings/store', { settings: configSettings.value });
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      success.value = resData.msg;
      if (configSettings.value.default_locale) {
        setAppLocale(configSettings.value.default_locale, false);
      }
      if (configSettings.value.session_timeout) {
        authStore.sessionTimeout = Number(configSettings.value.session_timeout);
      }
    } else {
      failure.value = resData?.msg || t('server.save_error');
    }
  } catch (error) {
    console.error('Error saving settings:', error.message);
    failure.value = t('server.save_error');
  }
}

watch(
  configSettings,
  () => {
    success.value = '';
    failure.value = '';
  },
  { deep: true }
);

onMounted(() => {
  getSettings();
});
</script>


<template>
  <SettingsPanelShell
    v-model:valid="formValid"
    class="mt-4"
    :title="$t('server.title')"
    icon="mdi-server-security"
    :submit-label="$t('server.save_server_settings')"
    :success="success"
    :failure="failure"
    @submit="formSubmit"
  >
    <v-row class="ga-y-6">
      <!-- Group 1: Server Info -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-server</v-icon>
            {{ $t('server.server_info') }}
          </legend>

          <v-text-field
            v-model="configSettings.root_url"
            :label="$t('server.root_url')"
            :clearable="false"
            readonly
            class="mb-5"
          >
            <template #append-inner>
              <LockTip :text="$t('server.readonly_env_hint')" />
            </template>
          </v-text-field>

          <v-text-field
            v-model="configSettings.reports_path"
            :label="$t('server.reports_path')"
            :clearable="false"
            readonly
          >
            <template #append-inner>
              <LockTip :text="$t('server.readonly_env_hint')" />
            </template>
          </v-text-field>
        </fieldset>
      </v-col>

      <!-- Group 2: Credentials -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-shield-account-outline</v-icon>
            {{ $t('server.credentials') }}
          </legend>

          <v-text-field
            :model-value="authStore?.user?.name || 'ServerAdmin'"
            :label="$t('common.name')"
            :clearable="false"
            readonly
            class="mb-5"
          >
            <template #append-inner>
              <LockTip :text="$t('server.serveradmin_cli_tip')" />
            </template>
          </v-text-field>

          <v-text-field
            :model-value="authStore?.user?.email || 'ServerAdmin'"
            :label="$t('auth.email')"
            :clearable="false"
            readonly
            class="mb-4"
          >
            <template #append-inner>
              <LockTip :text="$t('server.serveradmin_cli_tip')" />
            </template>
          </v-text-field>

          <v-sheet
            rounded="lg"
            border
            class="pa-3 bg-grey-lighten-5 text-body-2 text-medium-emphasis"
          >
            <div class="font-weight-medium text-high-emphasis mb-1">
              {{ $t('account.serveradmin_cli_notice') }}
            </div>
            <i18n-t keypath="account.serveradmin_cli_example" tag="div" class="mb-1">
              <template #command>
                <code class="text-primary font-weight-medium">php artisan ccplus:resetadminpw</code>
              </template>
            </i18n-t>
            <div>{{ $t('account.serveradmin_cli_refer') }}</div>
          </v-sheet>
        </fieldset>
      </v-col>

      <!-- Group 3: Localization -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-earth</v-icon>
            {{ $t('server.localization_settings') }}
          </legend>

          <v-select
            v-model="configSettings.default_locale"
            :label="$t('server.default_language')"
            :items="languageOptions"
            item-title="title"
            item-value="value"
            class="mb-5"
          />

          <v-autocomplete
            v-model="configSettings.time_zone"
            :label="$t('server.time_zone')"
            :placeholder="$t('server.select_time_zone')"
            :items="timeZones"
            class="mb-5"
          />
          <NumberStepper
            v-model="configSettings.session_timeout"
            :label="$t('server.session_timeout', 'Session Timeout')"
            :display-text="formatDurationMinutes(configSettings.session_timeout)"
            :clearable="false"
            :rules="[numberRule]"
            :min="15"
            :max="1440"
            :step="15"
          />


        </fieldset>
      </v-col>


      <!-- Group 4: Harvest Settings -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-cloud-download-outline</v-icon>
            {{ $t('server.harvest_settings') }}
          </legend>

          <YmInput
            v-model="configSettings.first_yearmon_51"
            :label="$t('server.first_harvest_51')"
            :rules="[yearmon]"
            class="mb-5"
          />

          <v-select
            v-model="configSettings.fiscalYr"
            :label="$t('fields.fiscal_year_start')"
            :placeholder="$t('server.select_month')"
            :items="localizedFyMonths"
            :rules="[required]"
            item-title="label"
            item-value="value"
            class="mb-5"
          />

          <NumberStepper
            v-model="configSettings.max_harvest_retries"
            :label="$t('server.max_harvest_retries')"
            :placeholder="$t('server.placeholder_retries')"
            :rules="[numberRule]"
            class="mb-5"
          />

          <NumberStepper
            v-model="configSettings.cookie_life"
            :label="$t('server.cookie_life')"
            :placeholder="$t('server.placeholder_cookie_life')"
            :rules="[numberRule]"
            class="mb-4"
          />

          <v-switch
            v-model="configSettings.log_login_fails"
            :label="$t('server.log_login_fails')"
            true-value="1"
            false-value="0"
            color="primary"
            hide-details
          />
        </fieldset>
      </v-col>
    </v-row>
  </SettingsPanelShell>
</template>
