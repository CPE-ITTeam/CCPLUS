<template>
  <div>
    <v-form v-model="formValid">
      <v-row class="d-flex ma-2" no-gutters>
        <v-col v-if="mutable_dtype=='edit'" class="d-flex pt-4 justify-center"><h1 align="center">Edit Platform settings</h1></v-col>
        <v-col v-else class="d-flex pt-4 justify-center"><h1 align="center">Connect a Platform</h1></v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-2" cols="8">
          <v-text-field v-model="form.name" label="Name" :readonly="!mutable_provider.can_edit" outlined dense></v-text-field>
        </v-col>
        <v-col v-if="mutable_dtype=='edit'" class="d-flex px-2" cols="2">
          <div class="idbox">
            <v-icon title="CC+ Platform ID">mdi-crosshairs-gps</v-icon>&nbsp; {{ mutable_provider.id }}
          </div>
        </v-col>
      </v-row>
      <v-row v-if="warn_inst" class="d-flex ma-1" no-gutters>
        <v-col class="d-flex px-2 warning justify-center"><span role="warning" v-text="warn_inst"></span></v-col>
      </v-row>
      <v-row v-else-if="mutable_dtype=='edit' && !is_admin && is_manager &&
                        (!mutable_provider.can_edit || (mutable_provider.is_conso && mutable_provider.inst_id!=1))"
             class="d-flex mb-1" no-gutters>
        <v-col v-if="mutable_provider.can_edit" class="d-flex px-2 warning justify-center">
          <span role="warning">Editting institution-specific settings only</span>
        </v-col>
        <v-col v-else class="d-flex px-2 warning justify-center">
          <span role="warning">This Platform Information is Read-Only</span>
        </v-col>
      </v-row>
      <div v-if="is_admin">
        <v-row v-if="mutable_dtype=='edit' && mutable_provider.connected.length>0" class="d-flex mx-2 my-0" no-gutters>
          <v-col class="d-flex px-2" cols="8">
            <v-autocomplete :items="mutable_provider.connected" v-model="form.inst_id" label="Existing Connections"
                            item-text="inst_name" item-value="inst_id" outlined dense hint="Choose a connection target"
                            persistent-hint :rules="instRules" :readonly="!is_admin" @change="changeInst"
            ></v-autocomplete>
          </v-col>
        </v-row>
        <v-row v-if="mutable_dtype=='connect' || show_unconnected" class="d-flex mx-2 my-0" no-gutters>
          <v-col class="d-flex px-2" cols="8">
            <v-autocomplete :items="unconnected_insts" v-model="form.inst_id" label="UnConnected Institutions"
                            item-text="name" item-value="id" outlined dense hint="Establish a new connection"
                            persistent-hint :rules="instRules" :readonly="!is_admin" @change="changeInst"
            ></v-autocomplete>
          </v-col>
          <v-col v-if="mutable_dtype=='edit' && form.inst_id != mutable_provider.inst_id" class="d-flex px-2" cols="2">
            <v-btn x-small color="primary" @click="initializeForm">Reset</v-btn>
          </v-col>
        </v-row>
      </div>
      <div v-else>
        <v-row class="d-flex ma-2" no-gutters>
          <v-col class="d-flex justify-center"><h3 align="center">{{ current_inst_name }}</h3></v-col>
        </v-row>
      </div>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="is_admin || (is_manager && form.inst_id!=1)" class="d-flex px-2" cols="3">
          <v-switch v-model="form.is_active" dense label="Active?" :disabled="mutable_dtype=='edit' && !is_admin &&
                                                                              provider.inst_id==1"></v-switch>
        </v-col>
        <v-col v-if="is_admin && form.inst_id==1" class="d-flex px-2" cols="9">
          <v-switch v-model="form.allow_inst_specific" dense label="Local Admins Can Harvest Additional Reports"></v-switch>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0 align-center" no-gutters>
        <v-col class="d-flex px-6" cols="6">Harvests Monthly on Day : </v-col>
        <v-col class="d-flex px-4" cols="2">
          <v-text-field v-model="mutable_provider.day_of_month" label="Day-of-Month" single-line dense type="number"
                        class="centered-input" disabled
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="mutable_provider.master_reports.length>0" class="d-flex px-4" cols="12">
          <span v-if="!this.reportsEnabled"><strong>Reports to Harvest</strong> (at least one is required)</span>
          <span v-else><strong>Reports to Harvest</strong></span>
        </v-col>
        <v-col v-else class="d-flex px-4" cols="12">
          <strong>No reports enabled globally</strong>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="mutable_provider.master_reports.length>0" class="d-flex px-4" cols="1">&nbsp;</v-col>
        <v-col v-if="mutable_provider.master_reports.length>0" class="d-flex px-4" cols="11">
          <v-list class="shaded" dense>
            <v-list-item v-for="rpt in mutable_provider.master_reports" :key="rpt.name" class="verydense">
              <v-checkbox v-model="form.report_state[rpt.name]['prov_enabled']" key="rpt.name" :label="rpt.name" dense
                          :disabled="(mutable_dtype=='edit' && is_manager && !is_admin && !mutable_provider.can_edit) ||
                                     (form.inst_id!=1 && provider.is_conso && form.report_state[rpt.name]['conso_enabled'])"
                          :rules="reportRules"
              ></v-checkbox>
            </v-list-item>
          </v-list>
          <div class="float-none"></div>
        </v-col>
        <v-col v-else class="d-flex" cols="12">&nbsp;</v-col>
      </v-row>
      <v-row v-if="mutable_provider.last_harvest!=null" class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-4" cols="12"><strong>Last Successful Harvest</strong></v-col>
      </v-row>
      <v-row v-if="mutable_provider.last_harvest!=null" class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="mutable_provider.master_reports.length>0" class="d-flex px-4" cols="1">&nbsp;</v-col>
        <v-col v-if="mutable_provider.last_harvest_id>0" class="d-flex px-4" cols="11">
          {{ mutable_provider.last_harvest }} &nbsp;
          <span>
            {<a title="Downloaded JSON" :href="'/harvests/'+mutable_provider.last_harvest_id+'/raw'"
                target="_blank">{{ mutable_provider.last_harvest_id }}</a>}
          </span>
        </v-col>
        <v-col v-else class="d-flex px-6" cols="11">
          {{ mutable_provider.last_harvest }}
        </v-col>
      </v-row>
      <v-row class="d-flex ma-2" no-gutters>
        <v-spacer></v-spacer>
        <v-col class="d-flex px-2 justify-center" cols="6">
          <v-btn x-small color="primary" @click="saveProv" :disabled="!formValid">Save Platform</v-btn>
        </v-col>
        <v-col class="d-flex px-2 justify-center" cols="6">
          <v-btn x-small color="primary" @click="cancelDialog">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-form>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import axios from 'axios';
  export default {
    props: {
            dtype: { type: String },
            provider: { type:Object, default: () => {} },
            institutions: { type:Array, default: () => [] }
           },
    data () {
      return {
        mutable_provider: {},
        formValid: true,
        warn_inst: '',
        current_inst_name: '',
        mutable_dtype: this.dtype,
        form: new window.Form({
            name: '',
            inst_id: 1,
            global_id: null,
            is_active: 1,
            allow_inst_specific: 0,
            sushi_stub: 1,
            report_state: {'PR':{'conso_enabled':false, 'prov_enabled':false}, 'DR':{'conso_enabled':false, 'prov_enabled':false},
                           'TR':{'conso_enabled':false, 'prov_enabled':false}, 'IR':{'conso_enabled':false, 'prov_enabled':false}},
        }),
      }
    },
    methods: {
      saveProv (event) {
          if (this.mutable_dtype == 'edit') {
            this.form.patch('/providers/'+this.mutable_provider['global_id'])
                .then( (response) => {
                    var _prov   = (response.result) ? Object.assign({},response.provider) : null;
                    var _result = (response.result) ? 'Success' : 'Fail';
                    this.$emit('prov-complete', { result:_result, msg:response.msg, prov:_prov });
                }).catch({});
          } else {
            this.form.post('/providers/connect')
                .then( (response) => {
                    var _prov   = (response.result) ? Object.assign({},response.provider) : null;
                    var _result = (response.result) ? 'Success' : 'Fail';
                    this.$emit('prov-complete', { result:_result, msg:response.msg, prov:_prov });
                }).catch({});
          }
      },
      cancelDialog () {
        this.$emit('prov-complete', { result:'Cancel', msg:null, prov:null });
      },
      changeInst() {
        if (this.form.inst_id == null || this.form.inst_id == '') return;
        this.warn_inst = '';
        // Set a flag for whether or not the new-inst is already connected
        let cnxIdx = this.provider.connected.findIndex(p => p.inst_id == this.form.inst_id);
        var is_connected = (cnxIdx < 0) ? false : true;

        // if chosen inst is already connected to the provider
        if (is_connected) {
          this.mutable_provider = Object.assign({},this.provider.connected[cnxIdx]);
          // Update form report_state to match the selected provider
          Object.assign(this.form.report_state, this.provider.connected[cnxIdx]['report_state']);
          this.current_inst_name = this.mutable_provider.inst_name;
          this.mutable_provider.connected = [...this.provider.connected];
          this.initializeForm();
        // Chosen inst is not yet connected
        } else {
          let _inst = this.institutions.find(ii => ii.id == this.form.inst_id);
          this.current_inst_name = (typeof(_inst) == 'undefined') ? "" : _inst.name;

          // Flip dtype to connect
          this.mutable_dtype = "connect";
          // Assigning conso-provider to a new inst_id?
          if (this.provider.is_conso && this.form.inst_id != 1) {
               this.warn_inst = "Saving this platform with new reports creates an institutional copy";
          }
          // We're about to connect it to the consortium?
          if (this.form.inst_id==1) {
              this.warn_inst = "Making this platform consortium-wide updates institutional definitions";
          }
          // Use consortium report_state if provider is conso-connected
          let consoIdx = this.provider.connected.findIndex(p => p.inst_id == 1);
          if (consoIdx >= 0) {
              Object.assign(this.form.report_state, this.provider.connected[consoIdx]['report_state']);
          // no conso connection; clear all the report states; admin needs to explicitly enable them
          } else {
              Object.keys(this.form.report_state).forEach( (key) =>  {
                  this.form.report_state[key]['prov_enabled'] = false;
                  this.form.report_state[key]['conso_enabled'] = false;
              });
          }
        }
      },
      initializeForm () {
        this.warn_inst = "";
        if (this.mutable_dtype == 'connect' && !this.provider.is_conso && this.provider.connected.length>0 && this.provider.inst_id==1) {
            this.warn_inst = "Making this platform consortium-wide updates institutional definitions";
        }
        // Setup initial form fields based on provider
        this.form.name = this.mutable_provider.name;
        this.form.global_id = this.mutable_provider.global_id;
        this.form.is_active = this.mutable_provider.is_active;
        this.form.allow_inst_specific = this.mutable_provider.allow_inst_specific;
        this.form.report_state = Object.assign({},this.mutable_provider.report_state);
        if ( this.is_admin ) {
            this.form.inst_id = this.mutable_provider.inst_id;
            let _inst = this.institutions.find(ii => ii.id == this.form.inst_id);
            this.current_inst_name = (typeof(_inst) == 'undefined') ? "" : _inst.name;
        } else {
            this.form.inst_id = this.institutions[0].id;
            this.current_inst_name = this.institutions[0].name;
        }
      },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin','is_serveradmin']),
      instRules() {
          if (this.mutable_dtype == 'connect') {
              return [ v => !!v || 'Institution is required' ];
          } else {
              return [];
          }
      },
      reportsEnabled() {
          return Object.values(this.form.report_state).some( rpt => rpt.prov_enabled == true);
      },
      reportRules() {
          return [ this.reportsEnabled || "At least one Report type must be selected" ];
      },
      unconnected_insts() {
          return this.institutions.filter(inst => !this.provider.connected.map(p => p.inst_id).includes(inst.id));
      },
      show_unconnected() {
          return (this.unconnected_insts.length>0 && this.form.allow_inst_specific==1);
      },
    },
    beforeMount() {
      if (this.provider.inst_id != null) {
          let current_inst = this.institutions.find(inst => this.provider.inst_id == inst.id);
          this.current_inst_name = (typeof(current_inst) == 'undefined') ? "" : current_inst.name;
      }
      this.mutable_provider = Object.assign({},this.provider);
      this.initializeForm();
    },
    mounted() {
      console.log('ProviderDialog Component mounted.');
    }
  }
</script>
<style scoped>
.verydense {
  max-height: 16px;
}
</style>
