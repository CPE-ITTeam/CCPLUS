<!-- components/DatasetViewer.vue -->
<script setup>
  import { ref, reactive, watch, onBeforeMount, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { fyMonths } from '@/plugins/CCPlusStore.js';
  import { tableSetup } from '@/composables/DataTableConfig.js';
  import DataToolbar from './DataToolbar.vue';
  import DataTable from './DataTable.vue';
  import DataForm from './DataForm.vue';

  const authStore = useAuthStore();
  const is_admin = authStore.is_admin;
  const is_conso_admin = authStore.is_conso_admin;
  const { ccGet, ccPost, ccPatch, ccDestroy, setConsoKey } = useAuthStore();
  var consoKey = ref(authStore.ccp_key);  
  const props = defineProps({
    datasetKey: { type: String, required: true }
  });

  // Dataset config map
  const datasetConfig = { ...tableSetup };

  // Reactive state
  var dtKey = ref(0);
  var dialogType = ref('');
  var dialogTitle = ref('Item');
  var allItems = reactive([]);
  var filteredItems = reactive([]);
  const filterOptions = reactive({});
  const headers = ref([]);
  const searchFields = ref([]);
  const search = ref('');
  const selectedRows = ref([]);
  const showSelectedOnly = ref(false);
  const dialogOpen = ref(false);
  const editingItem = ref(null);
  const editableFields = ref([]);
  const urlRoot = ref('');
  var itemOptions = {};
  var success = ref('');
  var failure = ref('');

  const isEditable = computed(() => {
    const config = datasetConfig[props.datasetKey];
    return (editingItem.value && config && editableFields.value.length>0);
  });

  const toolbarFilters = computed(() => {
    return Object.fromEntries(
      Object.entries(filterOptions).filter(([key,val]) => filterOptions[key]['show'])
    )
  });

  const formSchema = computed(() => {
    const config = datasetConfig[props.datasetKey];
    if (props.datasetKey=='roles') {
      filterOptions.role.items = filterOptions.role.items.filter(r => !r.name.includes('Consortium'))
    }
    return {
      type: dialogType.value,
      fields: [...config.fields],
      requiredKeys: [...config.required],
      options: {...filterOptions},
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
      allItems = [ ...data.records ];
      filteredItems = [ ...data.records ];
      itemOptions = { ...data.options };
    } catch (error) {
      console.error('Error fetching records for '+datasetKey+' : ', error);
    }
    // set datatable header, display, and editor options
    headers.value = [{ title: "", key: "" }];
    config.fields.forEach( (fld, idx) => {
      if (fld.header) headers.value.push({title: fld.label, key: fld.name});
      if (fld.name == 'conso' && !is_conso_admin) return;
      config.fields[idx].static = config.static.includes(fld.name);
      // Set filterOptions for select(s) and toggle
      if ( (fld.type == 'select' || fld.type == 'mselect' || fld.type == 'selectObj' || fld.type == 'toggle') &&
           !config.fields[idx].static && fld.options == 'fromURL' &&
           typeof(itemOptions[fld.name]) != 'undefined' ) {
        let initVal = (fld.type == 'mselect') ? [] : null;
        filterOptions[fld.name] = {
          'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'show': fld.isFilter, 'col': fld.filterCol, 'items': [...itemOptions[fld.name]], 'value': initVal
        };
      } else if (Array.isArray(fld.options)) {
        filterOptions[fld.name] = {
          'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'show': fld.isFilter, 'col': fld.filterCol, 'items': [...fld.options], 'value': fld.options[0]
        };
      } else if ( fld.name == 'fiscalYr') {
        filterOptions['fiscalYr'] = {
          'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'show': true, 'items': [...fyMonths], 'value': null
        };
      }
    });
    // Set arrays for searchable and editable fields
    searchFields.value = config.fields.filter(fld => fld.searchable).map(f => f.name);
    editableFields.value = config.fields.filter(fld => fld.editable).map(f => f.name);
    urlRoot.value = config.urlRoot;
  }

  function handleToggle(value) {
    showSelectedOnly.value = value;
    if (value) search.value = '';
  }

  function handleChangeConso(value) {
    consoKey.value = value;
    setConsoKey(value);
    loadDataset(props.datasetKey);
    emit('updateConso', value);
    dtKey.value++;
  }

  function handleEdit(item) {
    const config = datasetConfig[props.datasetKey];
    dialogType.value = "Edit";
    dialogTitle = "Edit "+config.dialogTitle;
    config.fields.forEach( (fld, idx) => {
      // Set current values for specific select/mselect fields
      if (fld.type == 'select' || fld.type == 'mselect' || fld.type == 'selectObj') {
        if (fld.name == 'institutions') item['institutions'] = item.inst_id;
        // if (fld.name == 'roles') item['roles'] = item.role;
      }
      config.fields[idx]['visible'] = true;
    });
    editingItem.value = {...item};
    dialogOpen.value = true;
  }

  function handleAddItem() {
    const config = datasetConfig[props.datasetKey];
    dialogType.value = "Add";
    dialogTitle = "Add New "+config.dialogTitle;
    editingItem.value = {};
    config.fields.forEach( (fld, idx) => {
      // Skip fields not required for Add
      if (!config.required.includes(fld.name)) return;
      // If field has options, but is only displayed in dialog, pre-set the value if it is set (e.g. conso)
      if (typeof(filterOptions[fld.name])!='undefined' && !filterOptions[fld.name].show) {
        editingItem.value[fld.name] = (filterOptions[fld.name].value) ? filterOptions[fld.name].value : null;
      } else {
        editingItem.value[fld.name] = null;
      }
      config.fields[idx]['visible'] = true;
    });
    dialogOpen.value = true;
  }

//  NOTE::: Currently - changing one filter means (re)apply all that are set to the original item-set
//
  function updateItems() {
    if (allItems.length==0) return;
    var filterResult = [...allItems];
    for (const key of Object.keys(filterOptions)) {
      const filter = filterOptions[key]; 
      if (!filter.show) continue;
      // If the result set is empty, just bail
      if (filterResult.length==0) break;

      // Check if filter is set
      if ( Array.isArray(filter.value) ) {
        if (filter.value.length == 0) continue;
      } else if (!filter.value) {
        continue;
      }
// console.log('Filtering Col = '+filter.col);
// console.log('Filter Type = '+filter.type);

      // Filter items by a single value
      if (filter.type == 'select') {
        filterResult = filterResult.filter( item => item[filter.col]==filter.value );
      // Filter items with a multi-select array
      } else if (filter.type == 'mselect') {
        // If item column is an array (like groups, roles, etc.)
        if ( Array.isArray(allItems[0][filter.col]) ) {
          filterResult = filterResult.filter( item => item[filter.col].some(f => filter.value.includes(f)) );
        // If item column is an single value
        } else {
          filterResult = filterResult.filter( item => filter.value.includes(item[filter.col]) );
        }
      } else if (filter.type == 'selectObj') {
console.log('Filter by selectObj still needs work');
      }
    }
    filteredItems = [...filterResult];
    dtKey.value++;
  }

  async function handleDelete(id) {
    let destroyUrl = urlRoot.value+'/delete/'+id;
    try {
      const response = await ccDestroy(destroyUrl);
      if (response.result) {
        allItems.splice(allItems.findIndex( ii => ii.id == id),1);
        filteredItems.splice(filteredItems.findIndex( ii => ii.id == id),1);
        success.value = response.msg
        dtKey.value++;
      } else {
        failure.value = response.msg
      }
    } catch (error) {
      console.error('Error deleting:', error);
    }
  }

  async function handleStatusUpdate(id, value) {
    let _idx = allItems.findIndex(ii => ii.id == id);
    if (_idx >= 0) {
      let _item = Object.assign({}, allItems[_idx])
      _item['is_active'] = (value == 'Active') ? 1 : 0;
      try {
        let url = urlRoot.value+'/update/'+id;
        const response = await ccPatch(url, {'is_active': _item['is_active']});
        if (response.result) {
          _item['status'] = value;
          allItems.splice(_idx,1,_item);
          _idx = filteredItems.findIndex(ii => ii.id == id);
          if (_idx >= 0) filteredItems.splice(_idx,1,_item);
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
    if (dialogType.value=='Edit') {
      try {
        let url = urlRoot.value+'/update/'+editingItem.value.id;
        const response = await ccPatch(url, updatedValues);
        if (response.result) {
          let _idx = allItems.findIndex(ii => ii.id == editingItem.value.id);
          allItems.splice(_idx,1,response.record);
          _idx = allItems.findIndex(ii => ii.id == editingItem.value.id);
          if (_idx >= 0) filteredItems.splice(_idx,1,response.record);
          success.value = response.msg
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.error('Error updating:', error);
      }
    } else if (dialogType.value=='Add') {
      try {
        let url =  urlRoot.value+'/store';
        const response = await ccPost(url, updatedValues);
        if (response.result) {
          allItems.push(response.record);
          success.value = response.msg
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.error('Error adding:', error);
      }
    }
    updateItems();
    dialogOpen.value = false;
    editingItem.value = null;
  }

  function handleFormCancel() {
    dialogOpen.value = false;
    editingItem.value = null;
  }
  const emit = defineEmits(['updateConso','setFilter']);
  onBeforeMount(() => loadDataset(props.datasetKey));
  watch(() => props.datasetKey, (newKey) => loadDataset(newKey));
</script>
<!--
  TODO:: Need to add a div/row for success/failure strings
-->
<template>
  <v-sheet>
    <DataToolbar v-model="toolbarFilters" :search="search" :showSelectedOnly="showSelectedOnly" :dataset="props.datasetKey"
                 @update:search="search = $event" @setFilter="updateItems" @update:showSelectedOnly="handleToggle"
                 @add="handleAddItem" @updateConso="handleChangeConso" />

    <DataTable v-if="consoKey!=''" :items="filteredItems" :search="search" :dataset="props.datasetKey" :key="dtKey"
               :showSelectedOnly="showSelectedOnly" :headers="headers" :editableFields="editableFields"
               :searchFields="searchFields" :selectedRows="selectedRows" @update:selectedRows="selectedRows = $event"
               @edit="handleEdit" @delete="handleDelete" @update:status="handleStatusUpdate" />

    <v-dialog v-if="editingItem && isEditable" v-model="dialogOpen" max-width="600px">
      <v-card>
        <v-card-title class="text-indigo-darken-2 pa-6 d-flex justify-space-between align-center">
          <span>{{ dialogTitle }}</span>
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
