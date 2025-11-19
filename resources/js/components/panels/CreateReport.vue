<script setup>
  import { ref, watch, computed, onBeforeMount } from 'vue';
  import { storeToRefs } from 'pinia';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
  import { useReportDates } from '@/composables/useReportDates'
  import MultiSelectCombobox from '../shared/MultiSelectCombobox.vue'
  import FlexCol from '../shared/FlexCol.vue'
  import YmInput from '../shared/YmInput.vue';
  // Pinia DataStores
  const { ccGet, ccPost } = useAuthStore();
  const authStore = useAuthStore();
  const { consortia } = storeToRefs(useCCPlusStore());
  const is_serveradmin = authStore.is_serveradmin;
  //
  var selectedConso = ref([]);
  var platformItems = ref([]);
  var institutionItems = ref([]);
  var institutionTypeItems = ref([]);
  var institutionGroupItems = ref([]);
  var accessMethodItems = ref([]);
  var usageMetricItems = ref([]);
  var searchMetricItems = ref([]);
  var turnawayItems = ref([]);
  var accessTypeItems = ref([]);
  var dataTypeItems = ref([]);
  var reports = ref([]);
  var all_fields = ref([]);
  // var otherItems = ref([]);
  // var excludedItems = ref([]);
  var fyMo = ref([]);
  const minYM = ref('');
  const toKey = ref(0);
  const selectedView = ref(0);
  const selectedReport = ref({});
  const selectedInstitutionTypes = ref([]);
  const selectedInstitutionGroups = ref([]);
  const selectedInstitutions = ref([]);
  const selectedPlatforms = ref([]);
  const selectedUsageMetrics = ref([]);
  const selectedSearchMetrics = ref([]);
  const selectedTurnaways = ref([]);
  const selectedAccessTypes = ref([]);
  const selectedAccessMethods = ref([]);
  const selectedDataTypes = ref([]);
  const selectedOtherElements = ref([]);

  const { reportDates, reportDateOptions, customStartDate, customEndDate } = useReportDates()

  const initializeOptions = async (key) => {
    try {
      const { data } = await ccGet("/api/reports/options/"+key);
      institutionItems.value = [...data.records.institutions];
      institutionTypeItems.value = [...data.records.institution_types];
      institutionGroupItems.value = [...data.records.groups];
      platformItems.value = [...data.records.platforms];
      dataTypeItems.value = [...data.records.data_types];
      accessTypeItems.value = [...data.records.access_types];
      accessMethodItems.value = [...data.records.access_methods];
      // global items... probably only need to get these once...
      reports.value = [...data.records.reports];
      all_fields.value = [...data.records.all_fields];
      usageMetricItems.value = [...data.records.usage_metrics];
      searchMetricItems.value = [...data.records.search_metrics];
      turnawayItems.value = [...data.records.turnaway_metrics];
      fyMo.value = data.records.fyMo;
    } catch (error) {
      console.log('Error loading options: '+error.message);
    }
  }
  const selectedReportId = computed(() => {
    return ( typeof(selectedReport.value.id)!='undefined' ) ? selectedReport.value.id : -1;
  });

  const filteredOtherElements = ref([]);
  // const filteredOtherElements = computed(() => {
  //   const r = selectedReportId - 1
  //   if (r < 0 || r >= otherItems.value.length) return []
  //   return (excludedItems.value.length>0)
  //       ? otherItems.value[r].filter(i => !excludedItems.value[r].includes(i))
  //       : otherItems.value[r];
  // });

  // 🔄 Sync filtered institutions to store
  // watch(institutionItems, () => {
  //   store.filteredInstitutions = institutionItems.value
  // })

  // 🔄 Reset downstream filters when upstream filters change
  watch(selectedConso, () => {
    initializeOptions(selectedConso.value);
  })
  watch( () => customStartDate.value, (yearmon) => {
      toKey.value++;
      minYM.value = yearmon;
    }
  );
  watch(selectedInstitutionTypes, () => {
    selectedInstitutionGroups.value = []
    selectedInstitutions.value = []
  })

  watch(selectedInstitutionGroups, () => {
    selectedInstitutions.value = []
  })
  onBeforeMount(() => {
    if (!is_serveradmin) {
      initializeOptions('conso');
    }
  });
</script>
<template>
  <v-row>
    <!-- Institution Filters -->
    <FlexCol>
      <div v-if="consortia.length>1 && is_serveradmin">
        <v-label class="colLabel">Choose a Consortium Instance</v-label>
        <v-select v-model="selectedConso" label="Consortium" :items="consortia"
                  itemTitle="name" itemValue="ccp_key" />
      </div>

      <v-label class="colLabel">Select Institutions</v-label>
      <MultiSelectCombobox v-model="selectedInstitutionTypes" label="Institution Types"
                           itemTitle="name" itemValue="id" :items="institutionTypeItems" />
      <MultiSelectCombobox v-model="selectedInstitutionGroups" label="Institution Groups"
                           itemTitle="name" itemValue="id" :items="institutionGroupItems" />
      <MultiSelectCombobox v-model="selectedInstitutions" label="Institutions"
                           itemTitle="name" itemValue="id" :items="institutionItems" />
                    
                           
    </FlexCol>
    <!-- Platform Filters -->
    <FlexCol>
      <v-label class="colLabel">Select Platforms</v-label>
      <MultiSelectCombobox v-model="selectedPlatforms" label="Platforms" itemTitle="name" itemValue="id"
                           :items="platformItems" />
    </FlexCol>

    <!-- Report Type Selection -->
    <FlexCol :lg="3">
      <v-label class="colLabel">Select Report Type</v-label>
      <v-radio-group v-model="selectedReport" class="me-8" return-object inline>
        <v-radio v-for="report in reports" :key="report.id" :label="report.legend" :value="report" />
      </v-radio-group>
      <v-expand-transition>
        <div v-if="selectedReportId>0">
          <v-radio-group label="Standard View (Optional)" v-model="selectedView" inline>
            <v-radio v-for="view in reports[selectedReportId - 1].children" :key="view.id"
                     :label="view.name" :value="view.id" />
          </v-radio-group>
        </div>
      </v-expand-transition>
    </FlexCol>

    <!-- Report Dates -->
    <FlexCol :lg="2">
      <v-label class="colLabel">Report Dates</v-label>
      <v-select v-model="reportDates" label="Choose Report Dates" :items="reportDateOptions"
                item-title="title" item-value="value" variant="outlined" hide-details />
      <YmInput v-model="customStartDate" label="Start Month" />
      <YmInput v-model="customEndDate" label="End Month" :minYM="minYM" :key="toKey"/>
    </FlexCol>
  </v-row>

  <v-divider></v-divider>

  <!-- COUNTER Filters -->
  <v-row>
    <FlexCol>
      <v-label class="colLabel">Usage Metrics</v-label>
      <MultiSelectCombobox v-model="selectedUsageMetrics" label="Investigations & Requests" :items="usageMetricItems"
                           dataName="Usage Metrics" itemTitle="name" itemValue="id" />

      <MultiSelectCombobox v-model="selectedSearchMetrics" label="Metric Type: Searches" :items="searchMetricItems"
                           dataName="Search Metrics" itemTitle="name" itemValue="id" />

      <MultiSelectCombobox v-model="selectedTurnaways" label="Turnaways" :items="turnawayItems"
                           itemTitle="name" itemValue="id" />
    </FlexCol>

    <FlexCol>
      <v-label class="colLabel">Access and Data</v-label>
      <MultiSelectCombobox v-model="selectedAccessMethods" label="Access Methods" :items="accessMethodItems"
                           itemTitle="name" itemValue="id" />

      <MultiSelectCombobox v-model="selectedAccessTypes" label="Access Types" :items="accessTypeItems"
                           itemTitle="name" itemValue="id" />

      <MultiSelectCombobox v-model="selectedDataTypes" label="Data Types" :items="dataTypeItems"
                           itemTitle="name" itemValue="id" />
    </FlexCol>

    <FlexCol>
      <v-label class="colLabel">Add Report Columns</v-label>
      <MultiSelectCombobox v-model="selectedOtherElements" :label="'Other Elements'" :items="filteredOtherElements"
                           itemTitle="name" itemValue="id" />
    </FlexCol>
  </v-row>
</template>

<style>
.align-mid { align-items: center; }
</style>
