<script setup>
  import { ref, reactive, watch, computed, onBeforeMount } from 'vue';
  import { storeToRefs } from 'pinia';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
  import SaveReport from '../dialogs/SaveReport.vue';
  import MultiSelectCombobox from '../shared/MultiSelectCombobox.vue'
  import ToggleIcon from '../shared/ToggleIcon.vue';
  import FlexCol from '../shared/FlexCol.vue'
  import YmInput from '../shared/YmInput.vue';
import { all } from 'axios';
  // Pinia DataStores
  const { ccGet, ccPost, setConso, fromToDates } = useAuthStore();
  const authStore = useAuthStore();
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
  var runType = ref('preview');
  var previewTitle = ref('');
  var loading = ref(false);
  var success = ref('');
  var failure = ref('');
  var saveDialog = ref(false);
  var savedReports = ref([]);
  var savedTitle = ref('');
  var report_items = ref([]);
  var reportColumns = ref([]);
  const footer_props = {'items-per-page-options': [10, 20, 50, 100]};
  const minYM = ref('');
  const savedReport = ref(null);
  const selectedView = ref(null);
  const masterReport = ref({});
  var optionsKey=ref(0);
  var dateRange=ref('');
  var reportDates = reactive({ fromYM: '', toYM: '' });
  const reportOptions = [ { title: 'Fiscal YTD', value: 'fYTD' }, { title: 'Prior FY', value: 'priorFy' },
                          { title: 'Calendar YTD', value: 'cYTD' }, { title: 'Custom', value: 'Custom' } ];
  const optionItems = reactive({ 'institution': [], 'provider': [], 'institutiontype': [], 'institutiongroup': [],
                                 'Dbase': [], 'datatype': [], 'sectiontype': [], 'accesstype': [], 'accessmethod': []
  });
  const selectedFields = reactive({ 'provider': [], 'institution': [], 'institutiontype': [], 'institutiongroup': [],
                                    'Dbase': [], 'datatype': [], 'sectiontype': [], 'accesstype': [], 'accessmethod': []
  });
  const selectedMetrics = reactive({'usage': [], 'search': [], 'turnaway': []});
  const dataFields = ref(['datatype', 'sectiontype','accesstype', 'accessmethod']);
  const enabledInfoFields = ref([]);


  const initializeOptions = async () => {
    try {
      const { data } = await ccGet("/api/reports/options");
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
      // global items... probably only need to get these once...
      masterReports.value = [...data.records.master_reports];
      reportViews.value = [...data.records.report_views];
      fyMo.value = data.records.fyMo;
    } catch (error) {
      console.log('Error loading options: '+error.message);
    }
  }
  const masterReportId = computed(() => {
    return ( typeof(masterReport.value.id)!='undefined' ) ? masterReport.value.id : -1;
  });
  const previewButtonEnabled = computed(() => {
    return (masterReportId.value>0 && reportDates.fromYM!='' && reportDates.toYM!='');
  });
  const metricItems = computed(() => {
    if (masterReportId.value<=0) {
      return {'usage':[], 'search':[], 'turnaway':[]};
    } else {
      return {   'usage': masterReport.value.report_fields.filter(fld => fld.metric_type == 'usage'),
                'search': masterReport.value.report_fields.filter(fld => fld.metric_type == 'search'),
              'turnaway': masterReport.value.report_fields.filter(fld => fld.metric_type == 'turnaway')
      };
    }
  });
  // Fields to be included in the show/hide option are computed here
  const infoFields = computed(() => {
    if (masterReportId.value<=0) return [];
    let _fields = [];
    masterReport.value.report_fields.forEach( fld => {
      //  Include institution/group/provider explicitly
      if (fld.qry_as == 'provider' || fld.qry_as == 'institution') {
        _fields.push({ 'id': fld.id, 'qry_as': fld.qry_as, 'legend': fld.legend, 'is_metric': fld.is_metric});
      } else if (fld.is_metric) {
        if (!metricItems.value[fld.metric_type].map(f => f.qry_as).includes(fld.qry_as)) {
          _fields.push({ 'id': fld.id, 'qry_as': fld.qry_as, 'legend': fld.legend, 'is_metric': fld.is_metric});
        }
      } else {
        if (!Object.keys(optionItems).includes(fld.qry_as)) {
          _fields.push({ 'id': fld.id, 'qry_as': fld.qry_as, 'legend': fld.legend, 'is_metric': fld.is_metric});
        }
      }
    });
    return _fields;
  });
  // Build an array of fields from selectors to match what controller(s) expect
  const all_fields = computed(() => {
    let returnFields = {};
    infoFields.value.forEach(fld => {
      returnFields[fld.qry_as] = {...fld};
      returnFields[fld.qry_as]['active'] =  (enabledInfoFields.value.includes(fld.id));
      if ( (fld.qry_as == 'institution' || fld.qry_as == 'provider') && selectedFields[fld.qry_as].length>0 ) {
        returnFields[fld.qry_as]['limit'] = [...selectedFields[fld.qry_as]];
      } else {
        returnFields[fld.qry_as]['limit'] = [];
      }
    });
    masterReport.value.report_fields.forEach( fld => {
      if (fld.qry_as != 'institution' && fld.qry_as != 'provider') { // already handled as infoField
        if (fld.is_metric) {
          if ( selectedMetrics['usage'].includes(fld.id) || selectedMetrics['search'].includes(fld.id) ||
               selectedMetrics['turnaway'].includes(fld.id) ) {
            returnFields[fld.qry_as] = {'id': fld.id, 'legend': fld.legend, 'is_metric': fld.is_metric, 'active': true,
                                        'qry_as': fld.qry_as, 'limit': []};
          }
        } else if (typeof(selectedFields[fld.qry_as]) != 'undefined') {
          let _active = (selectedFields[fld.qry_as].length>0 || (fld.qry_as=='Dbase' && masterReport.value.name=='DR'));
          let _limit = (selectedFields[fld.qry_as].length>0) ? selectedFields[fld.qry_as] : [];
          returnFields[fld.qry_as] = {'id': fld.id, 'legend': fld.legend, 'is_metric': fld.is_metric, 'active': _active,
                                      'qry_as': fld.qry_as, 'limit': _limit};
        }
      }
    });
    return returnFields;
  });
  const filteredHeaders = computed( () => {
    // Limit to reporting period totals only?
    if (RPTonly.value == 'Active') {
      return (reportFormat.value == 'Compact')
              ? reportColumns.value.filter(h => h.active && (h.key.substr(0,3)=='RP_' || h.is_metric==0) )
              : reportColumns.value.filter(h => h.active && (h.key=='Reporting_Period_Total' || h.is_metric==0));
    } else {
      return reportColumns.value.filter(h => h.active);
    }
  });
  const showDatabases = computed(() => {
    return (masterReportId.value<=0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'Dbase');
  });
  const showDataTypes = computed(() => {
    return (masterReportId.value<=0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'datatype');
  });
  const showSectionTypes = computed(() => {
    return (masterReportId.value<=0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'sectiontype');
  });
  const showAccessTypes = computed(() => {
    return (masterReportId.value<=0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'accesstype');
  });
  const showAccessMethods = computed(() => {
    return (masterReportId.value<=0) ? false : masterReport.value.report_fields.some(fld => fld.qry_as == 'accessmethod');
  });

  const emit = defineEmits(['updateConso']);

  async function handleChangeConso(conso) {
    const response = await setConso(conso.id,conso.ccp_key);
    initializeOptions();
    emit('updateConso', conso);
  }

  // Update selectors when master report is changed
//NOTE::: sure about this??
  function selectMaster() {
      // Initialize metrics to all-ON based on master fields
      Object.keys(selectedMetrics).forEach( (type) =>  {
        selectedMetrics[type] = metricItems.value[type].map( m => m.id);
      });
      // Enable all data field options (leave others alone)
      dataFields.value.forEach( (field) =>  {
        selectedFields[field] = optionItems[field].map( f => f.id);
      });
      // Enable all other info fields by-default
      enabledInfoFields.value = infoFields.value.map(f => f.id);
      optionsKey.value++;
  }
  function loadPreset(loadType) {
    // Clear current settings
    Object.keys(selectedMetrics).forEach( (type) => { selectedMetrics[type] = []; });
    // Get report details
    const theReport = (loadType=='view') ? reportViews.value.find( rv => rv.id == selectedView.value)
                                         : savedReports.value.find( sr => sr.id == savedReport.value);
    if (typeof(theReport) == 'undefined') return;
    let reportFieldIds = theReport.report_fields.map(fld => fld.id);
    // Extra tasks for loading savedReports
    if (loadType == 'saved') {
      savedTitle.value = theReport.title;
      // Set date-fields
      dateRange.value = theReport.date_range;
      reportDates.fromYM = theReport.ym_from;
      reportDates.toYM = theReport.ym_to;
      toKey.value++;

      // Set switches and format
      reportFormat.value = theReport.format;
      RPTonly.value = (theReport.rpt_only==1) ? 'Active' : 'Inactive';
      zeroRecs.value = (theReport.exclude_zeros==1) ? 'Active' : 'Inactive';

      // Update masterReport
      let theMaster = masterReports.value.find(mr => mr.id == theReport.master_id);
      if (typeof(theMaster) == 'undefined') {
        failure.value = "Record is corrupt - master report ID: "+theReport.master_id+" not found!";
        return;
      }
      masterReport.value = {...theMaster};

      // Update selectedView if report_id is not a master
      if (theReport.master_id != theReport.report_id) { // set selectedView
        let theView = reportViews.value.find( rv => rv.id == theReport.report_id);
        if (typeof(theView) == 'undefined') {
          failure.value = "Record is corrupt - Report View ID: "+theReport.report_id+" not found!";
          return;
        }
        selectedView.value = {...theView};
      }
      // Enable info column options (but only for loadType=='saved')
      enabledInfoFields.value = [];
      infoFields.value.forEach( ifld => {
        if (theReport.report_fields.some(fld => fld.id == ifld.id)) {
          enabledInfoFields.value.push(ifld.id);
        }
      });
    }
    // Enable metrics
    Object.keys(selectedMetrics).forEach( (type) =>  {
      selectedMetrics[type] = metricItems.value[type].filter(m => reportFieldIds.includes(m.id)).map(m=>m.id);
    });
    // Enable data fields
    theReport.report_fields.filter(rf => rf.is_metric==0).forEach( fld => {
      if ( typeof(selectedFields[fld.qry_as]) != 'undefined' ) {
        selectedFields[fld.qry_as] = [...fld.limit];
      }
    });
    optionsKey.value++;
  }

  // Respond to emit from dialog and save/update the config
  async function saveConfig(data) {
      if (data.title=='' && data.id==0) {
          failure.value = 'A name is required to save the configuration';
          return;
      }
      let args = { title: data.title, save_id: data.id, date_range: dateRange.value, from: reportDates.fromYM,
                    to: reportDates.toYM, format: reportFormat.value, fields: all_fields.value };
      args['rpt_only'] = (RPTonly.value=='Active') ? 1 : 0;
      args['zeros'] = (zeroRecs.value=='Active') ? 1 : 0;
      args['report_id'] = (selectedView.value==null) ? masterReportId.value : selectedView.value.id;
      try {
        const response = await ccPost('/api/savedreports/store', args);
        if (response.result) {
          if (data.id>0) {  // updated existing
            savedReports.value.splice(savedReports.value.findIndex(rpt => rpt.id == data.id),1,response.record);
          } else {          // created new
            savedReports.value.push(response.record);
          }
          success.value = response.msg
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.log('Error adding:', error);
      }
      saveDialog.value = false;
  }
  async function getReportData () {
    let args = { from: reportDates.fromYM, to: reportDates.toYM, report_id: masterReportId.value,
                 format: reportFormat.value, fields: all_fields.value };
    // update displayed columns before pulling records
    if (runType.value != 'export') {
      loading.value = true;
      const resp = await ccPost('/api/reports/updateColumns', args);
      if (resp.result) {
        reportColumns.value = [...resp.columns];
      }
    }
    // Add args for the data pull
    args['preview'] = 100;
    args['runtype'] = runType.value;
    args['rpt_only'] = (RPTonly.value=='Active') ? 1 : 0;
    args['zeros'] = (zeroRecs.value=='Active') ? 1 : 0;
// console.log('Getting data for this...');
// console.log(args);
// return;
    if (runType.value == 'preview') {
      // make a tite for the Preview panel
      previewTitle.value = (savedTitle.value != '') ? savedTitle.value
                                                    : selectedConso.name + " : "+masterReport.value.name;
      previewTitle.value += " - From: "+reportDates.fromYM+" To: "+reportDates.toYM;
      try {
        const response = await ccPost('/api/reports/usageData', args);
        if (response.result) {
          report_items.value = [...response.usage];
          if (typeof(response.db_options)!='undefined') {
            optionItems['Dbase'] = [...response.db_options];
          }
//NOTE::
//  This is where We change focus to the preview panel... and(?) close the config panel....
//
          panels.value = ['preview'];
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.log('Error adding:', error);
      }
      loading.value = false;
//
//NOTE:: TODO :: probably needs to work like the DatsetViewer export operation does... instead of get(),
//    :: ( the usage-report-data path doesn't exist ... /api/reports/usageData is now a POST
//
    } else if (runType.value == 'export') {
        let a = document.createElement('a');
        a.target = 'blank';
        a.href = "/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&');
        a.click();
    }
  }
  // update fromYM and toYM when dateRange choice changes
  function changeReportDates() {
    let dates = fromToDates(dateRange.value);
    reportDates.fromYM = dates.from;
    reportDates.toYM = dates.to;
  }
  function goExport() {
    runType = 'export';
    getReportData();
    runType = '';
  }
  watch( () => reportDates.fromYM, (yearmon) => {
      toKey.value++;
      minYM.value = yearmon;
  } );
  watch( selectedFields['institutiontype'], () => {
    selectedFields['institutiongroup'] = [];
    selectedFields['institution'] = [];
  } );
  watch( selectedFields['institutiongroup'], () => {
    selectedFields['institution'] = [];
  } );
  onBeforeMount( () => {
    if (selectedConso.value.length>0) initializeOptions();
  });
</script>
<template>
  <v-expansion-panels multiple class="mt-6 rounded-lg" v-model="panels">
    <v-expansion-panel value='scope' class="rounded-lg border">
      <v-expansion-panel-title>Report Scope</v-expansion-panel-title>
      <v-expansion-panel-text class="rounded-lg">
        <v-row>
          <FlexCol v-if="consortia.length>1 && is_serveradmin">
            <v-label class="colLabel">Choose a Consortium Instance</v-label>
            <v-autocomplete v-model="selectedConso" label="Consortium" :items="consortia" item-title="name" item-value="ccp_key"
                            density="compact" return-object @update:modelValue="handleChangeConso" />
          </FlexCol>
          <FlexCol v-if="selectedConso!='' && savedReports.length>0">
            <v-label class="colLabel">Load a Saved Report</v-label>
            <v-autocomplete v-model="savedReport" label="Saved Reports" :items="savedReports"
                item-title="title" item-value="id" density="compact"
                @update:modelValue="loadPreset('saved')" />
          </FlexCol>
          <FlexCol v-if="previewButtonEnabled">
            <v-btn color="primary" @click="getReportData">Load Preview</v-btn>
          </FlexCol>
          <FlexCol v-if="previewButtonEnabled">
            <v-btn color="primary" @click="saveDialog=true">Save Configuration</v-btn>
          </FlexCol>
        </v-row>
        <v-row v-if="selectedConso==''">
          <h3>Current Consortium not Properly Defined!</h3>
          <p>Check system installation; logging out and back in <strong>might</strong> fix the issue.</p>
        </v-row>
        <v-row v-if="success || failure" class="d-flex pt-2" no-gutters>
          <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </v-row>
        <v-row v-if="selectedConso!=''">
          <!-- Institution Filters -->
          <FlexCol>
            <v-label class="colLabel">Select Institutions</v-label>
            <MultiSelectCombobox v-model="selectedFields['institutiontype']" label="Institution Types"
                itemTitle="name" itemValue="id" :items="optionItems['institutiontype']" />
            <MultiSelectCombobox v-if="optionItems['institutiongroup'].length>0" v-model="selectedFields['institutiongroup']"
                label="Institution Groups" itemTitle="name" itemValue="id" :items="optionItems['institutiongroup']" />
            <MultiSelectCombobox v-model="selectedFields['institution']" label="Institutions"
                itemTitle="name" itemValue="id" :items="optionItems['institution']" />
          </FlexCol>

          <!-- Platform Filters -->
          <FlexCol>
            <v-label class="colLabel">Select Platforms</v-label>
            <MultiSelectCombobox v-model="selectedFields['provider']" label="Platforms" itemTitle="name" itemValue="id"
                                 :items="optionItems['provider']" />
            <v-label v-if="showDatabases" class="colLabel">Select Database</v-label>
            <MultiSelectCombobox v-if="showDatabases" v-model="selectedFields['Dbase']" label="Databases"
                                 itemTitle="name" itemValue="id" :items="optionItems['Dbase']" />
          </FlexCol>

          <!-- Report Type Selection -->
          <FlexCol :lg="3">
            <v-label class="colLabel">Select Report Type</v-label>
            <v-radio-group v-model="masterReport" class="me-8" return-object inline @update:modelValue="selectMaster">
              <v-radio v-for="report in masterReports" :key="report.id" :label="report.legend" :value="report" />
            </v-radio-group>
            <v-expand-transition>
              <div v-if="masterReportId>0">
                <v-radio-group label="Standard View (Optional)" v-model="selectedView" inline
                               @update:modelValue="loadPreset('view')">
                  <v-radio v-for="view in masterReports[masterReportId - 1].children" :key="view.id"
                          :label="view.name" :value="view.id"/>
                </v-radio-group>
              </div>
            </v-expand-transition>
          </FlexCol>

          <!-- Report Dates -->
          <FlexCol :lg="2">
            <v-label class="colLabel">Report Dates</v-label>
            <v-select v-model="dateRange" label="Choose Report Dates" :items="reportOptions" variant="outlined"
                      item-title="title" item-value="value" hide-details @update:modelValue="changeReportDates"/>
            <YmInput v-model="reportDates['fromYM']" label="Start Month"/>
            <YmInput v-model="reportDates['toYM']" label="End Month" :minYM="minYM" :key="toKey"/>
          </FlexCol>
        </v-row>

        <v-divider></v-divider>

        <!-- COUNTER Filters -->
        <v-row v-if="selectedConso!=''" >
          <FlexCol v-if="metricItems['usage'].length>0 || metricItems['search'].length>0 || metricItems['turnaway'].length>0">
            <v-label class="colLabel">Usage Metrics</v-label>
            <MultiSelectCombobox v-if="metricItems['usage'].length>0" v-model="selectedMetrics['usage']"
                                 label="Investigations & Requests" :items="metricItems['usage']"
                                 itemTitle="legend" itemValue="id" :key="'us_'+optionsKey"/>

            <MultiSelectCombobox v-if="metricItems['search'].length>0" v-model="selectedMetrics['search']"
                                 label="Metric Type: Searches" :items="metricItems['search']"
                                 itemTitle="legend" itemValue="id" :key="'ss_'+optionsKey" />

            <MultiSelectCombobox v-if="metricItems['turnaway'].length>0" v-model="selectedMetrics['turnaway']"
                                 label="Turnaways" :items="metricItems['turnaway']"
                                 itemTitle="legend" itemValue="id" :key="'ts_'+optionsKey" />
          </FlexCol>
          <FlexCol v-else></FlexCol>

          <FlexCol v-if="showAccessMethods || showAccessTypes || showDataTypes || showSectionTypes">
            <v-label class="colLabel">Access and Data</v-label>
            <MultiSelectCombobox v-if="showAccessMethods" v-model="selectedFields['accessmethod']" label="Access Methods"
                                 :items="optionItems['accessmethod']" itemTitle="name" itemValue="id" :key="'am_'+optionsKey"/>

            <MultiSelectCombobox v-if="showAccessTypes" v-model="selectedFields['accesstype']" label="Access Types"
                                 :items="optionItems['accesstype']" itemTitle="name" itemValue="id" :key="'at_'+optionsKey"/>

            <MultiSelectCombobox v-if="showDataTypes" v-model="selectedFields['datatype']" label="Data Types"
                                 :items="optionItems['datatype']" itemTitle="name" itemValue="id" :key="'dt_'+optionsKey"/>

            <MultiSelectCombobox v-if="showSectionTypes" v-model="selectedFields['sectiontype']" label="Section Types"
                                 :items="optionItems['sectiontype']" itemTitle="name" itemValue="id" :key="'st_'+optionsKey"/>
          </FlexCol>
          <FlexCol v-else></FlexCol>
          <FlexCol>
            <!-- Report format - CC+ .vs. COUNTER -->
            <v-radio-group label="Report formatting" v-model="reportFormat" inline>
              <v-radio label="CC+ Compact" value='Compact'></v-radio>
              <v-radio label="COUNTER-R5" value='COUNTER'></v-radio>
            </v-radio-group>
            <!-- Toggles for zero-use records and limit to report-period totals -->
            <ToggleIcon v-model="zeroRecs" toggleable :size="36" />
            <v-label class="colLabel">Exclude Zero-use records?</v-label>
            <div v-if="reportFormat=='COUNTER'">
              <ToggleIcon v-model="RPTonly" toggleable :size="36" />
              <v-label class="colLabel">Limit to reporting period totals</v-label>
            </div>
          </FlexCol>
          <!-- Info Columns on/off -->
          <FlexCol :lg="2">
            <v-label class="colLabel">Information Columns</v-label>
            <MultiSelectCombobox v-model="enabledInfoFields" label="Selectable Columns" :items="infoFields"
                                 itemTitle="legend" itemValue="id" :key="'sc_'+optionsKey" />
          </FlexCol>
        </v-row>
      </v-expansion-panel-text>
    </v-expansion-panel>

    <!-- Preview Panel-->
    <v-expansion-panel value='preview' class="rounded-lg border">
      <v-expansion-panel-title>Report Preview</v-expansion-panel-title>
      <v-expansion-panel-text class="rounded-lg">
        <v-row class="d-flex mb-1 align-end" no-gutters>
          <v-col class="d-flex px-4">
            <h3>{{ previewTitle }}</h3>
          </v-col>
          <v-col class="d-flex px-4">
            <v-btn color="green" @click="goExport">Export</v-btn>
          </v-col>
        </v-row>
        <v-data-table :headers="reportColumns" :items="report_items" :loading="loading"
                      :footer-props="footer_props" dense :key="dtKey">
        </v-data-table>
<!--
  NOTE:: do we still need/want to reformat/template the values like this...?

          <template v-for="header in filteredHeaders" v-slot:[`item.${header.value}`]="{ item }">
            <template v-if="header.is_metric==1">
              <span>{{ parseInt(item[header.value]).toLocaleString("en-US") }}</span>
            </template>
            <template v-else><span>{{ item[header.value] }}</span></template>
          </template>
        </v-data-table>
-->
      </v-expansion-panel-text>
    </v-expansion-panel>
  </v-expansion-panels>

  <!-- Save configuration dialog -->
  <v-dialog v-model="saveDialog">
    <v-card>
      <v-card-title>
        <span>Save Report Configuration</span>
        <v-spacer></v-spacer>
      </v-card-title>
      <v-card-text>
        <SaveReport :saved_reports="savedReports" @save="saveConfig" @cancel="saveDialog=false" />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style>
.align-mid { align-items: center; }
</style>
