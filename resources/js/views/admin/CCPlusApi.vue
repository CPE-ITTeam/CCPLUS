<!-- views/admin/CCPlusApi.vue -->
<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';

const { t } = useI18n();
const authStore = useAuthStore();

const dataDialog = ref(false);
const action = ref('');
const title = ref('');
const getRecords = ref('');
const copied = ref(false);

const appUrl = import.meta.env.VITE_APP_URL || '/';
const ccpKey = computed(() => authStore.ccp_key);
const is_admin = computed(() => authStore.is_admin);
const is_serveradmin = computed(() => authStore.is_serveradmin);

const endpoints = computed(() => [
  { show: is_serveradmin.value, label: t('routes.consortia'), dataset: 'Consortia', arg: '', path: 'api/consortia/get', interactive: true },
  {
    show: true,
    label: t('routes.institutions'),
    dataset: 'Institutions',
    arg: is_admin.value ? 'admin' : 'viewer',
    path: is_admin.value ? 'api/institutions/get/admin' : 'api/institutions/get/viewer',
    interactive: true,
  },
  { show: is_admin.value, label: t('routes.institution_groups'), dataset: 'Groups', arg: '', path: 'api/groups/get', interactive: true },
  { show: true, label: t('routes.institution_types'), dataset: 'Types', arg: '', path: 'api/types/get', interactive: true },
  { show: is_serveradmin.value, label: t('routes.platforms'), dataset: 'Platforms', arg: '', path: 'api/platforms/get', interactive: true },
  { show: true, label: t('routes.connections'), dataset: 'Connections', arg: '', path: 'api/connections/get', interactive: true },
  { show: true, label: t('routes.credentials'), dataset: 'Credentials', arg: '', path: 'api/credentials/get', interactive: true },
  { show: is_admin.value, label: t('routes.credentials_audit'), dataset: 'Audit', arg: '', path: 'api/audit/get', interactive: true },
  { show: true, label: t('routes.users'), dataset: 'Users', arg: '', path: 'api/users/get', interactive: true },
  { show: true, label: t('api_docs.user_roles'), dataset: 'Roles', arg: '', path: 'api/roles/get', interactive: true },
  { show: true, label: t('api_docs.saved_reports'), dataset: 'SavedReports', arg: '', path: 'api/savedreports/get', interactive: true },
  { show: true, label: t('api_docs.data_for_saved_report'), dataset: '', arg: '', path: 'api/savedreports/execute/<-report_id->', interactive: false },
]);

const visibleEndpoints = computed(() => endpoints.value.filter((e) => e.show));
const endpointColumns = computed(() => {
  const list = visibleEndpoints.value;
  const mid = Math.ceil(list.length / 2);
  return [list.slice(0, mid), list.slice(mid)];
});

async function getJson(dataset, arg = '') {
  title.value = dataset;
  action.value = 'GET';
  copied.value = false;
  let url = '/api/' + dataset.toLowerCase() + '/get';
  url += arg === '' ? '' : '/' + arg;

  try {
    const response = await api.get(url);
    const records = response?.data?.records ?? response?.records;
    if (records !== undefined) {
      getRecords.value = JSON.stringify(records, null, 2);
    } else {
      getRecords.value = t('api_docs.no_records_or_unauthorized');
    }
    dataDialog.value = true;
  } catch (error) {
    getRecords.value = t('api_docs.retrieval_failed', { message: error.message });
    dataDialog.value = true;
  }
}

async function copyJson() {
  if (!getRecords.value) return;
  await navigator.clipboard.writeText(getRecords.value);
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2000);
}
</script>

<template>
  <div>
    <SettingsPanelShell
      :title="$t('api_docs.title')"
      icon="mdi-api"
    >
      <!-- Top Row: Endpoint Format & Required Headers -->
      <v-row class="mt-0">
        <!-- Left Column: Endpoint Format -->
        <v-col cols="12" lg="6" class="pt-0">
          <SectionHeading>
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-link-variant</v-icon>
            {{ $t('api_docs.endpoint_format') }}
          </SectionHeading>

          <p class="text-body-medium text-medium-emphasis mb-3">
            {{ $t('api_docs.intro') }}
          </p>

          <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis mb-1">
            {{ $t('api_docs.links_format_intro') }}
          </div>
          <CodeBlock class="d-flex align-center justify-space-between flex-wrap ga-2">
            <code class="text-body-medium font-weight-bold text-primary">
              {{ appUrl }}api/&lt;-dataset-&gt;/&lt;-method-&gt;/&lt;-arguments&gt;
            </code>
            <span class="text-body-small text-medium-emphasis">{{ $t('api_docs.see_examples_below') }}</span>
          </CodeBlock>
        </v-col>

        <!-- Right Column: Required Authentication Headers -->
        <v-col cols="12" lg="6" class="pt-0">
          <SectionHeading>
            <v-icon start size="22" class="me-2" aria-hidden="true">mdi-key-variant</v-icon>
            {{ $t('api_docs.required_headers') }}
          </SectionHeading>

          <p class="text-body-medium text-medium-emphasis mb-3">
            {{ $t('api_docs.headers_description') }}
          </p>

          <CodeBlock>
            <pre class="text-body-medium ma-0" style="white-space: pre-wrap;">{{ $t('api_docs.bearer_token_hint') }}
X-Tenant: {{ ccpKey }}</pre>
          </CodeBlock>
        </v-col>
      </v-row>

      <v-divider class="my-5" />

      <!-- Bottom Section: 2-Column Interactive Example Endpoints -->
      <SectionHeading>
        <v-icon start size="22" class="me-2" aria-hidden="true">mdi-code-json</v-icon>
        {{ $t('api_docs.example_links') }}
      </SectionHeading>

      <v-row class="mt-0">
        <v-col
          v-for="(colEndpoints, colIdx) in endpointColumns"
          :key="colIdx"
          cols="12"
          md="6"
          class="pt-0"
        >
          <v-list>
            <template v-for="(ep, idx) in colEndpoints" :key="ep.path">
              <v-list-item
                :link="ep.interactive"
                @click="ep.interactive ? getJson(ep.dataset, ep.arg) : undefined"
              >
                <template #prepend>
                  <v-chip
                    size="x-small"
                    :color="ep.interactive ? 'success' : 'secondary'"
                    variant="tonal"
                    label
                    class="font-weight-bold me-3"
                  >
                    GET
                  </v-chip>
                </template>

                <v-list-item-title class="d-flex flex-wrap align-center ga-2">
                  <span class="font-weight-medium">{{ ep.label }}</span>
                  <code class="text-body-small bg-grey-lighten-4 px-2 py-1 rounded text-medium-emphasis">
                    {{ appUrl }}{{ ep.path }}
                  </code>
                </v-list-item-title>

                <template v-if="ep.interactive" #append>
                  <v-chip size="small" color="primary" variant="text" append-icon="mdi-play-circle-outline">
                    {{ $t('api_docs.try_it') }}
                  </v-chip>
                </template>
              </v-list-item>

              <v-divider v-if="idx < colEndpoints.length - 1" />
            </template>
          </v-list>
        </v-col>
      </v-row>
    </SettingsPanelShell>

    <!-- JSON Response Preview Dialog -->
    <v-dialog v-model="dataDialog" max-width="800px">
      <v-card>
        <v-card-title class="pa-4 bg-info text-white d-flex justify-space-between align-center">
          <div class="d-flex align-center ga-2">
            <v-chip size="small" color="white" variant="flat" label class="font-weight-bold text-info">
              {{ action }}
            </v-chip>
            <span class="text-title-medium font-weight-bold">{{ title }}</span>
          </div>

          <div class="d-flex align-center ga-2">
            <v-btn
              size="small"
              variant="tonal"
              color="white"
              :prepend-icon="copied ? 'mdi-check' : 'mdi-content-copy'"
              @click="copyJson"
            >
              {{ copied ? $t('api_docs.copied') : $t('api_docs.copy_json') }}
            </v-btn>
            <v-btn
              icon
              variant="text"
              color="white"
              :aria-label="$t('dialogs.close_dialog_aria')"
              @click="dataDialog = false"
            >
              <v-icon size="18">mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>
        <v-card-text>
          <CodeBlock class="overflow-auto" max-height="60vh">
            <pre class="text-body-medium ma-0" style="white-space: pre-wrap;">{{ getRecords }}</pre>
          </CodeBlock>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>
