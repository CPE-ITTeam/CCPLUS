<!-- components/DatasetViewer.vue -->
<script setup>
  import { ref, watch, onMounted, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { tableSetup } from '@/plugins/DataTableConfig.js';
  import DataToolbar from './DataToolbar.vue';
  import DataTable from './DataTable.vue';

  const { ccGet } = useAuthStore();
  const props = defineProps({
    datasetKey: { type: String, required: true }
  });

  // Dataset config map
  const datasetConfig = { ...tableSetup };

  // Reactive state
  const items = ref([]);
  const headers = ref([{ title: "", key: "" }]);
  const searchFields = ref([]);
  const search = ref('');
  const selectedRows = ref([]);
  const showSelectedOnly = ref(false);

  // Load dataset
  const loadDataset = async (datasetKey) => {
    const config = datasetConfig[datasetKey];
    try {
      // const { data } = await ccGet(key.url);
      const { data } = await ccGet(config.url);
      items.value = [ ...data.records ];
      headers.value = config.headers.map(h => ({
        title: h.title,
        key: String(h.key),
      }));
      searchFields.value = config.searchFields.map(f => String(f));
    } catch (error) {
      console.error('Error fetching records for '+datasetKey+' : ', error);
    }
  }

  watch(() => props.datasetKey, (newKey) => loadDataset(newKey));

  // Add actions column dynamically
  const computedHeaders = computed(() => [
    ...headers.value,
    { title: 'Actions', key: 'actions' },
  ]);

  function handleToggle(value) {
    showSelectedOnly.value = value;
    if (value) search.value = '';
  }

  function handleEdit(item) {
    console.log('Edit clicked for:', item);
    // TODO: Add modal or routing logic here
  }

  function handleDelete(item) {
    console.log('Delete clicked for:', item);
    // TODO: Add confirmation and deletion logic here
  }

  function handleStatusUpdate(id, key, value) {
    const item = items.value.find(i => i.id === id);
    if (item) {
      item[key] = value;
    }
  }
  onMounted(() => loadDataset(props.datasetKey));
</script>

<template>
  <v-sheet>
    <DataToolbar :search="search" :showSelectedOnly="showSelectedOnly" dataset="institutions"
                 @update:search="search = $event" @update:showSelectedOnly="handleToggle" />
    <DataTable :items="items" :search="search" :showSelectedOnly="showSelectedOnly"
               :searchFields="searchFields" :headers="computedHeaders" :selectedRows="selectedRows"
               @update:selectedRows="selectedRows = $event" @edit="handleEdit" @delete="handleDelete"
               @update:status="handleStatusUpdate" />
  </v-sheet>
</template>
