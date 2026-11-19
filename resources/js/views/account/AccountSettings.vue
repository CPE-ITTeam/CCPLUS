<!-- views/account/AccountSettings.vue -->
<script setup>
// 1. Framework & Libraries
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useTheme } from 'vuetify';
import { setAppLocale } from '@/plugins/i18n.js';

// 2. Services, Stores & Config
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { normalizeFiscalStartMonth } from '@/composables/useReportingDates.js';
import { tableSetup } from '@/config/dataTableConfig.js';

// 3. Components
import UserSettingsPanel from '@/components/panels/UserSettingsPanel.vue';
import HomeInstPanel from '@/components/panels/HomeInstPanel.vue';
import ApiSettingsPanel from '@/components/panels/ApiSettingsPanel.vue';

const route = useRoute();
const { t, locale, availableLocales } = useI18n();
const authStore = useAuthStore();

const vuetifyTheme = useTheme();
const applyTheme = (themeName) => {
  const valid = ['light', 'emerald', 'indigo', 'plum', 'slate'].includes(themeName) ? themeName : 'light';
  if (typeof vuetifyTheme.change === 'function') {
    vuetifyTheme.change(valid);
  } else {
    vuetifyTheme.global.name.value = valid;
  }
};

const activeTab = computed(() => route.meta?.tab || 'profile');

const success = ref('');
const failure = ref('');
const loadingUser = ref(false);
const inheritedFy = ref(null);
const inheritedLang = ref('en');
const acctSettings = ref({
  name: '',
  email: '',
  fiscalYr: null,
  language: null,
  password: null,
  confirm_pass: null,
  access_token: null,
  token_expires: null,
  inst_name: '',
  roles: [],
});

const homeInstSuccess = ref('');
const homeInstFailure = ref('');
const loadingHomeInst = ref(false);
const homeInstSettings = ref({});
const optionsMap = ref({
  fiscalYr: [],
  language: [],
  type: [],
  groups: [],
});

const canEditHomeInst = computed(() => {
  if (!authStore.is_admin) return false;
  if (authStore.is_conso_admin) return true;
  const instId = authStore.user_inst_id;
  if (!instId) return false;
  return (
    authStore.adminInsts.includes(instId) ||
    authStore.roles.some((r) => r.name === 'Admin' && r.inst_id === instId)
  );
});

const activeHomeDataset = computed(() =>
  authStore.is_conso_admin ? tableSetup.consortia : tableSetup.institutions
);

const homePanelTitle = computed(() =>
  authStore.is_conso_admin
    ? t('account.conso_settings_title')
    : t('account.home_institution_title')
);

const homePanelSubmitLabel = computed(() =>
  authStore.is_conso_admin
    ? t('account.save_conso_settings')
    : t('account.save_institution_settings')
);

const localizedFyMonths = computed(() => {
  const formatter = new Intl.DateTimeFormat(locale.value || 'en', { month: 'short' });
  return Array.from({ length: 12 }, (_, idx) => {
    const formatted = formatter.format(new Date(2024, idx, 1));
    return {
      label: formatted.charAt(0).toUpperCase() + formatted.slice(1).replace(/\.$/, ''),
      value: idx + 1,
    };
  });
});

const languageOptions = computed(() =>
  (availableLocales || ['en', 'de', 'es', 'fi', 'fr', 'hu']).map((code) => ({
    id: code,
    name: code.toUpperCase(),
  }))
);

const buildOptionsMap = () => {
  optionsMap.value.fiscalYr = localizedFyMonths.value.map((m) => ({
    id: m.value,
    name: m.label,
  }));
  optionsMap.value.language = languageOptions.value;
};

const getSettings = async () => {
  if (!authStore.user?.id) return;
  try {
    const response = await api.get('/api/users/settings/' + authStore.user.id);
    const resData = response?.data !== undefined ? response.data : response;
    const records = resData?.records;
    if (records) {
      inheritedFy.value = normalizeFiscalStartMonth(resData?.defaultFiscalYr);
      inheritedLang.value = resData?.defaultLanguage || 'en';
      acctSettings.value = {
        ...records,
        fiscalYr: normalizeFiscalStartMonth(records.fiscalYr),
        language: records.language || null,
        password: null,
        confirm_pass: null,
      };
      if (!authStore.is_conso_admin && records.institution) {
        homeInstSettings.value = { ...records.institution };
      }
    }
  } catch (error) {
    console.error('Error fetching user settings:', error.message);
  }
};

const getHomeInstSettings = async () => {
  buildOptionsMap();

  if (authStore.is_conso_admin) {
    try {
      const response = await api.get('/api/consortia/current');
      const resData = response?.data !== undefined ? response.data : response;
      const consortium = resData?.consortium;
      if (consortium) {
        homeInstSettings.value = {
          status: consortium.status || 'Active',
          ccp_key: consortium.ccp_key || '',
          name: consortium.name || '',
          admin_name: consortium.admin_name || '',
          email: consortium.email || '',
          fiscalYr: consortium.fiscalYr || resData?.defaultFiscalYr || 'January',
          language: consortium.language || resData?.defaultLanguage || 'en',
          theme: consortium.theme || 'light',
        };
        applyTheme(homeInstSettings.value.theme);
      }
    } catch (error) {
      console.error('Error fetching consortium settings:', error.message);
    }
    return;
  }

  if (!homeInstSettings.value?.id && authStore.user_inst_id) {
    try {
      const roleParam = authStore.is_admin ? 'Admin' : 'User';
      const response = await api.get(`/api/institutions/get/${roleParam}`);
      const resData = response?.data !== undefined ? response.data : response;
      const list = resData?.institutions || resData?.records || [];
      const found = list.find((inst) => inst.id === authStore.user_inst_id);
      if (found) {
        homeInstSettings.value = { ...found };
      }
      if (resData?.types) optionsMap.value.type = resData.types;
      if (resData?.groups) optionsMap.value.groups = resData.groups;
    } catch (error) {
      console.error('Error fetching home institution settings:', error.message);
    }
  }
};

async function userSubmit() {
  success.value = '';
  failure.value = '';
  loadingUser.value = true;
  try {
    const url = '/api/users/update/' + authStore.user.id;
    const response = await api.patch(url, acctSettings.value);
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      await getSettings();
      if (authStore.user) {
        authStore.user.name = acctSettings.value.name;
        authStore.user.email = acctSettings.value.email;
        authStore.user.fiscalYr = acctSettings.value.fiscalYr;
      }
      setAppLocale(acctSettings.value.language || inheritedLang.value || 'en');
      await nextTick();
      success.value = resData.msg || t('account.update_success');
    } else {
      failure.value = resData?.msg || t('account.update_failed');
    }
  } catch (error) {
    console.error('Error updating profile configuration:', error);
    failure.value = t('account.update_exception');
  } finally {
    loadingUser.value = false;
  }
}

async function homeInstSubmit() {
  if (!canEditHomeInst.value) return;
  homeInstSuccess.value = '';
  homeInstFailure.value = '';
  loadingHomeInst.value = true;
  try {
    if (authStore.is_conso_admin) {
      const response = await api.patch('/api/consortia/current', {
        name: homeInstSettings.value.name,
        admin_name: homeInstSettings.value.admin_name,
        email: homeInstSettings.value.email,
        fiscalYr: homeInstSettings.value.fiscalYr,
        language: homeInstSettings.value.language,
        theme: homeInstSettings.value.theme || 'light',
      });
      const resData = response?.data !== undefined ? response.data : response;
      if (resData?.result) {
        const savedTheme = homeInstSettings.value.theme || 'light';
        applyTheme(savedTheme);
        authStore.setTheme(savedTheme);
        if (!acctSettings.value.language && homeInstSettings.value.language) {
          setAppLocale(homeInstSettings.value.language);
        }
        await nextTick();
        homeInstSuccess.value = resData.msg || t('account.conso_update_success');
      } else {
        homeInstFailure.value = resData?.msg || t('account.update_failed');
      }
    } else {
      const instId = homeInstSettings.value.id || authStore.user_inst_id;
      const response = await api.patch(`/api/institutions/update/${instId}`, {
        name: homeInstSettings.value.name,
        local_id: homeInstSettings.value.local_id,
        fte: homeInstSettings.value.fte,
        notes: homeInstSettings.value.notes,
      });
      const resData = response?.data !== undefined ? response.data : response;
      if (resData?.result) {
        acctSettings.value.inst_name = homeInstSettings.value.name;
        await nextTick();
        homeInstSuccess.value = resData.msg || t('account.inst_update_success');
      } else {
        homeInstFailure.value = resData?.msg || t('account.update_failed');
      }
    }
  } catch (error) {
    console.error('Error updating home institution settings:', error);
    homeInstFailure.value = t('account.update_exception');
  } finally {
    loadingHomeInst.value = false;
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

watch(
  acctSettings,
  () => {
    success.value = '';
    failure.value = '';
  },
  { deep: true }
);

watch(
  homeInstSettings,
  () => {
    homeInstSuccess.value = '';
    homeInstFailure.value = '';
  },
  { deep: true }
);

const loadTabData = async (tab) => {
  if (tab === 'profile') {
    await getSettings();
  } else if (tab === 'institution') {
    await getSettings();
    await getHomeInstSettings();
  } else if (tab === 'api') {
    await getSettings();
  }
};

watch(
  () => homeInstSettings.value.theme,
  (newTheme) => {
    if (authStore.is_conso_admin && newTheme) {
      applyTheme(newTheme);
    }
  }
);

watch(activeTab, (newTab) => {
  loadTabData(newTab);
});

onMounted(() => {
  loadTabData(activeTab.value);
});
</script>

<template>
  <div v-if="authStore?.user">
    <UserSettingsPanel v-if="activeTab === 'profile'" v-model="acctSettings"
      :title="$t('account.title', { name: authStore.user.name })" :fy-options="localizedFyMonths"
      :language-options="languageOptions" :default-fy="inheritedFy" :default-language="inheritedLang"
      :is-admin="authStore.is_admin" :loading="loadingUser" :success="success"
      :failure="failure" @submit="userSubmit" @make-token="makeToken" @destroy-token="destroyToken" />

    <HomeInstPanel v-else-if="activeTab === 'institution'" v-model="homeInstSettings" :dataset="activeHomeDataset"
      :title="homePanelTitle" :icon="authStore.is_conso_admin ? 'mdi-lan' : 'mdi-domain'"
      :submit-label="homePanelSubmitLabel" :options-map="optionsMap" :is-server-admin="authStore.is_serveradmin"
      :is-conso-admin="authStore.is_conso_admin" :can-edit-org="canEditHomeInst" :loading="loadingHomeInst"
      :success="homeInstSuccess" :failure="homeInstFailure" @submit="homeInstSubmit" />

    <ApiSettingsPanel v-else-if="activeTab === 'api'" :access-token="acctSettings.access_token"
      :token-expires="acctSettings.token_expires" :failure="failure" @make-token="makeToken"
      @destroy-token="destroyToken" />
  </div>
</template>
