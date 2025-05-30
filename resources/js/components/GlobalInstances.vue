<template>
  <div>
    <v-row class="d-flex mt-2 justify-center" no-gutters>
      <v-btn small color="primary" @click="createForm">Add Consortium Instance</v-btn>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table :headers="con_headers" :items="mutable_consortia" item-key="id" :options="dt_options"
                  :hide-default-footer="hide_user_footer" :key="dtKey">
      <template v-slot:item.active="{ item }">
        <span v-if="item.is_active">
          <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
        </span>
        <span v-else>
          <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
        </span>
      </template>
      <template v-slot:item.action="{ item }">
        <v-btn v-if="!consoDialog" icon @click="editForm(item.id)">
          <v-icon title="Edit Instance Settings">mdi-cog-outline</v-icon>
        </v-btn>
        <v-btn icon @click="destroy(item.id)">
          <v-icon title="Delete Instance">mdi-trash-can-outline</v-icon>
        </v-btn>
      </template>
    </v-data-table>
    <v-dialog v-model="consoDialog" content-class="ccplus-dialog">
        <v-container grid-list-sm>
          <v-form v-model="formValid">
            <v-row class="d-flex ma-0">
              <v-col class="d-flex pt-2 justify-center">
                <h1 v-if="dialogType=='edit'" align="center">Edit Consortium settings</h1>
                <h1 v-else align="center">Create new consortium instance</h1>
              </v-col>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.name" :value="current_consortium.name" label="Consortium name" outlined dense required
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.ccp_key" label="Database Prefix Key" outlined dense :readonly="dialogType=='edit'"
                            :rules="pkRules"  hint="Cannot be modified once created!"
              ></v-text-field>
            </v-row>
            <v-row v-if="dialogType=='create' && form.ccp_key.length>0" class="d-flex mx-2 mb-2 warning-message">
              Once created, the database key cannot be changed from within the CC-Plus application.
              Changes are possible at the operating system level only.
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.admin_user" label="Username" readonly outlined dense></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field id="admin_pass" name="admin_pass" label="Administrator Password" outlined dense
                            :type="pw_show ? 'text' : 'password'" :append-icon="pw_show ? 'mdi-eye-off' : 'mdi-eye'"
                            @click:append="pw_show = !pw_show" v-model="form.admin_pass" :rules="passwordRules"
                            :required="dialogType=='create'"
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field id="admin_confirm_pass" name="admin_confirm_pass" label="Confirm password" outlined dense
                            :type="pwc_show ? 'text' : 'password'" :append-icon="pwc_show ? 'mdi-eye-off' : 'mdi-eye'"
                            @click:append="pwc_show = !pwc_show" v-model="form.admin_confirm_pass" :rules="passwordRules"
                            :required="dialogType=='create'"
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.email" :value="current_consortium.email" label="Email of Consortium Administrator"
                            outlined dense clearable
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2 align-center">
              <v-col class="d-flex px-2" cols="4">
                <v-switch v-model="form.is_active" :value="current_consortium.is_active" label="Active?" dense></v-switch>
              </v-col>
            </v-row>
            <v-row class="d-flex mx-2 align-center">
              <v-col class="d-flex px-2" cols="4">
                <v-btn x-small color="primary" @click="formSubmit" :disabled="!formValid">Save Consortium</v-btn>
              </v-col>
              <v-col class="d-flex px-2" cols="4">
                <v-btn x-small color="primary" type="button" @click="consoDialog=false">Cancel</v-btn>
              </v-col>
            </v-row>
          </v-form>
        </v-container>
    </v-dialog>
  </div>
</template>
<script>
  import Swal from 'sweetalert2';
  import axios from 'axios';
  export default {
    props: {
      consortia: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        dialogError: '',
        mutable_consortia: [...this.consortia],
        current_consortium: {},
        con_headers: [
            { text: 'Status', value: 'active', align: 'center' },
            { text: 'Database Key', value: 'ccp_key' },
            { text: 'Name', value: 'name' },
            { text: 'Email', value: 'email' },
            { text: 'Actions', value: 'action', align: 'end', sortable: false },
        ],
        dtKey: 1,
        dt_options: {itemsPerPage:10, sortBy:['name'], sortDesc:[false], multiSort:true, mustSort:false},
        hide_user_footer: true,
        hide_counter_footer: true,
        consoDialog: false,
        dialogType: "create",
        formValid: true,
        pw_show: false,
        pwc_show: false,
        form: new window.Form({
            ccp_key: '',
            name: '',
            email: '',
            is_active: 1,
            admin_user: 'Administrator',
            admin_pass: '',
            admin_confirm_pass: '',
        }),
      }
    },
    methods: {
      changeStatus(Id, state) {
        var _idx = this.mutable_consortia.findIndex(c => c.id == Id);
        if (_idx < 0) return;
        axios.patch('/consortia/'+Id, { is_active: state })
             .then((response) => {
              if (response.data.result) {
                // Update mutable_consortia record with new value
                this.mutable_consortia[_idx].is_active = state;
                this.dtKey++;
            } else {
                this.failure = response.msg;
            }
        });
      },
      formSubmit (event) {
          this.success = '';
          this.failure = '';
          this.dialogError = '';
          if (this.dialogType == 'edit') {
              this.form.patch('/consortia/'+this.current_consortium.id)
                  .then((response) => {
                      if (response.result) {
                          // Update mutable_consortia record with newly saved values...
                          var idx = this.mutable_consortia.findIndex(u => u.id == this.current_consortium.id);
                          Object.assign(this.mutable_consortia[idx], response.consortium);
                          this.success = response.msg;
                          this.dtKey++;
                      } else {
                          this.failure = response.msg;
                      }
                  });
          } else if (this.dialogType == 'create') {
            if (this.form.admin_pass != this.form.admin_confirm_pass) {
                this.dialogError = 'Passwords do not match! Please re-enter';
                return;
            }
            if (this.form.ccp_key == '' || this.form.ccp_key == null) {
                this.dialogError = 'Database Key is Required';
                return;
            }
            this.form.post('/consortia')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new consortium onto the mutable array and re-sort it
                        this.mutable_consortia.push(response.consortium);
                        this.mutable_consortia.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                        this.dtKey++;
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
          }
          this.consoDialog = false;
      },
      destroy (conId) {
          Swal.fire({
            title: 'Are you sure?',
            text: "Deleting a Consortium cannot be reversed, only manually recreated."+
                  "This operation will NOT remove database tables or harvested data."+
                  "These tasks will need to be handled at the operating system level.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, proceed'
          }).then((result) => {
            if (result.value) {
                axios.delete('/consortia/'+conId)
                     .then( (response) => {
                         if (response.data.result) {
                             this.success = response.data.msg;
                             this.failure = '';
                             this.mutable_consortia.splice(this.mutable_consortia.findIndex(c=> c.id == conId),1);
                             this.dtKey++;
                       } else {
                             this.success = '';
                             this.failure = response.data.msg;
                         }
                     })
                     .catch({});
            }
          })
          .catch({});
      },
      // Edit Consortium DOES NOT MODIFY the consortium admin account(s). That is done via the Users routes
      // so... the form.admin_*  fields are ignored by the consortium update() method
      editForm (consoId) {
          this.failure = '';
          this.success = '';
          this.dialogError = '';
          this.dialogType = "edit";
          this.current_consortium = this.mutable_consortia[this.mutable_consortia.findIndex(c=> c.id == consoId)];
          this.form.ccp_key = this.current_consortium.ccp_key;
          this.form.name = this.current_consortium.name;
          this.form.is_active = this.current_consortium.is_active;
          this.form.enable_harvesting = this.current_consortium.enable_harvesting;
          this.form.email = this.current_consortium.email;
          this.form.admin_user = 'Administrator';
          this.form.admin_pass = '';
          this.form.admin_confirm_pass = '';
          this.consoDialog = true;
      },
      // Create Consortium Dsets the initial consortium admin account.
      // so... the form.admin_*  fields matter for the consortium store() method
      createForm () {
          this.failure = '';
          this.success = '';
          this.dialogError = '';
          this.dialogType = "create";
          this.current_consortium = {ccp_key: '', name: '', email: '', is_active: 1, enable_harvesting: 1};
          this.form.ccp_key = '';
          this.form.name = '';
          this.form.email = '';
          this.form.is_active = 1;
          this.form.enable_harvesting = 1;
          this.form.admin_user = 'Administrator';
          this.form.admin_pass = '';
          this.form.admin_confirm_pass = '';
          this.consoDialog = true;
      },
    },
    computed: {
      pkRules() {
        return [ v => !!v || 'Prefix Key is required',
                 v => v.length <= 10 || 'Prefix Key limited to 10 characters'
               ];
      },
      passwordRules() {
          if (this.dialogType == 'create') {
              return [ v => !!v || 'Password is required',
                       v => v.length >= 8 || 'Password must be at least 8 characters'
                     ];
          } else {
              return [];
          }
      }
    },
    mounted() {
      console.log('GlobalAdmin Dashboard mounted.');
    }
  }
</script>
