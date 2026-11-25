<!-- resources/js/components/dialogs/ManageConnectionsDialog.vue -->
<script setup>
import { onMounted, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import InstitutionScopeSwitch from '@/components/ui/InstitutionScopeSwitch.vue';

const { t } = useI18n();
const authStore = useAuthStore();
const is_conso_admin = authStore.is_conso_admin;

const props = defineProps({
  item: { type: Object, required: true },
  options: { type: Object, required: true },
});

const emit = defineEmits(['submit', 'cancel']);

const consoEnabled = ref(false);
const useInstGroups = ref(false);
const selectedInstIds = ref([]);
const selectedGroupIds = ref([]);
const reportOptions = ref([]);
const selectedPlatform = ref(null);
const selectedReport = ref('');
const mutableItem = { ...props.item, flags: { ...props.item.flags } };

const instOptions = computed(() =>
  (props.options.institutions || []).filter((inst) => inst.id > 1)
);
const groupOptions = computed(() => props.options.groups || []);
const requiredRule = (v) => !!v || t('validation.field_required');

const showSelectors = computed(
  () => !consoEnabled.value && (props.item.type === 'Edit' || selectedReport.value !== '')
);

function onConsoSwitchChange(enabled) {
  if (enabled) {
    selectedInstIds.value = [];
    selectedGroupIds.value = [];
  }
}

function onScopeModeChange(isGroups) {
  if (isGroups) {
    selectedInstIds.value = [];
  } else {
    selectedGroupIds.value = [];
  }
}

function resetForm() {
  if (props.item.type === 'Add') {
    consoEnabled.value = false;
    useInstGroups.value = false;
    selectedInstIds.value = [];
    selectedGroupIds.value = [];
    selectedReport.value = '';
    selectedPlatform.value = null;
    return;
  }

  consoEnabled.value = Boolean(props.item.flags?.conso);
  if (consoEnabled.value) {
    useInstGroups.value = false;
    selectedInstIds.value = [];
    selectedGroupIds.value = [];
  } else {
    selectedInstIds.value = [...(props.item.flags?.insts || [])];
    selectedGroupIds.value = [...(props.item.flags?.groups || [])];
    useInstGroups.value = selectedGroupIds.value.length > 0 && selectedInstIds.value.length === 0;
  }
}

function updatePlatform(plat) {
  if (!plat) {
    reportOptions.value = [];
    return;
  }
  mutableItem.id = plat.id;
  reportOptions.value = Array.isArray(plat.reports)
    ? (props.options.reports || [])
        .filter((rpt) => plat.reports.includes(rpt.id))
        .map((rpt) => rpt.name)
    : [];
}

function updateReport(name) {
  if (!name || !selectedPlatform.value) return;
  mutableItem.rept = name;

  const instKey = `${name.toLowerCase()}_insts`;
  const platInsts = selectedPlatform.value[instKey] || [];
  if (platInsts.includes(1)) {
    consoEnabled.value = true;
    useInstGroups.value = false;
    selectedInstIds.value = [];
    selectedGroupIds.value = [];
    return;
  }

  consoEnabled.value = false;
  selectedInstIds.value = instOptions.value
    .filter((inst) => platInsts.includes(inst.id))
    .map((inst) => inst.id);

  const groupKey = `${name.toLowerCase()}_groups`;
  const platGroups = selectedPlatform.value[groupKey] || [];
  selectedGroupIds.value = groupOptions.value
    .filter((grp) => platGroups.includes(grp.id))
    .map((grp) => grp.id);

  useInstGroups.value = selectedGroupIds.value.length > 0 && selectedInstIds.value.length === 0;
}

function submitForm() {
  mutableItem.flags.conso = consoEnabled.value;
  mutableItem.flags.insts = consoEnabled.value || useInstGroups.value ? [] : [...selectedInstIds.value];
  mutableItem.flags.groups = consoEnabled.value || !useInstGroups.value ? [] : [...selectedGroupIds.value];
  emit('submit', mutableItem);
}

onMounted(() => {
  resetForm();
});
</script>

<template>
  <v-form @submit.prevent="submitForm">
    <div class="d-flex flex-column ga-3">
      <v-switch
        v-if="is_conso_admin"
        v-model="consoEnabled"
        :label="$t('connections.enable_for_consortium')"
        color="primary"
        @update:model-value="onConsoSwitchChange"
      />

      <v-autocomplete
        v-if="props.item.type === 'Add'"
        v-model="selectedPlatform"
        :items="props.item.connections"
        :label="$t('fields.platform')"
        return-object
        item-title="platform"
        item-value="id"
        :rules="[requiredRule]"
        @update:model-value="updatePlatform"
      />

      <v-select
        v-if="props.item.type === 'Add' && reportOptions.length > 0"
        v-model="selectedReport"
        :items="reportOptions"
        :label="$t('connections.report_to_connect')"
        :rules="[requiredRule]"
        @update:model-value="updateReport"
      />

      <template v-if="showSelectors">
        <InstitutionScopeSwitch
          v-if="instOptions.length > 0 && groupOptions.length > 0"
          v-model="useInstGroups"
          class="mb-n2"
          @update:model-value="onScopeModeChange"
        />

        <MultiSelectCombobox
          v-if="!useInstGroups && instOptions.length > 0"
          v-model="selectedInstIds"
          :items="instOptions"
          :label="$t('connections.enable_institutions')"
          item-title="name"
          item-value="id"
        />

        <MultiSelectCombobox
          v-else-if="groupOptions.length > 0"
          v-model="selectedGroupIds"
          :items="groupOptions"
          :label="$t('connections.enable_groups')"
          item-title="name"
          item-value="id"
        />
      </template>
    </div>

    <div class="d-flex justify-end ga-2 mt-6">
      <v-btn variant="tonal" color="secondary" @click="emit('cancel')">
        {{ $t('common.cancel', 'Cancel') }}
      </v-btn>
      <v-btn type="submit" color="primary" class="px-6">
        {{ $t('common.save', 'Save') }}
      </v-btn>
    </div>
  </v-form>
</template>
