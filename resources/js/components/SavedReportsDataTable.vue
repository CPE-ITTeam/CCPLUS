<template>
  <div>
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
      <v-col class="d-flex align-center" cols="3">
        <!-- <v-btn class='btn' small color="primary" @click="updateRecords()">Create A Report</v-btn> -->
        <a href="/reports/create"><v-btn color="primary" small>Create A Report</v-btn></a>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2">
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
    </v-row>
    <div v-if='(is_admin || is_manager) && (success || failure)'>
      <v-row class="status-message">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </v-row>
    </div>
    <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_reports" item-key="id"
                  show-select :options="dt_options" :key="dtKey">
      <template v-slot:item.exclude_zeros="{ item }">
        <span v-if="item.exclude_zeros==1">No</span>
        <span v-else>Yes</span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-btn icon @click="goPreview(item.id)">
            <v-icon title="Launch Report Preview">mdi-open-in-new</v-icon>
          </v-btn>
          <v-btn icon @click="editForm(item.id)">
            <v-icon title="Edit Report Settings">mdi-cog-outline</v-icon>
          </v-btn>
          <v-btn icon @click="destroy(item.id)">
            <v-icon title="Delete Saved Report">mdi-trash-can-outline</v-icon>
          </v-btn>
        </span>
      </template>
    </v-data-table>
    <v-dialog v-model="reportDialog" content-class="ccplus-dialog">
      <v-container grid-list-sm>
        <v-form v-model="formValid">
          <v-row class="d-flex ma-0" no-gutters>
            <v-col class="d-flex pt-2 justify-center"><h1 align="center">Edit Report Settings</h1></v-col>
          </v-row>
          <v-row class="d-flex ma-0" no-gutters>
            <v-col class="d-flex px-4" cols="8">
              <v-text-field v-model="form.title" label="Title" outlined dense></v-text-field>
            </v-col>
          </v-row>
          <v-row class="d-flex ma-0" no-gutters>
            <v-col class="d-flex px-4">
              Report based on {{ cur_report.report_name }} : {{ cur_report.report_legend }}
            </v-col>
          </v-row>
          <v-row class="d-flex ma-0" no-gutters>
            <v-col class="d-flex px-4"><strong>Includes Fields</strong></v-col>
          </v-row>
          <v-row v-for="field in cur_report.fields" :key="field.id" class="d-flex ma-0" no-gutters>
            <v-col class="d-flex pl-8" v-if="field.column != null" cols="4">{{ field.name }} :</v-col>
            <v-col class="d-flex px-4" v-if="field.column != null" cols="8"><strong>{{ field.qry_as }}</strong></v-col>
          </v-row>
          <v-row class="d-flex my-1" no-gutters>
            <v-col class="d-flex px-4">
              (Fields and filter settings can be changed on the &nbsp;
              <a :href="'/reports/preview?saved_id='+cur_report.id">
                report preview page.)
                <v-icon title="Launch Report Preview">mdi-open-in-new</v-icon>
              </a>
            </v-col>
          </v-row>
          <v-row class="d-flex mt-1" no-gutters>
            <v-col class="d-flex px-4" cols="6">
              <v-select :items="formats" v-model="form.format" label="Report Format" outlined dense></v-select>
            </v-col>
            <v-col class="d-flex px-4" cols="6">
              <v-checkbox v-model="form.exclude_zeros" :value="form.exclude_zeros" label="Exclude Zeros" dense>
              </v-checkbox>
            </v-col>
          </v-row>
          <v-row class="d-flex mt-1" no-gutters>
            <v-col class="d-flex px-4"><strong>Report Dates</strong></v-col>
          </v-row>
          <v-row class="d-flex ma-0" no-gutters>
            <v-col class="d-flex px-8">
              <v-radio-group v-model="form.date_range" dense>
                <v-radio class="verydense" :label="'Latest Month ['+maxYM+']'" value='latestMonth'></v-radio>
                <v-radio class="verydense" :label="'Latest Year ['+latestYear+']'" value='latestYear'></v-radio>
                <v-radio class="verydense" :label="'Fiscal Year-to-Date ['+fiscalTD+']'" value='fiscalTD'></v-radio>
                <v-radio class="verydense" :label="'Custom Date Range'" value='Custom'></v-radio>
              </v-radio-group>
            </v-col>
          </v-row>
          <v-row v-if="form.date_range=='Custom' || form.date_range=='FYTD'" class="d-flex ma-0" no-gutters>
            <v-col class="d-flex" cols="2">&nbsp;</v-col>
            <v-col class="d-flex px-4">
              <date-range :minym="minYM" :maxym="maxYM" :ymfrom="minYM" :ymto="maxYM"></date-range>
            </v-col>
          </v-row>
          <v-row class="d-flex mt-1" no-gutters>
            <v-col class="d-flex px-4" cols="4">
              <v-btn x-small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
                Save Report
              </v-btn>
            </v-col>
            <v-col class="d-flex px-4" cols="4">
              <v-btn x-small color="primary" type="button" @click="reportDialog=false">Cancel</v-btn>
            </v-col>
          </v-row>
          <div v-if="dialog_failure || dialog_success" class="status-message">
            <span v-if="dialog_success" class="good" role="alert" v-text="dialog_success"></span>
            <span v-if="dialog_failure" class="fail" role="alert" v-text="dialog_failure"></span>
          </div>
        </v-form>
      </v-container>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import Swal from 'sweetalert2'
  import axios from 'axios'
  import Form from '@/js/plugins/Form';
  window.Form = Form;
  export default {
    props: {
      reports: { type:Array, default: () => [] },
      counter_reports: { type:Array, default: () => [] },
      filters: { type:Object, default: () => {} },
      fy_month: { type: Number, default: 1 },
    },
    data () {
      return {
          success: '',
          failure: '',
          dialog_success: '',
          dialog_failure: '',
          mutable_reports: [ ...this.reports ],
          mutable_filters: { ...this.filters },
          mutable_options: { 'codes':[] },
          headers: [
            { text: 'Report Title', value: 'title' },
            { text: 'Report', value: 'master_name' },
            { text: 'Date Range', value: 'date_range' },
            { text: 'Last Run Date', value: 'last_harvest' },
            { text: 'Format', value: 'format' },
            { text: 'Include Zeros', value: 'exclude_zeros', align: 'center' },
            { text: 'Actions', value: 'action', align: 'end', sortable: false },
          ],
          dt_options: {itemsPerPage:10, sortBy:['updated_at'], sortDesc:[false],
                       multiSort:true, mustSort:false},
          bulk_actions: ['Pause', 'ReStart'],
          bulkAction: '',
          selectedRows: [],
          allSelected: { 'codes':false },
          dtKey: 1,
          reportDialog: false,
          report_data: [],
          formValid: true,
          cur_report: {},
          maxYM: '',
          minYM: '',
          latestYear: '',
          fiscalTD: '',
          formats: ['Compact','COUNTER'],
          form: new window.Form({
            title: '',
            date_range: '',
            ym_from: '',
            ym_to: '',
            format: '',
            exclude_zeros: 1,
          }),
        }
    },
    methods: {
        editForm (id) {
          this.failure = '';
          this.success = '';
          this.dialog_failure = '';
          this.dialog_success = '';
          this.cur_report = this.mutable_reports.find(r => r.id == id);
          // Setup date bounds and strings based on master report
          this.maxYM = this.report_data[this.cur_report.master_name].YM_max;
          this.minYM = this.report_data[this.cur_report.master_name].YM_min;
          // Setup latestYear string
          let max_parts = this.maxYM.split("-");
          let firstMonth = new Date(max_parts[0], max_parts[1] - 1, 1);
          firstMonth.setMonth(firstMonth.getMonth()-11);
          let ym_from = firstMonth.toISOString().substring(0,7);
          if (ym_from<this.minYM) {
            ym_from = this.minYM;
          }
          this.latestYear = ym_from+' to '+this.maxYM;
          // Setup Fiscal YTD string
          let fyStartYr = ( this.fy_month > max_parts[1] ) ? max_parts[0]-1 : max_parts[0];
          let fyFirstMonth = new Date(fyStartYr, this.fy_month-1, 1);
          ym_from = fyFirstMonth.toISOString().substring(0,7);
          this.fiscalTD = ym_from+' to '+this.maxYM;
          // Setup rest of the form fields
          this.form.title = this.cur_report.title;
          this.form.date_range = this.cur_report.date_range;
          this.form.ym_from = this.cur_report.ym_from;
          this.form.ym_to = this.cur_report.ym_to;
          this.form.format = this.cur_report.format;
          this.form.exclude_zeros = this.cur_report.exclude_zeros;
          this.reportDialog = true;
          this.form.resetOriginal();
        },
        formSubmit() {
            this.success = '';
            this.failure = '';
            this.dialog_failure = '';
            this.dialog_success = '';
            let idx = this.mutable_reports.findIndex(r => r.id == this.cur_report.id);
            if ( idx < 0 ) {
              this.dialog_failure = 'Error - Cannot find report to be saved! Something is broken...';
              return;
            }
            // Apply form values
            this.form.patch('/my-reports/'+this.cur_report.id)
                     .then( (response) => {
                       if (response.result) {
                         this.form.resetOriginal();
                         // Update the entry in the mutable array
                         this.mutable_reports[idx] = Object.assign({}, response.report);
                         this.dialog_success = response.msg;
                       } else {
                         this.dialog_success = '';
                         this.dialog_failure = response.msg;
                       }
                     });
        },
        destroy (id) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "Deleting this report cannot be reversed, only manually recreated.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, proceed'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/my-reports/'+id)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               // Remove the setting from the display
                               this.mutable_reports.splice(this.mutable_reports.findIndex(s=> s.id == id),1);
                           } else {
                               self.success = '';
                               self.failure = response.data.msg;
                           }
                       })
                       .catch({});
              }
            })
            .catch({});
        },
        updateAvailable(filt) {
          let filters = JSON.stringify([]);
          axios.get('/reports-available?filters='+filters)
               .then((response) => {
                   this.report_data = response.data.reports;
               })
               .catch(error => {});
        },
        goPreview(reportId) {
          window.open('/reports/preview?saved_id='+reportId, "_blank");
        },
    },
    computed: {
      ...mapGetters(['is_admin','is_viewer'])
    },
    beforeCreate() {
      // Initialize local datastore if it is not there
      if (!localStorage.getItem('store')) {
          this.$store.commit('initialiseStore');
      }
	  },
    mounted() {
      this.updateAvailable();
      console.log('HomeSavedReports Component mounted.');
    }
  }
</script>

<style scoped>
.verydense {
  max-height: 18px;
  background-color: #fff;
}
</style>
