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
  const filter_options = defineModel({type: Object, required: true});
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
  const anyFilterSet = computed(() => {
    for (const key of Object.keys(filter_options.value)) {
      const filter = filter_options.value[key]; 
      if (!filter.show) continue;
      if ( Array.isArray(filter.value) ) {
        if (filter.value.length > 0) return true;
      } else if (filter.value) {
        return true;
      }
    }
    return false;
  });
  function resetFilters() {
    for (const key of Object.keys(filter_options.value)) {
      const filter = filter_options.value[key]; 
      if (!filter.show) continue;
      if ( Array.isArray(filter.value) ) {
        if (filter.value.length > 0) filter.value = [];
      } else if (filter.value) {
        filter.value = null;
      }
    }
    emit('setFilter');
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
  <v-row class="my-2" no-gutters>
    <FlexCol v-if="!consoOnly" cols="4">
      <v-row no-gutters >
        <SearchAndSelect :search="search" :showSelectedOnly="showSelectedOnly"
                         @update:search="$emit('update:search', $event)"
                         @update:showSelectedOnly="$emit('update:showSelectedOnly', $event)"/>
      </v-row>
    </FlexCol>
    <v-col v-if="is_serveradmin && consortia.length>1 && props.dataset!='jobs'" class="flex px-4" cols="3">
      <v-autocomplete v-model="consoKey" label="Consortium" :items="consortia" item-title="name"
                      return-object item-value="ccp_key" @update:modelValue="$emit('updateConso', $event)" />
    </v-col>
    <!-- Export, Import, & Add -->
    <ExportAndImport v-if="!consoOnly" @export="$emit('export')" @import="$emit('import')" @add="$emit('add')"/>
  </v-row>
  <!-- Search + Selected Toggle -->
  <v-row class="my-2">
    <v-col v-if="props.selectedRows.length>0 && props.bulkOptions.items.length>0" cols="2">
      <BulkActions v-model="props.bulkOptions" @bulkAction="$emit('bulkAction', $event)" />
    </v-col>
    <v-col v-else cols="2">&nbsp;</v-col>
    <v-col v-if="anyFilterSet" cols="1">
      <v-btn v-if="anyFilterSet" icon="mdi-restore" color="#dd0000" @click="resetFilters"></v-btn>
    </v-col>
    <v-col v-else cols="1">&nbsp;</v-col>
    <ToolbarFilters v-model="filter_options" @setFilter="$emit('setFilter')" />
  </v-row>
</template>
