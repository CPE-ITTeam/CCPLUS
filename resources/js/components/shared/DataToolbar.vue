<!-- components/shared/DataToolbar.vue -->
<script setup>
  import { ref, computed } from 'vue';
  import { storeToRefs } from 'pinia';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
  import FlexCol from './FlexCol.vue';
  import SearchAndSelect from './SearchAndSelect.vue';
  import ToolbarFilters from './ToolbarFilters.vue';
  import BulkActions from './BulkActions.vue';
  import ExportAndImport from './ExportAndImport.vue';
  const filter_options = defineModel({type: Array, required: true});
  const props = defineProps({
    search: { type: String, required: true },
    showSelectedOnly: { type: Boolean, required: true },
    dataset: { type: String, required: true },
    selectedRows: { type: Array, required: true },
    bulkOptions: {type: Object, required: true },
  });

  const authStore = useAuthStore();
  const { consortia } = storeToRefs(useCCPlusStore());
  const is_serveradmin = authStore.is_serveradmin;
  var consoKey = ref(authStore.ccp_key);

  const consoOnly = computed(() => {
    return (consoKey.value == '' && is_serveradmin);
  });

  const flat_filter_options = computed(() => {
    let _options = {};
    filter_options.value.forEach( (row,idx) => {
      for (const key of Object.keys(row)) {
        _options[key] = {...filter_options.value[idx][key]};
      };
    });
    return _options;
  });

  const anyFilterSet = computed(() => {
    var is_set = false;
    for (const key of Object.keys(flat_filter_options.value)) {
      const filter = flat_filter_options.value[key];
      if (!filter.show) continue;
      if ( Array.isArray(filter.value) ) {
        if (filter.value.length > 0) is_set = true;
      } else if (filter.value) {
        is_set = true;
      }
      if (is_set) break;
    }
    return is_set;
  });

  function handleFilter(filt) {
    // update filter by finding it in the filter_options rows
    let updated = false;
    filter_options.value.forEach( (row,idx) => {
      if (updated || typeof(filter_options.value[idx][filt.key]) == 'undefined') return;
      filter_options.value[idx][filt.key]['value'] = filt.value;
      updated = true;
    });
    if (updated) emit('setFilter', filt);
  }

  const emit = defineEmits([
    'updateConso',
    'update:search',
    'update:showSelectedOnly',
    'add',
    'export',
    'import',
    'setFilter',
    'bulkAction'
  ]);
</script>

<template>
  <!-- Search + Selected Toggle -->
  <v-row class="mt-2 mb-2" no-gutters>
    <FlexCol v-if="!consoOnly" cols="4">
      <v-row no-gutters >
        <SearchAndSelect :search="search" :showSelectedOnly="showSelectedOnly"
                         @update:search="$emit('update:search', $event)"
                         @update:showSelectedOnly="$emit('update:showSelectedOnly', $event)"/>
      </v-row>
    </FlexCol>
    <v-col v-if="is_serveradmin && consortia.length>1 && props.dataset!='jobs'" class="flex px-4" cols="3">
      <v-autocomplete v-model="consoKey" label="Consortium" :items="consortia" item-title="name" density="compact"
                      return-object item-value="ccp_key" @update:modelValue="$emit('updateConso', $event)" />
    </v-col>
    <!-- Export, Import, & Add -->
    <ExportAndImport v-if="!consoOnly" :showRefresh="props.dataset=='platforms'" @export="$emit('export')"
                     @import="$emit('import')" @add="$emit('add')"
                     @refresh="$emit('bulkAction', {action:'Full Refresh'})"/>
  </v-row>
  <!-- Search + Selected Toggle -->
  <v-row class="ma-0" no-gutters>
    <v-col v-if="props.selectedRows.length>0 && props.bulkOptions.items.length>0" cols="2">
      <BulkActions v-model="props.bulkOptions" @bulkAction="$emit('bulkAction', $event)" />
    </v-col>
    <v-col v-else cols="2">&nbsp;</v-col>
    <v-col v-if="anyFilterSet" cols="1">
      <v-btn icon="mdi-restore" color="#dd0000" @click="$emit('setFilter',{key: 'reset'})"></v-btn>
    </v-col>
    <v-col v-else cols="1">&nbsp;</v-col>
    <ToolbarFilters :filters="filter_options[0]" @setFilter="handleFilter($event)" />
  </v-row>
  <v-row v-if="filter_options.length>1" v-for="(row, index) in filter_options" :key="index" class="ma-0" no-gutters>
    <v-col v-if="index>0" cols="3">&nbsp;</v-col>
    <ToolbarFilters v-if="index>0" :filters="filter_options[index]" @setFilter="handleFilter($event)" />
  </v-row>
</template>
