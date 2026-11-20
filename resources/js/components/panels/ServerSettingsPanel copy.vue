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
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

const workerSuccess = ref('');
const workerFailure = ref('');


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
  log_login_fails: '0',
});
const formValid = ref(true);

const workerInfo = ref({
  harvester_running: 0,
  processor_running: 0,
  harvest_queue_count: 0,
  process_queue_count: 0,
  consortia: [],
});
const workerBusy = ref(false);

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
      log_login_fails: String(records.log_login_fails ?? '0'),
    };
  } catch (error) {
    console.error('Error fetching settings:', error.message);
  }
};



async function fetchWorkerStatus() {
  try {
    const res = await api.get('/api/settings/workers');
    const data = res?.data ?? res;
    if (data?.result) workerInfo.value = data;
  } catch (e) {
    console.error('Error fetching worker status:', e.message);
  }
}

async function startWorker(worker, targetConso = null) {
  workerBusy.value = true;
  workerSuccess.value = '';
  workerFailure.value = '';
  try {
    const res = await api.post('/api/settings/workers/run', {
      worker,
      consortium: targetConso || authStore.ccp_key,
    });
    const data = res?.data ?? res;
    if (data?.result) {
      workerSuccess.value = data.msg;
      setTimeout(fetchWorkerStatus, 800);
    } else {
      workerFailure.value = data?.msg || 'Failed to start worker.';
    }
  } catch (e) {
    workerFailure.value = 'Failed to start worker.';
  } finally {
    workerBusy.value = false;
  }
}


async function formSubmit() {
  try {
    const response = await api.post('/api/settings/store', { settings: configSettings.value });
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      success.value = resData.msg;
      if (configSettings.value.default_locale) {
        setAppLocale(configSettings.value.default_locale, false);
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

watch(
  () => authStore.ccp_key,
  () => {
    fetchWorkerStatus();
  }
);

onMounted(() => {
  getSettings();
  fetchWorkerStatus();
});
</script>

<template>
  <SettingsPanelShell v-model:valid="formValid" class="mt-4" :title="$t('server.title')" icon="mdi-server-security"
    :submit-label="$t('server.save_server_settings')" :success="success" :failure="failure" @submit="formSubmit">
    <v-row class="ga-y-6">
      <!-- Group 1: Server Info -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-server</v-icon>
            {{ $t('server.server_info') }}
          </legend>

          <v-text-field v-model="configSettings.root_url" :label="$t('server.root_url')" :clearable="false" readonly
            class="mb-5">
            <template #append-inner>
              <LockTip :text="$t('server.readonly_env_hint')" />
            </template>
          </v-text-field>

          <v-text-field v-model="configSettings.reports_path" :label="$t('server.reports_path')" :clearable="false"
            readonly>
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

          <v-text-field :model-value="authStore?.user?.name || 'ServerAdmin'" :label="$t('common.name')"
            :clearable="false" readonly class="mb-5">
            <template #append-inner>
              <LockTip :text="$t('server.serveradmin_cli_tip')" />
            </template>
          </v-text-field>

          <v-text-field :model-value="authStore?.user?.email || 'ServerAdmin'" :label="$t('auth.email')"
            :clearable="false" readonly class="mb-4">
            <template #append-inner>
              <LockTip :text="$t('server.serveradmin_cli_tip')" />
            </template>
          </v-text-field>

          <v-sheet rounded="lg" border class="pa-3 bg-grey-lighten-5 text-body-2 text-medium-emphasis">
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

          <v-select v-model="configSettings.default_locale" :label="$t('server.default_language')"
            :items="languageOptions" item-title="title" item-value="value" class="mb-5" />

          <v-autocomplete v-model="configSettings.time_zone" :label="$t('server.time_zone')"
            :placeholder="$t('server.select_time_zone')" :items="timeZones" />
        </fieldset>
      </v-col>      <!-- Group 4: Harvest Settings -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-cloud-download-outline</v-icon>
            {{ $t('server.harvest_settings') }}
          </legend>

          <YmInput v-model="configSettings.first_yearmon_51" :label="$t('server.first_harvest_51')" :rules="[yearmon]"
            class="mb-5" />

          <v-select v-model="configSettings.fiscalYr" :label="$t('fields.fiscal_year_start')"
            :placeholder="$t('server.select_month')" :items="localizedFyMonths" :rules="[required]" item-title="label"
            item-value="value" class="mb-5" />

          <NumberStepper v-model="configSettings.max_harvest_retries" :label="$t('server.max_harvest_retries')"
            :placeholder="$t('server.placeholder_retries')" :rules="[numberRule]" class="mb-5" />

          <NumberStepper v-model="configSettings.cookie_life" :label="$t('server.cookie_life')"
            :placeholder="$t('server.placeholder_cookie_life')" :rules="[numberRule]" class="mb-4" />

          <v-switch v-model="configSettings.log_login_fails" :label="$t('server.log_login_fails')" true-value="1"
            false-value="0" color="primary" hide-details />
        </fieldset>
      </v-col>
    </v-row>

    <v-divider class="my-6" />
    <!-- Queue & Background Workers -->
    <fieldset class="border-0 pa-0 ma-0">
      <div class="d-flex align-center justify-space-between mb-4">
        <legend class="text-title-large font-weight-semibold text-primary d-flex align-center">
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-cog-sync-outline</v-icon>
          {{ $t('server.queue_workers', 'Queue & Background Workers') }}
        </legend>
        <v-btn size="small" variant="text" prepend-icon="mdi-refresh" @click="fetchWorkerStatus">
          {{ $t('common.refresh', 'Refresh') }}
        </v-btn>
      </div>

      <!-- Global Harvester Worker Banner -->
      <v-sheet border rounded="lg" class="pa-4 mb-4 d-flex flex-wrap align-center justify-space-between ga-3">
        <div>
          <div class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <span>Global Harvester Worker (ccplus:harvester)</span>
            <v-chip size="x-small" :color="workerInfo.harvester_running > 0 ? 'success' : 'secondary'" variant="tonal">
              {{ workerInfo.harvester_running > 0 ? `${workerInfo.harvester_running} Running` : 'Idle' }}
            </v-chip>
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Total across all consortia: <strong>{{ workerInfo.harvest_queue_count }}</strong> in Harvest Queue ·
            <strong>{{ workerInfo.process_queue_count }}</strong> in Process Queue
          </div>
        </div>
        <v-btn color="primary" variant="tonal" prepend-icon="mdi-play" :loading="workerBusy"
          @click="startWorker('harvester')">
          Run Harvester
        </v-btn>
      </v-sheet>

      <!-- Worker Feedback Alerts (between Harvester and Processor blocks) -->
      <StatusAlerts v-model:success="workerSuccess" v-model:failure="workerFailure" mode="modal"
        container-class="mb-4" />

      <!-- Per-Consortium Report Processor Card (Secondary Background) -->
      <!-- Per-Consortium Report Processor Card -->
      <v-sheet v-if="workerInfo.consortia?.length > 0" border rounded="lg" class="overflow-hidden">
        <v-table density="comfortable">
          <thead style="background-color: rgba(var(--v-theme-secondary), 0.12);">
            <tr>
              <th>Consortium</th>
              <th class="text-end">Harvest Queue</th>
              <th class="text-end">Process Queue</th>
              <th class="text-center">Report Processor</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="con in workerInfo.consortia" :key="con.ccp_key">
              <td>
                <div class="font-weight-medium">{{ con.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ con.ccp_key }}</div>
              </td>
              <td class="text-end">{{ con.harvest_queue_count }}</td>
              <td class="text-end font-weight-bold">{{ con.process_queue_count }}</td>
              <td class="text-center">
                <v-chip size="x-small" :color="con.processor_running > 0 ? 'success' : 'secondary'" variant="tonal">
                  {{ con.processor_running > 0 ? `${con.processor_running} Running` : 'Idle' }}
                </v-chip>
              </td>
              <td class="text-end">
                <v-btn size="small" color="primary" variant="tonal" prepend-icon="mdi-play" :loading="workerBusy"
                  :disabled="con.processor_running > 0" @click="startWorker('reportprocessor', con.ccp_key)">
                  Run Processor
                </v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-sheet>

    </fieldset>

  </SettingsPanelShell>
</template>
