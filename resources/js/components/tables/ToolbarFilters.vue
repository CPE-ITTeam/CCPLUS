<!-- components/tables/ToolbarFilters.vue -->
<template>
  <div class="d-flex flex-row flex-wrap align-center ga-3 w-100 pt-2">
    <template v-for="(filter, name) in props.filters" :key="name">
      <!-- Year-Month Range Filter (Shared Preset + Start/End Selector) -->
      <YmRangeSelector
        v-if="filter.show && filter.type == 'ymrange'"
        v-model="props.filters[name].value"
        :label="filter.label"
        inline
        clearable
        @update:modelValue="handleFilter(name, $event)"
      />

      <div
        v-else-if="filter.show"
        class="flex-shrink-0 d-flex align-center"
        style="width: calc(25% - 12px); min-width: 240px; max-width: 300px; min-height: 56px;"
      >
        <!-- Toggle filter case -->
        <div v-if="filter.type == 'toggle'" class="d-flex align-center h-100 pl-2">
          <ToggleIcon
            v-model="props.filters[name].value"
            :label="filter.label"
            toggleable
            :size="36"
            @update:modelValue="handleFilter(name, $event)"
          />
        </div>

        <!-- Multi-select combobox case -->
        <MultiSelectCombobox
          v-else-if="filter.type == 'mselect'"
          v-model="props.filters[name].value"
          :label="filter.label"
          multiple
          :items="filter.items"
          :item-title="filter.txt"
          :item-value="filter.val"
          style="width: 100%;"
          @update:modelValue="handleFilter(name, $event)"
        />

        <!-- Standard Autocomplete case -->
        <v-autocomplete
          v-else-if="filter.type == 'select'"
          v-model="props.filters[name].value"
          :label="filter.label"
          :items="filter.items"
          :item-title="filter.txt"
          :item-value="filter.val"
          clearable
          style="width: 100%;"
          @update:modelValue="handleFilter(name, $event)"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps">
              <template #prepend>
                <v-icon
                  size="small"
                  :color="props.filters[name].value === (filter.val ? item.raw?.[filter.val] : item.raw) ? 'primary' : 'medium-emphasis'"
                >
                  {{
                    props.filters[name].value === (filter.val ? item.raw?.[filter.val] : item.raw)
                      ? 'mdi-radiobox-marked'
                      : 'mdi-radiobox-blank'
                  }}
                </v-icon>
              </template>
            </v-list-item>
          </template>
        </v-autocomplete>

        <!-- Multi-text select case -->
        <MultiSelectCombobox
          v-else-if="filter.type == 'mtext'"
          v-model="props.filters[name].value"
          :label="filter.label"
          :items="filter.items"
          multiple
          style="width: 100%;"
          @update:modelValue="handleFilter(name, $event)"
        />

        <!-- Standard Dropdown select case -->
        <v-select
          v-else-if="filter.type == 'text'"
          v-model="props.filters[name].value"
          :label="filter.label"
          :items="filter.items"
          clearable
          style="width: 100%;"
          @update:modelValue="handleFilter(name, $event)"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import YmRangeSelector from '@/components/ui/YmRangeSelector.vue';

const props = defineProps({
  filters: { type: Object, required: true },
});

const emit = defineEmits(['setFilter']);

function handleFilter(keyName, newValue) {
  emit('setFilter', { key: keyName, value: newValue });
}
</script>
