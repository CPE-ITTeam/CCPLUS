<template>
  <div>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" @click="createForm">Create a User</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" @click="importForm">Import Users</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <a @click="exportDialog=true;">
          <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export Users to Excel
        </a>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details clearable
        ></v-text-field>
      </v-col>
    </v-row>
    <v-row class="d-flex ma-0" no-gutters>
      <v-col v-if="is_admin && mutable_institutions.length>1" class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_institutions" v-model="filters['inst']" @change="updateFilters('inst')"
                        label="Limit by Institution"  item-text="name" item-value="id" multiple>

          <template v-if="is_admin" v-slot:prepend-item>
            <v-list-item @click="filterAll('inst')">
               <span v-if="allSelected.inst">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-if="is_admin" v-slot:selection="{ item, index }">
            <span v-if="index==0 && mutable_institutions.length==filters['inst'].length">
              All Institutions
            </span>
            <span v-else-if="index==0 && !allSelected.inst">{{ item.name }}</span>
            <span v-else-if="index===1 && !allSelected.inst" class="text-grey text-caption align-self-center">
              &nbsp; +{{ filters['inst'].length-1 }} more
            </span>
          </template>

        </v-autocomplete>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <v-select :items="status_options" v-model="filters['stat']" @change="updateFilters('stat')"
                  label="Limit by Status"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="3">
        <div v-if="filters['roles'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('roles')"/>&nbsp;
        </div>
        <v-select :items="allowed_roles" v-model="filters['roles']" @change="updateFilters('roles')" multiple
                  label="Limit by Role(s)"  item-text="name" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="filterAll('roles')">
               <span v-if="allSelected.roles">Clear Selections</span>
               <span v-else>Select All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-if="is_admin" v-slot:selection="{ item, index }">
            <span v-if="index==0 && allowed_roles.length==filters['roles'].length">
              All Roles
            </span>
            <span v-else-if="index==0 && !allSelected.roles">{{ item.name }}</span>
            <span v-else-if="index===1 && !allSelected.roles" class="text-grey text-caption align-self-center">
              &nbsp; +{{ filters['roles'].length-1 }} more
            </span>
          </template>
        </v-select>
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table :headers="headers" :items="mutable_users" item-key="id" :options="mutable_options"
                  :key="'DT'+dtKey" :search="search" @update:options="updateOptions">
        <template v-slot:item.status="{ item }">
          <span v-if="item.is_active">
            <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
          </span>
          <span v-else>
            <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
          </span>
        </template>
        <template v-slot:item.action="{ item }">
          <span class="dt_action">
            <v-btn icon @click="editForm(item.id)">
              <v-icon title="Edit User Settings">mdi-cog-outline</v-icon>
            </v-btn>
            <v-btn icon class="pl-4" @click="destroy(item.id)">
              <v-icon title="Delete User">mdi-trash-can-outline</v-icon>
            </v-btn>
          </span>
        </template>
        <v-alert slot="no-results" :value="true" color="error" icon="warning">
          Your search for "{{ search }}" found no results.
        </v-alert>
    </v-data-table>
    <v-dialog v-model="importDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Users</v-card-title>
        <v-spacer></v-spacer>
        <v-card-subtitle><strong>NOTE: Users cannot be deleted during an import operation.</strong></v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <v-layout wrap>
              <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
              <p>
                Use caution when using this import function. Password fields will be encrypted when they are saved.
                The <strong>import source file</strong>, however, could contain clear-text user passwords as CSV
                values. Protecting or deleting this file after a successful import is recommended to help prevent
                unauthorized access to the data and settings for your consortium.
              </p>
              <p><strong>User imports operate as both "Add" and "Update".</strong><br />
                If an ID in column-1 of the import file matches an existing user -OR- if the ID does not match any
                existing user, but the email in column-2 does match an existing user, the import will update that user.
                Otherwise, the import will perform an "Add" operation. Any import row with an empty or non-existent
                institution ID in column-9 will be ignored.
              </p>
              <ul><strong>Updating users</strong>:
                <li>Updates will overrwite all fields for the user, with the possible exception of the password, with
                    the values in the import file.</li>
                <li>Import rows (with a matching ID) that attempt to set an existing user's email to a value already
                    defined for another user will result in an unchanged email address and other values updated.</li>
                <li>Rows with a blank or empty password value will result in an unchanged password and the other fields
                    updated.
                </li>
              </ul>
              <ul><strong>Adding users</strong>:
                <li>The surest way to add users is to assign new, sequentially increasing values in the column-1 (ID),
                    and a unique email address in column-2.</li>
                <li>Import rows that attempt to add a user with an email field value that matches the email address for
                    another user will be ignored.</li>
                <li>Rows with a blank or empty password values will be ignored.</li>
              </ul>
            </v-layout>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="importSubmit" :disabled="csv_upload==null">
                Run Import
            </v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="userDialog" content-class="ccplus-dialog">
      <user-dialog :dtype="dialogType" :user="current_user" :allowed_roles="allowed_roles" :institutions="mutable_institutions"
                   :groups="all_groups" @user-complete="userDialogDone" :key='udKey'
      ></user-dialog>
    </v-dialog>
    <v-dialog v-model="exportDialog" max-width="600px">
      <v-card>
        <v-card-title>Export Users</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <p>
              The records to be exported depend on the display context and values defined for filters in the user interface.
              <strong>In order to retrieve all records, all filters must be cleared first.</strong>
            </p>
            <p v-if="filters.inst.length==0">
              <strong>Note:&nbsp; By default, the export will only include user accounts and institutions that have accounts
                defined in the system. Enabling the checkbox (below) will include all other institutions in the output listing,
                albeit without actual user names/emails/etc. The resulting CSV can be editted and imported to CC+ to modify
                or create user accounts in-bulk.</strong>
            </p>
            <v-checkbox v-if="filters.inst.length==0" v-model="all_insts" label="Include Institutions with no users?" dense
            ></v-checkbox>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-col class="d-flex">
            <v-btn small color="primary" type="submit" @click="exportSubmit">Run Export</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn small type="button" color="primary" @click="exportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
</div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import Swal from 'sweetalert2';
  import axios from 'axios';
  export default {
    props: {
            users: { type:Array, default: () => [] },
            allowed_roles: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            all_groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        mutable_users: [ ...this.users ],
        mutable_institutions: [ ...this.institutions ],
        filters: { inst: [], stat:'ALL', roles:[] },
        current_user: {},
        pw_show: false,
        pwc_show: false,
        dialogType: 'create',
        userDialog: false,
        importDialog: false,
        exportDialog: false,
        all_insts: false,
        udKey: 0,
        search: '',
        status_options: ['ALL', 'Active', 'Inactive'],
        allSelected: {'inst': false, 'roles': false},
        headers: [
          { text: 'Status', value: 'status' },
          { text: 'User Name ', value: 'name' },
          { text: 'Institution', value: 'institution.name' },
          { text: 'Email', value: 'email' },
          { text: 'Roles', value: 'role_string' },
          { text: 'Last Login', value: 'last_login' },
          { text: 'Actions', value: 'action', align: 'end', sortable: false },
        ],
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
      }
    },
    methods: {
        exportSubmit () {
            this.success = '';
            this.failure = '';
            let url = "/users-export?all_insts="+this.all_insts;
            if (this.filters['inst'].length > 0 || this.filters['roles'].length > 0 ||
                this.filters['stat'] != '' && this.filters['stat'] != null ) {
                let _filters = JSON.stringify(this.filters);
                url += "&filters="+_filters;
            }
            window.location.assign(url);
            this.exportDialog = false;
        },
        destroy (userid) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "All information related to this user will also be deleted!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/users/'+userid)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               this.mutable_users.splice(this.mutable_users.findIndex(u=> u.id == userid),1);
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
        userDialogDone ({ result, msg, user, new_inst }) {
            this.success = '';
            this.failure = '';
            // Dialog may have created institutions and still not return success
            if (new_inst.length>0) {
                // Add any new institutions onto the mutable array and re-sort it
                for (const inst of new_inst) {
                    this.mutable_institutions.push(inst);
                }
                this.mutable_institutions.sort((a,b) => {
                  if ( a.name < b.name ) return -1;
                  if ( a.name > b.name ) return 1;
                  return 0;
                });
                this.$emit('new-inst', new_inst);
            }
            if (result == 'Success') {
                if (this.dialogType == 'edit') {
                    let _idx = this.mutable_users.findIndex(u=> u.id == user.id);
                    this.mutable_users[_idx] = user;
                } else if (this.dialogType == 'create') {
                    this.mutable_users.push(user);
                    this.mutable_users.sort((a,b) => {
                      if ( a.name < b.name ) return -1;
                      if ( a.name > b.name ) return 1;
                      return 0;
                    });
                }
                this.success = msg;
                this.dtKey += 1;
            } else if (result == 'Fail') {
                this.failure = msg;
            } else if (result != 'Cancel') {
                this.failure = 'Unexpected Result returned from dialog - programming error!';
            }
            this.userDialog = false;
        },
        importForm () {
            this.csv_upload = null;
            this.importDialog = true;
            this.userDialog = false;
        },
        editForm (userid) {
            this.failure = '';
            this.success = '';
            this.dialogType = "edit";
            this.current_user = this.mutable_users[this.mutable_users.findIndex(u=> u.id == userid)];
            this.udKey += 1;
            this.userDialog = true;
            this.importDialog = false;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.dialogType = "create";
            var _inst = (this.is_admin) ? null : this.institutions[0].id;
            this.current_user = {roles: [1], inst_id: _inst};
            this.udKey += 1;
            this.userDialog = true;
            this.importDialog = false;
        },
        updateFilters(filter) {
            this.$store.dispatch('updateAllFilters',this.filters);
            // update allSelected flag
            if (filter == 'inst') {
              this.allSelected['inst'] = (this.filters['inst'].length > 0 &&
                                          this.filters['inst'].length == this.mutable_institutions.length);
            } else if (filter == 'roles') {
              this.allSelected['roles'] = (this.filters['roles'].length > 0 &&
                                           this.filters['roles'].length == this.allowed_roles.length);
            }
            this.updateRecords();
        },
        clearFilter(filter) {
            if (filter == 'inst') {
              this.filters['inst'] = [];
              this.allSelected['inst'] = false;
            } else if (filter == 'roles') {
              this.filters['roles'] = [];
              this.allSelected['roles'] = false;
            }
            this.$store.dispatch('updateAllFilters',this.filters);
            this.updateRecords();
        },
        // @click function for filtering/clearing all options on a filter
        filterAll(filter) {
            if (typeof(this.allSelected[filter]) == 'undefined') return;
            // Turned an all-options filter OFF?
            if (this.allSelected[filter]) {
              this.filters[filter] = [];
              this.allSelected[filter] = false;
            // Turned an all-options filter ON
            } else {
              this.filters[filter] = (filter == 'inst')
                                     ? this.mutable_institutions.map( ii => ii.id )
                                     : this.allowed_roles.map ( r => r.id );
              this.allSelected[filter] = true;
            }
            this.updateRecords();
        },
        updateRecords() {
            this.success = "";
            this.failure = "";
            let _filters = JSON.stringify(this.filters);
            axios.get("/users?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_users = response.data.users;
                 })
                 .catch(err => console.log(err));
        },
        importSubmit (event) {
            this.success = '';
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            axios.post('/users/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response users
                         this.mutable_users = response.data.users;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
            this.importDialog = false;
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
        changeStatus(userId, state) {
          axios.patch('/users/'+userId, { is_active: state })
               .then( (response) => {
                 if (response.data.result) {
                   var _idx = this.mutable_users.findIndex(uu=>uu.id == userId);
                   this.mutable_users[_idx].is_active = state;
                 }
               })
               .catch(error => {});
        },
    },
    computed: {
      ...mapGetters(['is_admin','all_filters','datatable_options']),
      passwordRules() {
          if (this.dialogType == 'create') {
              return [ v => !!v || 'Password is required',
                       v => v.length >= 8 || 'Password must be at least 8 characters'
                     ];
          // formSubmit handles password validation for edit
          } else {
              return [];
          }
      }
    },
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','users');
	  },
    mounted() {
      // Apply any existing store filters
      Object.assign(this.filters, this.all_filters);

      // if institutions array has only one member, set the id in the filter before getting records
      if (this.mutable_institutions.length == 1) {
          this.filters['inst'] = [this.mutable_institutions[0].id];
      }

      // If not admin, remove the institution column
      if (!this.is_admin) {
          this.headers.splice(this.headers.findIndex(h=> h.text == 'Institution'),1);
      }

      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);

      // Load Users
      this.updateRecords();
      this.dtKey += 1;           // update the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
      console.log('UserData Component mounted.');
    }
  }
</script>

<style>

</style>
