<!-- views/admin/ManualHarvest.vue -->
<script setup>
import { ref, computed, watch, onBeforeMount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useReportingDates } from '@/composables/useReportingDates.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import YmInput from '@/components/ui/YmInput.vue';

const { t } = useI18n();
const authStore = useAuthStore();
const { fromToDates } = useReportingDates();

defineOptions({ name: 'ManualHarvest', inheritAttrs: false });
const emit = defineEmits(['newHarvests', 'updatedHarvests', 'updateConso']);

const consoKey = computed(() => authStore.ccp_key);
const loading = ref(false);
const success = ref('');
const failure = ref('');
const maxYM = ref('');
const inst_name = ref('');
const allPlats = ref(false);
const allConsoPlats = ref(false);
const allInsts = ref(false);
const institutions = ref([]);
const institution_options = ref([]);
const inst_groups = ref([]);
const selectedInstitutions = ref([]);
const platforms = ref([]);
const available_platforms = ref([]);
const master_reports = ref([]);
const available_reports = ref([]);
const fields = ref([]);
const fyMo = ref([]);
const group_id = ref(null);
const dateRange = ref('');
const minYM = ref('');
const toKey = ref(0);

const reportOptions = computed(() => [
  { title: t('options.date_range.fYTD'), value: 'fYTD' },
  { title: t('options.date_range.priorFy'), value: 'priorFy' },
  { title: t('options.date_range.cYTD'), value: 'cYTD' },
  { title: t('options.date_range.priorCy'), value: 'priorCy' },
  { title: t('options.date_range.last12Months'), value: 'last12Months' },
  { title: t('options.date_range.Custom'), value: 'Custom' },
]);

const form = ref({
  inst: [],
  inst_group_id: 0,
  plat: [],
  reports: [],
  fromYM: '',
  toYM: '',
  when: 'later',
  skip_harvested: 1,
});

const initializeOptions = async () => {
  try {
    const response = await api.get('/api/harvests/options');
    const records = response?.data?.records ?? response?.records ?? {};
    institutions.value = [...(records.institutions || [])];
    institution_options.value = [...(records.institutions || [])];
    inst_groups.value = [...(records.groups || [])];
    platforms.value = [...(records.platforms || [])];
    available_platforms.value = [...(records.platforms || [])];
    master_reports.value = [...(records.master_reports || [])];
    fields.value = [...(records.fields || [])];
    fyMo.value = records.fyMo || [];

    if (institutions.value.length === 1) {
      inst_name.value = institutions.value[0].name;
      form.value.inst = [institutions.value[0].id];
      onInstChange();
    }
  } catch (error) {
    console.error('Error loading harvest options:', error.message);
  }
};

const instSelectionString = computed(() => {
  if (form.value.inst.length === institution_options.value.length) return t('harvest.all_institutions');
  const inst = institutions.value.find((ii) => ii.id === form.value.inst[0]);
  if (!inst) return '';
  let ret = inst.name;
  if (form.value.inst.length > 1) {
    ret += ` +${form.value.inst.length - 1} ${form.value.inst.length > 2 ? t('common.others') : t('common.more')}`;
  }
  return ret;
});

const platSelectionString = computed(() => {
  if (allPlats.value) return t('harvest.all_platforms');
  if (allConsoPlats.value) return t('harvest.all_conso_platforms');
  if (form.value.plat.length > 0) {
    const plat = platforms.value.find((p) => p.id === form.value.plat[0]);
    if (plat) {
      let ret = plat.name;
      if (form.value.plat.length > 1) {
        ret += ` +${form.value.plat.length - 1} ${form.value.plat.length > 2 ? t('common.others') : t('common.more')}`;
      }
      return ret;
    }
  }
  return '';
});

const selections_made = computed(() => selectedInstitutions.value.length > 0 || form.value.plat.length > 0);
const submit_enabled = computed(
  () =>
    form.value.plat.length > 0 &&
    form.value.reports.length > 0 &&
    selectedInstitutions.value.length > 0 &&
    form.value.fromYM !== '' &&
    form.value.toYM !== ''
);

function resetForm() {
  form.value = {
    inst: institutions.value.length === 1 ? [institutions.value[0].id] : [],
    inst_group_id: 0,
    plat: [],
    reports: [],
    fromYM: '',
    toYM: '',
    when: 'later',
    skip_harvested: 1,
  };
  group_id.value = null;
  dateRange.value = '';
  available_reports.value = [];
  allInsts.value = false;
  allPlats.value = false;
  allConsoPlats.value = false;
  available_platforms.value = [...platforms.value];
  selectedInstitutions.value = institutions.value.length === 1 ? [institutions.value[0].id] : [];
  success.value = '';
  failure.value = '';
}

function onGroupChange() {
  failure.value = '';
  form.value.inst_group_id = group_id.value || 0;
  form.value.inst = [];
  selectedInstitutions.value = [];
  if (!group_id.value) {
    available_platforms.value = [...platforms.value];
  } else {
    const group = inst_groups.value.find((g) => g.id === group_id.value);
    if (group?.institutions) {
      group.institutions.forEach((inst) => selectedInstitutions.value.push(inst.id));
    }
    updatePlatforms();
  }
}

function onInstChange() {
  failure.value = '';
  group_id.value = null;
  form.value.inst_group_id = 0;
  allInsts.value = form.value.inst.length === institutions.value.length && institutions.value.length > 0;
  if (allInsts.value) {
    form.value.inst = institutions.value.map((ii) => ii.id);
  }
  selectedInstitutions.value = [...form.value.inst];
  if (form.value.inst.length === 0) {
    available_platforms.value = [...platforms.value];
  } else {
    updatePlatforms();
  }
}

function updatePlatforms() {
  if (selectedInstitutions.value.length === 0) {
    available_platforms.value = [...platforms.value];
  } else {
    available_platforms.value = platforms.value.filter((plat) =>
      plat.institutions.some((id) => id === 1 || selectedInstitutions.value.includes(id))
    );
  }
}

function onPlatChange() {
  failure.value = '';
  const conso_list = platforms.value.filter((p) => p.institutions.some((id) => id === 1)).map((p2) => p2.id);
  allPlats.value = platforms.value.length === form.value.plat.length && form.value.plat.length > 0;
  allConsoPlats.value = !allPlats.value && conso_list.length === form.value.plat.length && form.value.plat.length > 0;

  const plat_list = allPlats.value
    ? platforms.value.map((p) => p.id)
    : allConsoPlats.value
      ? [...conso_list]
      : [...form.value.plat];

  form.value.reports = [];
  available_reports.value = [];
  if (plat_list.length === 0) return;

  plat_list.forEach((pid) => {
    const cur_plat = platforms.value.find((p) => p.id === pid);
    if (!cur_plat?.reports) return;
    master_reports.value.forEach((rpt) => {
      if (available_reports.value.some((r) => r.name === rpt.name) || !cur_plat.reports[rpt.name]) return;
      const rptMeta = cur_plat.reports[rpt.name];
      const add =
        rptMeta.conso ||
        (Array.isArray(rptMeta.insts) && rptMeta.insts.some((inst) => selectedInstitutions.value.includes(inst)));
      if (add) available_reports.value.push(rpt);
    });
  });
}

async function formSubmit() {
  if (form.value.reports.length === 0) {
    failure.value = t('harvest.no_reports_selected');
    return;
  }
  if (!form.value.fromYM || !form.value.toYM) {
    failure.value = t('harvest.date_range_required');
    return;
  }

  loading.value = true;
  success.value = '';
  failure.value = '';

  try {
    const response = await api.post('/api/harvests/store', { ...form.value });
    const resData = response?.data !== undefined ? response.data : response;
    if (resData?.result) {
      success.value = resData.msg;
      if (resData.new_harvests?.length > 0) {
        emit('newHarvests', { harvests: resData.new_harvests, bounds: resData.bounds });
      }
      if (resData.upd_harvests?.length > 0) {
        emit('updatedHarvests', resData.upd_harvests);
      }
    } else {
      failure.value = resData?.msg || t('harvest.store_failed');
    }
  } catch (error) {
    console.error('Error queuing harvests:', error.message);
    failure.value = t('harvest.store_failed');
  } finally {
    loading.value = false;
  }
}

function updateAllPlats(scope) {
  if (scope === 'Clear') {
    allPlats.value = false;
    allConsoPlats.value = false;
    form.value.plat = [];
    available_platforms.value = [...platforms.value];
  } else if (scope === 'ALL') {
    allPlats.value = true;
    allConsoPlats.value = false;
    form.value.plat = platforms.value.map((p) => p.id);
    available_platforms.value = [...platforms.value];
  } else {
    allPlats.value = false;
    allConsoPlats.value = true;
    form.value.plat = platforms.value.filter((plat) => plat.institutions.some((id) => id === 1)).map((p2) => p2.id);
    available_platforms.value = platforms.value.filter((p) => form.value.plat.includes(p.id));
  }
  onPlatChange();
}

function updateAllInsts() {
  group_id.value = null;
  form.value.inst_group_id = 0;
  if (allInsts.value) {
    allInsts.value = false;
    form.value.inst = [];
  } else {
    allInsts.value = true;
    form.value.inst = institutions.value.map((ii) => ii.id);
  }
  selectedInstitutions.value = [...form.value.inst];
  updatePlatforms();
}

function changeHarvestDates() {
  if (dateRange.value === 'Custom') return;
  const dates = fromToDates(dateRange.value);
  form.value.fromYM = dates.from;
  form.value.toYM = dates.to;
}

watch(
  () => form.value.fromYM,
  (yearmon) => {
    toKey.value++;
    minYM.value = yearmon;
  }
);

watch(
  () => authStore.ccp_key,
  () => {
    resetForm();
    initializeOptions();
  }
);

onBeforeMount(() => {
  initializeOptions();
});

onMounted(() => {
  const dt = new Date();
  maxYM.value = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`;
});
</script>
<template>
  <SettingsPanelShell
    v-if="consoKey !== ''"
    :title="$t('routes.manual_harvest')"
    icon="mdi-cloud-download-outline"
    :loading="loading"
    :success="success"
    :failure="failure"
    @submit="formSubmit"
  >
    <v-alert
      v-if="institution_options.length === 0"
      type="warning"
      variant="tonal"
      :text="$t('harvest.no_institutions')"
    />
    <v-alert
      v-else-if="available_platforms.length === 0"
      type="warning"
      variant="tonal"
      :text="$t('harvest.no_platforms')"
    />

    <template v-else>
      <SectionHeading>
        <v-icon start size="22" class="me-2" aria-hidden="true">mdi-target</v-icon>
        {{ $t('harvest.scope_and_platforms') }}
      </SectionHeading>

      <v-row class="mt-0">
        <template v-if="institution_options.length > 1">
          <v-col v-if="form.inst_group_id === 0" cols="12" md="4" class="pt-0">
            <v-autocomplete
              v-model="form.inst"
              :items="institution_options"
              :label="$t('routes.institutions')"
              item-value="id"
              item-title="name"
              multiple
              @update:model-value="onInstChange"
            >
              <template #prepend-item>
                <v-list-item @click="updateAllInsts">
                  <span>{{ allInsts ? $t('harvest.clear_selections') : $t('harvest.all_institutions') }}</span>
                </v-list-item>
                <v-divider class="mt-1" />
              </template>
              <template #selection="{ index }">
                <span v-if="index === 0">{{ instSelectionString }}</span>
              </template>
            </v-autocomplete>
          </v-col>

          <v-col v-if="form.inst.length === 0" cols="12" md="4" class="pt-0">
            <v-autocomplete
              v-model="group_id"
              :items="inst_groups"
              :label="$t('routes.institution_groups')"
              item-title="name"
              item-value="id"
              @update:model-value="onGroupChange"
            />
          </v-col>
        </template>

        <v-col v-else cols="12" md="4" class="pt-0">
          <v-text-field
            :model-value="inst_name"
            :label="$t('fields.institution')"
            readonly
          />
        </v-col>

        <v-col v-if="available_platforms.length > 0" cols="12" md="4" class="pt-0">
          <v-autocomplete
            v-model="form.plat"
            :items="available_platforms"
            :label="$t('routes.platforms')"
            item-value="id"
            item-title="name"
            multiple
            @update:model-value="onPlatChange"
          >
            <template #prepend-item>
              <v-list-item v-if="allConsoPlats || allPlats" @click="updateAllPlats('Clear')">
                <span>{{ $t('harvest.clear_selections') }}</span>
              </v-list-item>
              <template v-else>
                <v-list-item @click="updateAllPlats('ALL')">
                  <span>{{ $t('harvest.all_platforms') }}</span>
                </v-list-item>
                <v-list-item @click="updateAllPlats('Conso')">
                  <span>{{ $t('harvest.all_conso_platforms') }}</span>
                </v-list-item>
              </template>
              <v-divider class="mt-1" />
            </template>
            <template #selection="{ index }">
              <span v-if="index === 0">{{ platSelectionString }}</span>
            </template>
          </v-autocomplete>
        </v-col>
      </v-row>

      <template v-if="available_reports.length > 0">
        <v-divider class="my-5" />

        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-calendar-range</v-icon>
          {{ $t('harvest.reports_and_period') }}
        </SectionHeading>

        <v-row class="mt-0">
          <v-col cols="12" md="4" class="pt-0">
            <v-select
              v-model="form.reports"
              :items="available_reports"
              :label="$t('harvest.reports_to_harvest')"
              item-title="legend"
              item-value="name"
              multiple
              chips
              closable-chips
            />
          </v-col>

          <template v-if="form.reports.length > 0">
            <v-col cols="12" sm="4" md="3" class="pt-0">
              <v-select
                v-model="dateRange"
                :label="$t('fields.date_range')"
                :items="reportOptions"
                item-title="title"
                item-value="value"
                @update:model-value="changeHarvestDates"
              />
            </v-col>

            <YmInput v-model="form.fromYM" :label="$t('fields.from')" :cols="2" class="pt-0" />
            <YmInput v-model="form.toYM" :label="$t('fields.to')" :minYM="minYM" :cols="2" :key="toKey" class="pt-0" />
          </template>
        </v-row>
      </template>

      <template v-if="form.reports.length > 0">
        <v-divider class="my-5" />

        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-cog-play-outline</v-icon>
          {{ $t('harvest.queue_options') }}
        </SectionHeading>

        <v-row class="mt-0 align-center">
          <v-col cols="12" md="6" class="pt-0">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis mb-1">
              {{ $t('harvest.queue_timing') }}
            </div>
            <v-radio-group v-model="form.when" inline hide-details>
              <v-radio :label="$t('harvest.overnight')" value="later" />
              <v-radio :label="$t('harvest.now')" value="now" />
            </v-radio-group>
          </v-col>

          <v-col cols="12" md="6" class="pt-0">
            <v-checkbox
              v-model="form.skip_harvested"
              :label="$t('harvest.skip_previously_harvested')"
              :true-value="1"
              :false-value="0"
              hide-details
            />
          </v-col>
        </v-row>
      </template>
    </template>

    <template v-if="submit_enabled || selections_made" #actions>
      <v-btn
        v-if="submit_enabled"
        type="submit"
        color="primary"
        class="px-6"
        :loading="loading"
      >
        {{ $t('common.submit') }}
      </v-btn>

      <v-btn
        v-if="selections_made"
        variant="tonal"
        color="secondary"
        @click="resetForm"
      >
        {{ $t('harvest.reset_selections') }}
      </v-btn>
    </template>
  </SettingsPanelShell>
</template>
