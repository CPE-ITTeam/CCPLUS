<template>
  <div>
    <v-container grid-list-md>
      <v-form v-model="formValid" :key="'UFrm'+form_key">
        <v-row class="d-flex ma-2" no-gutters>
         <v-col v-if="mutable_dtype=='edit'" class="d-flex pt-4 justify-center">
           <h1 v-if="sushi_prov.inst_id==1" align="center">Edit Consortium COUNTER Credentials</h1>
           <h1 v-else align="center">Edit Institutional COUNTER Credentials</h1>
         </v-col>
         <v-col v-else class="d-flex pt-4 justify-center"><h1 align="center">Create COUNTER Connection</h1></v-col>
        </v-row>
        <div v-if="sushi_inst.id==null || sushi_prov.id==null">
          <v-row class="d-flex ma-2 justify-center" no-gutters>
            <v-col v-if="institutions.length>1" class="d-flex px-2" cols="5">
              <v-autocomplete :items="connectable_institutions" v-model="sushi_inst" return-object item-text="name"
                              label="Choose an Institution"></v-autocomplete>
            </v-col>
            <v-col v-else class="d-flex px-2" cols="5"><strong>{{ sushi_inst.name }}</strong></v-col>
            <v-col cols="2" class="d-flex justify-center"> &lt;&lt; -- &gt;&gt; </v-col>
            <v-col v-if="providers.length>1" class="d-flex px-2" cols="5">
              <v-autocomplete :items="connectable_providers" v-model="sushi_prov" return-object item-text="name"
                              label="Choose a Platform">
                <template #item="{ item, on, attrs }">
                  <v-list-item v-on="on" v-bind="attrs" #default="{ active }">
                    <v-list-item-avatar>
                      <v-icon v-if="item.inst_id==1">mdi-account-multiple</v-icon>
                      <v-icon v-else-if="item.inst_id >1">mdi-home-outline</v-icon>
                    </v-list-item-avatar>
                    <v-list-item-content> {{ item.name }} </v-list-item-content>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>
            <v-col v-else class="d-flex px-2" cols="5"><strong>{{ sushi_prov.name}}</strong></v-col>
          </v-row>
        </div>
        <div v-else>
          <v-row class="d-flex ma-2 justify-center" no-gutters>
            <strong>{{ sushi_inst.name }} &lt;&lt; -- &gt;&gt; {{ sushi_prov.name }}</strong>
          </v-row>
          <v-row class="d-flex mx-2" no-gutters>
            <v-col v-if="mutable_dtype == 'create' || setting.can_edit" class="d-flex px-2" cols="6">
              <v-switch v-model="enable_switch" dense label="Enable Harvesting"
                        @change="statusval=(enable_switch) ? 'Enabled' : 'Disabled'"
              ></v-switch>
            </v-col>
            <v-col class="d-flex px-2 align-center" cols="6">
              <strong>Platform COUNTER Release : {{ sushi_prov.selected_release }}</strong>
            </v-col>
          </v-row>
          <template v-for="cnx in sushi_prov.connectors">
            <v-row class="d-flex mx-2" no-gutters>
              <v-col v-if="cnx.required || (setting[cnx.name] != null && setting[cnx.name]!='')" class="d-flex px-2" cols="10">
                <v-text-field v-if="cnx.required" v-model="form[cnx.name]" :label='cnx.label' :id='cnx.name' outlined
                              :rules="[required]" clearable></v-text-field>
                <v-text-field v-else v-model="form[cnx.name]" :label='cnx.label' :id='cnx.name' outlined></v-text-field>
              </v-col>
            </v-row>
          </template>
          <div v-if="mutable_dtype=='create' && !sushi_prov.is_conso">
            <v-row class="d-flex my-0 mx-2" no-gutters>
              <v-col class="d-flex px-2 warning justify-center">
                <span>Creating this setting will add a platform definition; one report-type is required</span>
              </v-col>
            </v-row>
            <v-row class="d-flex  my-0 mx-2 justify-center" no-gutters>
              <strong>Report(s) to Harvest</strong>
            </v-row>
            <template v-for="rpt in sushi_prov.master_reports">
              <v-row class="d-flex ma-0 mx-2" no-gutters>
                <v-col class="d-flex px-4 justify-center" cols="12">
                  <v-checkbox v-model="form.report_state[rpt.name]['prov_enabled']" :label="rpt.name" class="verydense"
                              key="rpt.name" :rules="reportRules"
                  ></v-checkbox>
                </v-col>
              </v-row>
            </template>
          </div>
          <v-row v-if="service_url!=null" class="d-flex ma-2" no-gutters>
            <v-col class="d-flex px-2" cols="10">
              <v-text-field v-model="service_url" label="COUNTER Service URL" outlined disabled></v-text-field>
            </v-col>
          </v-row>
          <div v-if="showTest">
            <div>{{ testStatus }}</div>
            <div v-for="row in testData">{{ row }}</div>
          </div>
        </div>
      </v-form>
    </v-container>
    <div v-if="success || failure" class="status-message">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-row class="d-flex ma-2" >
      <v-col v-if="sushi_inst.id!=null && sushi_prov.id!=null" class="d-flex px-2 justify-center">
        <v-btn small class='btn' color="primary" @click="saveSetting"
               :disabled="!formValid || (mutable_dtype=='edit' && !setting.can_edit)">Save</v-btn>
      </v-col>
      <v-col class="d-flex px-2 justify-center">
        <v-btn small class='btn' type="button" color="primary" @click="cancelDialog">Cancel</v-btn>
      </v-col>
    </v-row>
    <v-row v-if="sushi_inst.id!=null && sushi_prov.id!=null" class="d-flex mt-1 mx-2">
      <v-col v-if="sushi_prov.selected_release=='5.1'" class="d-flex px-2 justify-center">
        <v-btn small color="secondary" type="button" @click="testSettings('status')">Service Status</v-btn>
      </v-col>
      <v-col class="d-flex px-2 justify-center">
        <v-btn small color="secondary" type="button" @click="testSettings('test')">Test Credentials</v-btn>
      </v-col>
    </v-row>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import axios from 'axios';
  export default {
    props: {
            dtype: { type: String, default: "create" },
            institutions: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            setting: { type:Object, default: () => {} },
            all_settings: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        form_key: 1,
        formValid: true,
        showTest: false,
        sushi_inst: {},
        sushi_prov: {},
        testData: '',
        testStatus: '',
        setting_id: null,
        mutable_dtype: this.dtype,
        statusval: 'Enabled',
        enable_switch: 1,
        report_status: {'PR':'PR_status', 'DR':'DR_status', 'TR':'TR_status', 'IR':'IR_status'},
        service_url: null,
        form: new window.Form({
            inst_id: null,
            prov_id: null,
            customer_id: '',
            requestor_id: '',
            api_key: '',
            extra_args: '',
            status: '',
            report_state: {'PR':{'conso_enabled':false, 'prov_enabled':false}, 'DR':{'conso_enabled':false, 'prov_enabled':false},
                           'TR':{'conso_enabled':false, 'prov_enabled':false}, 'IR':{'conso_enabled':false, 'prov_enabled':false}},
        }),
      }
    },
    watch: {
      sushi_inst: function (inst) {
        this.form.inst_id = this.sushi_inst.id;
        this.form.inst_id = (this.mutable_dtype == 'edit') ? this.setting.inst_id : this.sushi_inst.id;
        if (this.all_settings.length>0 && this.form.prov_id != null && this.form.inst_id != null) {
          this.testExisting();
          this.form_key += 1;
        }
      },
      sushi_prov: function (prov) {
        this.form.prov_id = (this.mutable_dtype == 'edit') ? this.setting.prov_id : this.sushi_prov.id;
        if (this.all_settings.length>0 && this.form.prov_id != null && this.form.inst_id != null) {
          this.testExisting();
          this.form_key += 1;
        }
        // Creating new means we should initialize the form report state also
        if (this.mutable_dtype == 'create') {
          // Update form report_state to match sushi_prov
          Object.assign(this.form.report_state, this.sushi_prov['report_state']);
        }
      },
    },
    methods: {
      testExisting() {
        let setting = this.all_settings.find(s => (s.prov_id == this.form.prov_id && s.inst_id == this.form.inst_id));
        if (typeof(setting) != 'undefined') {
          this.form.customer_id = setting.customer_id;
          this.form.requestor_id = setting.requestor_id;
          this.form.api_key = setting.api_key;
          this.form.extra_args = setting.extra_args;
          this.form.status = setting.status;
          this.mutable_dtype = 'edit';
          this.setting_id = setting.id;
        }
      },
      saveSetting (event) {
          this.success = '';
          this.failure = '';
          this.form.inst_id = this.sushi_inst.id;
          this.form.prov_id = (this.mutable_dtype == 'edit') ? this.setting.prov_id : this.sushi_prov.id;
          this.form.status = this.statusval;
          // All connectors are required - whether they work or not is a matter of testing+confirming
          this.sushi_prov.connectors.forEach( (cnx) => {
              if (cnx.required) {
                  if (this.form[cnx.name] == '' || this.form[cnx.name] == null) {
                      this.failure = "Error: "+cnx.name+" must be supplied to connect to this platform!";
                  }
              }
          });
          if (this.failure != '') return;
          if (this.mutable_dtype == 'create') {
            // Call create() method on sushisettings controller to add to the table
            this.form.post('/sushisettings')
              .then((response) => {
                  if (response.result) {
                      this.$emit('sushi-done', { result:'Created', msg:response.msg, setting:response.setting });
                  } else {
                      this.$emit('sushi-done', { result:'Fail', msg:response.msg, setting:null });
                  }
              });
          } else {  // edit/update
            this.form.patch('/sushisettings/'+this.setting_id)
                .then((response) => {
                  if (response.result) {
                      this.$emit('sushi-done', { result:'Updated', msg:response.msg, setting:response.setting });
                  } else {
                      this.$emit('sushi-done', { result:'Fail', msg:response.msg, setting:null });
                  }
                });
          }
          this.sushi_inst = { ...this.default_inst};
          this.sushi_prov = { ...this.default_prov};
      },
      cancelDialog () {
        this.success = '';
        this.failure = '';
        this.sushi_inst = { ...this.default_inst};
        this.sushi_prov = { ...this.default_prov};
        this.form_key += 1;
        this.$emit('sushi-done', { result:'Cancel', msg:null, setting:null });
      },
      testSettings (type) {
          this.failure = '';
          this.success = '';
          this.testData = '';
          this.testStatus = "... Working ...";
          this.showTest = true;
          var testArgs = {'type' : type, 'prov_id' : this.form.prov_id};
          if (this.sushi_prov.connectors.some(c => c.name === 'requestor_id' && c.required)) {
            testArgs['requestor_id'] = this.form.requestor_id;
          }
          if (this.sushi_prov.connectors.some(c => c.name === 'customer_id' && c.required)) {
            testArgs['customer_id'] = this.form.customer_id;
          }
          if (this.sushi_prov.connectors.some(c => c.name === 'api_key' && c.required)) {
            testArgs['api_key'] = this.form.api_key;
          }
          if (this.sushi_prov.connectors.some(c => c.name === 'extra_args' && c.required)) {
            testArgs['extra_args'] = this.form.extra_args;
          }
          axios.post('/sushisettings-test', testArgs)
               .then((response) => {
                  if (response.data.result) {
                      this.testStatus = response.data.result;
                      this.testData = response.data.rows;
                  } else {
                      this.testStatus = response.data.msg;
                      this.testData = (response.data.rows.length>0) ? $rows : "Test Failed";
                  }
              })
             .catch(error => {});
      },
      required: function (value) {
        return (value) ? true : 'required field';
      },
      isEmpty(obj) {
        for (var i in obj) return false;
        return true;
      }
    },
    computed: {
      ...mapGetters(['is_manager','is_admin']),
      default_inst: function () {
        return (this.institutions.length == 1) ? { ...this.institutions[0] } : {id: null};
      },
      default_prov: function () {
        return (this.providers.length == 1) ? { ...this.providers[0] } : { id: null };
      },
      connectable_providers() {
        if (this.mutable_dtype == 'edit' || this.form.inst_id == null) return this.providers;
        return this.providers.filter( p => (p.inst_id == this.form.inst_id || p.inst_id == 1 || p.inst_id == null) &&
                                            this.filtered_settings.filter( s => s.prov_id == p.id )
                                                                  .every( s2 => { s2.inst_id != this.form.inst_id })
                                    );
      },
      connectable_institutions() {
        if (this.mutable_dtype == 'edit' || this.form.prov_id == null) return this.institutions;
        return this.institutions.filter( ii => this.filtered_settings.filter( s => s.inst_id == ii.id )
                                                                     .every( s2 => { s2.prov_id != this.form.prov_id })
                                       );
      },
      filtered_settings() {
        if ( this.form.inst_id == null) {
          return (this.form.prov_id == null) ? [ ...this.all_settings ]
                                             : this.all_settings.filter( s => s.prov_id == this.form.prov_id );
        } else {
          return (this.form.prov_id == null) ? this.all_settings.filter( s => s.inst_id == this.form.inst_id )
                                             : this.all_settings.filter( s => s.inst_id == this.form.inst_id &&
                                                                              s.prov_id == this.form.prov_id );
        }
      },
      reportsEnabled() {
          return Object.values(this.form.report_state).some( rpt => rpt.prov_enabled == true);
      },
      reportRules() {
          return [ this.reportsEnabled || "At least one Report type must be selected" ];
      },
    },
    mounted() {
      if (this.dtype == 'edit' && this.isEmpty(this.setting)) this.mutable_dtype = 'create';
      if (this.mutable_dtype == 'edit') {
          this.setting_id = this.setting.id;
          this.form.inst_id = this.setting.inst_id;
          this.form.prov_id = this.setting.prov_id;
          this.form.customer_id = this.setting.customer_id;
          this.form.requestor_id = this.setting.requestor_id;
          this.form.api_key = this.setting.api_key;
          this.form.extra_args = this.setting.extra_args;
          this.statusval = this.setting.status;
          this.enable_switch = (this.setting.status == 'Enabled') ? 1 : 0;
          this.sushi_prov = { ...this.setting.provider};
          this.sushi_inst = { ...this.setting.institution};
          this.service_url = this.setting.provider.service_url;
      } else {
          this.statusval = 'Enabled';
          this.sushi_inst = { ...this.default_inst};
          this.sushi_prov = { ...this.default_prov};
          if (this.institutions.length == 1) this.form.inst_id = this.institutions[0].id;
      }
      console.log('SushiDialog Component mounted.');
    }
  }
</script>
<style scoped>
.verydense {
  max-height: 16px;
}
</style>
