<!-- toolbar/FiltersAndActions -->
<script setup>
  const filters = defineModel({type: Object, required: true});
  const emit = defineEmits(['setFilter']);
</script>

<template>
  <v-col v-for="(filter,idx) in filters" :key="idx" :cols="(typeof(filter.cols)=='undefined') ? 2 : filter.cols">

    <!-- AutoComplete - multiple select -->
    <v-autocomplete v-if="filter.show && filter.type=='mselect'" v-model="filters[filter.name].value" :label="filter.label"
        multiple :items="filter.items" :item-title="filter.txt" :item-value="filter.val"
        @update:modelValue="$emit('setFilter')" />

    <!-- AutoComplete - single item select -->
    <v-autocomplete v-if="filter.show && filter.type=='select'" v-model="filters[filter.name].value" :label="filter.label"
        :items="filter.items" :item-title="filter.txt" :item-value="filter.val"
        @update:modelValue="$emit('setFilter')" />

    <!-- AutoComplete - return single object -- needed anywhere??
    <v-autocomplete v-if="filter.type=='select'" v-model="filters[filter.name].value" :label="filter.label"
        return-object :items="filter.items" :item-title="filter.txt" :item-value="filter.val" />
    -->
    <!-- Return a Single Scalar value -->
    <v-select v-if="filter.show && filter.type=='text'" v-model="filters[filter.name].value" :label="filter.label"
        :items="filter.items" @update:modelValue="$emit('setFilter')" />
  </v-col>
</template>