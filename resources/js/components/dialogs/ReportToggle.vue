<!-- components/dialogs/ReportToggle.vue (Script) -->
<script setup>
import { onMounted, ref, computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';
import BaseDialogLayout from './BaseDialogLayout.vue';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';

const props = defineProps({
  item: { type: Object, required: true },
  options: { type: Object, required: true },
});

const authStore = useAuthStore();
const is_conso_admin = authStore.is_conso_admin;

const consoStr = ref('Inactive');
const allInsts = ref(false);
const allGroups = ref(false);
const selectedInsts = ref([]);
const selectedGroups = ref([]);
const report_options = ref([]);
const selectedPlatform = ref(null);
const selectedReport = ref('');
const mutable_item = { ...props.item };

const insts = props.options.institutions.filter(ii => ii.id > 1);
const groups = [...props.options.groups];
const requiredRule = (v) => !!v || 'This field is required';
const emit = defineEmits(['submit', 'cancel']);

const consoFlag = computed(() => consoStr.value === 'Active');
const showSelectors = computed(() => {
  return (!consoFlag.value && (props.item.type === 'Edit' || selectedReport.value !== ''));
});

function changeInsts() {
  if (allInsts.value && selectedInsts.value.length < insts.length) allInsts.value = false;
  if (selectedInsts.value.length === insts.length) allInsts.value = true;
}

function updateAllInsts() {
  selectedGroups.value = [];
  selectedInsts.value = allInsts.value ? [] : [...insts];
  allInsts.value = !allInsts.value;
}

function changeGroups() {
  if (allGroups.value && selectedGroups.value.length < groups.length) allGroups.value = false;
  if (selectedGroups.value.length === groups.length) allGroups.value = true;
}

function updateAllGroups() {
  selectedInsts.value = [];
  selectedGroups.value = allGroups.value ? [] : [...groups];
  allGroups.value = !allGroups.value;
}

function updateConsoFlag() {
  if (consoStr.value === 'Active') {
    selectedInsts.value = [];
    selectedGroups.value = [];
  }
}

function resetForm() {
  if (props.item.type === 'Add' || props.item.flags.conso) {
    selectedInsts.value = [];
    selectedGroups.value = [];
  }
  if (props.item.type === 'Add') {
    consoStr.value = 'Inactive';
    selectedReport.value = '';
    selectedPlatform.value = null;
  } else {
    if (props.item.flags.conso) {
      consoStr.value = 'Active';
    } else {
      consoStr.value = 'Inactive';
      selectedInsts.value = insts.filter(ii => props.item.flags.insts.includes(ii.id));
      selectedGroups.value = groups.filter(gg => props.item.flags.groups.includes(gg.id));
    }
  }
}

function updatePlatform(plat) {
  if (!plat) return;
  mutable_item.id = plat.id;
  if (typeof plat.reports === 'undefined') {
    report_options.value = [];
  } else {
    report_options.value = props.options.reports
      .filter(rpt => plat.reports.includes(rpt.id))
      .map(r2 => r2.name);
  }
}

function updateReport(name) {
  if (!name) return;
  mutable_item.rept = name;
  let _key = name.toLowerCase() + '_insts';
  if (typeof selectedPlatform.value[_key] === 'undefined') {
    selectedInsts.value = [];
  } else {
    if (selectedPlatform.value[_key].includes(1)) {
      consoStr.value = 'Active';
      selectedInsts.value = [];
      selectedGroups.value = [];
      return;
    } else {
      selectedInsts.value = insts.filter(ii => selectedPlatform.value[_key].includes(ii.id));
    }
  }
  _key = name.toLowerCase() + '_groups';
  selectedGroups.value = typeof selectedPlatform.value[_key] === 'undefined'
    ? []
    : groups.filter(gg => selectedPlatform.value[_key].includes(gg.id));
}

function submitForm() {
  mutable_item.flags.conso = consoFlag.value;
  mutable_item.flags.insts = consoFlag.value ? [] : selectedInsts.value.map(ii => ii.id);
  mutable_item.flags.groups = consoFlag.value ? [] : selectedGroups.value.map(gg => gg.id);
  emit('submit', mutable_item);
}

onMounted(() => {
  resetForm();
});
</script>

<!-- components/dialogs/ReportToggle.vue (Template) -->
<template>
  <BaseDialogLayout @close="emit('cancel')">
    <template #title>
      {{ props.item.type === 'Add' ? 'Connect Report' : 'Manage Connections' }}
    </template>

    <v-form @submit.prevent="submitForm">
      <v-container class="pa-0">
        <v-row v-if="is_conso_admin" class="d-flex align-center mb-4" no-gutters>
          <v-col class="d-flex align-center">
            <span class="text-subtitle-1 font-weight-bold mr-4">Enable For Entire Consortium</span>
            <ToggleIcon v-model="consoStr" toggleable :size="36" @update:modelValue="updateConsoFlag" />
          </v-col>
        </v-row>

        <v-row v-if="props.item.type == 'Add'" class="d-flex mb-2" no-gutters>
          <v-col class="d-flex">
            <v-autocomplete 
              v-model="selectedPlatform" 
              :items="props.item.connections" 
              label="Platform"
              return-object 
              item-title="platform" 
              item-value="id" 
              variant="outlined"
              density="compact"
              :rules="[requiredRule]"
              @update:modelValue="updatePlatform"
            />
          </v-col>
        </v-row>

        <v-row v-if="props.item.type == 'Add' && report_options.length > 0" class="d-flex mb-2" no-gutters>
          <v-col class="d-flex">
            <v-select 
              v-model="selectedReport" 
              :items="report_options" 
              label="Report to Connect" 
              variant="outlined"
              density="compact"
              :rules="[requiredRule]"
              @update:modelValue="updateReport"
            />
          </v-col>
        </v-row>

        <v-row v-if="showSelectors" class="d-flex mb-2" no-gutters>
          <v-col v-if="insts.length > 0" class="d-flex" cols="12">
            <v-autocomplete 
              v-model="selectedInsts" 
              :items="insts" 
              label="Enable Institution(s)"
              multiple 
              return-object 
              item-title="name" 
              item-value="id"
              variant="outlined"
              density="compact"
              @update:modelValue="changeInsts"
            >
              <template #prepend-item>
                <v-list-item @click="updateAllInsts">
                  <v-list-item-title class="font-weight-bold color-primary">
                    {{ allInsts ? 'Clear Selections' : 'All Institutions' }}
                  </v-list-item-title>
                </v-list-item>
                <v-divider class="mt-1" />
              </template>
              <template #selection="{ item, index }">
                <span v-if="index === 0 && allInsts">All Institutions</span>
                <span v-else-if="index === 0 && !allInsts">{{ selectedInsts[0]?.name }}</span>
                <span v-else-if="index === 1 && !allInsts" class="text-grey text-caption align-self-center ml-1">
                  +{{ selectedInsts.length - 1 }} more
                </span>
              </template>
            </v-autocomplete>
          </v-col>
        </v-row>

        <v-row v-if="showSelectors" class="d-flex mb-2" no-gutters>
          <v-col v-if="groups.length > 0" class="d-flex" cols="12">
            <v-autocomplete 
              v-model="selectedGroups" 
              :items="groups" 
              label="Enable Group(s)"
              multiple 
              return-object 
              item-title="name" 
              item-value="id"
              variant="outlined"
              density="compact"
              @update:modelValue="changeGroups"
            >
              <template v-if="groups.length === 1" #prepend-item>
                <v-list-item @click="updateAllGroups">
                  <v-list-item-title class="font-weight-bold color-primary">
                    {{ allGroups ? 'Clear Selections' : 'All Groups' }}
                  </v-list-item-title>
                </v-list-item>
                <v-divider class="mt-1" />
              </template>
              <template #selection="{ item, index }">
                <span v-if="index === 0 && allGroups">All Groups</span>
                <span v-else-if="index === 0 && !allGroups">{{ selectedGroups[0]?.name }}</span>
                <span v-else-if="index === 1 && !allGroups" class="text-grey text-caption align-self-center ml-1">
                  +{{ selectedGroups.length - 1 }} more
                </span>
              </template>
            </v-autocomplete>
          </v-col>
        </v-row>

        <v-divider v-if="selectedInsts.length > 0 || selectedGroups.length > 0" class="my-4" />

        <div v-if="selectedInsts.length > 0" class="mb-4">
          <h5 class="text-subtitle-2 font-weight-bold mb-2 text-medium-emphasis">Connected Institutions</h5>
          <div v-for="inst in selectedInsts" :key="inst.id" class="text-body-2 py-1 px-2 bg-grey-lighten-4 rounded mb-1">
            {{ inst.name }}
          </div>
        </div>

        <div v-if="selectedGroups.length > 0" class="mb-4">
          <h5 class="text-subtitle-2 font-weight-bold mb-2 text-medium-emphasis">Connected Institution Groups</h5>
          <div v-for="group in selectedGroups" :key="group.id" class="text-body-2 py-1 px-2 bg-grey-lighten-4 rounded mb-1">
            {{ group.name }}
          </div>
        </div>

        <template v-if="selectedInsts.length === 0 && selectedGroups.length === 0">
          <v-divider class="my-4" />
          <div class="text-body-1 font-weight-bold py-2">
            {{ consoFlag ? 'Connected Consortium-Wide' : 'No Active Connections' }}
          </div>
        </template>
      </v-container>
      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <template #actions>
      <v-spacer />
      <v-btn color="primary" variant="flat" @click="submitForm">Save</v-btn>
      <v-btn variant="text" @click="emit('cancel')">Cancel</v-btn>
    </template>
  </BaseDialogLayout>
</template>
