<!-- components/dialogs/ReportToggle.vue -->
<script setup>
  import { onMounted, ref, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import ToggleIcon from '../shared/ToggleIcon.vue';

  const props = defineProps({
    item: { type: Object, required: true },
    options: { type: Object, required: true },
  });
  
  const authStore = useAuthStore();
  const is_conso_admin = authStore.is_conso_admin;
  var consoStr = ref('Inactive');
  var allInsts = ref(false);
  var allGroups = ref(false);
  var selectedInsts = ref([]);
  var selectedGroups = ref([]);
  var report_options = ref([]);
  var selectedPlatform = ref(null);
  var selectedReport = ref('');
  var mutable_item = {...props.item};
  // Keep all institutions in options - EXCEPT inst_id=1
  // (removed to avoid collision w/ conso toggle switch)
  const insts = props.options.institutions.filter(ii => ii.id > 1);
  const groups = [...props.options.groups];
  const requiredRule = (v) => !!v || 'This field is required';
  const emit = defineEmits(['submit','cancel']);
  const consoFlag = computed( () => { return (consoStr.value == 'Active') });
  const showSelectors = computed(() => {
    return (!consoFlag.value && ( props.item.type=='Edit' || selectedReport.value!='') );
  });

  // @change for institutions select
  function changeInsts() {
    if (allInsts.value && selectedInsts.value.length < insts.length) allInsts.value = false;
    if (selectedInsts.value.length == insts.length) allInsts.value = true;
  }
  // @change function for setting/clearing all institutions
  function updateAllInsts() {
    selectedGroups.value = [];
    selectedInsts.value = (allInsts.value) ? [] : insts.map(ii => ii.id);
    allInsts.value = !allInsts.value;
  }
  // @change for groups select
  function changeGroups() {
    if (allGroups.value && selectedGroups.value.length < groups.length) allGroups.value = false;
    if (selectedGroups.value.length == groups.length) allGroups.value = true;
  }
  // @change function for setting/clearing all groups
  function updateAllGroups() {
    selectedInsts.value = [];
    selectedGroups.value = (allGroups.value) ? [] : groups.map(ii => ii.id);
    allGroups.value = !allGroups.value;
  }
  function updateConsoFlag() {
    // Setting conso=yes means clearing selected insts and groups
    if (consoStr.value == 'Active') {
      selectedInsts.value = [];
      selectedGroups.value = [];
    }
  }
  // Reset form back to initial state (could connect to a U/I button)
  function resetForm() {
    if (props.item.type == 'Add' || props.item.flags.conso) {
      selectedInsts.value = [];
      selectedGroups.value = [];
    }
    if (props.item.type == 'Add') {
      consoStr.value = 'Inactive';
      selectedReport.value = '';
      selectedPlatform.value = null;
    } else {
      if (props.item.flags.conso) {
        consoStr.value = 'Active';
      } else {
        consoStr.value = 'Inactive';
        selectedInsts.value = insts.filter(ii => props.item.flags.insts.includes(ii.id));
        selectedGroups.value = groups.filter(ii => props.item.flags.groups.includes(ii.id));
      }
    }
  }
  // Add-operation-related : update platform and set report_options for the platform
  function updatePlatform(plat) {
    mutable_item.id = plat.id;
    if (typeof(plat.reports)=='undefined') {
      report_options.value = [];
    } else {
      report_options.value = props.options.reports.filter(rpt => plat.reports.includes(rpt.id))
                                                  .map( r2 => r2.name );
    }
  }
  // Add-operation-related : update report and set selectedInsts and selectedGroups for the report
  function updateReport(name) {
    mutable_item.rept = name;
    let _key = name.toLowerCase()+'_insts';
    if (typeof(selectedPlatform.value[_key]) == 'undefined') {
      selectedInsts.value = [];
    } else {
      if (selectedPlatform.value[_key].includes(1)) { // is conso?
        consoStr.value = 'Active';
        selectedInsts.value = [];
        selectedGroups.value = [];
        return;
      } else {
        selectedInsts.value = insts.filter(ii => selectedPlatform.value[_key].includes(ii.id));
      }
    }
    _key = name.toLowerCase()+'_groups';
      selectedGroups.value = ( typeof(selectedPlatform.value[_key]) == 'undefined') ? []
                             : groups.filter(gg => selectedPlatform.value[_key].includes(gg.id));
  }
  function submitForm() {
    mutable_item.flags.conso = consoFlag.value;
    mutable_item.flags.insts  = (consoFlag.value) ? [] : selectedInsts.value.map(ii => ii.id);
    mutable_item.flags.groups = (consoFlag.value) ? [] : selectedGroups.value.map(gg => gg.id);
    emit('submit', mutable_item);
  }
  onMounted(() => {
    resetForm();
  });
</script>

<template>
  <v-form @submit.prevent="submitForm">
    <v-container>
      <v-row class="d-flex" no-gutters>
        <v-col v-if="is_conso_admin" class="d-flex" cols="12">
          <strong>Enable For Entire Consortium</strong>
          <ToggleIcon v-model="consoStr" toggleable :size="36" @update:modelValue="updateConsoFlag"/>
        </v-col>
      </v-row>
      <v-row v-if="props.item.type=='Add'" class="d-flex" no-gutters>
        <v-col class="d-flex px-2">
          <v-autocomplete v-model="selectedPlatform" :items="props.item.connections" label="Platform"
                          return-object item-title="platform" item-value="id" :rules="[requiredRule]"
                          @update:modelValue="updatePlatform">
          </v-autocomplete>
        </v-col>
      </v-row>
      <v-row v-if="props.item.type=='Add' && report_options.length>0" class="d-flex" no-gutters>
        <v-col class="d-flex px-2">
          <v-select v-model="selectedReport" :items="report_options" label="Report to Connect" 
                    item-title="name" item-value="name" :rules="[requiredRule]"
                    @update:modelValue="updateReport">
          </v-select>
        </v-col>
      </v-row>
      <v-row v-if="showSelectors" class="d-flex" no-gutters>
        <v-col v-if="insts.length>0" class="d-flex px-2" cols="12">
          <v-autocomplete :items="insts" v-model="selectedInsts" label="Enable Institution(s)"
                          @change="changeInsts" multiple return-object item-title="name" item-value="id">
            <template v-slot:prepend-item>
              <v-list-item @click="updateAllInsts">
                  <span v-if="allInsts">Clear Selections</span>
                  <span v-else>All Institutions</span>
              </v-list-item>
              <v-divider class="mt-1"></v-divider>
            </template>
            <template v-slot:selection="{ item, index }">
              <span v-if="index==0 && allInsts">All Institutions</span>
              <span v-else-if="index==0 && !allInsts">{{ selectedInsts[0].name }}</span>
              <span v-else-if="index==1 && !allInsts" class="text-grey text-caption align-self-center">
                +{{ selectedInsts.length-1 }} more
              </span>
            </template>
          </v-autocomplete>
        </v-col>
      </v-row>
      <v-row v-if="showSelectors" class="d-flex" no-gutters>
        <v-col v-if="groups.length>0" class="d-flex px-2" cols="12">
          <v-autocomplete :items="groups" v-model="selectedGroups" label="Enable Group(s)"
                          @change="changeGroups" multiple return-object item-title="name" item-value="id">
            <template v-if="groups.length==1" v-slot:prepend-item>
              <v-list-item @click="updateAllGroups">
                  <span v-if="allGroups">Clear Selections</span>
                  <span v-else>All Groups</span>
              </v-list-item>
              <v-divider class="mt-1"></v-divider>
            </template>
            <template v-slot:selection="{ item, index }">
              <span v-if="index==0 && allGroups">All Groups</span>
              <span v-else-if="index==0 && !allGroups">{{ selectedGroups[0].name }}</span>
              <span v-else-if="index==1 && !allGroups" class="text-grey text-caption align-self-center">
                +{{ selectedGroups.length-1 }} more
              </span>
            </template>
          </v-autocomplete>
        </v-col>
      </v-row>
      <hr v-if="selectedInsts.length>0 || selectedGroups.length>0" width="90%">
      <div v-if="selectedInsts.length>0">
        <v-row class="d-flex my-1" no-gutters><h5>Connected Institutions</h5></v-row>
        <v-row v-for="inst in selectedInsts" class="d-flex ma-0" no-gutters>
          <v-col class="d-flex px-2" cols="12">{{ inst.name }}</v-col>
        </v-row>
      </div>
      <div v-if="selectedGroups.length>0">
        <v-row class="d-flex my-1" no-gutters><h5>Connected Institution Groups</h5></v-row>
        <v-row v-for="group in selectedGroups" class="d-flex ma-0" no-gutters>
          <v-col class="d-flex px-2" cols="12">{{ group.name }}</v-col>
        </v-row>
      </div>
      <hr width="90%">
      <v-row v-if="selectedInsts.length==0 && selectedGroups.length==0">
        <v-col v-if="consoFlag" class="d-flex" cols="12"><strong>Connected Consortium-Wide</strong></v-col>
        <v-col v-else class="d-flex" cols="12"><strong>No Active Connections</strong></v-col>
      </v-row>
      <hr v-if="selectedInsts.length==0 && selectedGroups.length==0" width="90%">
      <v-row class="d-flex mt-2" no-gutters>
        <v-col cols="12" class="text-left">
          <v-btn color="primary" @click="submitForm">Save</v-btn>
          <v-btn variant="text" class="ml-2" @click="emit('cancel')">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-container>
  </v-form>
</template>

<style scoped>
  .text-medium-emphasis {
    color: rgba(0, 0, 0, 0.6);
  }
  .error-highlight {
    border: 1px solid red;
  }
</style>
