  <!-- Mail Settings Panel -->
<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useAuthStore } from '@/plugins/authStore.js';
  const { ccGet, ccPost } = useAuthStore();
  import { useValidationRules } from '@/composables/useValidationRules.js'
  import FlexCol from '../shared/FlexCol.vue'

  const { required, numberRule, emailRule } = useValidationRules()
  const presetItems = [
    {text: 'SMTP with STARTTLS (Port 587)', 'data': {mailer:'smtp-starttls',encryption:'TLS',port:587}},
    {text: 'SMTP over SSL (Port 465)', data: {mailer:'smtps',encryption:'SSL',port:465}},
    {text: 'SMTP (Plain, Port 25)', data: {mailer:'SMTP (Plain, port 25)',encryption:null,port:25}},
    {text: 'Custom', data: {mailer:'custom',encryption:null,port:null}},
  ];
  const success = ref('');
  const failure = ref('');
  const mailers = ['smtp', 'smtp-starttls', 'smtps', 'esmtp'];
  const crypts = ['None', 'SSL', 'TLS'];
  const mailSettings = ref([]);
  const mailFormValid = ref(true);
  const pw_show = ref(false);
  var mailPreset = ref({text:null, data:{mailer:null}});

  // Get mail settings
  const getSettings = async () => {
    try {
      const { data } = await ccGet("/api/getSettings/mail");
      mailSettings.value = {...data.records};
      // check for match against presets and if not, set as custom
      var matched;
      presetItems.forEach( (preset) => {
        matched = true;
        Object.keys(preset.data).forEach ( (key) => {
          if (preset.data[key] != mailSettings.value[key]) {
            matched = false;
            return;
          }
        });
        if (matched) {
          mailPreset.value = Object.assign({},preset);
          return;
        }
        matched = false;
      });
      if (!matched) {
        mailPreset.value = {
          text: 'Custom',
          data: {
            mailer: mailSettings.value['mailer'],
            encryption: mailSettings.value['encryption'],
            port: mailSettings.value['port']
          }
        }
      }
    } catch (error) {
      console.log('Error fetching settings: '+error.message);
    }
  }
  function updatePreset() {
    if (typeof(mailPreset.value.data) != 'undefined') {
      Object.keys(mailPreset.value.data).forEach( (key) => {
        mailSettings[key] = mailPreset.value.data[key];
      });
    }
  }
  async function formSubmit() {
    try {
      const response = await ccPost("/api/setSettings", { settings: mailSettings.value });
      if (response.result) {
        success.value = response.msg
      } else {
        failure.value = response.msg
      }
    } catch (error) {
      console.log('Error saving settings: '+error.message);
    }
  }
  // Watch for changes in settings to clear messages
  watch(mailSettings, () =>
    { success.value = '';
      failure.value = '';
    }, { deep: true }
  );
  onMounted(() => {
    getSettings();
  });
</script>
<template>
  <v-form @submit.prevent="formSubmit" v-model="mailFormValid">
    <v-sheet>
      <v-row>
        <FlexCol>
          <v-label>Mail Configuration</v-label>
          <v-row class="mt-2">
            <v-col>
              <v-select label="Mail Protocol & Encryption" :items="presetItems" v-model="mailPreset" 
                        @change="updatePreset()" placeholder="Select a mail configuration" item-title="text"
                        item-value="text" variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
          <v-row v-if="mailPreset.text=='Custom'">
            <v-col>
              <v-select label="Protocol" :items="mailers" v-model="mailSettings.mailer" placeholder="Select protocol"
                      :rules="[required]" variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
          <v-row v-if="mailPreset.text=='Custom'">
            <v-col>
              <v-text-field label="Port" v-model="mailSettings.port" placeholder="e.g. 587"
                      :rules="[numberRule]" variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
          <v-row v-if="mailPreset.text=='Custom'">
            <v-col>
              <v-select label="Encryption" :items="crypts" v-model="mailSettings.encryption" placeholder="Select encryption type"
                      :rules="[required]" variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
        </FlexCol>
        <FlexCol>
          <v-label>Mail Configuration</v-label>
          <v-row class="mt-2">
            <v-col>
              <v-text-field label="Host Name" v-model="mailSettings.host" placeholder="e.g. smtp.example.com"
                    variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-text-field label="User Name" v-model="mailSettings.mail_username" placeholder="Enter username"
                    variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-text-field label="Password" v-model="mailSettings.mail_password" :type="pw_show ? 'text' : 'password'"
                    :append-icon="pw_show ? 'mdi-eye' : 'mdi-eye-off'" @click:append="pw_show = !pw_show"
                    placeholder="Enter Password" variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
        </FlexCol>
        <FlexCol>
          <v-label>Sender Info</v-label>
          <v-row class="mt-2">
            <v-col>
              <v-text-field label="From Name" v-model="mailSettings.from_name" placeholder="e.g. Admin"
                    variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-text-field label="From Email" v-model="mailSettings.from_address" placeholder="e.g. admin@example.com"
                    :rules="[emailRule]" variant="outlined" hide-details="auto" class="mb-2" />
            </v-col>
          </v-row>
        </FlexCol>
        <FlexCol>
          <v-label>More Settings</v-label>
          <p>&nbsp;</p><p>Reserved for future use.</p>
        </FlexCol>
      </v-row>
      <div class="status-message" v-if="success || failure">
          <span v-if="failure" class="d-flex mx-1 redNotice">{{ failure }}</span>
          <span v-else-if="success" class="d-flex mx-1 greenNotice">{{ success }}</span>
      </div>
      <v-row>
        <FlexCol>
          <v-btn type="submit" color="primary" class="mt-4">Save Mail Settings</v-btn>
        </FlexCol>
      </v-row>
    </v-sheet>
  </v-form>
</template>
<style scoped>
 .redNotice { color: #ee0000; }
 .greenNotice { color: #339933; }
</style>
