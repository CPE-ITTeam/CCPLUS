<!--/views/admin/Reporting.vue-->

<script setup>
import { ref, reactive, watch, computed, onBeforeMount } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import { useReportingDates } from '@/composables/useReportingDates.js'; // Updated to newly renamed composable
import api from '@/services/api.js'; // Automated Axios Client Service
import SaveReport from '@/components/dialogs/SaveReport.vue';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import FlexCol from '@/components/ui/FlexCol.vue'
import YmInput from '@/components/ui/YmInput.vue';
import { exportDatasetToExcel } from '@/utils/excelJsExporter.js';

// Pinia DataStores & Modular Core Helpers
const authStore = useAuthStore();
const { fromToDates } = useReportingDates(); // Cleanly loaded refactored hook

const consoKey = ref(authStore.ccp_key);
var selectedConso = ref(authStore.ccp_key);
const { consortia } = storeToRefs(useCCPlusStore());
const is_serveradmin = authStore.is_serveradmin;
var panels = ref(['scope']);
var masterReports = ref([]);
var reportViews = ref([]);
var fyMo = ref([]);
var toKey = ref(0);
var dtKey = ref(0);
var reportFormat = ref('Compact');
var zeroRecs = ref('Active');
var RPTonly = ref('Inactive');
var previewTitle = ref('');
var loading = ref(false);
var success = ref('');
var failure = ref('');
var saveDialog = ref(false);
var savedReports = ref([]);
var savedTitle = ref('');
var report_items = ref([]);
var reportColumns = ref([]);
const footer_props = { 'items-per-page-options': [10, 20, 50, 100] };
const minYM = ref('');
const savedReport = ref(null);
const selectedView = ref(null);
const masterReport = ref({});
var optionsKey = ref(0);
var dateRange = ref('');
var reportDates = reactive({ fromYM: '', toYM: '' });

// Expanded options array matching all capabilities supported by useReportingDates
const reportOptions = [
  { title: 'Fiscal YTD', value: 'fYTD' }, 
  { title: 'Prior FY', value: 'priorFy' },
  { title: 'Calendar YTD', value: 'cYTD' }, 
  { title: 'Prior Calendar Year', value: 'priorCy' }, 
  { title: 'Rolling 12 Months', value: 'last12Months' }, 
  { title: 'Custom', value: 'Custom' }
];

const optionItems = reactive({
  'institution': [], 'provider': [], 'institutiontype': [], 'institutiongroup': [],
  'Dbase': [], 'datatype': [], 'sectiontype': [], 'accesstype': [], 'accessmethod': []
});
const selectedFields = reactive({
  'provider': [], 'institution': [], 'institutiontype': [], 'institutiongroup': [],
  'Dbase': [], 'datatype': [], 'sectiontype': [], 'accesstype': [], 'accessmethod': []
});
const selectedMetrics = reactive({ 'usage': [], 'search': [], 'turnaway': [] });
const dataFields = ref(['datatype', 'sectiontype', 'accesstype', 'accessmethod']);
const enabledInfoFields = ref([]);

const initializeOptions = async () => {
  try {
    const { data } = await api.get("/api/reports/options");
    optionItems['institution'] = [...data.records.institutions];
    optionItems['institutiontype'] = [...data.records.institution_types];
    optionItems['institutiongroup'] = [...data.records.groups];
    optionItems['provider'] = [...data.records.platforms];
    optionItems['Dbase'] = [...data.records.databases];
    optionItems['datatype'] = [...data.records.data_types];
    optionItems['sectiontype'] = [...data.records.section_types];
    optionItems['accesstype'] = [...data.records.access_types];
    optionItems['accessmethod'] = [...data.records.access_methods];
    savedReports.value = [...data.records.saved_reports];
    masterReports.value = [...data.records.master_reports];
    reportViews.value = [...data.records.report_views];
    fyMo.value = data.records.fyMo;
  } catch (error) {
    console.log('Error loading options: ' + error.message);
  }
}

const homeInst = computed(() => {
  return (optionItems['institution'].length == 1) ? optionItems['institution'][0].name : '';
});

const masterReportId = computed(() => {
  return (typeof (masterReport.value.id) != 'undefined') ? masterReport.value.id : -1;
});

const previewButtonEnabled = computed(() => {
  return (masterReportId.value > 0 && reportDates.fromYM != '' && reportDates.toYM != '');
});

const metricItems = computed(() => {
  if (masterReportId.value <= 0) {
    return { 'usage': [], 'search': [], 'turnaway': [] };
  } else {
    return {
      'usage': masterReport.value.report_fields.filter(fld => fld.metric_type == 'usage'),
      'search': masterReport.value.report_fields.filter(fld => fld.metric_type == 'search'),
      'turnaway': masterReport.value.report_fields.filter(fld => fld.metric_type == 'turnaway')
    };
  }
});

const infoFields = computed(() => {
  if (masterReportId.value <= 0) return [];
  let _fields = [];
  masterReport.value.report_fields.forEach(fld => {
    if (fld.qry_as == 'provider' || fld.qry_as == 'institution') {
      _fields.push({ 'id': fld.id, 'qry_as': fld.qry_as, 'legend': fld.legend, 'is_metric': fld.is_metric });
    } else if (fld.is_metric) {
      if (!metricItems.value[fld.metric_type].map(f => f.qry_as).includes(fld.qry_as)) {
        _fields.push({ 'id': fld.id, 'qry_as': fld.qry_as, 'legend': fld.legend, 'is_metric': fld.is_metric });
      }
    } else {
      if (!Object.keys(optionItems).includes(fld.qry_as)) {
        _fields.push({ 'id': fld.id, 'qry_as': fld.qry_as, 'legend': fld.legend, 'is_metric': fld.is_metric });
      }
    }
  });
  return _fields;
});

const all_fields = computed(() => {
  let returnFields = {};
  infoFields.value.forEach(fld => {
    returnFields[fld.qry_as] = { ...fld };
    returnFields[fld.qry_as]['active'] = (enabledInfoFields.value.includes(fld.id));
    if (fld.qry_as == 'institution' || fld.qry_as == 'provider') {
      returnFields[fld.qry_as]['limit'] = [];
      if (fld.qry_as == 'institution' && homeInst != '') {
        returnFields[fld.qry_as]['limit'] = [optionItems['institution'][0].id];
      } else if (selectedFields[fld.qry_as].length > 0) {
        returnFields[fld.qry_as]['limit'] = [...selectedFields[fld.qry_as]];
      }
    }
  });
  masterReport.value.report_fields.forEach(fld => {
    if (fld.qry_as != 'institution' && fld.qry_as != 'provider') {
      if (fld.is_metric) {
        if (selectedMetrics['usage'].includes(fld.id) || selectedMetrics['search'].includes(fld.id) ||
          selectedMetrics['turnaway'].includes(fld.id)) {
          returnFields[fld.qry_as] = {
            'id': fld.id, 'legend': fld.legend, 'is_metric': fld.is_metric, 'active': true,
            'qry_as': fld.qry_as, 'limit': []
          };
        }
      } else if (typeof (selectedFields[fld.qry_as]) != 'undefined') {
        let _active = (selectedFields[fld.qry_as].length > 0 || (fld.qry_as == 'Dbase' && masterReport.value.name == 'DR'));
        let _limit = (selectedFields[fld.qry_as].length > 0) ? selectedFields[fld.qry_as] : [];
        returnFields[fld.qry_as] = {
          'id': fld.id, 'legend': fld.legend, 'is_metric': fld.is_metric, 'active': _active,
          'qry_as': fld.qry_as, 'limit': _limit
        };
      }
    }
  });
  return returnFields;
});

const filteredHeaders = computed(() => {
  if (RPTonly.value == 'Active') {
    return (reportFormat.value == 'Compact')
      ? reportColumns.value.filter(h => h.active && (h.key.substr(0, 3) == 'RP_' || h.is_metric == 0))
      : reportColumns.value.filter(h => h.active && (h.key == 'Reporting_Period_Total' || h.is_metric == 0));
  } else {
    return reportColumns.value.filter(h => h.active);
  }
});

const showDatabases = computed(() => {
  return (masterReportId.value <= 0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'Dbase');
});
const showDataTypes = computed(() => {
  return (masterReportId.value <= 0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'datatype');
});
const showSectionTypes = computed(() => {
  return (masterReportId.value <= 0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'sectiontype');
});
const showAccessTypes = computed(() => {
  return (masterReportId.value <= 0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'accesstype');
});
const showAccessMethods = computed(() => {
  return (masterReportId.value <= 0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'accessmethod');
});

const emit = defineEmits(['updateConso']);

async function handleChangeConso(conso) {
  authStore.setConsoKey(conso.ccp_key);
  initializeOptions();
  emit('updateConso', conso);
}

function selectMaster() {
  Object.keys(selectedMetrics).forEach((type) => {
    selectedMetrics[type] = metricItems.value[type].map(m => m.id);
  });
  dataFields.value.forEach((field) => {
    selectedFields[field] = optionItems[field].map(f => f.id);
  });
  enabledInfoFields.value = infoFields.value.map(f => f.id);
  optionsKey.value++;
}

function loadPreset(loadType) {
  Object.keys(selectedMetrics).forEach((type) => { selectedMetrics[type] = []; });
  const theReport = (loadType == 'view') ? reportViews.value.find(rv => rv.id == selectedView.value)
    : savedReports.value.find(sr => sr.id == savedReport.value);
  if (typeof (theReport) == 'undefined') return;
  let reportFieldIds = theReport.report_fields.map(fld => fld.id);

  if (loadType == 'saved') {
    savedTitle.value = theReport.title;
    dateRange.value = theReport.date_range;
    reportDates.fromYM = theReport.ym_from;
    reportDates.toYM = theReport.ym_to;
    toKey.value++;

    reportFormat.value = theReport.format;
    RPTonly.value = (theReport.rpt_only == 1) ? 'Active' : 'Inactive';
    zeroRecs.value = (theReport.exclude_zeros == 1) ? 'Active' : 'Inactive';
    let theMaster = masterReports.value.find(mr => mr.id == theReport.master_id);
    if (typeof (theMaster) == 'undefined') {
      failure.value = "Record is corrupt - master report ID: " + theReport.master_id + " not found!";
      return;
    }
    masterReport.value = { ...theMaster };
    if (theReport.master_id != theReport.report_id) {
      let theView = reportViews.value.find(rv => rv.id == theReport.report_id);
      if (typeof (theView) == 'undefined') {
        failure.value = "Record is corrupt - Report View ID: " + theReport.report_id + " not found!";
        return;
      }
      selectedView.value = { ...theView };
    }
    enabledInfoFields.value = [];
    infoFields.value.forEach(ifld => {
      if (theReport.report_fields.some(fld => fld.id == ifld.id)) {
        enabledInfoFields.value.push(ifld.id);
      }
    });
  }
  Object.keys(selectedMetrics).forEach((type) => {
    selectedMetrics[type] = metricItems.value[type].filter(m => reportFieldIds.includes(m.id)).map(m => m.id);
  });
  theReport.report_fields.filter(rf => rf.is_metric == 0).forEach(fld => {
    if (typeof (selectedFields[fld.qry_as]) != 'undefined') {
      selectedFields[fld.qry_as] = [...fld.limit];
    }
  });
  optionsKey.value++;
}
async function saveConfig(data) {
  if (data.title == '' && data.id == 0) {
    failure.value = 'A name is required to save the configuration';
    return;
  }
  let args = {
    title: data.title, save_id: data.id, date_range: dateRange.value, from: reportDates.fromYM,
    to: reportDates.toYM, format: reportFormat.value, fields: all_fields.value
  };
  args['rpt_only'] = (RPTonly.value == 'Active') ? 1 : 0;
  args['zeros'] = (zeroRecs.value == 'Active') ? 1 : 0;
  args['report_id'] = (selectedView.value == null) ? masterReportId.value : selectedView.value.id;
  try {
    const { data: response } = await api.post('/api/savedreports/store', args);
    if (response.result) {
      if (data.id > 0) {
        savedReports.value.splice(savedReports.value.findIndex(rpt => rpt.id == data.id), 1, response.record);
      } else {
        savedReports.value.push(response.record);
      }
      success.value = response.msg;
    } else {
      failure.value = response.msg;
    }
  } catch (error) {
    console.log('Error adding:', error);
  }
  saveDialog.value = false;
}
async function getReportData(runType) {
  let args = {
    from: reportDates.fromYM, to: reportDates.toYM, report_id: masterReportId.value,
    format: reportFormat.value, fields: all_fields.value
  };
  if (runType != 'export') {
    loading.value = true;
    const { data: resp } = await api.post('/api/reports/updateColumns', args);
    if (resp.result) {
      reportColumns.value = [...resp.columns];
    }
  }
  args['runtype'] = runType;
  args['rpt_only'] = (RPTonly.value == 'Active') ? 1 : 0;
  args['zeros'] = (zeroRecs.value == 'Active') ? 1 : 0;
  let conso_name = (typeof (selectedConso.name) != 'undefined') ? selectedConso.name : consoKey.value;
  previewTitle.value = (savedTitle.value != '') ? savedTitle.value : conso_name + " : " + masterReport.value.name;
  previewTitle.value += " - From: " + reportDates.fromYM + " To: " + reportDates.toYM;
  if (runType == 'preview') {
    args['preview'] = 100;
    try {
      const { data: response } = await api.post('/api/reports/usageData', args);
      if (response.result) {
        report_items.value = [...response.usage];
        if (typeof (response.db_options) != 'undefined') {
          optionItems['Dbase'] = [...response.db_options];
        }
        panels.value = ['preview'];
        dtKey.value++;
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.log('Error getting preview data:', error);
    }
    loading.value = false;
  } else if (runType == 'export') {
    try {
      const { data: response } = await api.post('/api/reports/usageData', args);
      if (response.result) {
        const workbook = XLSX.utils.book_new();
        const worksheet = XLSX.utils.aoa_to_sheet([...response.headers]);
        XLSX.utils.sheet_add_json(worksheet, response.usage, { origin: -1, skipHeader: true });
        XLSX.book_append_sheet(workbook, worksheet, 'CCPlus_Usage');
        XLSX.writeFile(workbook, response.filename);
      } else {
        failure.value = response.msg;
      }
    } catch (error) {
      console.log('Error exporting data:', error);
    }
  }
}

// Updated to map the new composable return properties ({ from, to }) into local component properties
function changeReportDates() {
  const dates = fromToDates(dateRange.value);
  reportDates.fromYM = dates.from;
  reportDates.toYM = dates.to;
}

watch(() => reportDates.fromYM, (yearmon) => {
  toKey.value++;
  minYM.value = yearmon;
});
watch(selectedFields['institutiontype'], () => {
  selectedFields['institutiongroup'] = [];
  selectedFields['institution'] = [];
});
watch(selectedFields['institutiongroup'], () => {
  selectedFields['institution'] = [];
});
onBeforeMount(() => {
  if (selectedConso.value.length > 0) initializeOptions();
});
</script>


<!--/views/admin/Reporting.vue-->

<template>
  <v-expansion-panels multiple class="mt-6 rounded-lg" v-model="panels">

    <!-- ----------------------------------------------------
    1. REPORT SCOPE ACCORDION PANEL
    ------------------------------------------------------- -->
    <v-expansion-panel value="scope" class="rounded-lg border">
      <v-expansion-panel-title>Report Scope</v-expansion-panel-title>
      <v-expansion-panel-text class="rounded-lg">

        <!-- Global Toolbar Controls -->
        <v-row>
          <!-- 🪓 REMOVED DUPED AUTOCOMPLETE CONSO SELECTOR HERE -->
          <FlexCol v-if="selectedConso.name != '' && savedReports.length > 0">
            <v-label class="colLabel">Load a Saved Report</v-label>
            <v-autocomplete v-model="savedReport" label="Saved Reports" :items="savedReports" item-title="title"
              item-value="id" density="compact" @update:modelValue="loadPreset('saved')" />
          </FlexCol>
          <FlexCol v-if="previewButtonEnabled" :lg="2">
            <v-btn @click="getReportData('preview')">Load Preview</v-btn>
          </FlexCol>
          <FlexCol v-if="previewButtonEnabled" :lg="2">
            <v-btn @click="saveDialog = true">Save Configuration</v-btn>
          </FlexCol>
          <FlexCol v-if="previewButtonEnabled" :lg="2">
            <v-btn color="green" @click="getReportData('export')">Export</v-btn>
          </FlexCol>
        </v-row>

        <!-- Dynamic Feedback Error Alerts -->
        <v-row v-if="selectedConso.name == ''">
          <h3>Current Consortium not Properly Defined!</h3>
          <p>Check system installation; logging out and back in <strong>might</strong> fix the issue.</p>
        </v-row>
        <v-row v-if="success || failure" class="d-flex pt-2" no-gutters>
          <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </v-row>

        <!-- Scope Criteria Matrix Selectors -->
        <v-row v-if="selectedConso.name != ''">
          <!-- Institution Controls -->
          <FlexCol v-if="homeInst == ''">
            <v-label class="colLabel">Select Institutions</v-label>
            <MultiSelectCombobox v-model="selectedFields['institutiontype']" label="Institution Types" itemTitle="name"
              itemValue="id" :items="optionItems['institutiontype']" />
            <MultiSelectCombobox v-if="optionItems['institutiongroup'].length > 0"
              v-model="selectedFields['institutiongroup']" label="Institution Groups" itemTitle="name" itemValue="id"
              :items="optionItems['institutiongroup']" />
            <MultiSelectCombobox v-model="selectedFields['institution']" label="Institutions" itemTitle="name"
              itemValue="id" :items="optionItems['institution']" />
          </FlexCol>
          <FlexCol v-else>
            <h3>Institution: <br />{{ homeInst }}</h3>
          </FlexCol>

          <!-- Provider & Database Platform Contexts -->
          <FlexCol>
            <v-label class="colLabel">Select Platforms</v-label>
            <MultiSelectCombobox v-model="selectedFields['provider']" label="Platforms" itemTitle="name" itemValue="id"
              :items="optionItems['provider']" />
            <v-label v-if="showDatabases" class="colLabel">Select Database</v-label>
            <MultiSelectCombobox v-if="showDatabases" v-model="selectedFields['Dbase']" label="Databases"
              itemTitle="name" itemValue="id" :items="optionItems['Dbase']" />
          </FlexCol>

          <!-- Master Report Node Branches -->
          <FlexCol :lg="3">
            <v-label class="colLabel">Select Report Type</v-label>
            <v-radio-group v-model="masterReport" class="me-8" return-object inline @update:modelValue="selectMaster">
              <v-radio v-for="report in masterReports" :key="report.id" :label="report.legend" :value="report" />
            </v-radio-group>
            <v-expand-transition>
              <div v-if="masterReportId > 0">
                <v-radio-group label="Standard View (Optional)" v-model="selectedView" inline
                  @update:modelValue="loadPreset('view')">
                  <v-radio v-for="view in masterReports[masterReportId - 1].children" :key="view.id" :label="view.name"
                    :value="view.id" />
                </v-radio-group>
              </div>
            </v-expand-transition>
          </FlexCol>

          <!-- Calendar Processing Dates Blocks -->
          <FlexCol :lg="2">
            <v-label class="colLabel">Report Dates</v-label>
            <v-select v-model="dateRange" label="Choose Report Dates" :items="reportOptions" item-title="title"
              item-value="value" @update:modelValue="changeReportDates" />
            <YmInput v-model="reportDates['fromYM']" label="Start Month" />
            <v-label class="colLabel">End Month</v-label>
            <YmInput v-model="reportDates['toYM']" label="End Month" :minYM="minYM" :key="toKey" />
          </FlexCol>
        </v-row>

        <v-divider class="my-4"></v-divider>

        <!-- ----------------------------------------------------
        2. COUNTER SPECIFIC DIMENSIONS & FIELDS
        ------------------------------------------------------- -->
        <v-row v-if="selectedConso.name != ''">
          <!-- Metric Calculations Arrays -->
          <FlexCol
            v-if="metricItems['usage'].length > 0 || metricItems['search'].length > 0 || metricItems['turnaway'].length > 0">
            <v-label class="colLabel">Usage Metrics</v-label>
            <MultiSelectCombobox v-if="metricItems['usage'].length > 0" v-model="selectedMetrics['usage']"
              label="Investigations & Requests" :items="metricItems['usage']" itemTitle="legend" itemValue="id"
              :key="'us_' + optionsKey" />
            <MultiSelectCombobox v-if="metricItems['search'].length > 0" v-model="selectedMetrics['search']"
              label="Metric Type: Searches" :items="metricItems['search']" itemTitle="legend" itemValue="id"
              :key="'ss_' + optionsKey" />
            <MultiSelectCombobox v-if="metricItems['turnaway'].length > 0" v-model="selectedMetrics['turnaway']"
              label="Turnaways" :items="metricItems['turnaway']" itemTitle="legend" itemValue="id"
              :key="'ts_' + optionsKey" />
          </FlexCol>
          <FlexCol v-else></FlexCol>

          <!-- Data Attribute Selectors -->
          <FlexCol v-if="showAccessMethods || showAccessTypes || showDataTypes || showSectionTypes">
            <v-label class="colLabel">Access and Data</v-label>
            <MultiSelectCombobox v-if="showAccessMethods" v-model="selectedFields['accessmethod']"
              label="Access Methods" :items="optionItems['accessmethod']" itemTitle="name" itemValue="id"
              :key="'am_' + optionsKey" />
            <MultiSelectCombobox v-if="showAccessTypes" v-model="selectedFields['accesstype']" label="Access Types"
              :items="optionItems['accesstype']" itemTitle="name" itemValue="id" :key="'at_' + optionsKey" />
            <MultiSelectCombobox v-if="showDataTypes" v-model="selectedFields['datatype']" label="Data Types"
              :items="optionItems['datatype']" itemTitle="name" itemValue="id" :key="'dt_' + optionsKey" />
            <MultiSelectCombobox v-if="showSectionTypes" v-model="selectedFields['sectiontype']" label="Section Types"
              :items="optionItems['sectiontype']" itemTitle="name" itemValue="id" :key="'st_' + optionsKey" />
          </FlexCol>
          <FlexCol v-else></FlexCol>

          <!-- Layout Sheet Format Switches -->
          <FlexCol>
            <v-radio-group label="Report formatting" v-model="reportFormat" inline>
              <v-radio label="CC+ Compact" value="Compact"></v-radio>
              <v-radio label="COUNTER-R5" value="COUNTER"></v-radio>
            </v-radio-group>
            <ToggleIcon v-model="zeroRecs" toggleable :size="36" />
            <v-label class="colLabel">Exclude Zero-use records?</v-label>
            <div v-if="reportFormat == 'COUNTER'">
              <ToggleIcon v-model="RPTonly" toggleable :size="36" />
              <v-label class="colLabel">Limit to reporting period totals</v-label>
            </div>
          </FlexCol>

          <!-- Column Display Toggles -->
          <FlexCol :lg="2">
            <v-label class="colLabel">Information Columns</v-label>
            <MultiSelectCombobox v-model="enabledInfoFields" label="Selectable Columns" :items="infoFields"
              itemTitle="legend" itemValue="id" :key="'sc_' + optionsKey" />
          </FlexCol>
        </v-row>

      </v-expansion-panel-text>
    </v-expansion-panel> 
    
    <!-- ----------------------------------------------------
       3. REPORT DATASET PREVIEW DATA GRID
    ------------------------------------------------------- -->
    <v-expansion-panel value="preview" class="rounded-lg border">
      <v-expansion-panel-title>Report Preview</v-expansion-panel-title>
      <v-expansion-panel-text class="rounded-lg">
        <v-row class="d-flex mb-1 align-end" no-gutters>
          <v-col class="d-flex px-4">
            <h3>{{ previewTitle }}</h3>
          </v-col>
        </v-row>
        
        <v-data-table :headers="filteredHeaders" :items="report_items" :loading="loading" :footer-props="footer_props"
          dense :key="dtKey">
        </v-data-table>
      </v-expansion-panel-text>
    </v-expansion-panel>
  </v-expansion-panels>

  <!-- ----------------------------------------------------
     4. SAVE CONFIGURATION FORM DIALOGUE COMPONENT
  ------------------------------------------------------- -->
  <v-dialog v-model="saveDialog">
    <v-card>
      <v-card-title>
        <span>Save Report Configuration</span>
        <v-spacer></v-spacer>
      </v-card-title>
      <v-card-text>
        <SaveReport :saved_reports="savedReports" @save="saveConfig" @cancel="saveDialog = false" />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>