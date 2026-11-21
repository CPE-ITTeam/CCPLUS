<!-- components/tables/toolbar/ToolbarFilterSelection.vue -->

<template>
  <div v-if="filterOptions && filterOptions.length > 0" class="w-100">
    <div v-for="(row, index) in filterOptions" :key="index" class="d-flex flex-column ga-1 flex-grow-1">
      <div v-if="index === 0" class="d-flex flex-wrap align-center ga-3 mb-1">
        <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
          {{ $t('tables.filter', visibleFilterCount) }}
        </span>

        <v-switch
          v-if="flatFilterOptions.is_conso"
          :model-value="consoSwitchState.modelValue"
          :indeterminate="consoSwitchState.indeterminate"
          :color="consoSwitchState.color"
          :label="consoSwitchState.label"
          inset
          true-icon="mdi-star-circle"
          false-icon="mdi-home-circle"
          class="flex-grow-0 my-n2"
          style="transform: scale(0.85); transform-origin: left center;"
          @click.prevent="cycleConsoSwitch"
        />

        <v-chip
          v-if="anyFilterSet"
          size="small"
          color="error"
          variant="tonal"
          prepend-icon="mdi-close-circle-outline"
          class="cursor-pointer font-weight-medium"
          @click="$emit('clearAll')"
        >
          {{ $t('tables.clear_all') }}
        </v-chip>

        <v-btn
          v-if="showRefreshButton"
          size="small"
          variant="tonal"
          prepend-icon="mdi-refresh"
          class="ms-2"
          @click="$emit('refreshRecords')"
        >
          {{ $t('tables.refresh_records') }}
        </v-btn>
      </div>

      <ToolbarFilters :filters="visibleRowFilters(row)" @setFilter="$emit('setFilter', $event)" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ToolbarFilters from '@/components/tables/toolbar/ToolbarFilters.vue';


const props = defineProps({
  filterOptions: { type: Array, required: true },
  isHarvestDataset: { type: Boolean, default: false },
  anyFilterSet: { type: Boolean, default: false },
  showRefreshButton: { type: Boolean, default: false },
});

const emit = defineEmits(['setFilter', 'clearAll', 'refreshRecords']);

const { t } = useI18n();

const flatFilterOptions = computed(() => {
  const options = {};
  props.filterOptions.forEach((row) => {
    for (const key of Object.keys(row)) {
      options[key] = { ...row[key] };
    }
  });
  return options;
});

const visibleFilterCount = computed(() =>
  props.filterOptions.flatMap((r) => Object.keys(visibleRowFilters(r))).length
);

const consoSwitchState = computed(() => {
  const val = flatFilterOptions.value.is_conso?.value || 'All';
  if (val === 'Active') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'primary',
      label: t('fields.limit_to_consortial', 'Limit to Consortial'),
    };
  }
  if (val === 'NonConso') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'secondary',
      label: t('fields.exclude_consortial', 'Exclude Consortial'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'success',
    label: t('fields.all_platforms', 'All Platforms'),
  };
});

function visibleRowFilters(row) {
  const copy = { ...row };
  delete copy.is_conso;
  if (props.isHarvestDataset) {
    delete copy.statuses;
  }
  return copy;
}

function cycleConsoSwitch() {
  const current = flatFilterOptions.value.is_conso?.value || 'All';
  const next = current === 'All' ? 'Active' : current === 'Active' ? 'NonConso' : 'All';
  emit('setFilter', { key: 'is_conso', value: next });
}
</script>
