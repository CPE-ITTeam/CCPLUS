<!-- components/tables/toolbar/ToolbarFilters.vue -->
<template>
  <v-row v-if="filterColumns.length > 0" align="start" class="w-100 pt-2 mt-0">
    <v-col
      v-for="col in filterColumns"
      :key="col.key"
      cols="12"
      md="4"
      class="d-flex flex-column ga-3 pt-1"
    >

      <template v-for="entry in col.items" :key="entry.name">
        <!-- Year-Month Range Filter (Vertical stack inside column, matching ManualHarvestPanel) -->
        <YmRangeSelector
          v-if="entry.filter.type == 'ymrange'"
          v-model="props.filters[entry.name].value"
          :label="entry.filter.label"
          clearable
          @update:modelValue="handleFilter(entry.name, $event)"
        />
        <!-- Toggle filter case (Vuetify switch: green Hide when off, warning Show when on) -->
        <div v-else-if="entry.filter.type == 'toggle'" class="d-flex align-center pl-1 flex-grow-0">
          <v-switch
            :model-value="props.filters[entry.name].value === 'Active' || props.filters[entry.name].value === true"
            :label="formatToggleLabel(entry)"
            color="warning"
            base-color="success"
            inset
            hide-details
            density="compact"
            class="flex-grow-0"
            style="transform: scale(0.9); transform-origin: left center;"
            @update:modelValue="handleFilter(entry.name, $event ? 'Active' : 'Inactive')"
          />
        </div>

        <!-- Multi-select combobox case -->
        <MultiSelectCombobox
          v-else-if="entry.filter.type == 'mselect'"
          v-model="props.filters[entry.name].value"
          :label="entry.filter.label"
          multiple
          :items="entry.filter.items"
          :item-title="entry.filter.txt"
          :item-value="entry.filter.val"
          :truncate="entry.filter.truncate"
          @update:modelValue="handleFilter(entry.name, $event)"
        />

        <!-- Standard Autocomplete case (single-select with radios) -->
        <v-autocomplete
          v-else-if="entry.filter.type == 'select'"
          v-model="props.filters[entry.name].value"
          :label="entry.filter.label"
          :items="entry.filter.items"
          :item-title="entry.filter.txt || 'name'"
          :item-value="entry.filter.val || 'id'"
          :clearable="false"
          @update:modelValue="handleFilter(entry.name, $event)"
        >
          <template #item="{ props: itemProps }">
            <v-list-item v-bind="itemProps" title="">
              <template #prepend="{ isSelected }">
                <v-icon
                  size="small"
                  :color="isSelected ? 'primary' : 'medium-emphasis'"
                >
                  {{ isSelected ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank' }}
                </v-icon>
              </template>
              <v-list-item-title class="text-truncate" :title="itemProps.title">
                {{ entry.filter.truncate ? formatTruncatedDisplay(itemProps.title, entry.filter.truncate) : itemProps.title }}
              </v-list-item-title>
            </v-list-item>
          </template>
          <template #selection="{ item }">
            <span :title="item.title" class="text-truncate">
              {{ entry.filter.truncate ? formatTruncatedDisplay(item.title, entry.filter.truncate) : item.title }}
            </span>
          </template>
        </v-autocomplete>

        <!-- Multi-text select case -->
        <MultiSelectCombobox
          v-else-if="entry.filter.type == 'mtext'"
          v-model="props.filters[entry.name].value"
          :label="entry.filter.label"
          :items="entry.filter.items"
          :truncate="entry.filter.truncate"
          multiple
          @update:modelValue="handleFilter(entry.name, $event)"
        />

        <!-- Standard Dropdown select case (primitive single-select with radios) -->
        <v-select
          v-else-if="entry.filter.type == 'text'"
          v-model="props.filters[entry.name].value"
          :label="entry.filter.label"
          :items="entry.filter.items"
          :clearable="false"
          @update:modelValue="handleFilter(entry.name, $event)"
        >
          <template #item="{ props: itemProps }">
            <v-list-item v-bind="itemProps" title="">
              <template #prepend="{ isSelected }">
                <v-icon
                  size="small"
                  :color="isSelected ? 'primary' : 'medium-emphasis'"
                >
                  {{ isSelected ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank' }}
                </v-icon>
              </template>
              <v-list-item-title class="text-truncate" :title="itemProps.title">
                {{ entry.filter.truncate ? formatTruncatedDisplay(itemProps.title, entry.filter.truncate) : itemProps.title }}
              </v-list-item-title>
            </v-list-item>
          </template>
          <template #selection="{ item }">
            <span :title="item.title" class="text-truncate">
              {{ entry.filter.truncate ? formatTruncatedDisplay(item.title, entry.filter.truncate) : item.title }}
            </span>
          </template>
        </v-select>
      </template>
    </v-col>
  </v-row>
</template>


<script setup>
import { computed } from 'vue';
import { useDataTableHelpers } from '@/composables/table/useDataTableHelpers.js';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import YmRangeSelector from '@/components/ui/YmRangeSelector.vue';

const { formatTruncatedDisplay } = useDataTableHelpers();

const props = defineProps({
  filters: { type: Object, required: true },
});

const emit = defineEmits(['setFilter']);

const SCOPE_KEYS = new Set([
  'consortia',
  'conso_id',
  'platforms',
  'plat_id',
  'institutions',
  'inst_id',
  'institution_groups',
  'groups',
  'group_id',
  'types',
  'type_id',
]);

const PERIOD_KEYS = new Set([
  'reports',
  'master_reports',
  'report_id',
  'ymrange',
  'yearmon',
  'yearmonths',
  'status',
  'statuses',
  'valid_types',
]);

const filterColumns = computed(() => {
  const scope = [];
  const period = [];
  const other = [];

  Object.entries(props.filters || {}).forEach(([name, filter]) => {
    if (!filter?.show) return;
    const entry = { name, filter };
    if (SCOPE_KEYS.has(name) || SCOPE_KEYS.has(filter.key)) {
      scope.push(entry);
    } else if (PERIOD_KEYS.has(name) || PERIOD_KEYS.has(filter.key) || filter.type === 'ymrange') {
      period.push(entry);
    } else {
      other.push(entry);
    }
  });

  if (other.length > 0) {
    return [
      { key: 'scope', items: scope },
      { key: 'period', items: period },
      { key: 'other', items: other },
    ];
  }
  if (period.length > 0) {
    return [
      { key: 'scope', items: scope },
      { key: 'period', items: period },
    ];
  }
  return scope.length > 0 ? [{ key: 'scope', items: scope }] : [];
});

const colMdSpan = computed(() => {
  const count = filterColumns.value.length;
  if (count === 1) return 4;
  if (count === 2) return 6;
  return 4;
});

function formatToggleLabel(entry) {
  const isOn =
    props.filters[entry.name]?.value === 'Active' ||
    props.filters[entry.name]?.value === true;
  const raw = String(entry.filter.label || '');

  if (isOn) {
    return raw.replace(/^Hide\b/i, 'Show');
  }
  return raw.replace(/^Show\b/i, 'Hide');
}

function handleFilter(keyName, newValue) {
  emit('setFilter', { key: keyName, value: newValue });
}
</script>
