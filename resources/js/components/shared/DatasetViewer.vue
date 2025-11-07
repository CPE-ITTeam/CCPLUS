<!-- components/DatasetViewer.vue -->
<script setup>
  import { ref, watch, onBeforeMount, computed } from 'vue';
  import { storeToRefs } from 'pinia';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
  import { tableSetup } from '@/composables/DataTableConfig.js';
  import DataToolbar from './DataToolbar.vue';
  import DataTable from './DataTable.vue';
  import DataForm from './DataForm.vue';

  const authStore = useAuthStore();
  const { ccGet, setConsoKey } = useAuthStore();
  const { consortia } = storeToRefs(useCCPlusStore());
  var consoKey = ref(authStore.ccp_key);

  const props = defineProps({
    datasetKey: { type: String, required: true }
  });

  // Dataset config map
  const datasetConfig = { ...tableSetup };

  // Reactive state
  var dtKey = ref(0);
  const items = ref([]);
  const headers = ref([]);
  const searchFields = ref([]);
  const search = ref('');
  const selectedRows = ref([]);
  const showSelectedOnly = ref(false);
  const dialogOpen = ref(false);
  const editingItem = ref(null);
  const filterOptions = ref({});
  const editableFields = ref([]);
  var urlOptions = {};

  const dialogTitle = computed(() => {
    const config = datasetConfig[props.datasetKey];
    return config.dialogTitle || 'Item';
  });

  const isEditable = computed(() => {
    const config = datasetConfig[props.datasetKey];
    return (editingItem.value && config && editableFields.value.length>0);
  });

  const formSchema = computed(() => {
    const config = datasetConfig[props.datasetKey];
    return {
      fields: [...config.fields],
      requiredKeys: [...config.required],
      options: {...filterOptions.value},
    };
  });

  // Load dataset
  const loadDataset = async (datasetKey) => {
    const config = datasetConfig[datasetKey];
    try {
      if (datasetKey == 'consortia') {
        items.value = [ ...consortia.value ];
      } else {
        if (consoKey.value=='') return;
//NOTE:: config.urls need to pass back filter options (named by config.name)
        const { data } = await ccGet(config.url);
        items.value = [ ...data.records ];
        urlOptions = { ...data.options };
      }
    } catch (error) {
      console.error('Error fetching records for '+datasetKey+' : ', error);
    }

    // set datatable header, display, and editor options
    headers.value = [{ title: "", key: "" }];
    config.fields.forEach( (fld, idx) => {
      if (fld.header) headers.value.push({title: fld.label, key: fld.name});
      if (typeof(config.static)!='undefined') {
        config.fields[idx].static = (config.static.indexOf(fld.name) > -1);
      } else {
        config.fields[idx].static = false;
      }
      // Set filterOptions for select, mselect and toggle
      if ( (fld.type == 'select' || fld.type == 'mselect' || fld.type == 'toggle') &&
           !config.fields[idx].static && fld.options == 'fromURL' &&
           typeof(urlOptions[fld.name]) != 'undefined' ) {
        filterOptions.value[fld.name] = {
          'label': fld.name, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'items': [...urlOptions[fld.name]]
        };
      }
    });
    // Set arrays for searchable and editable fields
    searchFields.value = config.fields.filter(fld => fld.searchable).map(f => f.name);
    editableFields.value = config.fields.filter(fld => fld.editable).map(f => f.name);
  }

  function handleToggle(value) {
    showSelectedOnly.value = value;
    if (value) search.value = '';
  }

  function handleChangeConso(value) {
    consoKey.value = value;
    setConsoKey(value);
    loadDataset(props.datasetKey);
    dtKey.value++;
  }

  function handleEdit(item) {
    editingItem.value = item;
    dialogOpen.value = true;
    console.log('Editing item:', editingItem.value);
  }

  function handleDelete(item) {
    console.log('Delete clicked for:', item);
    // TODO: Add confirmation and deletion logic here
  }

  function handleStatusUpdate(id, key, value) {
    const item = items.value.find(i => i.id === id);
    if (item) item[key] = value;
  }

  function handleFormSubmit(updatedValues) {
    console.log('Form submitted:', updatedValues);
    dialogOpen.value = false;
    editingItem.value = null;
  }

  function handleFormCancel() {
    dialogOpen.value = false;
    editingItem.value = null;
  }

  onBeforeMount(() => loadDataset(props.datasetKey));
  watch(() => props.datasetKey, (newKey) => loadDataset(newKey));
</script>

<template>
  <v-sheet>
    <DataToolbar :search="search" :showSelectedOnly="showSelectedOnly" :dataset="props.datasetKey"
                 :filter_options="filterOptions" @update:search="search = $event"
                 @update:showSelectedOnly="handleToggle" @update:conso="handleChangeConso" />

    <DataTable v-if="consoKey!=''" :items="items" :search="search" :showSelectedOnly="showSelectedOnly"
               :headers="headers" :key="dtKey" :editableFields="editableFields" :searchFields="searchFields"
               :selectedRows="selectedRows" @update:selectedRows="selectedRows = $event" @edit="handleEdit"
               @delete="handleDelete" @update:status="handleStatusUpdate" />

    <v-dialog v-if="editingItem && isEditable" v-model="dialogOpen" max-width="600px">
      <v-card>
        <v-card-title class="text-indigo-darken-2 pa-6 d-flex justify-space-between align-center">
          <span>Edit {{ dialogTitle }}</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="handleFormCancel">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-text>
          <DataForm :schema="formSchema" :initialValues="editingItem"
                    @submit="handleFormSubmit" @cancel="handleFormCancel" />
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-sheet>
</template>
<style scoped>
  .close-btn {
    border-radius: 50%;
    width: 28px;
    height: 28px;
    min-width: 28px;
    padding: 0;
  }
</style>
