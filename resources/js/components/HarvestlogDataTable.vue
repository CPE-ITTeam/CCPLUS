<template>
  <div>
    <v-row no-gutters>
      <v-col class="d-flex" cols="3">&nbsp;</v-col>
      <v-col class="d-flex align-center" cols="3">
        <v-btn class='btn' small color="primary" @click="updateLogRecords()">{{ update_button }}</v-btn>
      </v-col>
      <v-col class="d-flex align-center" cols="3">
        <v-btn class='btn' small type="button" @click="clearAllFilters()">Clear Filters</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details clearable
        ></v-text-field>
      </v-col>
    </v-row>
    <v-row no-gutters>
      <v-col v-if="is_admin || is_viewer" class="d-flex px-2 align-center" cols="2">
        <v-switch v-model="conso_switch" dense label="Limit to Consortium" @change="updateConsoOnly()"></v-switch>
      </v-col>
      <v-col v-else class="d-flex px-2" cols="2">
        <div v-if="mutable_filters['updated']!=null && mutable_filters['updated']!=''" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('updated')"/>&nbsp;
        </div>
        <v-select :items="mutable_updated" v-model="mutable_filters['updated']" @change="updateFilters('updated')"
                  label="Updated"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['providers'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('providers')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_options['providers']" v-model="mutable_filters['providers']"
                        @change="updateFilters('providers')" multiple label="Platform(s)" item-text="name" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('providers')">
               <span v-if="allSelected.providers">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index==0 && allSelected.providers">All Platforms</span>
            <span v-else-if="index==0 && !allSelected.providers">{{ item.name }}</span>
            <span v-else-if="index===1 && !allSelected.providers" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['providers'].length-1 }} more
            </span>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col v-if="(is_admin || is_viewer) && institutions.length>1 && (inst_filter==null || inst_filter=='I')"
             class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['institutions'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('institutions')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_options['institutions']" v-model="mutable_filters['institutions']"
                        @change="updateFilters('institutions')" multiple label="Institution(s)"  item-text="name" item-value="id">
          <template v-if="is_admin || is_viewer" v-slot:prepend-item>
            <v-list-item @click="filterAll('institutions')">
               <span v-if="allSelected.institutions">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-if="is_admin || is_viewer" v-slot:selection="{ item, index }">
            <span v-if="index==0 && allSelected.institutions">
              All Institutions
            </span>
            <span v-else-if="index==0 && !allSelected.institutions">{{ item.name }}</span>
            <span v-else-if="index===1 && !allSelected.institutions" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['institutions'].length-1 }} more
            </span>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col v-if="(is_admin || is_viewer) && groups.length>1 && (inst_filter==null || inst_filter=='G')"
             class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['groups'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('groups')"/>&nbsp;
        </div>
        <v-autocomplete :items="groups" v-model="mutable_filters['groups']" @change="updateFilters('groups')" multiple
                        label="Institution Group(s)"  item-text="name" item-value="id">
          <template v-if="is_admin || is_viewer" v-slot:prepend-item>
            <v-list-item @click="filterAll('groups')">
               <span v-if="allSelected.groups">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-if="is_admin || is_viewer" v-slot:selection="{ item, index }">
            <span v-if="index==0 && allSelected.groups">All Groups</span>
            <span v-else-if="index==0 && !allSelected.groups">{{ item.name }}</span>
            <span v-else-if="index===1 && !allSelected.groups" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['groups'].length-1 }} more
            </span>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['reports'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('reports')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['reports']" v-model="mutable_filters['reports']" multiple
                  @change="updateFilters('reports')" label="Report(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['yymms'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('yymms')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_options['yymms']" v-model="mutable_filters['yymms']"
                        @change="updateFilters('yymms')" multiple label="Usage Date(s)" item-text="name" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('yymms')">
               <span v-if="allSelected.yymms">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index==0 && allSelected.yymms">All Months</span>
            <span v-else-if="index==0 && !allSelected.yymms">{{ item }}</span>
            <span v-else-if="index===1 && !allSelected.yymms" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['yymms'].length-1 }} more
            </span>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col v-if="!is_admin && !is_viewer" class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['codes'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('codes')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['codes']" v-model="mutable_filters['codes']" @change="updateFilters('codes')" multiple
                  label="Error Code">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('codes')">
               <span v-if="allSelected.codes">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index == 0 && allSelected.codes">All Error Codes</span>
            <span v-else-if="index < 2 && !allSelected.codes">{{ item }}</span>
            <span v-else-if="index === 2 && !allSelected.codes" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['codes'].length-2 }} more
            </span>
            <span v-if="index <= 1 && index < mutable_filters['codes'].length-1 && !allSelected.codes">, </span>
          </template>
        </v-select>
      </v-col>
      <v-col v-if="!is_admin && !is_viewer" class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['harv_stat'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('harv_stat')"/>&nbsp;
        </div>
        <v-select :items="harv_stat" v-model="mutable_filters['harv_stat']" @change="updateFilters('harv_stat')"
                  multiple label="Status(es)" item-text="opt" item-value="id"
        ></v-select>
      </v-col>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex px-2 align-center" cols="3">
        <div v-if="datesFromTo!='|'" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear date range" @click="clearFilter('date_range')"/>&nbsp;
        </div>
        <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
        ></date-range>
      </v-col>
      <v-col v-if="is_admin && is_viewer" class="d-flex px-2" cols="2">
        <div v-if="mutable_filters['updated']!=null && mutable_filters['updated']!=''" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('updated')"/>&nbsp;
        </div>
        <v-select :items="mutable_updated" v-model="mutable_filters['updated']" @change="updateFilters('updated')"
                  label="Updated"
        ></v-select>
      </v-col>
      <v-col v-if="truncatedResult" class="d-flex px-2 align-center" cols="3">
        <span class="fail" role="alert">Result Truncated To 500 Records</span>
      </v-col>
      <v-col v-else class="d-flex" cols="3">&nbsp;</v-col>
      <v-col v-if="is_admin && is_viewer" class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['codes'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('codes')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['codes']" v-model="mutable_filters['codes']" @change="updateFilters('codes')" multiple
                  label="Error Code">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('codes')">
               <span v-if="allSelected.codes">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index == 0 && allSelected.codes">All Error Codes</span>
            <span v-else-if="index < 2 && !allSelected.codes">{{ item }}</span>
            <span v-else-if="index === 2 && !allSelected.codes" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['codes'].length-2 }} more
            </span>
            <span v-if="index <= 1 && index < mutable_filters['codes'].length-1 && !allSelected.codes">, </span>
          </template>
        </v-select>
      </v-col>
      <v-col v-if="is_admin && is_viewer" class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['harv_stat'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('harv_stat')"/>&nbsp;
        </div>
        <v-select :items="harv_stat" v-model="mutable_filters['harv_stat']" @change="updateFilters('harv_stat')"
                  multiple label="Status(es)" item-text="opt" item-value="id"
        ></v-select>
      </v-col>
    </v-row>
    <div v-if='is_admin || is_manager'>
      <v-row class="d-flex pa-1 align-center" no-gutters>
        <v-col v-if='is_admin || is_manager' class="d-flex px-2" cols="3">
          <v-select :items='bulk_actions' v-model='bulkAction' @change="processBulk()" label="Bulk Actions"
                    :disabled='selectedRows.length==0'></v-select>
        </v-col>
        <v-col v-if='is_admin || is_manager' class="d-flex px-4 align-center" cols="3">
          <span v-if="selectedRows.length>0" class="form-fail">( Will affect {{ selectedRows.length }} rows )</span>
          <span v-else>&nbsp;</span>
        </v-col>
      </v-row>
      <v-row v-if='(success || failure)' class="status-message" no-gutters>
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </v-row>
    </div>
    <v-data-table v-if='is_admin || is_manager' v-model="selectedRows" :headers="headers" :items="mutable_harvests"
                  :loading="loading" show-select item-key="id" :options="mutable_dt_options" @update:options="updateOptions"
                  :footer-props="footer_props" :key="dtKey" :search="search">
      <template v-slot:top="{ pagination, options, updateOptions }">
        <v-data-footer :pagination="pagination" :options="mutable_dt_options" @update:options="updateOptions"
                       items-per-page-text="$vuetify.dataTable.itemsPerPageText" :items-per-page-options="dt_page_options"/>
      </template>
      <template v-slot:item.prov_name="{ item }">
        <span v-if="item.prov_inst_id==1">
          <v-icon title="Consortium Platform">mdi-account-multiple</v-icon>&nbsp;
        </span>
        {{ item.prov_name.substr(0,63) }}
        <span v-if="item.prov_name.length>63">...</span>
      </template>
      <template v-slot:item.id="{ item }">
        <span v-if="item.rawfile!=null">{<a title="Downloaded JSON" :href="'/harvests/'+item.id+'/raw'">{{ item.id }}</a>}</span>
        <span v-else>{{ item.id }}</span>
        <v-icon title="Manual Retry/Confirm Link" @click="goURL(item.retryUrl)" color="#3686B4">mdi-barley</v-icon>
      </template>
      <template v-slot:item.error.id="{ item }">
        <span v-if="item.error.id>0">
          {{ item.error.id }} 
          <v-icon title="View Error Details" @click="showErrorDetails(item.error)" :class="item.status">mdi-dots-vertical</v-icon>
        </span>
        <span v-else >Success</span>
      </template>
      <template v-slot:item.status="{ item }">
        <span >
          <v-icon :title="item.status" :class="item.status">mdi-record</v-icon>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-data-table v-else :headers="headers" :items="mutable_harvests" :loading="loading" item-key="id"
                  :options="mutable_dt_options" @update:options="updateOptions" :footer-props="footer_props">
      <template v-slot:item.prov_name="{ item }">
        <span v-if="item.prov_inst_id==1">
          <v-icon title="Consortium Platform">mdi-account-multiple</v-icon>&nbsp;
        </span>
        {{ item.prov_name.substr(0,63) }}
        <span v-if="item.prov_name.length>63">...</span>
      </template>
      <template v-slot:item.error.id="{ item }">
        <span v-if="item.error.id>0">{{ item.error.id }}</span>
        <span v-else>&nbsp;</span>
      </template>
    </v-data-table>
    <v-dialog v-model="errorDialog" max-width="600px">
      <error-details :error_data="current_error" @close-dialog="closeErrorDialog" ></error-details>
    </v-dialog>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import { mapGetters } from 'vuex'
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            errors: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} },
            codes: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'Result Date', value: 'updated' },
          { text: 'Platform', value: 'prov_name' },
          { text: 'Release', value: 'release', align: 'center' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Report', value: 'report_name', align: 'center' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Harvest ID', value: 'id', align: 'center', width: '100px'},
          { text: 'Result', value: 'error.id' },
          { text: 'Status', value: 'status', align: 'center'},
        ],
        dt_page_options: [10,25,50,100,-1],
        footer_props: { 'items-per-page-options': [10,25,50,100,-1] },
        mutable_harvests: this.harvests,
        mutable_filters: this.filters,
        inst_filter: null,
        conso_switch: 0,
        limit_prov_ids: [],
        mutable_dt_options: {},
        mutable_updated: [],
        mutable_options: { 'providers': [], 'institutions': [], 'codes': [], 'reports': [], 'yymms': [] },
        allSelected: {'providers':false, 'institutions':false, 'codes':false, 'groups':false, 'yymms':false},
        truncatedResult: false,
        yymms: [],
        harv_stat: [ {id:'Success', opt:'Success'}, {id:'BadCreds', opt:'Bad Credentials'},  {id:'NoRetries', opt:'Out of Retries'},
                     {id:'Fail', opt:'Other Fails'} ],
        bulk_actions: ['ReStart','ReStart as r5','ReStart as r5.1','Delete'],
        harv: {},
        selectedRows: [],
        minYM: '',
        maxYM: '',
        dtKey: 1,
        rangeKey: 1,
        bulkAction: '',
        success: '',
        failure: '',
        loading: false,
        errorDialog: false,
        current_error: {id:null, message:'', explanation:'', detail:'', process_step:'', help_url:''},
        update_button: "Display Records",
        search: '',
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to update state
          // (just not the FIRST change that happens on page load)
          if (this.rangeKey > 1 && this.all_filters.toYM != '' && this.all_filters.fromYM != '' &&
              this.all_filters.toYM != null && this.all_filters.fromYM != null) {
              this.mutable_filters['toYM'] = this.filter_by_toYM;
              this.mutable_filters['fromYM'] = this.filter_by_fromYM;
              this.$store.dispatch('updateAllFilters',this.mutable_filters);
          }
          this.rangeKey += 1;           // force re-render of the date-range component
        }
      },
    },
    methods: {
        // Changing filters means clearing SelectedRows - otherwise Bulk Actions could affect
        // one of many rows no longer displayed.
        updateFilters(filt) {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.selectedRows = [];
            // Setting an inst or group filter clears the other one
            if (this.mutable_filters['institutions'].length>0) {
                this.inst_filter = "I";
                this.mutable_filters['groups'] = [];
            } else if (this.mutable_filters['groups'].length>0) {
                this.inst_filter = "G";
                this.mutable_filters['institutions'] = [];
            }
            // update allSelected flag
            if (typeof(this.allSelected[filt]) != 'undefined') {
                this.allSelected[filt] = ( this.mutable_filters[filt].length==this[filt].length &&
                                           this.mutable_filters[filt].length>0 );
            }
        },
        clearAllFilters() {
            Object.keys(this.mutable_filters).forEach( (key) =>  {
              if (key == 'fromYM' || key == 'toYM' || key == 'updated') {
                  this.mutable_filters[key] = '';
              } else {
                  this.mutable_filters[key] = [];
              }
            });
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            // Reset error code options to inbound property
            Object.keys(this.mutable_options).forEach( (key) => {
              this.mutable_options[key] = [...this[key]];
              if (typeof(this.allSelected[key]) != 'undefined') this.allSelected[key] = false;
            });
            this.inst_filter = null;
            this.conso_switch = false;
            this.limit_prov_ids = [];
            this.rangeKey += 1;           // force re-render of the date-range component
        },
        clearFilter(filter) {
            if (filter == 'date_range') {
                this.mutable_filters['toYM'] = '';
                this.mutable_filters['fromYM'] = '';
                this.rangeKey += 1;           // force re-render of the date-range component
            } else if (filter == 'updated') {
                this.mutable_filters[filter] = '';
            } else {
                this.mutable_filters[filter] = [];
                if (filter=='institutions' || filter=='groups') this.inst_filter = null;
                if ( Object.keys(this.mutable_options).includes(filter) ) {
                  this.mutable_options[filter] = [...this[filter]];
                }
            }
            if (typeof(this.allSelected[filter]) != 'undefined') this.allSelected[filter] = false;
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.selectedRows = [];
        },
        // Applies limit-to consortium switch by updating/managing the array of providers to limit to
        updateConsoOnly() {
            // If no filters active, just apply the conso_only
            if ( this.mutable_filters['providers'].length==0 ) {
              this.limit_prov_ids = (this.conso_switch) ? this.providers.filter(p => p.inst_id==1).map(p=>p.id) : [];
            } else {
              this.limit_prov_ids = (this.conso_switch)
                  ? this.providers.filter(p => p.inst_id==1 && this.mutable_filters['providers'].includes(p.id)).map(p=>p.id)
                  : [];
              if (this.limit_prov_ids.length>0) {
                this.mutable_options['providers'] = this.providers.filter(p => this.limit_prov_ids.includes(p.id));
              } else if (this.mutable_filters['providers'].length > 0) {
                this.mutable_options['providers'] = this.providers.filter(p => this.mutable_filters['providers'].includes(p.id));
              } else {
                this.mutable_options['providers'] = [ ...this.providers];
              }
            }
        },
        // filt holds the filter options to be left alone when the JSON returns options
        updateLogRecords(filt) {
            this.success = "";
            this.failure = "";
            this.loading = true;
            if (this.filter_by_toYM != null) this.mutable_filters['toYM'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) this.mutable_filters['fromYM'] = this.filter_by_fromYM;
            let filters_copy = {...this.mutable_filters};
            if (this.conso_switch) {
              filters_copy.providers = [...this.limit_prov_ids];
            }
            // replace 'No Error' string as a "code" with null for the purpose of reloading the records
            let  _cidx = filters_copy.codes.findIndex(c => c == 'No Error');
            if ( _cidx >= 0 ) {
              filters_copy.codes.splice( _cidx, 1, 0 );
            }
            let _filters = JSON.stringify(filters_copy);
            axios.get("/harvests?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_harvests = response.data.harvests;
                     this.mutable_updated = response.data.updated;
                     this.truncatedResult = response.data.truncated;
                     this.mutable_options['codes'] = response.data.code_opts;
                     this.mutable_options['reports'] = this.reports.filter( r => response.data.rept_opts.includes(r.id) );
                     this.mutable_options['institutions'] = this.institutions.filter( i => response.data.inst_opts.includes(i.id) );
                     this.mutable_options['providers'] = this.providers.filter( p => response.data.prov_opts.includes(p.id) );
                     this.mutable_options['yymms'] = [...response.data.yymms];
                     // Make sure *something* is in the yymms array
                     if (this.mutable_options['yymms'].length == 0) this.yymms = [...this.yymms];
                     this.update_button = "Refresh Records";
                     this.loading = false;
                     this.dtKey++;
                 })
                 .catch(err => console.log(err));
            _cidx = this.mutable_filters.codes.findIndex(c => c == 0);
            if ( _cidx >= 0 ) {
                this.mutable_filters.codes.splice(_cidx, 1);
                this.mutable_filters.codes.unshift('No Error');
            }
            this.selectedRows = [];
        },
        updateOptions(options) {
            if (Object.keys(this.mutable_dt_options).length === 0) return;
            Object.keys(this.mutable_dt_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_dt_options[key]) {
                    this.mutable_dt_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_dt_options);
        },
        processBulk() {
            this.success = "";
            this.failure = "";
            let msg = "";
            msg = "Bulk processing will proceed through each requested harvest sequentially.";
            msg += "<br><br>";
            if (this.bulkAction.substring(0,6) == 'ReStart') {
                msg += "Restarting the selected harvests will reset the attempts counters to zero and";
                msg += " immediately add the harvests to the processing queue.";
                msg += "<br><strong>NOTE: </strong>Harvests related to inactive institutions or platforms, or with";
                msg += " disabled or suspended Sushi credentials will be skipped and will not restart.";
            } else if (this.bulkAction == 'Delete') {
                msg += "Deleting the selected harvest records is not reversible!";
                msg += "<br><br><strong>NOTE:<br /><font color='Red'>The stored data for the selected harvests and all related";
                msg += " failure/warning records will also be removed!</font></strong>.";
            }
            Swal.fire({
              title: 'Are you sure?',
              html: msg,
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, Proceed!'
            }).then((result) => {
              if (result.value) {
                this.success = "Working...";
                if (this.bulkAction == 'Delete') {
                  let settingIDs = this.selectedRows.map( s => s.id );
                  axios.post('/bulk-harvest-delete', { harvests: settingIDs })
                  .then( (response) => {
                    if (response.data.result) {
                      response.data.removed.forEach( _id => {
                        this.mutable_harvests.splice(this.mutable_harvests.findIndex( h => h.id == _id),1);
                      });
                      this.selectedRows = [];
                      this.success = response.data.msg;
                    } else {
                      this.failure = response.data.msg;
                      return false;
                    }
                  })
                  .catch({});
                // Restarting will need their row removed from the datatable also... they will move to the Queued component
                } else {
                    let _harvests = this.selectedRows.map( h => h.id);
                    if (this.bulkAction.length>7) {
                      var new_status = this.bulkAction.substring(this.bulkAction.indexOf("as r")+4);
                      this.bulkAction = new_status;
                    }
                    axios.post('/update-harvest-status', { ids: _harvests, status: this.bulkAction })
                    .then( (response) => {
                      if (response.data.result) {
                        this.success = response.data.msg
                        // Remove (unskipped) harvests from selectedRows and mutable_harvests
                        _harvests.filter( _id => !response.data.skipped.includes(_id)).forEach( m_id => {
                            this.selectedRows.splice(this.selectedRows.findIndex(h => h.id === m_id),1);
                            this.mutable_harvests.splice(this.mutable_harvests.findIndex(h => h.id === m_id),1);
                        });
                        this.$emit('restarted-harvest');
                      } else {
                        this.failure = response.data.msg;
                        return false;
                      }
                    }).catch(error => {});
                }
              }
              this.dtKey += 1;           // force re-render of the datatable
              this.bulkAction = '';
          })
          .catch({});
        },
        goEdit (logId) {
            window.location.assign('/harvests/'+logId+'/edit');
        },
        goURL(url) {
          window.open(url, "_blank");
        },
        showErrorDetails(error) {
            this.current_error = { ...error };
            this.errorDialog = true;
        },
        closeErrorDialog() { this.errorDialog = false; },
        // @change function for filtering/clearing all consortium providers
        filterConsoProv() {
          // Just checked the box for all consortium providers
          if (this.allSelected.providers) {
            this.consortiumProviders.forEach( (cp) => {
              if (!this.mutable_filters['providers'].includes(cp.id)) {
                this.mutable_filters['providers'].push(cp.id);
              }
            });
          // Just cleared the box for all consortium providers
          } else {
            this.consortiumProviders.forEach( (cp) => {
              var idx = this.mutable_filters['providers'].findIndex( p => p == cp.id)
              if (idx >= 0) this.mutable_filters['providers'].splice(idx,1);
            });
          }
        },
        // @change function for filtering/clearing all options on a filter
        filterAll(filt) {
          if (typeof(this.allSelected[filt]) == 'undefined') return;
          // Turned an all-options filter OFF?
          if (this.allSelected[filt]) {
            this.mutable_filters[filt] = [];
            this.allSelected[filt] = false;
            if (filt == "institutions" || filt == "groups") this.inst_filter = null;
          // Turned an all-options filter ON
          } else {
            if (filt == 'codes' || filt == 'yymms') {
                this.mutable_filters[filt] = [...this.mutable_options[filt]];
            } else {
                this.mutable_filters[filt] = this.mutable_options[filt].map(o => o.id);
            }
            this.allSelected[filt] = true;
            if (filt == "institutions") {
                this.inst_filter = "I";
                this.mutable_filters['groups'] = [];
            } else if (filt == "groups") {
                this.inst_filter = "G";
                this.mutable_filters['institutions'] = [];
            }
          }
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'is_viewer', 'filter_by_fromYM', 'filter_by_toYM', 'all_filters',
                     'datatable_options']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
      consortiumProviders() {
        return this.providers.filter(p => p.inst_id==1);
      },
    },
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','harvestlogs');
    },
    mounted() {
      // Update any null/empty filters w/ store-values
      Object.keys(this.all_filters).forEach( (key) =>  {
        if (key == 'fromYM' || key == 'toYM' || key == 'updated') {
            if (this.mutable_filters[key] == null || this.mutable_filters[key] == "")
                this.mutable_filters[key] = this.all_filters[key];
        } else {
            if (typeof(this.mutable_filters[key]) != 'undefined') {
                if (this.mutable_filters[key].length == 0)
                    this.mutable_filters[key] = this.all_filters[key];
            }
        }
      });

      // Inst-filter > Group-filter, if one has a value, set the flag
      if (this.mutable_filters['institutions'].length>0) {
          this.mutable_filters['groups'] = [];
          this.inst_filter = 'I';
      } else if (this.mutable_filters['groups'].length>0) {
          this.inst_filter = 'G';
      }

      // Set initial filter options
      Object.keys(this.mutable_options).forEach( (key) => {
        this.mutable_options[key] = [...this[key]];
      });
      this.mutable_updated = ["Last Hour","Last 24 hours","Last Week"];

      // Set datatable options with store-values
      Object.assign(this.mutable_dt_options, this.datatable_options);

      // Setup date-bounds for the date-selector
      if (typeof(this.bounds[0]) != 'undefined') {
         this.minYM = this.bounds[0].YM_min;
         this.maxYM = this.bounds[0].YM_max;
      }

      // Remove institution column in output if not admin or viewer
      if (!this.is_admin && !this.is_viewer) {
          this.headers.splice(this.headers.findIndex(h=>h.value == "inst_name"),1);
      }

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      // If inbound filters given (as a PROP) , get the initial records
      if (this.filters.institutions.length > 0 || this.filters.providers.length > 0) {
          this.updateLogRecords();
      }

      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style scoped>
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
.Success { color: #00dd00; }
.Fail { color: #dd0000; }
.NoRetries { color: #999999; }
.BadCreds { color: #ff9900; }
.Other { color: #990099 }
</style>
