<!-- shared/ToolbarFilters -->
<script setup>
import MultiSelectCombobox from './MultiSelectCombobox.vue';
import ToggleIcon from '../shared/ToggleIcon.vue';

  const props = defineProps({
    filters: { type: Object, required: true },
  });
  const emit = defineEmits(['setFilter']);
  function handleFilter(filt) {
    emit('setFilter', {key: filt.name, value: filt.value});
  }
</script>

<template>
  <v-col v-for="(filter,idx) in props.filters" :key="idx" class="d-flex pl-2" cols="2">

    <!-- AutoComplete - multiple select -->
    <MultiSelectCombobox v-if="filter.show && filter.type=='mselect'" v-model="props.filters[filter.name].value" :label="filter.label"
        multiple :items="filter.items" :item-title="filter.txt" :item-value="filter.val" density="compact"
        @update:modelValue="handleFilter(filter)" />

    <!-- AutoComplete - single item select -->
    <v-autocomplete v-if="filter.show && filter.type=='select'" v-model="props.filters[filter.name].value" :label="filter.label"
        :items="filter.items" :item-title="filter.txt" :item-value="filter.val" density="compact" variant="outlined"
        @update:modelValue="handleFilter(filter)" />

    <!-- AutoComplete - return single object -- needed anywhere??
    <v-autocomplete v-if="filter.type=='select'" v-model="m_filters[filter.name].value" :label="filter.label"
        return-object :items="filter.items" :item-title="filter.txt" :item-value="filter.val" />
    -->

    <!-- Return array of Scalar values -->
    <MultiSelectCombobox v-if="filter.show && filter.type=='mtext'" v-model="props.filters[filter.name].value" :label="filter.label"
        :items="filter.items" @update:modelValue="handleFilter(filter)" multiple density="compact" />

    <!-- Return a Single Scalar value -->
    <v-select v-if="filter.show && filter.type=='text'" v-model="props.filters[filter.name].value" :label="filter.label"
        :items="filter.items" @update:modelValue="handleFilter(filter)" density="compact" variant="outlined" />

    <!-- Return Active/Inactive via toggle icon -->
    <ToggleIcon v-if="filter.show && filter.type=='toggle'" v-model="props.filters[filter.name].value" :label="filter.label"
        toggleable :size="36" @update:modelValue="handleFilter(filter)" />
  </v-col>
</template>