<!-- components/tables/DataToolbar.vue -->
<template>
  <v-card class="pa-4 mb-4" border>
    
    <!-- TIER 0: LABELS -->
    <div class="d-flex flex-wrap align-center ga-3 w-100 mb-1">
      <div v-if="!consoOnly || is_serveradmin" style="width: 280px;" class="flex-shrink-0">
        <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
          Search
        </span>
      </div>
    </div>

    <!-- TIER 1: CORE ROW -->
    <div class="d-flex flex-wrap align-center ga-3 w-100 mb-3">
      <div v-if="!consoOnly || is_serveradmin" style="width: 280px;" class="flex-shrink-0">
        <SearchAndSelect :search="search" @update:search="$emit('update:search', $event)" />
      </div>
    </div>

    <!-- TIER 1.5: FILTERS BLOCK -->
    <div v-if="filter_options && filter_options.length > 0" class="w-100 mb-3">
      <div v-for="(row, index) in filter_options" :key="index" class="d-flex flex-column ga-1 flex-grow-1">
        <div v-if="index === 0" class="d-flex align-center ga-3 mb-1">
          <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
            {{ filter_options.flatMap(r => Object.keys(r)).length > 1 ? 'Filters' : 'Filter' }}
          </span>
          <v-btn v-if="anyFilterSet" prepend-icon="mdi-close-circle-outline" color="error" @click="$emit('setFilter', { key: 'reset' })">
            Clear All
          </v-btn>
          <v-btn v-if="needsRefreshButton && consoKey != ''" prepend-icon="mdi-refresh" class="ms-2" @click="$emit('refreshRecords')">
            Refresh Records
          </v-btn>
        </div>
        <ToolbarFilters :filters="filter_options[index]" @setFilter="handleFilter($event)" />
      </div>
    </div>

    <!-- TIER 2: ACTIONS PANEL -->
    <v-divider class="my-3" />
    <div class="d-flex flex-wrap align-center justify-space-between ga-4 w-100 pt-1">
      <div class="d-flex align-center ga-3" style="min-height: 40px; min-width: 360px;">
        <v-expand-x-transition>
          <div v-if="props.selectedRows && props.selectedRows.length > 0" class="d-flex align-center ga-3 w-100">
            <BulkActions v-if="props.bulkOptions?.items?.length > 0" v-model="props.bulkOptions" style="width: 280px;" @bulkAction="$emit('bulkAction', $event)" />
          </div>
        </v-expand-x-transition>
      </div>

      <div class="d-flex align-center flex-wrap ga-2 ms-auto">
        <ExportAndImport v-if="showExportImport" :showRefresh="props.dataset == 'platforms'" :exportOnly="(props.dataset == 'audit')" :hideExport="props.hideExport" @export="$emit('export')" @import="importDialog = true" @add="$emit('add')" @refresh="$emit('bulkAction', { action: 'Full Refresh' })" />
      </div>
    </div>

    <v-dialog v-model="importDialog">
      <ImportDialog :dataset="props.dataset" @close="importDialog = false" @imported="handleImported" />
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import SearchAndSelect from '@/components/ui/SearchAndSelect.vue';
import ToolbarFilters from './ToolbarFilters.vue';
import BulkActions from './BulkActions.vue';
import ExportAndImport from './ExportAndImport.vue';
import ImportDialog from '@/components/dialogs/ImportDialog.vue';

const filter_options = defineModel({ type: Array, required: true });
const props = defineProps({
  search: { type: String, required: true },
  showSelectedOnly: { type: Boolean, required: true },
  dataset: { type: String, required: true },
  selectedRows: { type: Array, required: true },
  bulkOptions: { type: Object, required: true },
  hideExport: { type: Boolean, required: true },
});

const authStore = useAuthStore();
const { consortia } = storeToRefs(useCCPlusStore());
const is_serveradmin = authStore.is_serveradmin;
const consoKey = ref(authStore.ccp_key);
const importDialog = ref(false);

const serverTab = computed(() => {
  return (props.dataset == 'consortia' || props.dataset == 'platforms');
});

const consoOnly = computed(() => {
  return (consoKey.value == '' && is_serveradmin);
});

const needsRefreshButton = computed(() => {
  return (props.dataset == 'harvests' || props.dataset == 'jobs' || props.dataset == 'audit');
});

const showExportImport = computed(() => {
  return (props.dataset == 'consortia' || (!consoOnly.value && props.dataset != 'harvests' && props.dataset != 'jobs'));
});

const flat_filter_options = computed(() => {
  let _options = {};
  filter_options.value.forEach((row, idx) => {
    for (const key of Object.keys(row)) {
      _options[key] = { ...filter_options.value[idx][key] };
    }
  });
  return _options;
});

const anyFilterSet = computed(() => {
  let is_set = false;
  for (const key of Object.keys(flat_filter_options.value)) {
    const filter = flat_filter_options.value[key];
    if (!filter.show) continue;
    
    if (Array.isArray(filter.value)) {
      if (filter.value.length > 0) is_set = true;
    } else if (filter.type == 'toggle') {
      is_set = (filter.value == 'Active');
    } else {
      const val = filter.value;
      if (val !== null && val !== undefined && val !== '' && val !== 0 && val !== '0' && val !== 'all' && val !== 'All') {
        is_set = true;
      }
    }
    if (is_set) break;
  }
  return is_set;
});

function handleFilter(filt) {
  let updated = false;
  filter_options.value.forEach((row, idx) => {
    if (updated || typeof (filter_options.value[idx][filt.key]) == 'undefined') return;
    filter_options.value[idx][filt.key]['value'] = filt.value;
    updated = true;
  });
  if (updated) emit('setFilter', filt);
}

function handleImported(data) {
  emit('imported', data);
  importDialog.value = false;
}

const emit = defineEmits([
  'updateConso',
  'update:search',
  'update:showSelectedOnly',
  'add',
  'export',
  'imported',
  'setFilter',
  'bulkAction',
  'refreshRecords'
]);
</script>
