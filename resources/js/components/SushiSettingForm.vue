<template>
  <div class="details">
  	<v-row no-gutters>
	    <h1 class="section-title">COUNTER API Credentials</h1>
      <v-col class="d-flex px-4 dt_action" cols="2">
        <v-icon title="Edit Credentials" @click="swapForm">mdi-cog-outline</v-icon>
        &nbsp; &nbsp;
        <v-icon title="Delete Credentials" @click="destroy(setting.id)">mdi-trash-can-outline</v-icon>
      </v-col>
  	</v-row>
    <div v-if="!showForm">
      <!-- form display control and confirmations  -->
      <!-- Values-only when form not active -->
      <v-row>
        <v-col v-if="setting.provider.connectors.some(c => c.name === 'customer_id')" cols="3">
          <strong>Customer ID: </strong>
          <span v-if="form.customer_id == '-required-'" class="Incomplete"><em>required</em></span>
          <span v-else>{{ form.customer_id }}</span>
        </v-col>
      	<v-col v-if="setting.provider.connectors.some(c => c.name === 'requestor_id')" cols="3">
          <strong>Requestor ID: </strong>
          <span v-if="form.requestor_id == '-required-'" class="Incomplete"><em>required</em></span>
          <span v-else>{{ form.requestor_id }}</span>
        </v-col>
      	<v-col v-if="setting.provider.connectors.some(c => c.name === 'api_key')" cols="3">
          <strong>API Key: </strong>
          <span v-if="form.api_key == '-required-'" class="Incomplete"><em>required</em></span>
          <span v-else>{{ form.api_key }}</span>
        </v-col>
        <v-col v-if="setting.provider.connectors.some(c => c.name === 'extra_args')" cols="3">
          <strong>Extra Args: </strong>
          <span v-if="form.extra_args == '-required-'" class="Incomplete"><em>required</em></span>
          <span v-else>{{ form.extra_args }}</span>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <strong>Support Email: </strong><a :href="'mailto:'+form.support_email">{{ form.support_email }}</a>
        </v-col>
      </v-row>
      <v-row>
        <v-col v-if="form.status == 'Enabled'" cols="4"><strong><font color='green'>Harvesting Enabled</font></strong></v-col>
        <v-col v-else><strong><font color='red'>Harvest Status: {{ form.status }}</font></strong></v-col>
        <v-col v-if="setting.next_harvest" cols="8">
          <strong>Next Harvest: </strong>{{ setting.next_harvest }}
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0 pt-1">
        <h2>Actions</h2>
      </v-row>
      <div class="d-flex ma-0 py-1">
        <v-btn small color="secondary" type="button" @click="testSettings"
               style="display:inline-block;margin-right:1em;">test</v-btn>
        <v-btn v-if="form.status == 'Enabled' || form.status == 'Suspended'" small color="warning" type="button"
               @click="changeStatus('Disabled')" style="display:inline-block;margin-right:1em;">disable</v-btn>
        <v-btn v-if="form.status != 'Enabled'" small color="green" type="button"
               @click="changeStatus('Enabled')" style="display:inline-block;margin-right:1em;">enable</v-btn>
        <a :href="'/harvests/create?inst='+setting.inst_id+'&prov='+setting.prov_id">
          <v-btn small color="primary" type="button" style="display:inline-block;margin-right:1em;">harvest</v-btn>
        </a>
      </div>
      <v-row class="d-flex ma-0 py-1 status-message" v-if="showTest || success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        <div v-if="showTest">
          <div>{{ testStatus }}</div>
          <div v-for="row in testData">{{ row }}</div>
        </div>
      </v-row>
    </div>

    <!-- display form if manager has activated it. onSubmit function closes and resets showForm -->
    <div v-else>
      <v-row>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
              class="in-page-form">
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'customer_id')">
            <v-text-field v-model="form.customer_id" label="Customer ID" outlined></v-text-field>
          </v-col>
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'requestor_id')">
            <v-text-field v-model="form.requestor_id" label="Requestor ID" outlined></v-text-field>
          </v-col>
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'api_key')">
            <v-text-field v-model="form.api_key" label="api_key" outlined></v-text-field>
          </v-col>
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'extra_args')">
            <v-text-field v-model="form.extra_args" label="Extra Arguments" outlined></v-text-field>
          </v-col>
          <v-col>
            <v-text-field v-model="form.support_email" label="Support Email" outlined></v-text-field>
          </v-col>
          <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
            Save Credentials
          </v-btn>
          <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </form>
      </v-row>
    </div>
  </div>
</template>

<script>
    import Form from '@/js/plugins/Form';
    import Swal from 'sweetalert2';
    window.Form = Form;
    export default {
        props: {
                setting: { type:Object, default: () => {} },
               },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
                showForm: false,
                showTest: false,
                testData: '',
                testStatus: '',
                form: new window.Form({
                    customer_id: this.setting.customer_id,
                    requestor_id: this.setting.requestor_id,
                    api_key: this.setting.api_key,
                    extra_args: this.setting.extra_args,
                    support_email: this.setting.support_email,
                    inst_id: this.setting.inst_id,
                    prov_id: this.setting.prov_id,
                    status: this.setting.status,
                })
            }
        },
        methods: {
            formSubmit (event) {
              this.form.patch('/sushisettings/'+this.setting.id)
                    .then( (response) => {
	                    this.warning = '';
	                    this.confirm = 'Credentials successfully updated.';
                      // Update form fields that may have been changed by the update
                      this.form.status = response.setting.status;
                      this.form.customer_id = response.setting.customer_id;
                      this.form.requestor_id = response.setting.requestor_id;
                      this.form.api_key = response.setting.api_key;
                      this.form.extra_args = response.setting.extra_args;
	                });
                this.showForm = false;
            },
            swapForm (event) {
                this.showForm = true;
            },
            hideForm (event) {
                this.showForm = false;
            },
            destroy (settingid) {
                var self = this;
                let message = "Deleting these credentials cannot be reversed, only manually recreated.";
                message += " NOTE: Harvest Log and Failed Harvest records connected to these credentials";
                message += " will also be deleted!";
                Swal.fire({
                  title: 'Are you sure?',
                  text: message,
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/sushisettings/'+settingid)
                           .then( (response) => {
                               if (response.data.result) {
                                   self.failure = '';
                                   self.success = response.data.msg;
                                   self.form.customer_id = '';
                                   self.form.requestor_id = '';
                                   self.form.api_key = '';
                                   self.form.extra_args = '';
                                   self.form.support_email = '';
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
            changeStatus (new_status) {
                axios.post('/sushisettings-update', {
                    inst_id: this.setting.inst_id,
                    prov_id: this.setting.prov_id,
                    status: new_status
                })
                .then( (response) => {
                    if (response.data.result) {
                        this.form.status = response.data.setting.status;
                    } else {
                        self.success = '';
                        self.failure = response.data.msg;
                    }
                })
                .catch(error => {});
            },
            testSettings (event) {
                this.showTest = true;
                this.testData = '';
                this.testStatus = "... Working ...";
                var testArgs = {'prov_id' : this.form.prov_id};
                if (this.setting.provider.connectors.some(c => c.name === 'requestor_id'))
                    testArgs['requestor_id'] = this.form.requestor_id;
                if (this.setting.provider.connectors.some(c => c.name === 'customer_id'))
                    testArgs['customer_id'] = this.form.customer_id;
                if (this.setting.provider.connectors.some(c => c.name === 'api_key'))
                    testArgs['api_key'] = this.form.api_key;
                if (this.setting.provider.connectors.some(c => c.name === 'extra_args'))
                    testArgs['extra_args'] = this.form.extra_args;
                axios.post('/sushisettings-test', testArgs)
                     .then((response) => {
                        if ( response.data.result == '') {
                            this.testStatus = "No results!";
                        } else {
                            this.testStatus = response.data.result;
                            this.testData = response.data.rows;
                        }
                    })
                   .catch(error => {});
            },
        },
        mounted() {
            this.showForm = false;
            console.log('SushiSettingForm Component mounted.');
        }
    }
</script>

<style scoped>
.Incomplete {
  color: #ff9900;
  font-style: italic;
}
</style>
