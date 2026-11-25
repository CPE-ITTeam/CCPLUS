<!-- resources/js/components/panels/ReportingPanel.vue -->
<script setup>
import { ref, reactive, watch, computed, onBeforeMount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';
import api from '@/services/api.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import SaveReport from '@/components/dialogs/SaveReport.vue';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import InstitutionScopeSwitch from '@/components/ui/InstitutionScopeSwitch.vue';
import YmRangeSelector from '@/components/ui/YmRangeSelector.vue';
import { exportUsageReportToExcel } from '@/utils/excelJsExporter.js';

defineOptions({ name: 'ReportingPanel', inheritAttrs: false });

const { t } = useI18n();
const authStore = useAuthStore();

const emit = defineEmits(['updateConso']);

const consoKey = computed(() => authStore.ccp_key);
const masterReports = ref([]);
const reportViews = ref([]);
const fyMo = ref(null);
const dtKey = ref(0);
const reportFormat = ref('COUNTER');
const zeroRecs = ref('Active');
const RPTonly = ref('Active');
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
const savedReport = ref(null);
const selectedView = ref(null);
const masterReport = ref({});
const optionsKey = ref(0);
const dateRange = ref('fYTD');
const useInstGroups = ref(false);
const reportDates = ref({ fromYM: '', toYM: '' });

const optionItems = reactive({
  institution: [],
  provider: [],
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

const consoPlatformFilter = ref('All');

const consoPlatformSwitch = computed(() => {
  if (consoPlatformFilter.value === 'Active') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'primary',
      label: t('fields.limit_to_consortial', 'Limit to Consortial'),
    };
  }
  if (consoPlatformFilter.value === 'NonConso') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'secondary',
      label: t('fields.exclude_consortial', 'Exclude Consortial'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'success',
    label: t('fields.all_platforms', 'All Platforms'),
  };
});

function cycleConsoPlatformFilter() {
  consoPlatformFilter.value =
    consoPlatformFilter.value === 'All'
      ? 'Active'
      : consoPlatformFilter.value === 'Active'
        ? 'NonConso'
        : 'All';
}

const filteredPlatformOptions = computed(() => {
  if (consoPlatformFilter.value === 'All') {
    return optionItems.provider;
  }
  return optionItems.provider.filter((plat) => {
    const isConso = Boolean(plat.is_conso ?? plat.institutions?.includes(1));
    return consoPlatformFilter.value === 'Active' ? isConso : !isConso;
  });
});



function onScopeModeChange(isGroups) {
  if (isGroups) {
    selectedFields.institution = [];
  } else {
    selectedFields.institutiongroup = [];
  }
}

const initializeOptions = async () => {
  try {
    const { data } = await api.get('/api/reports/options');
    optionItems.institution = [...(data.records.institutions || [])];
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

const homeInst = computed(() =>
  optionItems.institution.length === 1 ? optionItems.institution[0].name : ''
);

const masterReportId = computed(() =>
  typeof masterReport.value.id !== 'undefined' ? masterReport.value.id : -1
);

const previewButtonEnabled = computed(() =>
  masterReportId.value > 0 && reportDates.value.fromYM !== '' && reportDates.value.toYM !== ''
);

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

const hasMetricColumns = computed(
  () => metricItems.value.usage.length > 0 || metricItems.value.search.length > 0 || metricItems.value.turnaway.length > 0
);

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
    let limit = [];
    if (fld.qry_as === 'institution') {
      if (homeInst.value !== '') {
        limit = [optionItems.institution[0].id];
      } else if (useInstGroups.value && selectedFields.institutiongroup.length > 0) {
        const instSet = new Set();
        selectedFields.institutiongroup.forEach((gid) => {
          const grp = optionItems.institutiongroup.find((g) => g.id === gid);
          grp?.institutions?.forEach((inst) => instSet.add(inst.id ?? inst));
        });
        limit = [...instSet];
      } else if (selectedFields.institution.length > 0) {
        limit = [...selectedFields.institution];
      }
    } else if (fld.qry_as === 'provider') {
      if (selectedFields.provider.length > 0) {
        limit = [...selectedFields.provider];
      } else if (consoPlatformFilter.value !== 'All') {
        limit = filteredPlatformOptions.value.map((p) => p.id);
      }
    }

    returnFields[fld.qry_as] = {
      ...fld,
      active: enabledInfoFields.value.includes(fld.id),
      limit,
    };

  });

  const activeMetrics = new Set([
    ...selectedMetrics.usage,
    ...selectedMetrics.search,
    ...selectedMetrics.turnaway,
  ]);

  masterReport.value.report_fields.forEach((fld) => {
    if (fld.qry_as === 'institution' || fld.qry_as === 'provider') return;

    if (fld.is_metric) {
      if (activeMetrics.has(fld.id)) {
        returnFields[fld.qry_as] = {
          id: fld.id,
          legend: fld.legend,
          is_metric: fld.is_metric,
          active: true,
          qry_as: fld.qry_as,
          limit: [],
        };
      }
      return;
    }

    if (fld.qry_as in selectedFields) {
      const limit = [...selectedFields[fld.qry_as]];
      returnFields[fld.qry_as] = {
        id: fld.id,
        legend: fld.legend,
        is_metric: fld.is_metric,
        active: limit.length > 0 || (fld.qry_as === 'Dbase' && masterReport.value.name === 'DR'),
        qry_as: fld.qry_as,
        limit,
      };
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

const hasAccessDataColumns = computed(
  () => showAccessMethods.value || showAccessTypes.value || showDataTypes.value || showSectionTypes.value
);

const formattingColOffset = computed(() => {
  const visibleColsBefore = (hasMetricColumns.value ? 1 : 0) + (hasAccessDataColumns.value ? 1 : 0);
  return (2 - visibleColsBefore) * 4;
});

function selectMaster() {
  Object.keys(selectedMetrics).forEach((type) => {
    selectedMetrics[type] = metricItems.value[type].map((m) => m.id);
  });
  dataFields.value.forEach((field) => {
    selectedFields[field] = optionItems[field].map((f) => f.id);
  });
  enabledInfoFields.value = infoFields.value
    .filter((f) => !/proprietary/i.test(`${f.qry_as} ${f.legend}`))
    .map((f) => f.id);
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
    savedTitle.value = theReport.title;
    dateRange.value = theReport.date_range;
    reportDates.value = { fromYM: theReport.ym_from || '', toYM: theReport.ym_to || '' };

    reportFormat.value = theReport.format;
    RPTonly.value = theReport.rpt_only === 1 ? 'Active' : 'Inactive';
    zeroRecs.value = theReport.exclude_zeros === 1 ? 'Active' : 'Inactive';
    const theMaster = masterReports.value.find((mr) => mr.id === theReport.master_id);
    if (typeof theMaster === 'undefined') {
      failure.value = `Record is corrupt - master report ID: ${theReport.master_id} not found!`;
      return;
    } masterReport.value = { ...theMaster };
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
  useInstGroups.value = selectedFields.institutiongroup.length > 0;
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
    date_range: dateRange.value || 'Custom',
    from: reportDates.value.fromYM,
    to: reportDates.value.toYM,
    format: reportFormat.value,
    fields: all_fields.value,
    rpt_only: RPTonly.value === 'Active' ? 1 : 0,
    zeros: zeroRecs.value === 'Active' ? 1 : 0,
    report_id:
      selectedView.value === null
        ? masterReportId.value
        : typeof selectedView.value === 'object'
          ? selectedView.value.id
          : selectedView.value,
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
    from: reportDates.value.fromYM,
    to: reportDates.value.toYM,
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
  previewTitle.value += ` - From: ${reportDates.value.fromYM} To: ${reportDates.value.toYM}`;

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
        await exportUsageReportToExcel(response.headers, response.usage, response.filename, 'CCPlus_Usage');
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



<!-- resources/js/components/panels/ReportingPanel.vue -->
<template>
  <div>
    <SettingsPanelShell class="mt-4" :title="$t('reports.report_scope')" icon="mdi-file-chart-outline"
      :loading="loading" :success="success" :failure="failure" @submit="getReportData('preview')">
      <v-alert v-if="!consoKey" type="error" variant="tonal" :text="$t('reports.conso_undefined')" />

      <template v-else>
        <!-- Saved Report Preset Loader -->
        <v-row v-if="savedReports.length > 0">
          <v-col cols="12" md="4">
            <v-autocomplete v-model="savedReport" :label="$t('reports.load_saved_report')" :items="savedReports"
              item-title="title" item-value="id" @update:model-value="loadPreset('saved')" />
          </v-col>
        </v-row>

        <!-- Section 1: Scope & Date Range -->
        <SectionHeading class="mb-2">
          <v-icon start size="20" class="me-2" aria-hidden="true">mdi-filter-outline</v-icon>
          {{ $t('reports.scope_criteria') }}
        </SectionHeading>

        <v-row class="mt-0">
          <!-- Column 1: Platforms, then Institutions / Institution Groups -->
          <v-col cols="12" md="4" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('harvest.scope_and_platforms') }}
            </div>

            <v-switch :model-value="consoPlatformSwitch.modelValue" :indeterminate="consoPlatformSwitch.indeterminate"
              :color="consoPlatformSwitch.color" :label="consoPlatformSwitch.label" true-icon="mdi-star-circle"
              false-icon="mdi-home-circle" class="mb-n2" @click.prevent="cycleConsoPlatformFilter" />

            <MultiSelectCombobox v-model="selectedFields.provider" :label="$t('routes.platforms')"
              :items="filteredPlatformOptions" item-title="name" item-value="id" />

            <template v-if="homeInst === ''">
              <InstitutionScopeSwitch v-if="optionItems.institutiongroup.length > 0" v-model="useInstGroups"
                class="mb-n2" @update:model-value="onScopeModeChange" />

              <MultiSelectCombobox v-if="!useInstGroups" v-model="selectedFields.institution"
                :label="$t('routes.institutions')" item-title="name" item-value="id" :items="optionItems.institution" />

              <MultiSelectCombobox v-else v-model="selectedFields.institutiongroup"
                :label="$t('routes.institution_groups')" item-title="name" item-value="id"
                :items="optionItems.institutiongroup" />
            </template>

            <v-text-field v-else :model-value="homeInst" :label="$t('fields.institution')" readonly />
          </v-col>

          <!-- Column 2: Report Type & Standard View -->
          <v-col cols="12" md="4" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.select_report_type') }}
            </div>

            <v-radio-group v-model="masterReport" return-object inline hide-details @update:model-value="selectMaster">
              <v-radio v-for="report in masterReports" :key="report.id" :label="report.legend" :value="report" />
            </v-radio-group>

            <v-expand-transition>
              <div v-if="masterReportId > 0 && masterReports[masterReportId - 1]?.children?.length">
                <v-radio-group v-model="selectedView" :label="$t('reports.standard_view')" inline hide-details
                  @update:model-value="loadPreset('view')">
                  <v-radio v-for="view in masterReports[masterReportId - 1].children" :key="view.id" :label="view.name"
                    :value="view.id" />
                </v-radio-group>
              </div>
            </v-expand-transition>
          </v-col>

          <!-- Column 3: Report Dates -->
          <v-col cols="12" md="4" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.report_dates') }}
            </div>
            <YmRangeSelector v-model="reportDates" :fy-start="fyMo" default-preset="fYTD"
              @update:preset="dateRange = $event" />
          </v-col>

        </v-row>

        <v-divider class="my-5" />

        <!-- Section 2: COUNTER Metrics, Filters & Formatting -->
        <SectionHeading class="mb-2">
          <v-icon start size="20" class="me-2" aria-hidden="true">mdi-tune-variant</v-icon>
          {{ $t('reports.metrics_and_options') }}
        </SectionHeading>

        <v-row class="mt-0">
          <!-- Column 1: Usage Metrics -->
          <v-col v-if="hasMetricColumns" cols="12" md="4" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.usage_metrics') }}
            </div>
            <MultiSelectCombobox v-if="metricItems.usage.length > 0" v-model="selectedMetrics.usage"
              :label="$t('reports.investigations_requests')" :items="metricItems.usage" item-title="legend"
              item-value="id" :key="'us_' + optionsKey" />
            <MultiSelectCombobox v-if="metricItems.search.length > 0" v-model="selectedMetrics.search"
              :label="$t('reports.metric_searches')" :items="metricItems.search" item-title="legend" item-value="id"
              :key="'ss_' + optionsKey" />
            <MultiSelectCombobox v-if="metricItems.turnaway.length > 0" v-model="selectedMetrics.turnaway"
              :label="$t('reports.turnaways')" :items="metricItems.turnaway" item-title="legend" item-value="id"
              :key="'ts_' + optionsKey" />
          </v-col>

          <!-- Column 2: Access & Data Filters -->
          <v-col v-if="hasAccessDataColumns" cols="12" md="4" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.access_and_data') }}
            </div>
            <MultiSelectCombobox v-if="showAccessMethods" v-model="selectedFields.accessmethod"
              :label="$t('reports.access_methods')" :items="optionItems.accessmethod" item-title="name" item-value="id"
              :key="'am_' + optionsKey" />
            <MultiSelectCombobox v-if="showAccessTypes" v-model="selectedFields.accesstype"
              :label="$t('reports.access_types')" :items="optionItems.accesstype" item-title="name" item-value="id"
              :key="'at_' + optionsKey" />
            <MultiSelectCombobox v-if="showDataTypes" v-model="selectedFields.datatype"
              :label="$t('reports.data_types')" :items="optionItems.datatype" item-title="name" item-value="id"
              :key="'dt_' + optionsKey" />
            <MultiSelectCombobox v-if="showSectionTypes" v-model="selectedFields.sectiontype"
              :label="$t('reports.section_types')" :items="optionItems.sectiontype" item-title="name" item-value="id"
              :key="'st_' + optionsKey" />
          </v-col>

          <!-- Column 3: Information Columns (pinned to Col 3 even before a report is picked) -->
          <v-col cols="12" md="4" :offset-md="formattingColOffset" class="d-flex flex-column ga-3">
            <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis">
              {{ $t('reports.information_columns') }}
            </div>
            <MultiSelectCombobox v-model="enabledInfoFields" :label="$t('reports.selectable_columns')"
              :items="infoFields" item-title="legend" item-value="id" :key="'sc_' + optionsKey" />
          </v-col>
        </v-row>

        <!-- Bottom Row: Formatting Switches (all 3 defaulted ON) + Database Selector -->
        <v-row align="center" class="mt-2 pt-2 border-t">
          <v-col cols="12" md="8" class="d-flex flex-wrap align-center ga-6 py-1">
            <v-switch v-model="reportFormat"
              :label="reportFormat === 'COUNTER' ? $t('reports.counter_format') : $t('reports.compact_format')"
              true-value="COUNTER" false-value="Compact" hide-details />
            <v-switch v-model="zeroRecs" :label="$t('reports.exclude_zeros')" true-value="Active" false-value="Inactive"
              hide-details />

            <v-switch v-model="RPTonly" :label="$t('reports.limit_rp_totals')" true-value="Active"
              false-value="Inactive" hide-details />
          </v-col>

          <v-col v-if="showDatabases" cols="12" md="4" class="py-1">
            <MultiSelectCombobox v-model="selectedFields.Dbase" :label="$t('reports.databases')" item-title="name"
              item-value="id" :items="optionItems.Dbase" />
          </v-col>
        </v-row>
      </template>

      <template v-if="previewButtonEnabled" #actions>
        <v-btn color="primary" class="px-6" prepend-icon="mdi-table-eye" :loading="loading"
          @click="getReportData('preview')">
          {{ $t('reports.load_preview') }}
        </v-btn>

        <v-btn color="secondary" variant="tonal" prepend-icon="mdi-content-save-outline" @click="saveDialog = true">
          {{ $t('reports.save_configuration') }}
        </v-btn>

        <v-btn color="success" variant="tonal" prepend-icon="mdi-microsoft-excel" :loading="exporting"
          @click="getReportData('export')">
          {{ $t('common.export') }}
        </v-btn>
      </template>
    </SettingsPanelShell>

    <SettingsPanelShell v-if="hasPreview" :title="previewTitle || $t('reports.report_preview')" icon="mdi-table-large"
      class="mt-6">
      <v-data-table :headers="filteredHeaders" :items="report_items" :loading="loading"
        :items-per-page-options="itemsPerPageOptions" density="compact" :key="dtKey" />
    </SettingsPanelShell>

    <v-dialog v-model="saveDialog">
      <v-card>
        <v-card-item>
          <template #title>
            <span class="text-h6 font-weight-semibold text-white">{{ $t('reports.save_configuration') }}</span>
          </template>
          <template #append>
            <v-btn icon="mdi-close" :aria-label="$t('dialogs.close_dialog_aria')" @click="saveDialog = false" />
          </template>
        </v-card-item>
        <v-card-text>
          <SaveReport :saved_reports="savedReports" @save="saveConfig" @cancel="saveDialog = false" />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>
