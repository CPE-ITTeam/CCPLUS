<!-- components/dialogs/ReportToggle.vue -->
<script setup>
  import { onMounted, ref, reactive } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import ToggleIcon from '../shared/ToggleIcon.vue';

  const props = defineProps({
    item: { type: Object, required: true },
    options: { type: Object, required: true },
  });
  
  const authStore = useAuthStore();
  const is_conso_admin = authStore.is_conso_admin;
  var consoFlag = ref(false);
  var consoStr = ref('Inactive');
  var allInsts = ref(false);
  var allGroups = ref(false);
  var selectedInsts = ref([]);
  var selectedGroups = ref([]);
  // Keep all institutions in options - EXCEPT inst_id=1
  // (removed to avoid collision w/ conso toggle switch)
  const insts = props.options.institutions.filter(ii => ii.id > 1);
  const groups = [...props.options.groups];

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
    consoFlag.value = !consoFlag.value;
  }
  // Reset form back to initial state (could connect to a U/I button)
  function resetForm() {
    if (props.item.flags.conso) {
      consoFlag.value = true;
      consoStr.value = 'Active';
    } else {
      selectedInsts.value = insts.filter(ii => props.item.flags.insts.includes(ii.id));
      selectedGroups.value = groups.filter(ii => props.item.flags.groups.includes(ii.id));
    }
  }
  function submitForm() {
    props.item.flags.conso = consoFlag.value;
    props.item.flags.insts  = (consoFlag.value) ? [] : selectedInsts.value.map(ii => ii.id);
    props.item.flags.groups = (consoFlag.value) ? [] : selectedGroups.value.map(gg => gg.id);
    emit('submit', props.item);
  }
  const emit = defineEmits(['submit','cancel']);
  onMounted(() => {
    resetForm();
  });
</script>

<template>
  <v-form @submit.prevent="submitForm">
    <v-container>
      <v-row class="d-flex" no-gutters>
        <v-col v-if="is_conso_admin" class="d-flex px-2" cols="11">
          <strong>Enable For Entire Consortium</strong>
          <ToggleIcon v-model="consoStr" toggleable :size="36" @update:modelValue="updateConsoFlag"/>
        </v-col>
      </v-row>
      <v-row v-if="!consoFlag" class="d-flex" no-gutters>
        <v-col v-if="selectedGroups.length==0" class="d-flex px-2" cols="11">
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
              <!-- <span v-else-if="index==0 && !allInsts">{{ item.name }}</span> -->
              <span v-else-if="index==0 && !allInsts">{{ selectedInsts[0].name }}</span>
              <span v-else-if="index==1 && !allInsts" class="text-grey text-caption align-self-center">
                +{{ selectedInsts.length-1 }} more
              </span>
            </template>
          </v-autocomplete>
        </v-col>
        <v-col v-if="groups.length>0 && selectedInsts.length==0 && selectedGroups.length==0" class="d-flex px-2" cols="1">
          <v-col class="d-flex px-1 justify-center" cols="1">
            <strong>OR</strong>
          </v-col>
        </v-col>
      </v-row>
      <v-row v-if="!consoFlag" class="d-flex" no-gutters>
        <v-col v-if="groups.length>0 && selectedInsts.length==0" class="d-flex px-2" cols="11">
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
              <!-- <span v-else-if="index==0 && !allGroups">{{ item.name }}</span> -->
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
        <v-row v-for="inst in insts" class="d-flex ma-0" no-gutters>
          <v-col v-if="selectedInsts.includes(inst)" class="d-flex px-2" cols="12">{{ inst.name }}</v-col>
        </v-row>
      </div>
      <div v-if="selectedGroups.length>0">
        <v-row class="d-flex my-1" no-gutters><h5>Connected Institution Groups</h5></v-row>
        <v-row v-for="group in groups" class="d-flex ma-0" no-gutters>
          <v-col v-if="selectedGroups.includes(group)" class="d-flex px-2" cols="12">{{ group.name }}</v-col>
        </v-row>
      </div>
      <hr v-if="selectedInsts.length>0 || selectedGroups.length>0" width="90%">
      <v-row v-if="selectedInsts.length==0 && selectedGroups.length==0">
        <v-col v-if="consoFlag" class="d-flex" cols="12"><strong>Connection Consortium-Wide</strong></v-col>
        <v-col v-else class="d-flex" cols="12"><strong>No Active Connections</strong></v-col>
      </v-row>
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
