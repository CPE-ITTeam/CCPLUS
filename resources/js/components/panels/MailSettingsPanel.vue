<!-- resources/js/components/panels/MailSettingsPanel.vue -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api.js';
import { useValidationRules } from '@/composables/useValidationRules.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';

defineOptions({ name: 'MailSettingsPanel', inheritAttrs: false });

const { t } = useI18n();
const { required, numberRule, emailRule } = useValidationRules();

const presetItems = computed(() => [
  { id: 'starttls', text: t('mail.preset_starttls_587'), data: { mailer: 'smtp-starttls', encryption: 'TLS', port: 587 } },
  { id: 'ssl', text: t('mail.preset_ssl_465'), data: { mailer: 'smtp', encryption: 'SSL', port: 465 } },
  { id: 'plain', text: t('mail.preset_plain_25'), data: { mailer: 'SMTP (Plain, port 25)', encryption: null, port: 25 } },
  { id: 'custom', text: t('mail.preset_custom'), data: { mailer: 'custom', encryption: null, port: null } },
]);

const success = ref('');
const failure = ref('');
const mailers = ['smtp', 'smtp-starttls', 'smtps', 'esmtp'];
const crypts = computed(() => [
  { title: t('mail.encryption_none'), value: 'None' },
  { title: 'SSL', value: 'SSL' },
  { title: 'TLS', value: 'TLS' },
]);

const mailSettings = ref({});
const mailFormValid = ref(true);
const pw_show = ref(false);
const mailPresetId = ref(null);

const mailPreset = computed(() => {
  return presetItems.value.find((p) => p.id === mailPresetId.value) || { id: null, text: '', data: {} };
});

const getSettings = async () => {
  try {
    const response = await api.get('/api/settings/get/mail');
    const records = response?.data?.records || response?.records || {};
    const rawEncryption = records.encryption ? String(records.encryption) : '';
    const normalizedEncryption =
      rawEncryption.toLowerCase() === 'ssl'
        ? 'SSL'
        : rawEncryption.toLowerCase() === 'tls'
          ? 'TLS'
          : rawEncryption || null;

    mailSettings.value = {
      mailer: records.mailer || null,
      port: records.port ?? '',
      encryption: normalizedEncryption,
      host: records.host || '',
      mail_username: records.mail_username || '',
      mail_password: records.mail_password || '',
      from_name: records.from_name || '',
      from_address: records.from_address || '',
    };

    if (!mailSettings.value.mailer && !mailSettings.value.port) {
      mailPresetId.value = null;
      return;
    }

    const matched = presetItems.value.find((preset) => {
      if (preset.id === 'custom') return false;
      const mailerMatch =
        preset.id === 'ssl'
          ? ['smtp', 'smtps'].includes(String(mailSettings.value.mailer).toLowerCase())
          : String(preset.data.mailer).toLowerCase() === String(mailSettings.value.mailer).toLowerCase();
      return (
        mailerMatch &&
        preset.data.encryption == mailSettings.value.encryption &&
        preset.data.port == mailSettings.value.port
      );
    });

    mailPresetId.value = matched ? matched.id : 'custom';
  } catch (error) {
    console.error('Error fetching settings:', error.message);
  }
};

function updatePreset(newId) {
  mailPresetId.value = newId;
  const selected = presetItems.value.find((p) => p.id === newId);
  if (selected?.data && selected.id !== 'custom') {
    Object.keys(selected.data).forEach((key) => {
      mailSettings.value[key] = selected.data[key];
    });
  }
}

async function formSubmit() {
  try {
    const response = await api.post('/api/settings/store', { settings: mailSettings.value });
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      success.value = resData.msg;
    } else {
      failure.value = resData?.msg || t('mail.save_error');
    }
  } catch (error) {
    console.error('Error saving settings:', error.message);
    failure.value = t('mail.save_error');
  }
}

watch(
  mailSettings,
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
    v-model:valid="mailFormValid"
    class="mt-4"
    :title="$t('routes.mail_settings')"
    icon="mdi-email-cog-outline"
    :submit-label="$t('mail.save_mail_settings')"
    :success="success"
    :failure="failure"
    @submit="formSubmit"
  >
    <v-row class="ga-y-4">
      <!-- Group 1: Mail Protocol & Encryption -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-email-lock-outline</v-icon>
            {{ $t('mail.mail_configuration') }}
          </legend>

          <v-select
            v-model="mailPresetId"
            :label="$t('mail.mail_protocol_encryption')"
            :items="presetItems"
            :placeholder="$t('mail.select_mail_config')"
            item-title="text"
            item-value="id"
            class="mb-5"
            @update:modelValue="updatePreset"
          />

          <template v-if="mailPreset.id === 'custom'">
            <v-select
              v-model="mailSettings.mailer"
              :label="$t('mail.protocol')"
              :items="mailers"
              :placeholder="$t('mail.select_protocol')"
              :rules="[required]"
              class="mb-5"
            />

            <v-text-field
              v-model="mailSettings.port"
              :label="$t('mail.port')"
              :placeholder="$t('mail.placeholder_port')"
              :rules="[numberRule]"
              class="mb-5"
            />

            <v-select
              v-model="mailSettings.encryption"
              :label="$t('mail.encryption')"
              :items="crypts"
              item-title="title"
              item-value="value"
              :placeholder="$t('mail.select_encryption')"
              :rules="[required]"
            />
          </template>
        </fieldset>
      </v-col>

      <!-- Group 2: Server & Authentication -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-server-network</v-icon>
            {{ $t('mail.server_authentication') }}
          </legend>

          <v-text-field
            v-model="mailSettings.host"
            :label="$t('mail.host_name')"
            :placeholder="$t('mail.placeholder_host')"
            class="mb-5"
          />

          <v-text-field
            v-model="mailSettings.mail_username"
            :label="$t('fields.user_name')"
            :placeholder="$t('mail.placeholder_username')"
            autocomplete="off"
            class="mb-5"
          />

          <v-text-field
            v-model="mailSettings.mail_password"
            :label="$t('fields.password')"
            :type="pw_show ? 'text' : 'password'"
            autocomplete="new-password"
            :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
            :placeholder="$t('mail.placeholder_password')"
            @click:append-inner="pw_show = !pw_show"
          />
        </fieldset>
      </v-col>

      <!-- Group 3: Sender Info -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-card-account-mail-outline</v-icon>
            {{ $t('mail.sender_info') }}
          </legend>

          <v-text-field
            v-model="mailSettings.from_name"
            :label="$t('mail.from_name')"
            :placeholder="$t('mail.placeholder_from_name')"
            class="mb-5"
          />

          <v-text-field
            v-model="mailSettings.from_address"
            :label="$t('mail.from_email')"
            :placeholder="$t('mail.placeholder_email')"
            :rules="[emailRule]"
          />
        </fieldset>
      </v-col>

      <!-- Group 4: More Settings -->
      <v-col cols="12" sm="6" lg="3">
        <fieldset class="border-0 pa-0 ma-0">
          <legend class="text-title-large font-weight-semibold text-primary mb-5 d-flex align-center">
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-cog-outline</v-icon>
            {{ $t('mail.more_settings') }}
          </legend>

          <p class="text-body-2 text-medium-emphasis">
            {{ $t('mail.reserved_future_use') }}
          </p>
        </fieldset>
      </v-col>
    </v-row>
  </SettingsPanelShell>
</template>
