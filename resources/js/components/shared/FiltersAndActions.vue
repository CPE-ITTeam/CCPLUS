<!-- toolbar/FiltersAndActions -->
<script setup>
  const props = defineProps({
    filters: { type: Object, required: true },
  });
  var filterModel;

//ToDo:: emit/change filter action needs to pass changes to parent component

  const emit = defineEmits(['customAction']);

  const emitAction = (filter, payload) => {
    emit('customAction', { filter, payload });
  };
</script>

<template>
  <v-col v-for="(filter,idx) in props.filters" :key="idx" :cols="(typeof(filter.cols)=='undefined') ? 2 : filter.cols">

    <!-- AutoComplete - multiple select -->
    <v-autocomplete v-if="filter.type=='mselect'" v-model="filterModel" :label="filter.label" multiple
        :items="filter.items" :item-title="filter.txt" :item-value="filter.val"
        @change="emitAction('filter.label', $event)" />

    <!-- AutoComplete - single item select -->
    <v-autocomplete v-if="filter.type=='select'" v-model="filterModel" :label="filter.label" :items="filter.items"
        :item-title="filter.txt" :item-value="filter.val" @change="emitAction('filter.name', $event)" />

    <!-- AutoComplete - return single object -- needed anywhere??
    <v-autocomplete v-if="filter.type=='select'" v-model="filterModel" :label="filter.label" return-object
        :items="props.options[filter.optionsKey]" :item-title="filter.txt" :item-value="filter.val"
        @change="emitAction('filter.name', $event)" />
    -->
    <!-- Return a Single Scalar value -->
    <v-select v-if="filter.type=='text'" v-model="filterModel" :label="filter.label" :items="filter.items" 
             :item-title="filter.txt" :item-value="filter.val" @change="emitAction('filter.name', $event)" />
  </v-col>
</template>