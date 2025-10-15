<script setup> 
  import { ref, watch, computed, onBeforeMount, onMounted } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { formToJSON } from 'axios';
  import YmInput from '../shared/YmInput.vue';
  const { ccGet, ccPost } = useAuthStore();
  defineOptions({ name: 'ManualHarvest', inheritAttrs: false });
  const emit = defineEmits(['newHarvests','updatedHarvests']);
  var success = ref('');
  var failure = ref('');
  var working = ref('');
  var maxYM = ref('');
  var inst_name = ref('');
  var allPlats = ref(false);
  var allConsoPlats = ref(false);
  var allInsts = ref(false);
  // var selected_insts = ref([]);
  var institutions = ref([]);
  var institution_options = ref([]);
  var inst_groups = ref([]);
  var platforms = ref([]);
  var available_platforms = ref([]);
  var master_reports = ref([]);
  var available_reports = ref([]);
  var fields = ref([]);
  var fyMo = ref([]);
  var group_id = ref(null);
  var form = ref({
      inst: [],
      inst_group_id: 0,
      plat: [],
      reports: [],
      fromYM: '',
      toYM: '',
      when: 'later',
      skip_harvested: 1,
  });
  const minYM = ref('');
  const toKey = ref(0);
  // // Pinia DataStore
  // const authStore = useAuthStore();
  // const is_admin = authStore.is_admin;
  // const is_serveradmin = authStore.is_serveradmin;
  // Get UI options
  const initializeOptions = async () => {
    try {
      const { data } = await ccGet("/api/getManualOptions");
      institutions.value = [...data.records.institutions];
      institution_options.value = [...data.records.institutions];
      inst_groups.value = [...data.records.groups];
      platforms.value = [...data.records.platforms];
      available_platforms.value = [...data.records.platforms];
      master_reports.value = [...data.records.master_reports];
      fields.value = [...data.records.fields];
      fyMo.value = data.records.fyMo;
      // if ( !is_admin ) {
      //     form.value.inst = [institutions.value[0].id];
      //     inst_name.value = institutions.value[0].name;
      //     onInstChange();
      // }
    } catch (error) {
      console.log('Error loading options: '+error.message);
    }
  }
  const instSelectionString = computed(() => {
    if (form.value.inst.length == institution_options.value.length) return "All Institutions";
    let ret_string = "";
    let inst = institutions.value.find(ii => ii.id == form.value.inst[0]);
    if (typeof(inst) != 'undefined') {
      ret_string = inst.name;
      if (form.value.inst.length > 1) {
        ret_string += " +"+(form.value.inst.length-1).toString();
        ret_string += (form.value.inst.length > 2) ? " others" : " more";
      }
    }
    return ret_string;
  });
  const platSelectionString = computed(() => {
    if (allPlats.value) {
      return "All Platforms";
    } else if (allConsoPlats.value) {
      return "All Consortium Platforms";
    }
    let ret_string = "";
    if (form.value.plat.length>0) {
      let plat = institutions.value.find(p => p.id == form.value.plat[0]);
      if (typeof(plat) != 'undefined') {
        ret_string = plat.name;
        if (form.value.plat.length > 1) {
          ret_string += " +"+(form.value.plat.length-1).toString();
          ret_string += (form.value.plat.length > 2) ? " others" : " more";
        }
      }
    }
    return ret_string;
  });
  const selections_made = computed(() => {
    return (form.value.inst.length>0 || form.value.plat.length>0 || form.value.inst_group_id!=0);
  });
  const submit_enabled = computed(() => {
    return (form.value.plat.length>0 && form.value.reports.length>0 &&
            (form.value.inst.length>0 || form.value.inst_group_id>0) &&
            form.value.fromYM!='' && form.value.toYM!='');
  });
  //Functions
  function resetForm () {
    // Reset form values
    form.value.inst = [];
    form.value.plat = [];
    group_id.value = null;
    form.value.inst_group_id = 0;
    form.value.reports = [];
    form.value.fromYM = '';
    form.value.toYM = '';
    available_reports.value = [];
    allInsts.value = false;
    allPlats.value = false;
    allConsoPlats.value = false;
    available_platforms.value = [ ...platforms.value];
    // if (props.presets['prov_id']) verifyPlatPreset();
  }
  // // Verify provider preset value
  // function verifyPlatPreset() {
  //   let preset_id = Number(props.presets['prov_id']);
  //   let plat = available_platforms.value.find(p => p.id == preset_id);
  //   if (plat) {
  //       form.value.plat = [preset_id];
  //       onPlatChange();
  //   } else {
  //       failure.value = 'The preset platform is not available - verify COUNTER API credentials';
  //       form.value.plat = [];
  //       props.presets['prov_id'] = null;
  //   }
  // }
  // Update available platforms when inst-group changes
  function onGroupChange() {
    form.value.inst_group_id = group_id.value;
    if (group_id.value == null ) {
        available_platforms.value = [ ...platforms.value];
        form.value.inst = [];
    } else {
        let group = inst_groups.value.find(g => g.id == group_id.value);
        if (typeof(group) != 'undefined') {
            group.institutions.forEach(inst => { form.value.inst.push(inst.id); });
        }
        updatePlatforms();
    }
    // if (props.presets['prov_id']) verifyPlatPreset();
  }
  // Update available platforms when inst changes
  function onInstChange() {
    failure.value = '';
    group_id.value = null;
    form.value.inst_group_id = 0;
    allInsts.value = (form.value.inst.length == institutions.value.length) ? true : false;
    if (allInsts.value) {
      form.value.inst = institutions.value.map(ii => ii.id);
    }
    if (form.value.inst.length == 0) {
        available_platforms.value = [ ...platforms.value];
    } else {
        updatePlatforms();
    }
    // if (props.presets['prov_id']) verifyPlatPreset();
  }

  // // External axios call to return available platforms
  // function updatePlatforms () {
  //   let inst_ids = (allInsts.value) ? JSON.stringify([0]) : JSON.stringify(form.value.inst);
  //   axios.get('/available-providers?inst_ids='+inst_ids+'&group_id='+form.value.inst_group_id)
  //         .then((response) => {
  //             available_platforms.value = [ ...response.data.providers];
  //         })
  //         .catch(error => {});
  // }
  // Filter available platforms based on other selections
  function updatePlatforms () {
    if (form.value.inst.length == 0 ) {
      available_platforms.value = [ ...response.data.providers];
    } else {
      available_platforms.value = platforms.value.filter( plat =>
        plat.institutions.some( id => (id==1 || form.value.inst.includes(id)) )
      );
    }
  }
  function onPlatChange() {
    failure.value = '';
    // get the list of conso-platform IDs and set/update the All-Platform flags
    let conso_list = platforms.value.filter(p => p.institutions.some(id => id==1))
                                    .map(p2 => p2.id);
    allPlats.value = (platforms.value.length == form.value.plat.length && form.value.plat.length > 0);
    allConsoPlats.value = (!allPlats.value && conso_list.length == form.value.plat.length && form.value.plat.length > 0);
    // Setup plat_list with IDs
    let plat_list = [];
    if (allPlats.value) {
        plat_list = platforms.value.map(p => p.id);
    } else if (allConsoPlats.value) {
        plat_list = [ ...conso_list];
    } else {
        plat_list = [ ...form.value.plat];
    }
    // If no platforms, set reports to all
    if (plat_list.length == 0) {
        available_reports.value = [ ...master_reports.value];
        return;
    }
    // Update available reports when platforms changes
    available_reports.value = [];
    plat_list.forEach(pid => {
        let cur_plat = platforms.value.find(p => p.id == pid);
        if (typeof(cur_plat) == 'undefined') return;
        // cur_plat has no reports or we've already got all 4 turned on, skip the rest
        if (typeof(cur_plat.reports) == 'undefined') return;
        if (available_reports.value.length == 4) return;
        // loop across all report-type and check cur_plat to see if it should be enabled
        master_reports.value.forEach(rpt => {
            // if already enabled or cur_plat missing the report in it's list, skip it
            if (available_reports.value.some(r => r.name == rpt.name) ||
                typeof(cur_plat.reports[rpt.name]) == 'undefined') return;
            let add = false;
            if (cur_plat.reports[rpt.name]=="ALL") {
              add = true;
            } else if (cur_plat.reports[rpt.name].length > 0) {
              cur_plat.reports[rpt.name].forEach( inst => {
                if (form.value.inst.includes(inst)) add = true;
              });
            }
            if (add) available_reports.value.push(rpt);
        });
    });
  }
  async function formSubmit () {
    if (form.value.reports.length == 0) {
        failure.value = 'No reports selected for harvesting';
        return;
    }
    // Set from/to in the form with values from the data store and check them
    if (form.value.toYM == '' || form.value.fromYM == '') {
        failure.value = 'Range of months to harvest is required';
        return;
    }
    if (form.value.toYM == '' && form.value.fromYM != '') form.value.toYM = form.value.fromYM;
    if (form.value.fromYM == '' && form.value.toYM != '') form.value.fromYM = form.value.toYM;
    working.value = ' ... Creating and updating harvest records ...';
    try {
        const response = await ccPost("/api/storeHarvests", { settings: form.value });
        if (response.result) {
            working.value = '';
            failure.value = '';
            success.value = response.msg;
            if (response.new_harvests.length>0) {
                emit('newHarvests', { harvests:response.new_harvests, bounds:response.bounds });
            }
            if (response.upd_harvests.length>0) {
                emit('updatedHarvests', response.upd_harvests);
            }
        } else {
            success.value = '';
            failure.value = response.msg;
        }
    } catch (error) {
        console.log('Error saving settings: '+error.message);
    }
  }
  // @change function for filtering/clearing all platform flags
  function updateAllPlats(scope) {
    // Clear the flags and form value
    if (scope == 'Clear') {
        allPlats.value = false;
        allConsoPlats.value = false;
        form.value.plat = [];
        available_platforms.value = [ ...platforms.value];
    // Turn on all platforms
    } else if (scope == 'ALL') {
        allPlats.value = true;
        allConsoPlats.value = false;
        form.value.plat = platforms.value.map(p => p.id);
        available_platforms.value = [ ...platforms.value];
    // Turn on all Consortium platforms"
    } else {
        allPlats.value = false;
        allConsoPlats.value = true;
        form.value.plat = platforms.value.filter(plat => plat.institutions.some( id => id==1))
                                         .map(p2 => p2.id);
        available_platforms.value = platforms.value.filter(p => form.value.plat.includes(p.id));
    }
    onPlatChange();
  }
  // @change function for filtering/clearing all institutions
  function updateAllInsts() {
    group_id.value = null;
    form.value.inst_group_id = 0;
    // All Insts is ON... turn it OFF and reset selected
    if (allInsts.value) {
      allInsts.value = false;
      form.value.inst = [];
    // All Insts is OFF... turn it ON and reset selected
    } else {
    // } else if (is_admin || is_viewer.value) {
      allInsts.value = true;
      form.value.inst = institutions.value.map(ii => ii.id);
    }
  }
  watch( () => form.value.fromYM, (yearmon) => {
      toKey.value++;
      minYM.value = yearmon;
    }
  );
  onBeforeMount(() => {
    initializeOptions();
    if (institutions.value.length == 1) {
        inst_name.value = institutions.value[0].name;
        form.value.inst = [institutions.value[0].id];
        onInstChange();
    }
  });
  onMounted(() => {
    // if ( !is_admin.value ) {
    //     form.value.inst = [institutions.value[0].id];
    //     inst_name.value = institutions.value[0].name;
    //     onInstChange();
    // }
    let dt = new Date();
    maxYM.value = dt.getFullYear() + '-' + ('0' + (dt.getMonth()+1)).slice(-2);
    // // Apply inbound institution preset (provider handled in the InstChange function)
    // if (props.presets['inst_id']) {
    //     let instid = Number(props.presets['inst_id']);
    //     form.value.inst = [instid];
    //     onInstChange();
    // }
    console.log('ManualHarvest Component mounted.');
  });
</script>
<template>
  <div v-if="institution_options.length == 0" class="d-flex pa-4">
     <h3>Instititions must be configured in order to create harvests</h3>
  </div>
  <div v-else-if="available_platforms.length == 0" class="d-flex pa-4">
    <h3>Platforms must be connected in order to create harvests</h3>
  </div>
  <div v-else>
    <div v-if="selections_made">
      <v-btn color="gray" small @click="resetForm">Reset Selections</v-btn>
    </div>
  	<form method="POST" action="" @submit.prevent="formSubmit">
      <div v-if="institution_options.length>1">
        <v-row class="d-flex align-mid ma-2" no-gutters>
          <v-col v-if="form.inst_group_id==0" class="d-flex px-2" cols="4" sm="4">
            <v-autocomplete :items="institution_options" v-model="form.inst" @change="onInstChange" multiple label="Institution(s)"
                            item-title="name" item-value="id" hint="Institution(s) to Harvest">
              <template v-slot:prepend-item>
                <v-list-item @click="updateAllInsts">
                   <span v-if="allInsts">Clear Selections</span>
                   <span v-else>All Institutions</span>
                </v-list-item>
                <v-divider class="mt-1"></v-divider>
              </template>
              <template v-slot:selection="{ item,index }" >
                <span v-if="index==0">{{ instSelectionString }}</span>
              </template> 
            </v-autocomplete>
          </v-col>
          <v-col v-if="form.inst.length==0 && form.inst_group_id==0 " class="d-flex px-2" cols="1" sm="1">
            <strong>OR</strong>
          </v-col>
          <v-col v-if="form.inst.length==0" class="d-flex px-2" cols="4" sm="4">
            <v-autocomplete :items="inst_groups" v-model="group_id" @change="onGroupChange" label="Institution Group"
                            item-title="name" item-value="id" hint="Institution group to harvest"
            ></v-autocomplete>
          </v-col>
        </v-row>
      </div>
      <div v-else>
        <v-row class="d-flex align-mid ma-2" no-gutters>
          <v-col class="d-flex px-2" cols="6" sm="4">
            <h5>Institution : {{ inst_name }}</h5>
          </v-col>
        </v-row>
      </div>
      <v-row v-if="available_platforms.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="3" sm="3">
          <v-autocomplete :items="available_platforms" v-model="form.plat" @change="onPlatChange" label="Platform(s)"
                          multiple item-title="name" item-value="id" hint="Platform(s) to Harvest">
            <template v-slot:prepend-item>
              <v-list-item v-if="allConsoPlats || allPlats" @click="updateAllPlats('Clear')">
                 <span>Clear Selections</span>
              </v-list-item>
              <v-list-item v-if="!allConsoPlats && !allPlats" @click="updateAllPlats('ALL')">
                 <span>All Platforms</span>
              </v-list-item>
              <v-list-item v-if="!allConsoPlats && !allPlats" @click="updateAllPlats('Conso')">
                 <span>All Consortium Platforms</span>
              </v-list-item>
              <v-divider class="mt-1"></v-divider>
            </template>
            <template v-slot:selection="{ item,index }" >
              <span v-if="index==0">{{ platSelectionString }}</span>
            </template> 
          </v-autocomplete>
        </v-col>
      </v-row>
      <v-row v-if="available_reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="6" sm="4">
          <v-select :items="available_reports" v-model="form.reports" label="Report(s) to Harvest" multiple chips
                    item-title="legend" item-value="name" hint="Choose which master reports to harvest"
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex flex-row ma-2 align-center" no-gutters>
        <v-col class="d-flex px-2" cols="2" sm="2"><h5>Month(s) to Harvest</h5></v-col>
        <YmInput v-model="form.fromYM" label="From" :cols="2"/>
        <YmInput v-model="form.toYM" label="To" :minYM="minYM" :cols="2" :key="toKey"/>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="12">
          <span>Queue the harvest(s) to begin</span>
          <v-radio-group v-model="form.when" row>
            <v-radio :label="'Overnight'" value='later'></v-radio>
            <v-radio :label="'Now'" value='now'></v-radio>
          </v-radio-group>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="12">
          <v-checkbox v-model="form.skip_harvested" label="Skip Previously Harvested Reports" dense></v-checkbox>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure || working">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        <span v-if="working" class="work" role="alert" v-text="working"></span>
      </div>
      <v-row v-if="submit_enabled" no-gutters>
      <!-- <v-row v-if="form.reports.length>0 && (form.inst.length>0 || form.inst_group_id>0) && form.plat.length>0" no-gutters> -->
        <v-btn small color="primary" type="submit">Submit</v-btn>
      </v-row>
      <v-row v-else-if="(form.inst.length>0 || form.inst_group_id>0) && form.plat.length>0 && available_reports.length==0" no-gutters>
        <span class="form-fail" role="alert">No reports defined or available for selected Platform/Institution.</span>
      </v-row>
    </form>
  </div>
</template>