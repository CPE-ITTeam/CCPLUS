<template>
  <div>
    <v-row no-gutters>
      <v-col class="d-flex" cols="3">&nbsp;</v-col>
      <v-col class="d-flex align-center" cols="3">
        <v-btn class='btn' small color="primary" @click="updateRecords()">Refresh Harvests</v-btn>
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
      <v-col v-if="is_admin" class="d-flex px-2 align-center" cols="2">
        <v-switch v-model="conso_switch" dense label="Limit to Consortium" @change="updateConsoOnly(true)"></v-switch>
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
      <v-col v-if="institutions.length>1 && (inst_filter==null || inst_filter=='I')"
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
      <v-col v-if="groups.length>1 && (inst_filter==null || inst_filter=='G') && (is_admin || is_viewer)"
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
      <v-col v-if="mutable_options['yymms'].length>0" class="d-flex px-2 align-center" cols="2">
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
        <div v-if="mutable_filters['statuses'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('statuses')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['statuses']" v-model="mutable_filters['statuses']" @change="updateFilters('statuses')"
                  multiple label="Status(es)" item-text="opt" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('statuses')">
              <span v-if="allSelected.statuses">Clear Selections</span>
              <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index == 0 && allSelected.statuses">All Status</span>
            <span v-else-if="index < 2 && !allSelected.statuses">{{ item.opt }}</span>
            <span v-else-if="index === 2 && !allSelected.statuses" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['statuses'].length-2 }} more
            </span>
            <span v-if="index <= 1 && index < mutable_filters['statuses'].length-1 && !allSelected.statuses">, </span>
          </template>
        </v-select>
      </v-col>
    </v-row>
    <v-row class="d-flex pa-1 align-center" no-gutters>
      <v-col v-if='is_admin || is_manager' class="d-flex px-2" cols="2">
        <v-select :items='bulk_actions' v-model='bulkAction' @change="processBulk()" label="Bulk Actions"
                  :disabled='selectedRows.length==0'></v-select>
      </v-col>
      <v-col v-if='is_admin || is_manager' class="d-flex px-4 align-center" cols="3">
        <span v-if="selectedRows.length>0" class="form-fail">( Will affect {{ selectedRows.length }} rows )</span>
        <span v-else>&nbsp;</span>
      </v-col>
      <v-col v-if='!is_admin && !is_manager' class="d-flex" cols="5">&nbsp;</v-col>
      <v-col v-if="truncatedResult" class="d-flex px-2 align-center" cols="3">
        <span class="fail" role="alert">Result Truncated To 500 Records</span>
      </v-col>
      <v-col v-if='is_admin || is_manager' class="d-flex px-2 align-center" cols="2">
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
      <v-col v-if='is_admin || is_manager' class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['statuses'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('statuses')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['statuses']" v-model="mutable_filters['statuses']" @change="updateFilters('statuses')"
                  multiple label="Status(es)" item-text="opt" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('statuses')">
               <span v-if="allSelected.statuses">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index == 0 && allSelected.statuses">All Status</span>
            <span v-else-if="index < 2 && !allSelected.statuses">{{ item.opt }}</span>
            <span v-else-if="index === 2 && !allSelected.statuses" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['statuses'].length-2 }} more
            </span>
            <span v-if="index <= 1 && index < mutable_filters['statuses'].length-1 && !allSelected.statuses">, </span>
          </template>
        </v-select>
      </v-col>
    </v-row>
    <div v-if='(is_admin || is_manager) && (success || failure)'>
      <v-row class="status-message">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </v-row>
    </div>
    <v-data-table v-model="selectedRows" :headers="headers" :items="filtered_harvests" :loading="loading" item-key="id"
                  show-select :options="mutable_dt_options" @update:options="updateOptions" :footer-props="footer_props"
                  :key="dtKey" :search="search">
      <template v-slot:top="{ pagination, options, updateOptions }">
        <v-data-footer :pagination="pagination" :options="mutable_dt_options" @update:options="updateOptions"
                       items-per-page-text="$vuetify.dataTable.itemsPerPageText" :items-per-page-options="dt_page_options"/>
      </template>
      <template v-slot:item.prov_name="{ item }">
        {{ item.prov_name.substr(0,63) }}
        <span v-if="item.prov_name.length>63">...</span>
      </template>
      <template v-slot:item.id="{ item }">
        <span v-if="item.rawfile!=null">{<a title="Downloaded JSON" :href="'/harvests/'+item.id+'/raw'">{{ item.id }}</a>}</span>
        <span v-else>{{ item.id }}</span>
        <v-icon title="Manual Retry/Confirm Link" @click="goURL(item.retryUrl)" color="#3686B4">mdi-barley</v-icon>
      </template>
      <template v-slot:item.dStatus="{ item }">
        <div class="d-flex align-center">
          <span>{{ item.dStatus }}</span>
          <v-tooltip top>
            <template v-slot:activator="{ on, attrs }">
              <v-btn icon><v-icon size="small" v-on="on">mdi-help-circle-outline</v-icon></v-btn>
            </template>
            <span> {{ statusTips[item.status] }}</span>
          </v-tooltip>
        </div>
      </template>
      <template v-slot:item.error.id="{ item }">
        <span v-if="item.error.id>0">
          {{ item.error.id }} 
          <v-icon title="View Error Details" @click="showErrorDetails(item.error)">mdi-dots-vertical</v-icon>
        </span>
        <span v-else >&nbsp;</span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
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
      institutions: { type:Array, default: () => [] },
      groups: { type:Array, default: () => [] },
      providers: { type:Array, default: () => [] },
      reports: { type:Array, default: () => [] },
      filters: { type:Object, default: () => {} },
    },
    data () {
      return {
        // Actual headers array is built from these in mounted()
        header_fields: [
          { label: 'Updated', name: 'updated' },
          { label: 'Platform', name: 'prov_name' },
          { label: 'Institution', name: 'inst_name' },
          { label: 'Report', name: 'report.name' },
          { label: 'Usage Date', name: 'yearmon' },
          { label: 'Harvest ID', name: 'id'},
          { label: 'Status', name: 'dStatus' },
          { label: 'Attempts', name: 'attempts' },
          { label: 'Last Result', name: 'error.id' },
        ],
        headers: [],
        harvest_jobs: [],
        mutable_dt_options: {},
        dt_page_options: [10,50,100,-1],
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        selectedRows: [],
        mutable_filters: { ...this.filters },
        filter_vars: {'providers':'prov_id','institutions':'inst_id','reports':'report_id','yymms':'yearmon','statuses':'dStatus'},
        filtered_harvests: [],
        conso_switch: 0,
        limit_prov_ids: [],
        limit_inst_ids: [],
        inst_filter: null,
        codes: [],
        yymms: [],
        statuses: [ {id:'Queued', opt:'Harvest Queue'}, {id:'Harvesting', opt:'Harvesting'},
                    {id:'Pending', opt:'Queued by Vendor'}, {id:'Paused', opt:'Paused'}, {id:'ReQueued', opt:'ReQueued'},
                    {id:'Waiting', opt:'Process Queue'}, {id:'Processing', opt:'Processing'} ],
        statusTips: {'Queued': 'Harvest will run in the current/next harvest cycle',
                     'Harvesting': 'Harvest data is being requested and prepared for processing',
                     'Pending': 'Harvest on-hold by provider, will be re-attempted automatically',
                     'Paused': 'Harvest is on-hold by CC+ admin, will not run until restarted',
                     'ReQueued': 'Harvest will move to the active queue in the next overnight cycle',
                     'Waiting': 'Raw Harvest data has been downloaded and is in line to be processed and stored',
                     'Processing': 'Raw Harvest data is being parsed and stored in the CC+ database'},
        mutable_options: { 'providers':[], 'institutions':[], 'groups':[], 'codes':[], 'statuses':[], 'reports':[], 'yymms':[] },
        saved_options: { 'providers':[], 'institutions':[], 'groups':[], 'codes':[], 'statuses':[], 'reports':[], 'yymms':[] },
        allSelected: {'providers':false, 'institutions':false, 'groups':false, 'codes':false, 'statuses':false, 'yymms':false},
        truncatedResult: false,
        bulk_actions: ['Pause', 'ReStart', 'Kill'],
        dtKey: 1,
        bulkAction: '',
        success: '',
        failure: '',
        loading: false,
        search: '',
        errorDialog: false,
        current_error: {id:null, message:'', explanation:'', detail:'', process_step:'', help_url:''},
      }
    },
    watch: {
      mutable_filters: {
        handler () {
          this.filtered_harvests = [ ...this.filter_harvests() ];
        },
        deep: true
      },
    },
    methods: {
        // Update records from the jobs queue
        updateRecords() {
            this.success = "";
            this.failure = "";
            this.loading = true;
            let filters_copy = Object.assign( {}, this.mutable_filters);
            if (this.conso_switch) {
              filters_copy.providers = [...this.limit_prov_ids];
            }
            // replace 'No Error' string as a "code" with null for the purpose of reloading the records
            let  _cidx = filters_copy.codes.findIndex(c => c == 'No Error');
            if ( _cidx >= 0 ) {
              filters_copy.codes.splice( _cidx, 1, 0 );
            }
            let _filters = JSON.stringify(filters_copy);
            axios.get("/harvest-queue?filters="+_filters)
                 .then((response) => {
                     this.harvest_jobs = [...response.data.jobs];
                     this.filtered_harvests = [...response.data.jobs],
                     this.truncatedResult = response.data.truncated;
                     // update filtering options; keep a copy in saved_options for resetting/clearing filters
                     this.saved_options['providers'] = (response.data.prov_ids.length > 0)
                                            ? this.providers.filter( p => response.data.prov_ids.includes(p.id) )
                                            : [...this.providers];
                     this.mutable_options['providers'] = [...this.saved_options.providers];
                     this.saved_options['institutions'] = (response.data.inst_ids.length > 0)
                                            ? this.institutions.filter( p => response.data.inst_ids.includes(p.id) )
                                            : [...this.institutions];
                     this.mutable_options['institutions'] = [...this.saved_options.institutions];
                     this.saved_options['reports'] = (response.data.rept_ids.length > 0)
                                            ? this.reports.filter( r => response.data.rept_ids.includes(r.id) )
                                            : [...this.reports];
                     this.mutable_options['reports'] = [...this.saved_options.reports];
                     this.saved_options['statuses'] = (response.data.statuses.length > 0)
                                            ? this.statuses.filter( s => response.data.statuses.includes(s.id) )
                                            : [...this.statuses];
                     this.mutable_options['statuses'] = [...this.saved_options.statuses];
                     this.saved_options['codes'] = (response.data.codes.length > 0) ? [...response.data.codes] : [...this.codes];
                     this.mutable_options['codes'] = [...this.saved_options.codes];
                     this.saved_options['yymms'] = (response.data.yymms.length > 0) ? [...response.data.yymms] : [...this.yymms];
                     this.mutable_options['yymms'] = [...this.saved_options.yymms];
                     // Make sure the codes and yymms options hold as much as they can (for clear filters)
                     if (this.mutable_options['codes'].length > this.codes.length) this.codes = [...this.mutable_options['codes']];
                     if (this.mutable_options['yymms'].length > this.yymms.length) this.yymms = [...this.mutable_options['yymms']];
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
        // Applies limit-to consortium switch by updating/managing the array of providers to limit to
        updateConsoOnly(reload) {
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
            if (reload) this.updateRecords();
        },
        // Changing filters means clearing SelectedRows - otherwise Bulk Actions could affect
        // one of many rows no longer displayed.
        updateFilters(filt) {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.selectedRows = [];
            // Setting an inst or group filter clears the other one
            if (this.mutable_filters['institutions'].length>0) {
                this.inst_filter = "I";
                this.limit_inst_ids = [...this.mutable_filters['institutions']];
                this.mutable_filters['groups'] = [];
            } else if (this.mutable_filters['groups'].length>0) {
                this.inst_filter = "G";
                var _new_id_set = [];
                this.limit_inst_ids = this.mutable_filters['groups'].forEach( (gid)  => {
                  let group = this.groups.find(g => g.id == gid);
                  if (typeof(group) != 'undefined') {
                    group.institutions.forEach( (inst) => {
                      if ( !_new_id_set.includes(inst.id) ) _new_id_set.push(inst.id);
                    });
                  }
                });
                this.limit_inst_ids = [..._new_id_set];
                this.mutable_filters['institutions'] = [];
            }
            // update allSelected flag
            if (typeof(this.allSelected[filt]) != 'undefined') {
                this.allSelected[filt] = ( this.mutable_filters[filt].length==this.saved_options[filt].length &&
                                           this.mutable_filters[filt].length>0 );
            }
        },
        clearFilter(filter) {
            this.mutable_filters[filter] = [];
            if (filter=='institutions' || filter=='groups') {
              this.inst_filter = null;
              this.limit_inst_ids = [];
            }
            if ( Object.keys(this.mutable_options).includes(filter) ) {
              this.mutable_options[filter] = [...this.saved_options[filter]];
            }
            if (typeof(this.allSelected[filter]) != 'undefined') this.allSelected[filter] = false;
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.selectedRows = [];
        },
        // @change function for filtering/clearing all options on a filter
        filterAll(filt) {
            if (typeof(this.allSelected[filt]) == 'undefined') return;
            // Turned an all-options filter OFF?
            if (this.allSelected[filt]) {
                this.mutable_filters[filt] = [];
                this.allSelected[filt] = false;
                if (filt == "institutions" || filt == "groups") {
                  this.inst_filter = null;
                  this.limit_inst_ids = [];
                }
            // Turned an all-options filter ON
            } else {
                if (filt == 'codes' || filt == 'statuses' || filt == 'yymms') {
                    this.mutable_filters[filt] = [...this.saved_options[filt]];
                } else {
                    this.mutable_filters[filt] = this.saved_options[filt].map(o => o.id);
                }
                this.allSelected[filt] = true;
                if (filt == "institutions" || filt == "groups") {
                  if (filt == "institutions") {
                      this.inst_filter = "I";
                      this.mutable_filters['groups'] = [];
                  } else {
                      this.inst_filter = "G";
                      this.mutable_filters['institutions'] = [];
                  }
                  this.limit_inst_ids = this.saved_options['institutions'].map( inst => inst.id)
                }
            }
        },
        clearAllFilters() {
            // Reset conso switch
            this.updateConsoOnly(false);
            // Reset filters
            Object.keys(this.mutable_filters).forEach( (key) =>  {
              this.mutable_filters[key] = [];
            });
            // Reset error code options to inbound property
            Object.keys(this.mutable_options).forEach( (key) => {
              this.mutable_options[key] = [...this.saved_options[key]];
              if (typeof(this.allSelected[key]) != 'undefined') this.allSelected[key] = false;
            });
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.inst_filter = null;
            this.rangeKey += 1;           // force re-render of the date-range component
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
            msg = "Bulk processing will proceed for all requested harvests and reload the list.";
            if (this.bulkAction == 'Pause') {
                msg += "<br>Paused Harvests will remain in the harvesting queue, but will be ignored while Paused.";
            } else if (this.bulkAction == 'ReStart') {
                msg += "<br>Only Paused Harvests will restarted. Harvests related to inactive institutions or platforms, or with";
                msg += " disabled or suspended Sushi credentials will be unchanged.";
            } else if (this.bulkAction == 'Kill') {
                msg += "Deleting the selected harvest records is not reversible, and harvested data will not be affected.";
                msg += " <br><br><strong>NOTE:</strong> all failure/warning records related to this harvest will also be deleted.";
            } else {
                this.failure = "Unknown bulk action requested.";
                return;
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
                if (this.bulkAction == 'Kill') {
                  let settingIDs = this.selectedRows.map( s => s.id );
                  axios.post('/bulk-harvest-delete', { harvests: settingIDs })
                  .then( (response) => {
                    if (response.data.result) {
                      response.data.removed.forEach( _id => {
                        this.harvest_jobs.splice(this.harvest_jobs.findIndex( h => h.id == _id),1);
                      });
                      this.selectedRows = [];
                      this.success = response.data.msg;
                    } else {
                      this.failure = response.data.msg;
                      return false;
                    }
                  })
                  .catch({});
                } else {  // Pause/Resume
                  let _harvests = this.selectedRows.map( h => h.id);
                  axios.post('/update-harvest-status', { ids: _harvests, status: this.bulkAction })
                  .then( (response) => {
                    if (response.data.result) {
                      this.success = response.data.msg
                      // reload the table, include filter options
                      this.updateRecords();
                    } else {
                      this.failure = response.data.msg;
                      return false;
                    }
                  }).catch(error => {});
                }
              }
              this.bulkAction = '';
          })
          .catch({});
        },
        goURL(url) {
          window.open(url, "_blank");
        },
        groupsToInsts(groupIds) {
          var _return_ids = [];
          groupIds.forEach( (gid)  => {
            let group = this.groups.find(g => g.id == gid);
            if (typeof(group) != 'undefined') {
              group.institutions.forEach( (inst) => {
                if ( !_return_ids.includes(inst.id) ) _return_ids.push(inst.id);
              });
            }
          });
          return [..._return_ids];
        },
        // Filter job_harvests (from last record load) by all the set filters 
        filter_harvests() {
          let returnSet = [ ...this.harvest_jobs ];
          Object.keys(this.filters).forEach( (key) =>  {
            if (this.mutable_filters[key].length > 0) {
              if (key == 'institutions' || key == 'groups') {
                returnSet = returnSet.filter( h => this.limit_inst_ids.includes(h.inst_id) );
              } else {
                returnSet = returnSet.filter( h => this.mutable_filters[key].includes(h[this.filter_vars[key]]) );
              }
            }
          });
          return returnSet;
        },
        showErrorDetails(error) {
            this.current_error = { ...error };
            this.errorDialog = true;
        },
        closeErrorDialog() { this.errorDialog = false; },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'is_viewer', 'all_filters', 'datatable_options']),
    },
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','harvestqueue');
    },
    mounted() {
      // Set datatable options with store-values
      Object.assign(this.mutable_dt_options, this.datatable_options);

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      // Setup datatable headers
      this.header_fields.forEach((fld) => {
          if (fld.label == 'Institution') {
            if (this.is_admin || this.is_viewer) this.headers.push({ text: fld.label, value: fld.name });
          } else if (fld.label == 'Attempts') {
            this.headers.push({ text: fld.label, value: fld.name, align:'center' });
          } else {
            this.headers.push({ text: fld.label, value: fld.name });
          }
      });

      // Load data records
      this.updateRecords();
      this.dtKey++;
      console.log('HarvestJobs Component mounted.');
    }
  }
</script>
<style scoped>
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
