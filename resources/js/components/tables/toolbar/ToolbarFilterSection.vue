<!-- components/tables/toolbar/ToolbarFilterSection.vue -->
<template>
  <div v-if="filterOptions && filterOptions.length > 0" class="w-100">
    <!-- Row 1: FILTER Heading + Clear All / Refresh Actions -->
    <div class="d-flex flex-wrap align-center ga-3 mb-1">
      <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
        {{ $t('tables.filter', visibleFilterCount) }}
      </span>

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

    <!-- 3-Column Filter & Switch Grid -->
    <ToolbarFilters :filters="mergedVisibleFilters" @setFilter="$emit('setFilter', $event)" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ToolbarFilters from '@/components/tables/toolbar/ToolbarFilters.vue';

const props = defineProps({
  filterOptions: { type: Array, required: true },
  isHarvestDataset: { type: Boolean, default: false },
  anyFilterSet: { type: Boolean, default: false },
  showRefreshButton: { type: Boolean, default: false },
});

defineEmits(['setFilter', 'clearAll', 'refreshRecords']);

const INLINE_SWITCH_KEYS = new Set([
  'is_conso',
  'inst_active_only',
  'scope_mode',
  'status_switch',
  'valid_types',
  'valid_target',
  'releases',
  'no_inst',
  'no_plat',
  'no_host',
]);

const flatFilterOptions = computed(() => {
  const options = {};
  props.filterOptions.forEach((row) => {
    for (const key of Object.keys(row)) {
      options[key] = row[key];
    }
  });
  return options;
});

const isCredentialsStatus = computed(
  () =>
    flatFilterOptions.value.status_switch?.value === 'Enabled' ||
    flatFilterOptions.value.status_switch?.value === 'Incomplete' ||
    (flatFilterOptions.value.status_switch &&
      flatFilterOptions.value.statuses !== undefined &&
      !flatFilterOptions.value.releases)
);

const mergedVisibleFilters = computed(() => {
  const merged = {};
  const switchVal = flatFilterOptions.value.status_switch?.value;
  props.filterOptions.forEach((row) => {
    for (const [key, val] of Object.entries(row)) {
      if (props.isHarvestDataset && key === 'statuses') continue;
      if (key === 'statuses' && flatFilterOptions.value.status_switch) {
        if (!isCredentialsStatus.value || switchVal !== 'All') continue;
      }
      merged[key] = val;
    }
  });
  return merged;
});

const visibleFilterCount = computed(() =>
  Object.entries(mergedVisibleFilters.value).filter(
    ([key, f]) => f?.show && !INLINE_SWITCH_KEYS.has(key)
  ).length
);
</script>
