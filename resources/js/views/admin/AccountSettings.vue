<!-- views/admin/AccountSettings.vue -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useValidationRules } from '@/composables/useValidationRules.js';
import { fyMonths } from '@/stores/useCCPlusStore.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import LockTip from '@/components/ui/LockTip.vue';

const { t, locale } = useI18n();
const authStore = useAuthStore();
const { required, email: emailRule } = useValidationRules();

const success = ref('');
const failure = ref('');
const acctSettings = ref({
  name: '',
  email: '',
  fiscalYr: null,
  password: null,
  confirm_pass: null,
  access_token: null,
  token_expires: null,
  inst_name: '',
  roles: [],
});

const consoSuccess = ref('');
const consoFailure = ref('');
const consoFormValid = ref(true);
const consoSettings = ref({
  ccp_key: '',
  name: '',
  email: '',
});

const formValid = ref(true);
const pw_show = ref(false);
const pwc_show = ref(false);
const pat_show = ref(false);
const tokenCopied = ref(false);

const passwordConfirmRules = computed(() => {
  if (!acctSettings.value.password) return [];
  return [
    (v) => !!v || t('validation.field_required'),
    (v) => v === acctSettings.value.password || t('validation.passwords_must_match'),
  ];
});

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
  if (!authStore.user?.id) return;
  try {
    const response = await api.get('/api/users/settings/' + authStore.user.id);
    const records = response?.data?.records || response?.records;
    if (records) {
      acctSettings.value = {
        ...records,
        password: null,
        confirm_pass: null,
      };
    }
  } catch (error) {
    console.error('Error fetching user settings:', error.message);
  }
};

const getConsoSettings = async () => {
  if (!authStore.is_conso_admin) return;
  try {
    const response = await api.get('/api/consortia/current');
    const consortium = response?.data?.consortium || response?.consortium;
    if (consortium) {
      consoSettings.value = {
        ccp_key: consortium.ccp_key || '',
        name: consortium.name || '',
        email: consortium.email || '',
      };
    }
  } catch (error) {
    console.error('Error fetching consortium settings:', error.message);
  }
};

async function userSubmit() {
  success.value = '';
  failure.value = '';
  try {
    const url = '/api/users/update/' + authStore.user.id;
    const response = await api.patch(url, acctSettings.value);
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      success.value = resData.msg || t('account.update_success');
      acctSettings.value.password = null;
      acctSettings.value.confirm_pass = null;
      if (authStore.user) {
        authStore.user.name = acctSettings.value.name;
        authStore.user.email = acctSettings.value.email;
        authStore.user.fiscalYr = acctSettings.value.fiscalYr;
      }
    } else {
      failure.value = resData?.msg || t('account.update_failed');
    }
  } catch (error) {
    console.error('Error updating profile configuration:', error);
    failure.value = t('account.update_exception');
  }
}

async function consoSubmit() {
  consoSuccess.value = '';
  consoFailure.value = '';
  try {
    const response = await api.patch('/api/consortia/current', {
      name: consoSettings.value.name,
      email: consoSettings.value.email,
    });
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      consoSuccess.value = resData.msg || t('account.conso_update_success');
    } else {
      consoFailure.value = resData?.msg || t('account.update_failed');
    }
  } catch (error) {
    console.error('Error updating consortium settings:', error);
    consoFailure.value = t('account.update_exception');
  }
}

const makeToken = async () => {
  try {
    const response = await api.post('/api/tokens/store');
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      acctSettings.value.access_token = resData.access_token;
      acctSettings.value.token_expires = resData.token_expires;
    } else {
      failure.value = resData?.msg || t('account.token_create_failed');
    }
  } catch (error) {
    console.error('Error creating token:', error.message);
  }
};

const destroyToken = async () => {
  try {
    const response = await api.delete('/api/tokens/destroy');
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      acctSettings.value.access_token = null;
      acctSettings.value.token_expires = null;
    } else {
      failure.value = resData?.msg || t('account.token_destroy_failed');
    }
  } catch (error) {
    console.error('Error destroying token:', error.message);
  }
};

async function copyToken() {
  if (!acctSettings.value.access_token) return;
  await navigator.clipboard.writeText(acctSettings.value.access_token);
  tokenCopied.value = true;
  setTimeout(() => {
    tokenCopied.value = false;
  }, 2000);
}

watch(
  acctSettings,
  () => {
    success.value = '';
    failure.value = '';
  },
  { deep: true }
);

watch(
  consoSettings,
  () => {
    consoSuccess.value = '';
    consoFailure.value = '';
  },
  { deep: true }
);

onMounted(() => {
  getSettings();
  getConsoSettings();
});
</script>
<template>
  <div v-if="authStore.user">
    <!-- Primary User Profile Panel -->
    <SettingsPanelShell
      v-model:valid="formValid"
      :title="$t('account.title', { name: authStore.user.name })"
      icon="mdi-account-cog-outline"
      :submit-label="$t('account.save_settings')"
      :success="success"
      :failure="failure"
      @submit="userSubmit"
    >
      <v-row class="mt-0">
        <!-- Column 1: Profile & Fiscal Year Preference -->
        <v-col cols="12" md="6" lg="4" class="pt-0">
          <SectionHeading>
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-card-account-details-outline</v-icon>
            {{ $t('account.profile_details') }}
          </SectionHeading>

          <v-text-field
            v-model="acctSettings.name"
            :label="$t('common.name')"
            :rules="[required]"
            class="mb-4"
          />

          <v-text-field
            v-model="acctSettings.email"
            :label="$t('auth.email')"
            :rules="[required, emailRule]"
            class="mb-4"
          />

          <v-select
            v-model="acctSettings.fiscalYr"
            :label="$t('fields.fiscal_year_start')"
            :items="localizedFyMonths"
            :rules="[required]"
            item-title="label"
            item-value="value"
          />
        </v-col>

        <!-- Column 2: Security & Personal Access Token -->
        <v-col cols="12" md="6" lg="4" class="pt-0">
          <SectionHeading>
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-lock-outline</v-icon>
            {{ $t('account.security_and_api') }}
          </SectionHeading>

          <v-text-field
            v-model="acctSettings.password"
            :label="$t('fields.password')"
            :type="pw_show ? 'text' : 'password'"
            autocomplete="new-password"
            :append-inner-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'"
            class="mb-4"
            @click:append-inner="pw_show = !pw_show"
          />

          <v-text-field
            v-model="acctSettings.confirm_pass"
            :label="$t('fields.confirm_password')"
            :type="pwc_show ? 'text' : 'password'"
            autocomplete="new-password"
            :append-inner-icon="pwc_show ? 'mdi-eye' : 'mdi-eye-off'"
            :rules="passwordConfirmRules"
            class="mb-4"
            @click:append-inner="pwc_show = !pwc_show"
          />

          <!-- Personal Access Token Controls -->
          <div v-if="!acctSettings.access_token">
            <v-btn color="secondary" prepend-icon="mdi-key-plus" @click="makeToken">
              {{ $t('account.create_access_token') }}
            </v-btn>
          </div>

          <div v-else>
            <v-text-field
              v-model="acctSettings.access_token"
              :label="$t('account.access_token')"
              readonly
              :type="pat_show ? 'text' : 'password'"
              :append-inner-icon="pat_show ? 'mdi-eye' : 'mdi-eye-off'"
              class="mb-2"
              @click:append-inner="pat_show = !pat_show"
            />

            <div class="d-flex align-center justify-space-between flex-wrap ga-2">
              <span class="text-body-small text-medium-emphasis">
                {{ $t('account.expires') }}:
                {{ acctSettings.token_expires ? new Date(acctSettings.token_expires).toLocaleString(locale) : $t('account.na') }}
              </span>

              <div class="d-flex ga-2">
                <v-btn
                  size="small"
                  variant="tonal"
                  color="primary"
                  :prepend-icon="tokenCopied ? 'mdi-check' : 'mdi-content-copy'"
                  @click="copyToken"
                >
                  {{ tokenCopied ? $t('api_docs.copied') : $t('common.copy') }}
                </v-btn>
                <v-btn
                  size="small"
                  color="error"
                  variant="tonal"
                  @click="destroyToken"
                >
                  {{ $t('account.destroy_token') }}
                </v-btn>
              </div>
            </div>
          </div>
        </v-col>

        <!-- Column 3: Organization & Permissions -->
        <v-col cols="12" md="12" lg="4" class="pt-0">
          <SectionHeading>
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-shield-account-outline</v-icon>
            {{ $t('account.organization_and_roles') }}
          </SectionHeading>

          <v-text-field
            :model-value="acctSettings.inst_name || $t('account.na')"
            :label="$t('fields.home_institution')"
            :clearable="false"
            readonly
            class="mb-4"
          >
            <template #append-inner>
              <LockTip :text="$t('account.home_institution_hint')" />
            </template>
          </v-text-field>

          <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis mb-2">
            {{ $t('account.user_roles') }}
          </div>

          <v-list v-if="acctSettings.roles && acctSettings.roles.length > 0">
            <template v-for="(role, idx) in acctSettings.roles" :key="idx">
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
              <v-divider v-if="idx < acctSettings.roles.length - 1" />
            </template>
          </v-list>

          <CodeBlock v-else class="text-body-medium text-medium-emphasis">
            {{ $t('account.no_roles_assigned') }}
          </CodeBlock>
        </v-col>
      </v-row>
    </SettingsPanelShell>

    <!-- Consortium Admin Settings Panel -->
    <SettingsPanelShell
      v-if="authStore.is_conso_admin"
      v-model:valid="consoFormValid"
      :title="$t('account.conso_settings_title')"
      icon="mdi-domain"
      :submit-label="$t('account.save_conso_settings')"
      :success="consoSuccess"
      :failure="consoFailure"
      class="mt-6"
      @submit="consoSubmit"
    >
      <v-row class="mt-0">
        <v-col cols="12" sm="4" class="pt-0">
          <v-text-field
            v-model="consoSettings.ccp_key"
            :label="$t('fields.consortium_key')"
            :clearable="false"
            readonly
          >
            <template #append-inner>
              <LockTip :text="$t('server.readonly_env_hint')" />
            </template>
          </v-text-field>
        </v-col>

        <v-col cols="12" sm="4" class="pt-0">
          <v-text-field
            v-model="consoSettings.name"
            :label="$t('fields.consortium_name')"
            :rules="[required]"
          />
        </v-col>

        <v-col cols="12" sm="4" class="pt-0">
          <v-text-field
            v-model="consoSettings.email"
            :label="$t('fields.consortium_email')"
            :rules="[required, emailRule]"
          />
        </v-col>
      </v-row>
    </SettingsPanelShell>
  </div>
</template>
