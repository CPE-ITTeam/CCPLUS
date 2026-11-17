<!-- resources/js/components/panels/ReportingPanel.vue -->
<script setup>
import { ref, reactive, watch, computed, onBeforeMount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useReportingDates } from '@/composables/useReportingDates.js';
import api from '@/services/api.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import SaveReport from '@/components/dialogs/SaveReport.vue';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import FlexCol from '@/components/ui/FlexCol.vue';
import YmInput from '@/components/ui/YmInput.vue';
import { exportDatasetToExcel } from '@/utils/excelJsExporter.js';

defineOptions({ name: 'ReportingPanel', inheritAttrs: false });

const { t } = useI18n();
const authStore = useAuthStore();
const { fromToDates } = useReportingDates();

const emit = defineEmits(['updateConso']);

const consoKey = computed(() => authStore.ccp_key);
const masterReports = ref([]);
const reportViews = ref([]);
const fyMo = ref(null);
const toKey = ref(0);
const dtKey = ref(0);
const reportFormat = ref('Compact');
const zeroRecs = ref('Active');
const RPTonly = ref('Inactive');
const previewTitle = ref('');
const loading = ref(false);
const exporting = ref(false);
const hasPreview = ref(false);
const success = ref('');
const failure = ref('');
const saveDialog = ref(false);
const savedReports = ref([]);
const savedTitle = ref('');
const report_items = ref([]);
const reportColumns = ref([]);
const itemsPerPageOptions = [10, 20, 50, 100];
const minYM = ref('');
const savedReport = ref(null);
const selectedView = ref(null);
const masterReport = ref({});
const optionsKey = ref(0);
const dateRange = ref('');
const isApplyingPreset = ref(false);
const reportDates = reactive({ fromYM: '', toYM: '' });

const reportOptions = computed(() => [
  { title: t('options.date_range.fYTD'), value: 'fYTD' },
  { title: t('options.date_range.priorFy'), value: 'priorFy' },
  { title: t('options.date_range.cYTD'), value: 'cYTD' },
  { title: t('options.date_range.priorCy'), value: 'priorCy' },
  { title: t('options.date_range.last12Months'), value: 'last12Months' },
  { title: t('options.date_range.Custom'), value: 'Custom' },
]);

const optionItems = reactive({
  institution: [],
  provider: [],
  institutiontype: [],
  institutiongroup: [],
  Dbase: [],
  datatype: [],
  sectiontype: [],
  accesstype: [],
  accessmethod: [],
});
const selectedFields = reactive({
  provider: [],
  institution: [],
  institutiontype: [],
  institutiongroup: [],
  Dbase: [],
  datatype: [],
  sectiontype: [],
  accesstype: [],
  accessmethod: [],
});
const selectedMetrics = reactive({ usage: [], search: [], turnaway: [] });
const dataFields = ref(['datatype', 'sectiontype', 'accesstype', 'accessmethod']);
const enabledInfoFields = ref([]);

const initializeOptions = async () => {
  try {
    const { data } = await api.get('/api/reports/options');
    optionItems.institution = [...(data.records.institutions || [])];
    optionItems.institutiontype = [...(data.records.institution_types || [])];
    optionItems.institutiongroup = [...(data.records.groups || [])];
    optionItems.provider = [...(data.records.platforms || [])];
    optionItems.Dbase = [...(data.records.databases || [])];
    optionItems.datatype = [...(data.records.data_types || [])];
    optionItems.sectiontype = [...(data.records.section_types || [])];
    optionItems.accesstype = [...(data.records.access_types || [])];
    optionItems.accessmethod = [...(data.records.access_methods || [])];
    savedReports.value = [...(data.records.saved_reports || [])];
    masterReports.value = [...(data.records.master_reports || [])];
    reportViews.value = [...(data.records.report_views || [])];
    fyMo.value = data.records.fyMo ?? null;
  } catch (error) {
    console.error('Error loading options:', error.message);
  }
};

const homeInst = computed(() => {
  return optionItems.institution.length === 1 ? optionItems.institution[0].name : '';
});

const masterReportId = computed(() => {
  return typeof masterReport.value.id !== 'undefined' ? masterReport.value.id : -1;
});

const previewButtonEnabled = computed(() => {
  return masterReportId.value > 0 && reportDates.fromYM !== '' && reportDates.toYM !== '';
});

const metricItems = computed(() => {
  if (masterReportId.value <= 0) {
    return { usage: [], search: [], turnaway: [] };
  }
  return {
    usage: masterReport.value.report_fields.filter((fld) => fld.metric_type === 'usage'),
    search: masterReport.value.report_fields.filter((fld) => fld.metric_type === 'search'),
    turnaway: masterReport.value.report_fields.filter((fld) => fld.metric_type === 'turnaway'),
  };
});

const infoFields = computed(() => {
  if (masterReportId.value <= 0) return [];
  const _fields = [];
  masterReport.value.report_fields.forEach((fld) => {
    if (fld.qry_as === 'provider' || fld.qry_as === 'institution') {
      _fields.push({ id: fld.id, qry_as: fld.qry_as, legend: fld.legend, is_metric: fld.is_metric });
    } else if (fld.is_metric) {
      if (!metricItems.value[fld.metric_type].map((f) => f.qry_as).includes(fld.qry_as)) {
        _fields.push({ id: fld.id, qry_as: fld.qry_as, legend: fld.legend, is_metric: fld.is_metric });
      }
    } else if (!Object.keys(optionItems).includes(fld.qry_as)) {
      _fields.push({ id: fld.id, qry_as: fld.qry_as, legend: fld.legend, is_metric: fld.is_metric });
    }
  });
  return _fields;
});

const all_fields = computed(() => {
  const returnFields = {};
  infoFields.value.forEach((fld) => {
    returnFields[fld.qry_as] = { ...fld };
    returnFields[fld.qry_as].active = enabledInfoFields.value.includes(fld.id);
    if (fld.qry_as === 'institution' || fld.qry_as === 'provider') {
      returnFields[fld.qry_as].limit = [];
      if (fld.qry_as === 'institution' && homeInst.value !== '') {
        returnFields[fld.qry_as].limit = [optionItems.institution[0].id];
      } else if (selectedFields[fld.qry_as].length > 0) {
        returnFields[fld.qry_as].limit = [...selectedFields[fld.qry_as]];
      }
    }
  });
  masterReport.value.report_fields.forEach((fld) => {
    if (fld.qry_as !== 'institution' && fld.qry_as !== 'provider') {
      if (fld.is_metric) {
        if (
          selectedMetrics.usage.includes(fld.id) ||
          selectedMetrics.search.includes(fld.id) ||
          selectedMetrics.turnaway.includes(fld.id)
        ) {
          returnFields[fld.qry_as] = {
            id: fld.id,
            legend: fld.legend,
            is_metric: fld.is_metric,
            active: true,
            qry_as: fld.qry_as,
            limit: [],
          };
        }
      } else if (typeof selectedFields[fld.qry_as] !== 'undefined') {
        const _active =
          selectedFields[fld.qry_as].length > 0 || (fld.qry_as === 'Dbase' && masterReport.value.name === 'DR');
        const _limit = selectedFields[fld.qry_as].length > 0 ? selectedFields[fld.qry_as] : [];
        returnFields[fld.qry_as] = {
          id: fld.id,
          legend: fld.legend,
          is_metric: fld.is_metric,
          active: _active,
          qry_as: fld.qry_as,
          limit: _limit,
        };
      }
    }
  });
  return returnFields;
});

const filteredHeaders = computed(() => {
  if (RPTonly.value === 'Active') {
    return reportFormat.value === 'Compact'
      ? reportColumns.value.filter((h) => h.active && (h.key.substr(0, 3) === 'RP_' || h.is_metric === 0))
      : reportColumns.value.filter((h) => h.active && (h.key === 'Reporting_Period_Total' || h.is_metric === 0));
  }
  return reportColumns.value.filter((h) => h.active);
});

const showDatabases = computed(() =>
  masterReportId.value <= 0 ? false : masterReport.value.report_fields.some((fld) => fld.qry_as === 'Dbase')
);
const showDataTypes = computed(() =>
  masterReportId.value <= 0 ? false : masterReport.value.report_fields.some((fld) => fld.qry_as === 'datatype')
);
const showSectionTypes = computed(() =>
  masterReportId.value <= 0 ? false : masterReport.value.report_fields.some((fld) => fld.qry_as === 'sectiontype')
);
const showAccessTypes = computed(() =>
  masterReportId.value <= 0 ? false : masterReport.value.report_fields.some((fld) => fld.qry_as === 'accesstype')
);
const showAccessMethods = computed(() =>
  masterReportId.value <= 0 ? false : masterReport.value.report_fields.some((fld) => fld.qry_as === 'accessmethod')
);

function selectMaster() {
  Object.keys(selectedMetrics).forEach((type) => {
    selectedMetrics[type] = metricItems.value[type].map((m) => m.id);
  });
  dataFields.value.forEach((field) => {
    selectedFields[field] = optionItems[field].map((f) => f.id);
  });
  enabledInfoFields.value = infoFields.value.map((f) => f.id);
  optionsKey.value++;
}
function loadPreset(loadType) {
  Object.keys(selectedMetrics).forEach((type) => {
    selectedMetrics[type] = [];
  });
  const theReport =
    loadType === 'view'
      ? reportViews.value.find((rv) => rv.id === selectedView.value)
      : savedReports.value.find((sr) => sr.id === savedReport.value);
  if (typeof theReport === 'undefined') return;
  const reportFieldIds = theReport.report_fields.map((fld) => fld.id);

  if (loadType === 'saved') {
    isApplyingPreset.value = true;
    savedTitle.value = theReport.title;
    dateRange.value = theReport.date_range;
    minYM.value = theReport.ym_from;
    reportDates.fromYM = theReport.ym_from;
    reportDates.toYM = theReport.ym_to;
    toKey.value++;
    setTimeout(() => {
      isApplyingPreset.value = false;
    }, 0);

    reportFormat.value = theReport.format;
    RPTonly.value = theReport.rpt_only === 1 ? 'Active' : 'Inactive';
    zeroRecs.value = theReport.exclude_zeros === 1 ? 'Active' : 'Inactive';
    const theMaster = masterReports.value.find((mr) => mr.id === theReport.master_id);
    if (typeof theMaster === 'undefined') {
      failure.value = `Record is corrupt - master report ID: ${theReport.master_id} not found!`;
      return;
    }
    masterReport.value = { ...theMaster };
    if (theReport.master_id !== theReport.report_id) {
      const theView = reportViews.value.find((rv) => rv.id === theReport.report_id);
      if (typeof theView === 'undefined') {
        failure.value = `Record is corrupt - Report View ID: ${theReport.report_id} not found!`;
        return;
      }
      selectedView.value = { ...theView };
    }

    enabledInfoFields.value = [];
    infoFields.value.forEach((ifld) => {
      if (theReport.report_fields.some((fld) => fld.id === ifld.id)) {
        enabledInfoFields.value.push(ifld.id);
      }
    });
  }
  Object.keys(selectedMetrics).forEach((type) => {
    selectedMetrics[type] = metricItems.value[type].filter((m) => reportFieldIds.includes(m.id)).map((m) => m.id);
  });
  theReport.report_fields
    .filter((rf) => rf.is_metric === 0)
    .forEach((fld) => {
      if (typeof selectedFields[fld.qry_as] !== 'undefined') {
        selectedFields[fld.qry_as] = [...fld.limit];
      }
    });
  optionsKey.value++;
}

async function saveConfig(data) {
  if (data.title === '' && data.id === 0) {
    failure.value = t('reports.name_required');
    return;
  }
  const args = {
    title: data.title,
    save_id: data.id,
    date_range: dateRange.value,
    from: reportDates.fromYM,
    to: reportDates.toYM,
    format: reportFormat.value,
    fields: all_fields.value,
    rpt_only: RPTonly.value === 'Active' ? 1 : 0,
    zeros: zeroRecs.value === 'Active' ? 1 : 0,
    report_id: selectedView.value === null ? masterReportId.value : selectedView.value.id,
  };
  try {
    const { data: response } = await api.post('/api/savedreports/store', args);
    if (response.result) {
      if (data.id > 0) {
        savedReports.value.splice(
          savedReports.value.findIndex((rpt) => rpt.id === data.id),
          1,
          response.record
        );
      } else {
        savedReports.value.push(response.record);
      }
      success.value = response.msg;
    } else {
      failure.value = response.msg;
    }
  } catch (error) {
    console.error('Error saving report configuration:', error);
  }
  saveDialog.value = false;
}

async function getReportData(runType) {
  success.value = '';
  failure.value = '';
  const args = {
    from: reportDates.fromYM,
    to: reportDates.toYM,
    report_id: masterReportId.value,
    format: reportFormat.value,
    fields: all_fields.value,
    runtype: runType,
    rpt_only: RPTonly.value === 'Active' ? 1 : 0,
    zeros: zeroRecs.value === 'Active' ? 1 : 0,
  };
  if (runType !== 'export') {
    loading.value = true;
    const { data: resp } = await api.post('/api/reports/updateColumns', args);
    if (resp.result) {
      reportColumns.value = [...resp.columns];
    }
  } else {
    exporting.value = true;
  }

  previewTitle.value = savedTitle.value !== '' ? savedTitle.value : `${consoKey.value} : ${masterReport.value.name}`;
  previewTitle.value += ` - From: ${reportDates.fromYM} To: ${reportDates.toYM}`;

  if (runType === 'preview') {
    args.preview = 100;
    try {
      const { data: response } = await api.post('/api/reports/usageData', args);
      if (response.result) {
        report_items.value = [...response.usage];
        if (typeof response.db_options !== 'undefined') {
          optionItems.Dbase = [...response.db_options];
        }
        hasPreview.value = true;
        dtKey.value++;
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.error('Error getting preview data:', error);
    } finally {
      loading.value = false;
    }
  } else if (runType === 'export') {
    try {
      const { data: response } = await api.post('/api/reports/usageData', args);
      if (response.result) {
        await exportDatasetToExcel(response.headers, response.usage, response.filename, 'CCPlus_Usage');
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.error('Error exporting data:', error);
    } finally {
      exporting.value = false;
    }
  }
}

function changeReportDates() {
  if (!dateRange.value || dateRange.value === 'Custom') return;
  isApplyingPreset.value = true;
  const dates = fromToDates(dateRange.value, fyMo.value);
  minYM.value = dates.from;
  reportDates.fromYM = dates.from;
  reportDates.toYM = dates.to;
  toKey.value++;
  setTimeout(() => {
    isApplyingPreset.value = false;
  }, 0);
}

watch(
  () => reportDates.fromYM,
  (yearmon) => {
    toKey.value++;
    minYM.value = yearmon;
    if (reportDates.toYM && yearmon && reportDates.toYM < yearmon) {
      reportDates.toYM = yearmon;
    }
    if (!isApplyingPreset.value && yearmon && dateRange.value && dateRange.value !== 'Custom') {
      dateRange.value = 'Custom';
    }
  }
);

watch(
  () => reportDates.toYM,
  (yearmon) => {
    if (!isApplyingPreset.value && yearmon && dateRange.value && dateRange.value !== 'Custom') {
      dateRange.value = 'Custom';
    }
  }
);

watch(selectedFields.institutiontype, () => {
  selectedFields.institutiongroup = [];
  selectedFields.institution = [];
});

watch(selectedFields.institutiongroup, () => {
  selectedFields.institution = [];
});

watch(
  () => authStore.ccp_key,
  () => {
    initializeOptions();
  }
);

onBeforeMount(() => {
  initializeOptions();
});
</script>

<template>
  <div>
    <SettingsPanelShell
      class="mt-4"
      :title="$t('reports.report_scope')"
      icon="mdi-file-chart-outline"
      :loading="loading"
      :success="success"
      :failure="failure"
      @submit="getReportData('preview')"
    >
      <v-alert
        v-if="!consoKey"
        type="error"
        variant="tonal"
        :text="$t('reports.conso_undefined')"
      />

      <template v-else>
        <!-- Saved Report Preset Loader -->
        <v-row v-if="savedReports.length > 0">
          <v-col cols="12" md="5">
            <v-autocomplete
              v-model="savedReport"
              :label="$t('reports.load_saved_report')"
              :items="savedReports"
              item-title="title"
              item-value="id"
              @update:model-value="loadPreset('saved')"
            />
          </v-col>
        </v-row>

        <!-- Section 1: Scope & Date Range -->
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-filter-outline</v-icon>
          {{ $t('reports.scope_criteria') }}
        </SectionHeading>

        <v-row>
          <!-- Institution Controls -->
          <FlexCol v-if="homeInst === ''" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.select_institutions') }}
            </div>
            <MultiSelectCombobox
              v-model="selectedFields.institutiontype"
              :label="$t('routes.institution_types')"
              itemTitle="name"
              itemValue="id"
              :items="optionItems.institutiontype"
            />
            <MultiSelectCombobox
              v-if="optionItems.institutiongroup.length > 0"
              v-model="selectedFields.institutiongroup"
              :label="$t('routes.institution_groups')"
              itemTitle="name"
              itemValue="id"
              :items="optionItems.institutiongroup"
            />
            <MultiSelectCombobox
              v-model="selectedFields.institution"
              :label="$t('routes.institutions')"
              itemTitle="name"
              itemValue="id"
              :items="optionItems.institution"
            />
          </FlexCol>
          <FlexCol v-else class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('fields.institution') }}
            </div>
            <v-text-field
              :model-value="homeInst"
              :label="$t('fields.institution')"
              readonly
            />
          </FlexCol>

          <!-- Provider & Database Platform Contexts -->
          <FlexCol>
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.select_platforms') }}
            </div>
            <v-select
              v-model="selectedFields.provider"
              :label="$t('routes.platforms')"
              :items="optionItems.provider"
              item-title="name"
              item-value="id"
              multiple
            >
              <template #selection="{ item, index }">
                <span v-if="index === 0" class="text-truncate">
                  {{ item.title }}
                  <span v-if="selectedFields.provider.length > 1" class="text-medium-emphasis">
                    (+{{ selectedFields.provider.length - 1 }})
                  </span>
                </span>
              </template>
            </v-select>
            <template v-if="showDatabases">
              <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis mt-1">
                {{ $t('reports.select_database') }}
              </div>
              <MultiSelectCombobox
                v-model="selectedFields.Dbase"
                :label="$t('reports.databases')"
                itemTitle="name"
                itemValue="id"
                :items="optionItems.Dbase"
              />
            </template>
          </FlexCol>

          <!-- Master Report & Standard View -->
          <FlexCol :lg="3" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.select_report_type') }}
            </div>
            <v-radio-group
              v-model="masterReport"
              return-object
              inline
              @update:model-value="selectMaster"
            >
              <v-radio v-for="report in masterReports" :key="report.id" :label="report.legend" :value="report" />
            </v-radio-group>

            <v-expand-transition>
              <div v-if="masterReportId > 0 && masterReports[masterReportId - 1]?.children?.length">
                <v-radio-group
                  v-model="selectedView"
                  :label="$t('reports.standard_view')"
                  inline
                  @update:model-value="loadPreset('view')"
                >
                  <v-radio
                    v-for="view in masterReports[masterReportId - 1].children"
                    :key="view.id"
                    :label="view.name"
                    :value="view.id"
                  />
                </v-radio-group>
              </div>
            </v-expand-transition>
          </FlexCol>

          <!-- Report Dates -->
          <FlexCol :lg="2" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.report_dates') }}
            </div>
            <v-select
              v-model="dateRange"
              :label="$t('fields.date_range')"
              :items="reportOptions"
              item-title="title"
              item-value="value"
              @update:model-value="changeReportDates"
            />
            <YmInput v-model="reportDates.fromYM" :label="$t('reports.start_month')" />
            <YmInput v-model="reportDates.toYM" :label="$t('reports.end_month')" :minYM="minYM" :key="toKey" />
          </FlexCol>
        </v-row>

        <v-divider class="my-5" />

        <!-- Section 2: COUNTER Metrics, Filters & Formatting -->
        <SectionHeading>
          <v-icon start size="22" class="me-2" aria-hidden="true">mdi-tune-variant</v-icon>
          {{ $t('reports.metrics_and_options') }}
        </SectionHeading>

        <v-row>
          <!-- Usage Metrics -->
          <FlexCol
            v-if="metricItems.usage.length > 0 || metricItems.search.length > 0 || metricItems.turnaway.length > 0"
            class="d-flex flex-column ga-3"
          >
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.usage_metrics') }}
            </div>
            <MultiSelectCombobox
              v-if="metricItems.usage.length > 0"
              v-model="selectedMetrics.usage"
              :label="$t('reports.investigations_requests')"
              :items="metricItems.usage"
              itemTitle="legend"
              itemValue="id"
              :key="'us_' + optionsKey"
            />
            <MultiSelectCombobox
              v-if="metricItems.search.length > 0"
              v-model="selectedMetrics.search"
              :label="$t('reports.metric_searches')"
              :items="metricItems.search"
              itemTitle="legend"
              itemValue="id"
              :key="'ss_' + optionsKey"
            />
            <MultiSelectCombobox
              v-if="metricItems.turnaway.length > 0"
              v-model="selectedMetrics.turnaway"
              :label="$t('reports.turnaways')"
              :items="metricItems.turnaway"
              itemTitle="legend"
              itemValue="id"
              :key="'ts_' + optionsKey"
            />
          </FlexCol>

          <!-- Access & Data Filters -->
          <FlexCol
            v-if="showAccessMethods || showAccessTypes || showDataTypes || showSectionTypes"
            class="d-flex flex-column ga-3"
          >
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.access_and_data') }}
            </div>
            <MultiSelectCombobox
              v-if="showAccessMethods"
              v-model="selectedFields.accessmethod"
              :label="$t('reports.access_methods')"
              :items="optionItems.accessmethod"
              itemTitle="name"
              itemValue="id"
              :key="'am_' + optionsKey"
            />
            <MultiSelectCombobox
              v-if="showAccessTypes"
              v-model="selectedFields.accesstype"
              :label="$t('reports.access_types')"
              :items="optionItems.accesstype"
              itemTitle="name"
              itemValue="id"
              :key="'at_' + optionsKey"
            />
            <MultiSelectCombobox
              v-if="showDataTypes"
              v-model="selectedFields.datatype"
              :label="$t('reports.data_types')"
              :items="optionItems.datatype"
              itemTitle="name"
              itemValue="id"
              :key="'dt_' + optionsKey"
            />
            <MultiSelectCombobox
              v-if="showSectionTypes"
              v-model="selectedFields.sectiontype"
              :label="$t('reports.section_types')"
              :items="optionItems.sectiontype"
              itemTitle="name"
              itemValue="id"
              :key="'st_' + optionsKey"
            />
          </FlexCol>
          <!-- Layout Format Switches -->
          <FlexCol class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.formatting') }}
            </div>
            <v-radio-group v-model="reportFormat" inline>
              <v-radio :label="$t('reports.compact_format')" value="Compact" />
              <v-radio :label="$t('reports.counter_format')" value="COUNTER" />
            </v-radio-group>

            <div class="d-flex align-center ga-2">
              <ToggleIcon v-model="zeroRecs" toggleable :size="32" />
              <span class="text-body-medium">{{ $t('reports.exclude_zeros') }}</span>
            </div>

            <div v-if="reportFormat === 'COUNTER'" class="d-flex align-center ga-2">
              <ToggleIcon v-model="RPTonly" toggleable :size="32" />
              <span class="text-body-medium">{{ $t('reports.limit_rp_totals') }}</span>
            </div>
          </FlexCol>

          <!-- Information Columns -->
          <FlexCol :lg="2" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.information_columns') }}
            </div>
            <MultiSelectCombobox
              v-model="enabledInfoFields"
              :label="$t('reports.selectable_columns')"
              :items="infoFields"
              itemTitle="legend"
              itemValue="id"
              :key="'sc_' + optionsKey"
            />
          </FlexCol>
        </v-row>
      </template>

      <template v-if="previewButtonEnabled" #actions>
        <v-btn
          color="primary"
          class="px-6"
          prepend-icon="mdi-table-eye"
          :loading="loading"
          @click="getReportData('preview')"
        >
          {{ $t('reports.load_preview') }}
        </v-btn>

        <v-btn
          color="secondary"
          variant="tonal"
          prepend-icon="mdi-content-save-outline"
          @click="saveDialog = true"
        >
          {{ $t('reports.save_configuration') }}
        </v-btn>

        <v-btn
          color="success"
          variant="tonal"
          prepend-icon="mdi-microsoft-excel"
          :loading="exporting"
          @click="getReportData('export')"
        >
          {{ $t('common.export') }}
        </v-btn>
      </template>
    </SettingsPanelShell>

    <!-- Report Preview Data Grid -->
    <SettingsPanelShell
      v-if="hasPreview"
      :title="previewTitle || $t('reports.report_preview')"
      icon="mdi-table-large"
      class="mt-6"
    >
      <v-data-table
        :headers="filteredHeaders"
        :items="report_items"
        :loading="loading"
        :items-per-page-options="itemsPerPageOptions"
        density="compact"
        :key="dtKey"
      />
    </SettingsPanelShell>

    <!-- Save Configuration Dialog -->
    <v-dialog v-model="saveDialog">
      <v-card>
        <v-card-item>
          <template #title>
            <span class="text-h6 font-weight-semibold text-white">{{ $t('reports.save_configuration') }}</span>
          </template>
          <template #append>
            <v-btn
              icon="mdi-close"
              :aria-label="$t('dialogs.close_dialog_aria')"
              @click="saveDialog = false"
            />
          </template>
        </v-card-item>
        <v-card-text>
          <SaveReport :saved_reports="savedReports" @save="saveConfig" @cancel="saveDialog = false" />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>
