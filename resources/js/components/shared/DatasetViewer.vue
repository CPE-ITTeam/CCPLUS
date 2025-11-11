<!-- components/DatasetViewer.vue -->
<script setup>
  import { ref, reactive, watch, onBeforeMount, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { tableSetup } from '@/composables/DataTableConfig.js';
  import DataToolbar from './DataToolbar.vue';
  import DataTable from './DataTable.vue';
  import DataForm from './DataForm.vue';

  const authStore = useAuthStore();
  const is_admin = authStore.is_admin;
  const { ccGet, ccPatch, setConsoKey } = useAuthStore();
  var consoKey = ref(authStore.ccp_key);  
  const props = defineProps({
    datasetKey: { type: String, required: true }
  });

  // Dataset config map
  const datasetConfig = { ...tableSetup };

  // Reactive state
  var dtKey = ref(0);
  var items = reactive([]);
  const headers = ref([]);
  const searchFields = ref([]);
  const search = ref('');
  const selectedRows = ref([]);
  const showSelectedOnly = ref(false);
  const dialogOpen = ref(false);
  const editingItem = ref(null);
  const filterOptions = ref({});
  const editableFields = ref([]);
  const updateUrl = ref('');
  var itemOptions = {};
  var success = ref('');
  var failure = ref('');

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
      if (consoKey.value=='') return;
//NOTE:: config.urls should also provide filter options (named by config.name)
      let itemsUrl = config.urlRoot+'/get';
      if (datasetKey == 'institutions' || datasetKey== 'platforms') {
        itemsUrl += (is_admin) ? '/admin' : '/viewer';
      }
      const { data } = await ccGet(itemsUrl);
      items = [ ...data.records ];
      itemOptions = { ...data.options };
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
           typeof(itemOptions[fld.name]) != 'undefined' ) {
        filterOptions.value[fld.name] = {
          'label': fld.name, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'items': [...itemOptions[fld.name]]
        };
      }
    });
    // Set arrays for searchable and editable fields
    searchFields.value = config.fields.filter(fld => fld.searchable).map(f => f.name);
    editableFields.value = config.fields.filter(fld => fld.editable).map(f => f.name);
    updateUrl.value = config.urlRoot+'/update';
  }

  function handleToggle(value) {
    showSelectedOnly.value = value;
    if (value) search.value = '';
  }

  function handleChangeConso(value) {
    consoKey.value = value;
    setConsoKey(value);
    loadDataset(props.datasetKey);
    emit('update:conso', value);
    dtKey.value++;
  }

  function handleEdit(item) {
    editingItem.value = item;
    dialogOpen.value = true;
  }

  async function handleDelete(item) {
    const config = datasetConfig[props.datasetKey];
    let destroyUrl = config.urlRoot+'/delete/'+editingItem.value.id;
    try {
      const response = await ccPatch(destroyUrl);
      if (response.result) {
        items.splice(items.findIndex( ii => ii.id == editingItem.value.id),1);
        success.value = response.msg
      } else {
        failure.value = response.msg
      }
    } catch (error) {
      console.error('Error deleting:', error);
    }
    editingItem.value = null;
  }

  async function handleStatusUpdate(id, value) {
    const idx = items.findIndex(ii => ii.id == id);
    if (idx >= 0) {
      let _item = Object.assign({}, items[idx])
      _item['is_active'] = (value == 'Active') ? 1 : 0;
      try {
        let url = updateUrl.value+'/'+id;
        const response = await ccPatch(url, {'is_active': _item['is_active']});
        if (response.result) {
          _item['status'] = value;
          items.splice(idx,1,_item);
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch {
        console.error('Error updating status', error);
      }
    }
  }

  async function handleFormSubmit(updatedValues) {
    try {
      let url = updateUrl.value+'/'+editingItem.value.id;
      const response = await ccPatch(url, updatedValues);
      if (response.result) {
        const idx = items.findIndex(ii => ii.id == editingItem.value.id);
        items.splice(idx,1,response.record);
        success.value = response.msg
        dtKey.value++;
      } else {
        failure.value = response.msg
      }
    } catch (error) {
      console.error('Error updating:', error);
    }
    dialogOpen.value = false;
    editingItem.value = null;
  }

  function handleFormCancel() {
    dialogOpen.value = false;
    editingItem.value = null;
  }
  const emit = defineEmits(['update:conso']);
  onBeforeMount(() => loadDataset(props.datasetKey));
  watch(() => props.datasetKey, (newKey) => loadDataset(newKey));
</script>
<!--
  TODO:: Need to add a div/row for success/failure strings
-->
<template>
  <v-sheet>
    <DataToolbar :search="search" :showSelectedOnly="showSelectedOnly" :dataset="props.datasetKey"
                 :filter_options="filterOptions" @update:search="search = $event"
                 @update:showSelectedOnly="handleToggle" @update:conso="handleChangeConso" />

    <DataTable v-if="consoKey!=''" :items="items" :search="search" :dataset="props.datasetKey" :key="dtKey"
               :showSelectedOnly="showSelectedOnly" :headers="headers" :editableFields="editableFields"
               :searchFields="searchFields" :selectedRows="selectedRows" @update:selectedRows="selectedRows = $event"
               @edit="handleEdit" @delete="handleDelete" @update:status="handleStatusUpdate" />

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
