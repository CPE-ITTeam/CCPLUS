<!-- shared/ToolbarFilters -->
<script setup>
  const props = defineProps({
    filters: { type: Object, required: true },
  });
  var m_filters = {...props.filters};
  const emit = defineEmits(['setFilter']);
  function handleFilter(filt) {
    emit('setFilter', {key: filt.name, value: filt.value});
  }
</script>

<template>
  <v-col v-for="(filter,idx) in m_filters" :key="idx" class="d-flex pl-2" cols="2">

    <!-- AutoComplete - multiple select -->
    <v-autocomplete v-if="filter.show && filter.type=='mselect'" v-model="m_filters[filter.name].value" :label="filter.label"
        multiple :items="filter.items" :item-title="filter.txt" :item-value="filter.val" density="compact"
        @update:modelValue="handleFilter(filter)" />

    <!-- AutoComplete - single item select -->
    <v-autocomplete v-if="filter.show && filter.type=='select'" v-model="m_filters[filter.name].value" :label="filter.label"
        :items="filter.items" :item-title="filter.txt" :item-value="filter.val" density="compact"
        @update:modelValue="handleFilter(filter)" />

    <!-- AutoComplete - return single object -- needed anywhere??
    <v-autocomplete v-if="filter.type=='select'" v-model="m_filters[filter.name].value" :label="filter.label"
        return-object :items="filter.items" :item-title="filter.txt" :item-value="filter.val" />
    -->

    <!-- Return array of Scalar values -->
    <v-select v-if="filter.show && filter.type=='mtext'" v-model="m_filters[filter.name].value" :label="filter.label"
        :items="filter.items" @update:modelValue="handleFilter(filter)" multiple density="compact" />

    <!-- Return a Single Scalar value -->
    <v-select v-if="filter.show && filter.type=='text'" v-model="m_filters[filter.name].value" :label="filter.label"
        :items="filter.items" @update:modelValue="handleFilter(filter)" density="compact" />
  </v-col>
</template>