<template>
  <div>
    <div>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="importForm">Import Types</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="createForm">Create a new type</v-btn>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="4">
          <a :href="'/institution/types/export/xlsx'">Export to Excel</a>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table :headers="headers" :items="mutable_types" item-key="id" :options="mutable_options"
                     :key="dtKey" @update:options="updateOptions">
        <template v-slot:item="{ item }">
          <tr>
            <td>{{ item.name }}</td>
            <td>
              <v-btn x-small class="btn btn-primary" type="button" @click="editForm(item.id)">Edit</v-btn>
              &nbsp; &nbsp;
              <v-btn x-small class='btn btn-danger' type="button" @click="destroy(item.id)">Delete</v-btn>
            </td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <v-dialog v-model="importDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Institution Types</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-row class="d-flex mb-2"><v-col class="d-flex pa-0">
              <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
            </v-col></v-row>
            <v-row class="d-flex ma-0"><v-col class="d-flex pa-0">
              <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
            </v-col></v-row>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="createDialog" max-width="800px">
      <v-card>
        <v-card-title>Create an Institution Type</v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" class="in-page-form"
              @keydown="form.errors.clear($event.target.name)">
          <v-card-text>
            <v-container grid-list-md>
              <v-row class="d-flex ma-0"><v-col class="d-flex pa-0">
                <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              </v-col></v-row>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn x-small color="primary" type="submit" :disabled="form.errors.any()">Save New Type</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="createDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>
    <v-dialog v-model="editDialog" max-width="800px">
      <v-card>
        <v-card-title>Edit an Institution Type</v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" class="in-page-form"
              @keydown="form.errors.clear($event.target.name)">
          <v-card-text>
            <v-container grid-list-md>
              <v-row class="d-flex ma-0"><v-col class="d-flex pa-0">
                <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              </v-col></v-row>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn x-small color="primary" type="submit" :disabled="form.errors.any()">Update Type</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="editDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </form>
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
            types: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        current_type: {},
        mutable_types: this.types,
        headers: [
          { text: 'Type', value: 'name' },
          { },
        ],
        form: new window.Form({
            name: '',
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
        importDialog: false,
        createDialog: false,
        editDialog: false,
        import_type: null,
        import_types: ['Full Replacement', 'New Additions']
      }
    },
    methods: {
        importForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.importDialog = true;
            this.createDialog = false;
            this.editDialog = false;
        },
        createForm () {
            this.form.name = '';
            this.createDialog = true;
            this.importDialog = false;
            this.editDialog = false;
        },
        editForm (typeid) {
            this.current_type = this.mutable_types[this.mutable_types.findIndex(t=> t.id == typeid)];
            this.form.name = this.current_type.name;
            this.editDialog = true;
            this.createDialog = false;
            this.importDialog = false;
        },
        importSubmit (event) {
            this.success = '';
            if (this.import_type == '') {
                this.failure = 'An import type is required';
                return;
            }
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            formData.append('type', this.import_type);
            axios.post('/institution/types/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                 })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response types
                         this.mutable_types = response.data.types;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
            this.importDialog = false;
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            if (this.editDialog) {
                this.form.patch('/institution/types/'+this.current_type.id)
                    .then((response) => {
                        if (response.result) {
                            // Update mutable_types record with new value
                            var idx = this.mutable_types.findIndex(t => t.id == this.current_type.id);
                            Object.assign(this.mutable_types[idx], response.type);
                            this.success = response.msg;
                        } else {
                            this.failure = response.msg;
                        }
                    });
                this.editDialog = false;
            } else if (this.createDialog) {
                this.form.post('/institution/types')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new type into the mutable array
                        this.mutable_types.push(response.type);
                        this.mutable_types.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
                this.createDialog = false;
            }
        },
        destroy(typeid) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "All institutions assigned this type will be reset to type = 1 (Not classified)",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/institution/types/'+typeid)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               this.mutable_types.splice(this.mutable_types.findIndex(t=> t.id == typeid),1);
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
        updateOptions(options) {
            if (Object.keys(this.mutable_options).length === 0) return;
            Object.keys(this.mutable_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_options[key]) {
                    this.mutable_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_options);
        },
    },
    computed: {
      ...mapGetters(['datatable_options'])
    },
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','institutiontypes');
  	},
    mounted() {
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('InstitutionTypes Component mounted.');
    }
  }
</script>

<style>

</style>
