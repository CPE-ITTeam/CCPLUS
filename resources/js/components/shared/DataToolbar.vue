<!-- components/shared/DataToolbar.vue -->
<script setup>
  import FlexCol from './FlexCol.vue';
  import SearchAndSelect from './SearchAndSelect.vue';
  import FiltersAndActions from './FiltersAndActions.vue';
  import ExportAndImport from './ExportAndImport.vue';
  const props = defineProps({
    search: { type: String, required: true },
    showSelectedOnly: { type: Boolean, required: true },
    dataset: { type: String, required: true }
  });
  const emit = defineEmits([
    'update:search',
    'update:showSelectedOnly',
    'add',
    'export',
    'import',
    'customAction'
  ]);
</script>

<template>
  <v-row class="my-6">
    <!-- Search + Selected Toggle -->
    <FlexCol>
      <v-row>
        <SearchAndSelect :search="search" :showSelectedOnly="showSelectedOnly"
                         @update:search="$emit('update:search', $event)"
                         @update:showSelectedOnly="$emit('update:showSelectedOnly', $event)"/>
      </v-row>
    </FlexCol>
    <!-- Dataset-Specific Filters + Add -->
    <FlexCol>
      <v-row>
        <FiltersAndActions :dataset="dataset" @customAction="$emit('customAction', $event)"
                           @add="$emit('add')"/>
      </v-row>
    </FlexCol>
    <!-- Export / Import -->
    <FlexCol>
      <v-row>
        <ExportAndImport @export="$emit('export')" @import="$emit('import')" />
      </v-row>
    </FlexCol>
  </v-row>
</template>
