<template>
  <div class="ma-0 pa-0">
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col v-if="conso.length>0" class="d-flex px-1">
        <h1>Usage Report Preview : {{ conso }}</h1>
      </v-col>
      <v-col v-else class="d-flex px-1">
        <h1>Usage Report Preview</h1>
      </v-col>
    </v-row>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col v-if="title != ''" class="d-flex px-1">
        <h3>{{ title }}</h3>
      </v-col>
    </v-row>
    <div class="d-flex flex-row mb-2">
      <div v-if="mutable_rangetype=='' || mutable_rangetype=='Custom'" class="d-flex pa-2">
        <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
        ></date-range>
      </div>
      <div v-else class="d-flex pa-2 align-center">
        <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('dateRange')"/>&nbsp;
        <strong>Preset Date Range</strong>: {{ mutable_rangetype }}
      </div>
      <div class="d-flex pa-2">
        <v-switch v-model="zeroRecs" label="Exclude Zero-Use Records?"></v-switch>
      </div>
    </div>
    <div>
      <v-radio-group v-model="format" @change="changeFormat" row>
        <template v-slot:label>
          <strong>Report formatting</strong>
        </template>
        <v-radio label="CC+ Compact" value='Compact'></v-radio>
        <v-radio label="COUNTER-R5" value='COUNTER'></v-radio>
      </v-radio-group>
    </div>
    <v-expansion-panels multiple focusable :value="panels">
      <v-expansion-panel>
        <v-expansion-panel-header>Show/Hide Columns</v-expansion-panel-header>
        <v-expansion-panel-content>
          <v-row class="d-flex wrap-column-boxes ma-0" no-gutters>
            <v-col class="d-flex pa-2 justify-center" cols="6">
              <v-btn class='btn' small type="button" color="primary" @click="setAllColumns(1)">Enable All</v-btn>
            </v-col>
            <v-col class="d-flex pa-2 justify-center" cols="6">
              <v-btn class='btn' small type="button" color="primary" @click="setAllColumns(0)">Disable All</v-btn>
            </v-col>
          </v-row>
          <v-row class="d-flex wrap-column-boxes ma-0" no-gutters>
            <v-col class="d-flex pa-2" cols="2" v-for="field in mutable_fields" :key="field.id">
              <v-checkbox :label="field.text" v-model="field.active" :value="field.active"
                          @change="onFieldChange(field)" :disabled="field.isFiltered"></v-checkbox>
            </v-col>
          </v-row>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <v-expansion-panel>
        <v-expansion-panel-header>Filters</v-expansion-panel-header>
        <v-expansion-panel-content>
          <v-row v-if="active_filter_count > 0" class="d-flex ma-1 wrap-filters" no-gutters>
            <div v-if='filter_data["Dbase"].active' cols="3">
              <v-col v-if='filter_data["Dbase"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["Dbase"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('Dbase')"/>&nbsp;
                <v-autocomplete :items='mutable_filter_options.database' v-model='filter_data.Dbase.value' multiple
                                @change="setFilter('Dbase')" label="Database" item-text="name" item-value="id"
                ></v-autocomplete>
              </v-col>
            </div>
            <div v-if='filter_data["platform"].active' cols="3">
              <v-col v-if='filter_data["platform"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["platform"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('platform')"/>&nbsp;
                <v-autocomplete :items='mutable_filter_options.platform' v-model='filter_data.platform.value' multiple
                                @change="setFilter('platform')" label="Platform" item-text="name" item-value="id"
                ></v-autocomplete>
              </v-col>
            </div>
            <div v-if='filter_data["provider"].active' cols="3">
              <v-col v-if='filter_data["provider"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["provider"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('provider')"/>&nbsp;
                <v-autocomplete :items='mutable_filter_options.provider' v-model='filter_data.provider.value' multiple
                                @change="setFilter('provider')" label="Provider" item-text="name" item-value="id"
                ></v-autocomplete>
              </v-col>
            </div>
            <div v-if='filter_data["institution"].active' cols="3">
              <v-col v-if='filter_data["institution"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["institution"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('institution')"/>&nbsp;
                <v-autocomplete :items='mutable_filter_options.institution' v-model='filter_data.institution.value' multiple
                                @change="setFilter('institution')" label="Institution" item-text="name" item-value="id"
                ></v-autocomplete>
              </v-col>
            </div>
            <div v-if='filter_data["institutiongroup"].active' cols="3">
              <v-col v-if='filter_data["institutiongroup"].value >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["institutiongroup"].value > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('institutiongroup')"/>&nbsp;
                <v-autocomplete :items='mutable_filter_options.institutiongroup' v-model='filter_data.institutiongroup.value'
                                @change="setFilter('institutiongroup')" label="Institution Group"
                                item-text="name" item-value="id"
                ></v-autocomplete>
              </v-col>
            </div>
            <div v-if='filter_data["datatype"].active' cols="3">
              <v-col v-if='filter_data["datatype"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["datatype"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('datatype')"/>&nbsp;
                <v-select :items='mutable_filter_options.datatype' v-model='filter_data.datatype.value' multiple
                          @change="setFilter('datatype')" label="Data Type" item-text="name" item-value="id"
                ></v-select>
              </v-col>
            </div>
            <div v-if='filter_data["sectiontype"].active' cols="3">
              <v-col v-if='filter_data["sectiontype"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["sectiontype"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('sectiontype')"/>&nbsp;
                  <v-select :items='mutable_filter_options.sectiontype' v-model='filter_data.sectiontype.value' multiple
                            @change="setFilter('sectiontype')" label="Section Type" item-text="name" item-value="id"
                ></v-select>
              </v-col>
            </div>
            <div v-if='filter_data["accesstype"].active' cols="3">
              <v-col v-if='filter_data["accesstype"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["accesstype"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('accesstype')"/>&nbsp;
                <v-select :items='mutable_filter_options.accesstype' v-model='filter_data.accesstype.value' multiple
                          @change="setFilter('accesstype')" label="Access Type" item-text="name" item-value="id"
                ></v-select>
              </v-col>
          </div>
            <div v-if='filter_data["accessmethod"].active' cols="3">
              <v-col v-if='filter_data["accessmethod"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["accessmethod"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('accessmethod')"/>&nbsp;
                <v-select :items='mutable_filter_options.accessmethod' v-model='filter_data.accessmethod.value' multiple
                          label="Access Method" @change="setFilter('accessmethod')" item-text="name" item-value="id"
                ></v-select>
              </v-col>
            </div>
            <div v-if='filter_data["yop"].active' cols="3">
              <v-col v-if='filter_data["yop"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["yop"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('yop')"/>&nbsp;
                     <v-text-field label="YOP from" v-model="filter_data['yop'].value[0]" @change="setYOP()">
                     </v-text-field>&nbsp;
                     <v-text-field label="YOP to" v-model="filter_data['yop'].value[1]" @change="setYOP()">
                     </v-text-field>
              </v-col>
            </div>
          </v-row>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>
    <v-row class="d-flex px-4" no-gutters>
      <v-col class="d-flex px-2 pt-6" cols="3">
        <v-btn class='btn' small type="button" color="primary" @click="previewData">{{ preview_text }}</v-btn>
      </v-col>
      <v-col class="d-flex px-2 pt-6" cols="3">
        <v-btn class='btn' small type="button" color="primary" @click="showForm">Save Configuration</v-btn>
      </v-col>
      <v-col class="d-flex px-2 pt-6" cols="3">
        <v-btn class='btn' small type="button" color="green" @click="goExport">Export</v-btn>
      </v-col>
      <v-col v-if="format=='COUNTER' || (filter_by_fromYM < filter_by_toYM)" class="d-flex pa-0" cols="3">
        <v-switch v-model="RPTonly" label="Limit to reporting period totals"></v-switch>
      </v-col>
    </v-row>
    <div v-if="!configForm" class="status-message">
      <v-row v-if="success || failure" class="d-flex pt-2" no-gutters>
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </v-row>
    </div>
    <v-container v-if="showPreview" fluid>
      <v-data-table :headers="filteredHeaders" :items="report_data" :loading="loading" :options="mutable_options"
                    :footer-props="footer_props" dense @update:options="updateOptions" :key="dtKey" class="elevation-1">
        <template v-for="header in filteredHeaders" v-slot:[`item.${header.value}`]="{ item }">
          <template v-if="header.is_metric==1">
            <span>{{ parseInt(item[header.value]).toLocaleString("en-US") }}</span>
          </template>
          <template v-else><span>{{ item[header.value] }}</span></template>
        </template>
      </v-data-table>
    </v-container>
    <v-dialog v-model="configForm" max-width="900px">
      <v-card>
        <v-card-title>
          <span>Save Report Configuration</span>
          <v-spacer></v-spacer>
        </v-card-title>
        <v-card-text>
          <v-container fluid grid-list-md>
            <form method="POST" action="" @submit.prevent="" @keydown="form.errors.clear($event.target.name)">
              <v-row class="d-flex pa-2" no-gutters>
                <v-col v-if="form.save_id==input_save_id" class="d-flex px-2" cols="5">
                  <h5>Create a new saved configuration</h5>
                </v-col>
                <v-col v-if="form.title=='' && saved_reports.length>0" class="d-flex px-2" cols="2">&nbsp;</v-col>
                <v-col v-if="form.title=='' && saved_reports.length>0" class="d-flex px-2" cols="5">
                  <h5>Overwrite a saved configuration</h5>
                </v-col>
              </v-row>
              <v-row class="d-flex pa-2" width="100%" no-gutters>
                <v-col v-if="form.save_id==input_save_id" class="d-flex" cols="5">
                  <input name="save_id" id="save_id" value=0 type="hidden">
                  <v-text-field v-model="form.title" label="Name" outlined></v-text-field>
                </v-col>
                <v-col v-if="form.title=='' && saved_reports.length>0 && form.save_id==input_save_id"
                       class="d-flex justify-center px-2" cols="2">
                  <h5>OR</h5>
                </v-col>
                <v-col v-if="form.title=='' && saved_reports.length>0" class="d-flex px-2" cols="5">
                  <input id="title" name="title" value="" type="hidden">
                  <v-select :items='saved_reports' v-model='form.save_id' label="Saved Report" item-text="title"
                            item-value="id"></v-select>
                </v-col>
              </v-row>
            </form>
          </v-container>
        </v-card-text>
        <v-spacer></v-spacer>
        <v-card-actions>
          <v-row class="d-flex justify-center" no-gutters>
            <v-col class="d-flex px-2" cols="3">
              <v-btn class='btn' small type="button" color="green" @click="saveConfig">Save</v-btn>
            </v-col>
            <v-col class="d-flex px-2" cols="3">
              <v-btn class='btn' small type="button" @click="hideForm">Cancel</v-btn>
            </v-col>
          </v-row>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import Form from '@/js/plugins/Form';
  window.Form = Form;
  export default {
    props: {
        preset_filters: { type:Object, default: () => {} },
        columns: { type:Array, default: () => [] },
        fields: { type:Array, default: () => [] },
        saved_reports: { type:Array, default: () => [] },
        filter_options: { type:Object, default: () => {} },
        input_save_id: { type:Number, default: 0 },
        rangetype: { type:String, default: '' },
        title: { type:String, default: '' },
        conso: { type:String, default: '' },
    },
    data () {
      return {
        showPreview: false,
        configForm: false,
        preview_text: 'Display Preview',
        loading: true,
        panels: [1],
        minYM: '',
        maxYM: '',
        dtKey: 1,
        rangeKey: 1,
        active_filter_count: 0,
        zeroRecs: 1,
        footer_props: {
            'items-per-page-options': [10, 20, 50, 100],
        },
        report_data: [],
        filter_data: {
          Dbase: { col:'db_id', act:'updateDataBase', value:[], name:'', active: false },
          platform: { col:'plat_id', act:'updatePlatform', value:[], name:'', active: false },
          provider: { col:'prov_id', act:'updateProvider', value:[], name:'', active: false },
          institution: { col:'inst_id', act:'updateInstitution', value:[], name:'', active: false },
          institutiongroup: { col:'institutiongroup_id', act:'updateInstGroup', value:0, name:'', active: false },
          datatype: { col:'datatype_id', act:'updateDataType', value: [], name:'', active: false },
          sectiontype: { col:'sectiontype_id', act:'updateSectionType', value: [], name:'', active: false },
          accesstype: { col:'accesstype_id', act:'updateAccessType', value: [], name:'', active: false },
          accessmethod: { col:'accessmethod_id', act:'updateAccessMethod', value: [], name:'', active: false },
          yop: { col:'yop', act:'updateYop', value:[], name:'', active: false },
        },
        mutable_fields: this.fields,
        mutable_cols: this.columns,
        mutable_rangetype: this.rangetype,
        mutable_filter_options: {},
        mutable_options: {},
        RPTonly: false,
        cur_year: '',
        success: '',
        failure: '',
        runtype: '',
        format: 'Compact',
        form: new window.Form({
            title: '',
            save_id: this.input_save_id,
        })
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to update filter options
          this.updateColumns();
        }
      },
    },
    methods: {
        previewData (event) {
            this.runtype = 'preview';
            if (!this.showPreview) {
                this.showPreview = true;
                this.preview_text = 'Refresh Preview';
            }
            this.getReportData().then(data => {
                  this.report_data = data.items;
            });
        },
        // Enable / Disable columns in the preview
        onFieldChange(field) {
          if (typeof(this.filter_data[field.id]) != 'undefined') {    // column has a filter?
              var hasFilter=true;
              var action = this.filter_data[field.id].act+'Filter';
          } else {
              var hasFilter=false;
          }

          // Turning on a field...
          if (field.active) {
              // If the field has filter, set it up
              if (hasFilter) {
                  // Turn on institution filter only if admin/manager AND not filtering by-Group
                  if (field.id == 'institution') {
                      // Enable institution and institutionGroup filtering
                      if (this.is_admin || this.is_viewer) {
                          this.filter_data.institution.active = true;
                          this.filter_data.institution.value = [];
                          this.filter_data.institutiongroup.active = true;
                          this.filter_data.institutiongroup.value = 0;
                          // action only for institution, not group (group isn't a column field)
                          this.$store.dispatch(action,[]);
                          this.active_filter_count += 2;
                      }
                  // Initialize other filter values
                  } else {
                      this.filter_data[field.id].active = true;
                      if (this.filter_data[field.id].value.constructor === Array) {
                          this.filter_data[field.id].value = [];
                          this.$store.dispatch(action,[]);
                      } else {
                          this.filter_data[field.id].value = 0;
                          this.$store.dispatch(action,0);
                      }
                      this.active_filter_count++;
                  }

                  // Update the columns
                  this.updateColumns();
              }
              // Turn on the column(s)
              for (var col in this.mutable_cols) {
                  if (this.mutable_cols[col].field == field.id) this.mutable_cols[col].active = 1;
              }
          // Turning off a field...
          } else {
              // If the field has filter, clean it up
              if (hasFilter) {
                  this.filter_data[field.id].active = false;
                  if (field.id == "institution") {
                      this.filter_data.institutiongroup.active = false;
                      this.filter_data.institutiongroup.value = 0;
                      this.active_filter_count--;
                  }
                  if (this.filter_data[field.id].value.constructor === Array) {
                      this.filter_data[field.id].value = [];
                  } else {
                      this.filter_data[field.id].value = 0;
                  }
                  this.$store.dispatch(action,this.filter_data[field.id].value);
                  this.updateColumns();
                  if (field.active) this.active_filter_count--;
              }
              // Turn off the column(s)
              for (var col in this.mutable_cols) {
                  if (this.mutable_cols[col].field == field.id) this.mutable_cols[col].active = 0;
              }
          }
        },
        clearFilter(filter) {
            // Treat preset date range as a filter for UI
            // inbound: set to whatever was saved; cleared: show date-selectors instead
            if (filter == 'dateRange') {
                // If we're clearing and displaying dropdowns, the type is now Custom
                this.mutable_rangetype = 'Custom';
                return;
            }
            let method = this.filter_data[filter].act+'Filter';
            if (this.filter_data[filter].value.constructor === Array) {
                this.$store.dispatch(method, []);
                this.filter_data[filter].value = [];
            } else {
                this.$store.dispatch(method, 0);
                this.filter_data[filter].value = 0;
            }
            this.filter_data[filter].name = '';
            let _field = this.mutable_fields.find( f => f.id == filter);
            if (typeof(_field)!='undefined') _field.isFiltered = false;
            if (filter == 'institution') {
                this.filter_data.institutiongroup.active = true;
            } else if (filter == 'institutiongroup') {
                this.filter_data.institution.active = true;
            } 
          },
        setFilter(filter) {
            let method = this.filter_data[filter].act+'Filter';
            this.$store.dispatch(method, this.filter_data[filter].value);
            let _field = this.mutable_fields.find( f => f.id == filter);
            if (this.filter_data[filter].value.constructor != Array) {
                let idx = this.mutable_filter_options[filter].findIndex(f => f.id==this.filter_data[filter].value);
                this.filter_data[filter].name = this.mutable_filter_options[filter][idx].name;
                if (typeof(_field)!='undefined') _field.isFiltered = (this.filter_data[filter].value > 0)
            } else {
                if (typeof(_field)!='undefined') _field.isFiltered = (this.filter_data[filter].value.length > 0)
            }
            if (filter == 'institution') {
                this.filter_data.institutiongroup.active = (this.filter_data.institution.value.length>0) ? false : true;
                this.filter_data.institutiongroup.value = 0;
            } else if (filter == 'institutiongroup') {
                this.filter_data.institution.active = (this.filter_data.institutiongroup.value>0) ? false : true;
                this.filter_data.institution.value = [];
            } 
        },
        setYOP() {
            this.failure = "";
            this.filter_data.yop.value.forEach((val, idx) => {
                if (!isNaN(val)) return;
                this.failure = "Only numbers allowed for YOP From-To values.";
                this.filter_data.yop.value[idx] = '';
            });
            if (this.filter_data.yop.value[0] == '') this.filter_data.yop.value[1] == '';
            if (this.filter_data.yop.value[0] == '' && this.filter_data.yop.value[1] == '') {
                this.filter_data['yop'].value = [0];
                this.$store.dispatch('updateYopFilter', [0]);
                return;
            }
            // Set Empty To to cur_year
            if (this.filter_data.yop.value[1] == '') this.filter_data.yop.value[1] = this.cur_year;
            // Empty From gets To
            if (this.filter_data.yop.value[0] == '') this.filter_data.yop.value[0] = this.filter_data.yop.value[1];
            // From>To throws error, To resets to current year
            if (this.filter_data.yop.value[0] > this.filter_data.yop.value[1]) {
                this.failure = "YOP:To automatically reset to "+this.cur_year;
                this.filter_data.yop.value[1] = this.cur_year;
            }
            this.$store.dispatch('updateYopFilter', this.filter_data.yop.value);
        },
        getReportData () {
          if (this.runtype != 'export') {
              this.loading = true;
          }

          //copy current params to modify
          let params = this.params;
          params['filters'] = JSON.stringify(this.all_filters);
          let _flds = {};
          this.mutable_fields.forEach(fld => {
            var fval = (typeof(this.filter_data[fld.id])=='undefined') ? '' : this.filter_data[fld.id].value;
            _flds[fld.id] = {active: fld.active, limit: fval};
          })
          params['fields'] = JSON.stringify(_flds);
          params['zeros'] = this.zeroRecs;
          params['format'] = this.format;
          params['RPTonly'] = this.RPTonly;

          if (this.runtype != 'export') {   // currently only other value is 'preview'
              return new Promise((resolve, reject) => {
                axios.get("/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&'))
                                .then((response) => {
                    let items = response.data.usage;
                    resolve({items});
                    // update platform filter options if they arrived with the data
                    if (typeof(response.data.pf_options)!='undefined') {
                        this.mutable_filter_options['platform'] = [ ...response.data.pf_options ];
                    }
                    // update database filter options if they arrived with the data
                    if (typeof(response.data.db_options)!='undefined') {
                        this.mutable_filter_options['database'] = [ ...response.data.db_options ];
                    }
                    this.loading = false;
                    this.runtype = '';
                })
                .catch(err => console.log(err));
              });
          } else {
              let a = document.createElement('a');
              a.target = 'blank';
              a.href = "/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&');
              a.click();
          }
        },
        changeFormat () {
          this.updateColumns();
        },
        updateColumns () {
          var self = this;
          axios.post('/update-report-columns', {
              filters: this.all_filters,
              fields: this.mutable_fields,
              format: this.format
          })
          .then( function(response) {
              if (response.data.result) {
                  self.mutable_cols = response.data.columns;
                  self.rangeKey += 1;           // force re-render of the date-range component
              } else {
                  self.failure = response.data.msg;
              }
          })
          .catch(error => {});
        },
        updateOptions(options) {
            if (Object.keys(this.mutable_options).length === 0) return;
            Object.keys(this.mutable_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_options[key]) {
                    this.mutable_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_options);
        },
        setAllColumns(flag) {
            this.mutable_fields.forEach( field => {
                // skip any fields with an active filter
                if ( field.isFiltered ) {
                    if (typeof(this.filter_data[field.id])!='undefined') {
                        this.filter_data[field.id].active = true;
                    }
                } else {
                    field.active = (flag == 1);
                    this.onFieldChange(field);
                }
            });
        },
        showForm (event) {
            this.configForm = true;
        },
        hideForm (event) {
            this.form.title = '';
            this.form.save_id = this.input_save_id;
            this.configForm = false;
        },
        saveConfig() {
            if (this.form.title=='' && this.form.save_id==0) {
                this.failure = 'A name is required to save the configuration';
                return;
            }
            let _flds = {};
            this.mutable_fields.forEach(fld => {
              var fval = (typeof(this.filter_data[fld.id])=='undefined') ? '' : this.filter_data[fld.id].value;
              _flds[fld.id] = {active: fld.active, limit: fval};
            })
            if (this.this.filter_data.institutiongroup.value > 0) {   // If filtering by-inst-group, add to the cols array
                _flds['institutiongroup'] = {active: false, limit: this.filter_data.institutiongroup.value};
            }
            let num_months = 1;     // default to lastMonth
            if (this.preset_filters.dateRange == 'latestYear') {
                num_months = 12;
            } else if (this.preset_filters.dateRange == 'Custom') {
                var from_parts = this.filter_by_fromYM.split("-");
                var to_parts = this.filter_by_toYM.split("-");
                var fromDate = new Date(from_parts[0], from_parts[1]-1, 1);
                var toDate = new Date(to_parts[0], to_parts[1]-1, 1);
                num_months = toDate.getMonth() - fromDate.getMonth() +
                         (12 * (toDate.getFullYear() - fromDate.getFullYear())) + 1;
            }
            axios.post('/my-reports', {
                title: this.form.title,
                save_id: this.form.save_id,
                report_id: this.all_filters.report_id,
                date_range: this.preset_filters.dateRange,
                from: this.filter_by_fromYM,
                to: this.filter_by_toYM,
                exclude_zeros: this.zeroRecs,
                format: this.format,
                fields: JSON.stringify(_flds),
            })
            .then((response) => {
                if (response.data.result) {
                    this.success = response.data.msg;
                } else {
                    this.failure = response.data.msg;
                }
                this.configForm = false;
            })
            .catch(error => {});
        },
        goExport() {
            this.runtype = 'export';
            this.getReportData();
            this.runtype = '';
        },
    },
    computed: {
      ...mapGetters(['is_admin','is_viewer','all_filters','filter_by_fromYM','filter_by_toYM','datatable_options']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
      params(nv) {  // Computed params to return pagination and settings
        return {
            preview: 100,
            runtype: this.runtype,
            report_id: this.all_filters.report_id,
        };
      },
      filteredHeaders() {
        // Limit to reporting period totals only?
        if (this.RPTonly) {
          return (this.format == 'Compact')
                 ? this.mutable_cols.filter(h => h.active && (h.value.substr(0,3)=='RP_' || h.is_metric==0) )
                 : this.mutable_cols.filter(h => h.active && (h.value=='Reporting_Period_Total' || h.is_metric==0));
        } else {
          return this.mutable_cols.filter(h => h.active);
        }
      },
    },
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','preview');
  	},
    mounted() {
      // Subscribe store to local storage
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      // Copy filter options into a mutable array
      this.mutable_filter_options = Object.assign({}, this.filter_options);

      // Set initial filter-state for inactive "filterable" columns, and count the active ones
      this.mutable_cols.forEach(col => {
        let idx = col.value;
        if (typeof(this.filter_data[idx]) != 'undefined') {    // filtered column?
            var action = this.filter_data[idx].act+'Filter';
            if (col.active) {
                if (this.filter_data[idx].value.constructor === Array) {
                    this.filter_data[idx].value = [];
                } else {
                    this.filter_data[idx].value = 0;
                }
                this.$store.dispatch(action,this.filter_data[idx].value);
                this.filter_data[idx].active = true;
                this.active_filter_count++;
            } else {
                if (this.filter_data[idx].value.constructor === Array) {
                    this.filter_data[idx].value = [];
                    this.$store.dispatch(action,[]);
                } else {
                    this.filter_data[idx].value = 0;
                    this.$store.dispatch(action,0);
                }
            }
        }
      });
      // Assign preset filter values
      for (let [key, data] of Object.entries(this.filter_data)) {
          let filt = data.act+'Filter';
          if (typeof(this.preset_filters[data.col]) != 'undefined') {
              this.$store.dispatch(filt,this.preset_filters[data.col]);
              if (this.preset_filters[data.col].constructor === Array) {
                  data.value = this.preset_filters[data.col].slice();
              } else {
                  if (this.preset_filters[data.col] > 0) {
                    data.value = this.preset_filters[data.col];
                    let idx = this.mutable_filter_options[key].findIndex(f => f.id==data.value);
                    data.name = this.mutable_filter_options[key][idx].name;
                  }
              }
          // filter_data column not in preset array, reset it in the store
          } else {
              if (data.value.constructor === Array) {
                  this.$store.dispatch(filt,[]);
              } else {
                  this.$store.dispatch(filt,0);
              }
          }
      }

      // initialize isFiltered values for the fields
      this.mutable_fields.forEach( field => {
          if (typeof(this.filter_data[field.id]) != 'undefined') {
              if (this.filter_data[field.id].value.constructor === Array) {
                  field.isFiltered = (this.filter_data[field.id].value.length>0);
              } else {
                  field.isFiltered = (this.filter_data[field.id].value>0);
              }
          } else {
              field.isFiltered = false;
          }
      });

      // If preset given for group AND inst, we will filter by-group and override inst(s).
      // If no preset provided for either, we will (initially) show filters both.
      if (this.is_admin || this.is_viewer) {
          if (this.preset_filters['institutiongroup_id']>0) {
            this.filter_data.institutiongroup.active = true;
            this.filter_data.institutiongroup.value = this.preset_filters['institutiongroup_id'];
            this.active_filter_count += (this.filter_data.institution.active) ? 0 : 1;
            this.filter_data.institution.active = false;
            this.filter_data.institution.value = [];
          }
          // If institution or group is active but unset, and the other is not active,
          // enable and initialize the other one.
          if (this.filter_data.institution.active && this.filter_data.institution.value.length==0 &&
             !this.filter_data.institutiongroup.active) {
            this.filter_data.institutiongroup.active = true;
            this.filter_data.institutiongroup.value = 0;
            this.active_filter_count++;
          }
          if (this.filter_data.institutiongroup.active && this.filter_data.institutiongroup.value <= 0 &&
             !this.filter_data.institution.active) {
            this.filter_data.institution.active = true;
            this.filter_data.institution.value = [];
            this.active_filter_count++;
          }
      }
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);

      // Assign preset report_id, and from/to date fields to the store variables
      this.$store.dispatch('updateReportId',this.preset_filters['report_id']);
      this.$store.dispatch('updateFromYM',this.preset_filters['fromYM']);
      this.$store.dispatch('updateToYM',this.preset_filters['toYM']);
      if (this.mutable_rangetype == 'latestYear') this.mutable_rangetype = "Up to latest 12 months";
      if (this.mutable_rangetype == 'latestMonth') this.mutable_rangetype = "Most recent available month";

      // Set options for all filters and in the datastore
      this.dtKey += 1;           // force re-render of the datatable component
      this.rangeKey += 1;        // force re-render of the date-range component

      // Get current year
      this.cur_year = (new Date()).getFullYear();
      console.log('ReportPreview Component mounted.');
    }
  }
</script>
<style>
.wrap-column-boxes {
    flex-flow: row wrap;
    align-items: flex-end;
 }
 .wrap-filters {
     flex-flow: row wrap;
     align-items: center;
  }
</style>
