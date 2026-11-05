<!--/views/admin/ServerSettings.vue-->

<!-- Mail Settings Panel -->
<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useAuthStore } from '@/stores/useAuthStore.js';
  import { useValidationRules } from '@/composables/useValidationRules.js'
  import { fyMonths, timeZones } from '@/stores/useCCPlusStore.js';
  import FlexCol from '@/components/ui/FlexCol.vue'
  import LockTip from '@/components/ui/LockTip.vue'
  const { ccGet, ccPost } = useAuthStore();
  // const { fyMonths, timeZones } = CCPlusStore();

  const { required, numberRule, booleanRule, yearmon } = useValidationRules()
  const success = ref('');
  const failure = ref('');
  const configSettings = ref([]);
  const formValid = ref(true);

  // Get mail settings
  const getSettings = async () => {
    try {
      const { data } = await ccGet("/api/settings/get/config");
      configSettings.value = {...data.records};
    } catch (error) {
      console.log('Error fetching settings: '+error.message);
    }
  }
  async function formSubmit() {
    try {
      const response = await ccPost("/api/settings/store", { settings: configSettings.value });
      if (response.result) {
        success.value = response.msg
      } else {
        failure.value = response.msg
      }
    } catch (error) {
      console.log('Error saving settings: '+error.message);
    }
  }
  // function ttClick() {
  //   console.log ('Clicked on tt');
  // }
  // Watch for changes in settings to clear messages
  watch(configSettings, () =>
    { success.value = '';
      failure.value = '';
    }, { deep: true }
  );
  onMounted(() => {
    getSettings();
  });
</script>
<template>
  <v-sheet>
    <v-form @submit.prevent="formSubmit" v-model="formValid">
      <v-row>
        <FlexCol>
          <v-label>Server Info</v-label>
          <v-row class="mt-2">
            <v-col>
              <v-text-field label="Root URL" v-model="configSettings.root_url" readonly  class="mb-2">
                <template v-slot:append>
                  <LockTip text="Readonly; Changeable in APP_ROOT:: .env" />
                </template>
              </v-text-field>
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-text-field label="Reports Path" v-model="configSettings.reports_path"
                            readonly  class="mb-2">
                <template v-slot:append>
                  <LockTip text="Readonly; Changeable in APP_ROOT:: .env" />
                </template>
              </v-text-field>
            </v-col>
          </v-row>
        </FlexCol>
        <FlexCol>
          <v-label>Harvest Settings</v-label>
          <v-row class="mt-2">
            <v-col>
              <v-text-field label="First 5.1 Harvest Default" v-model="configSettings.first_yearmon_51"
                            :rules="[yearmon]"   class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-select label="Fiscal Year Start" :items="fyMonths" v-model="configSettings.fiscalYr" 
                        placeholder="Select a month" :rules="[required]" item-title="label" item-value="value"
                         class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-select label="Time Zone" :items="timeZones" v-model="configSettings.time_zone" 
                        placeholder="Select a time zone"  class="mb-2" />
            </v-col>
          </v-row>
        </FlexCol>
        <FlexCol>
          <v-label>Retry & Logging</v-label>
          <v-row class="mt-2">
            <v-col>
              <v-text-field label="Max Harvest Retries" v-model="configSettings.max_harvest_retries" placeholder="'e.g. 5"
                            :rules="[numberRule]"   class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-text-field label="Cookie Life (days)" v-model="configSettings.cookie_life" placeholder="e.g. 30"
                    :rules="[required, numberRule]"   class="mb-2" />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-switch label="Log Login Fails" v-model="configSettings.log_login_fails" class="mb-2" />
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
          <v-btn type="submit" color="primary" class="mt-4">Save Server Settings</v-btn>
        </FlexCol>
      </v-row>
    </v-form>
  </v-sheet>
</template>
<style scoped>
 .redNotice { color: #ee0000; }
 .greenNotice { color: #339933; }
</style>
