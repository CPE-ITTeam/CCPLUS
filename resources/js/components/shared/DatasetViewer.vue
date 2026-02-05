<!-- components/shared/DatasetViewer.vue -->
<script setup>
  import { ref, reactive, watch, onBeforeMount, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { fyMonths } from '@/plugins/CCPlusStore.js';
  import { tableSetup } from '@/composables/DataTableConfig.js';
  import DataToolbar from './DataToolbar.vue';
  import DataTable from './DataTable.vue';
  import DataForm from './DataForm.vue';
  import ReportToggle from '../dialogs/ReportToggle.vue';

  const authStore = useAuthStore();
  const is_admin = authStore.is_admin;
  const is_conso_admin = authStore.is_conso_admin;
  const { ccGet, ccPost, ccPatch, ccDestroy, setConso } = useAuthStore();
  var consoKey = ref(authStore.ccp_key);  
  const props = defineProps({
    datasetKey: { type: String, required: true }
  });

  // Dataset config map
  const datasetConfig = { ...tableSetup };

  // Reactive state
  var dtKey = ref(0);
  var formDialogType = ref('');
  var formDialogTitle = ref('Item');
  var reptDialogSubtitle = ref('Item');
  var allItems = reactive([]);
  var filteredItems = reactive([]);
  var reptItem = reactive({});
  var allOptions = {};
  var bulkOptions = ref([]);
  const filterOptions = reactive({});
  const headers = ref([]);
  const searchFields = ref([]);
  const search = ref('');
  const selectedRows = ref([]);
  const showSelectedOnly = ref(false);
  const formDialogOpen = ref(false);
  const editingItem = ref(null);
  const reptDialog = ref(false);
  const editableFields = ref([]);
  const urlRoot = ref('');
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
    return {
      dataset: props.datasetKey,
      type: formDialogType.value,
      fields: [...config.fields],
      requiredKeys: [...config.required],
      options: {...allOptions},
    };
  });

  // Load dataset
  const loadDataset = async (datasetKey) => {
    const config = datasetConfig[datasetKey];
    try {
      if (consoKey.value=='') return;
      let itemsUrl = config.urlRoot+'/get';
      if (datasetKey == 'institutions' || datasetKey== 'platforms') {
        itemsUrl += (is_admin) ? '/admin' : '/viewer';
      }
      const { data } = await ccGet(itemsUrl);
      allItems = [ ...data.records ];
      filteredItems = [ ...data.records ];
      allOptions = { ...data.options };
    } catch (error) {
      console.log('Error fetching records for '+datasetKey+' : ', error);
    }
    // Setup bulkOptions
    bulkOptions.value = {'dataset': props.datasetKey, 'items': []};
    if (typeof(config.bulkOptions) != 'undefined') bulkOptions.value.items = [...config.bulkOptions];
    // institutions dataset needs groups for "add-to-group" action
    if (props.datasetKey == 'institutions' && typeof(allOptions.groups) != 'undefined') {
      bulkOptions.value['groups'] = [...allOptions.groups];
    }
    // set datatable header, display, and editor options
    headers.value = [{ title: "", key: "" }];
    config.fields.forEach( (fld, idx) => {
      if (fld.header) headers.value.push({title: fld.label, key: fld.name});
      if (fld.name == 'conso' && !is_conso_admin) return;
      config.fields[idx].static = config.static.includes(fld.name);
      // Set filterOptions for select(s) and toggle
      if ( (fld.type == 'select' || fld.type == 'mselect' || fld.type == 'selectObj' || fld.type == 'toggle') &&
           fld.options == 'fromURL' && typeof(allOptions[fld.name]) != 'undefined' ) {
        var f_options = [];
        if ( typeof(fld.optTxt) == 'undefined' || typeof(fld.optVal) == 'undefined' ||
            (props.datasetKey=='roles' && fld.name=='role')) {
          filterOptions[fld.name] = {
            'name': fld.name, 'label': fld.label, 'type': 'text', 'show': fld.isFilter, 'col': fld.filterCol,
            'items': [...allOptions[fld.name]], 'value': null
          };
        // limit filter options based on allItems
        } else {
          let initVal = (fld.type == 'mselect') ? [] : null;
          // use flatMap since item[fld.filterCol] may hold an array of values...
          f_options = allOptions[fld.name].filter(
            opt => allItems.flatMap( itm => itm[fld.filterCol] ).includes(opt[fld.optVal])
          );
          filterOptions[fld.name] = {
            'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
            'show': fld.isFilter, 'col': fld.filterCol, 'items': [...f_options], 'value': initVal
          };
        }
      } else if (Array.isArray(fld.options)) {
        filterOptions[fld.name] = {
          'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'show': fld.isFilter, 'col': fld.filterCol, 'items': [...fld.options], 'value': fld.options[0]
        };
      } else if (fld.name == 'fiscalYr') {
        allOptions['fiscalYr'] = [...fyMonths];
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

  async function handleChangeConso(conso) {
    consoKey.value = conso.ccp_key;
    const response = await setConso(conso.id,conso.ccp_key);
    loadDataset(props.datasetKey);
    emit('updateConso', conso);
    dtKey.value++;
  }

  function handleEdit(item) {
    const config = datasetConfig[props.datasetKey];
    formDialogType.value = "Edit";
    formDialogTitle = "Edit "+config.dialogTitle;
    config.fields.forEach( (fld, idx) => {
      if (props.datasetKey=='institutions' && fld.name=='creds') {
        config.fields[idx]['visible'] = false;
      } else {
        config.fields[idx]['visible'] = true;
      }
      // Set current values for specific fields
      if (fld.type == 'select' && fld.name == 'institutions') item['institutions'] = item.inst_id;
    });
    editingItem.value = {...item};
    formDialogOpen.value = true;
  }

  function handleAddItem() {
    const config = datasetConfig[props.datasetKey];
    formDialogType.value = "Add";
    formDialogTitle = "Add New "+config.dialogTitle;
    editingItem.value = {};
    config.fields.forEach( (fld, idx) => {
      // Skip fields not required for Add
      if (!config.required.includes(fld.name)) return;
      // Toggles should have addValue set in DataTableConfig
      if (fld.type == 'toggle') {
        editingItem.value[fld.name] = fld.addValue;
      } else {
        editingItem.value[fld.name] = null;
      }
      config.fields[idx]['visible'] = true;
    });
    formDialogOpen.value = true;
  }

  async function handleBulk(data) {
console.log('Bulk Action emit caught : '+data.action);
console.log('There are '+selectedRows.value.length+' Rows selected');
    let bulkUrl = urlRoot.value+'/bulk';
    let args = {ids: selectedRows.value.map(ii => ii.id)};
    Object.keys(data).forEach( (key) => { args[key] = data[key]; });
    // Update the items
    try {
// NOTE:: bulk routes need to accept *at minumum* 'ids' and 'action'
//        and then return ... either  'affectedIds' or 'items' :
//          for affectedIds, update status (or whatever);
//          for items, needs to replace the complete item(s) in the rows
      const response = await ccPost(bulkUrl, args);

      if (response.result) {
        if (response.affectedIds.length>0 && data.action == 'Set Active' || data.action == 'Set Inactive') {
          allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => {
            if ( typeof(itm.is_active) != 'undefined' ) itm.is_active = (data.action == 'Set Active') ? 1 : 0;
            if ( typeof(itm.status) != 'undefined' ) itm.status = (data.action == 'Set Active') ? 'Active' : 'Inactive';
          })
// NOTE:: will need to handle the various return data/actions that come back
//     :: if possible, standardizing action options/values that are similar
//     :: across dataSets would make this section way less clunky
        } else if (data.action == 'Create New Group' || data.action=='Add to Existing Group') {
          if (data.action == 'Create New Group') {
            filterOptions.groups.items.push({'id': response.group.id, 'name': response.group.name});
          }
          allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => {
            if ( typeof(itm.group_string) != 'undefined' ) {
              itm.group_string += (itm.group_string.length>0) ? ',' : '';
              itm.group_string += response.group.name;
            }
            if (Array.isArray(itm.group_ids)) itm.group_ids.push(response.group.id);
          });
        } else if (data.action == 'Delete') {
          response.affectedIds.forEach( itemId => {
            allItems.splice(allItems.findIndex( ii => ii.id == itemId),1);
            filteredItems.splice(filteredItems.findIndex( ii => ii.id == itemId),1);
          });
        } else if (data.action == 'Some other Action') {

        }
        success.value = response.msg
        // Reset the item rows and filteredItems
        updateItems();
      } else {
        failure.value = response.msg
      }
    } catch (error) {
      console.log('Error processing bulk request: ', error);
    }
  }

  // function sortItems() {
  //   const config = datasetConfig[props.datasetKey];
  //   const sortCol = config.sortby;
  //   if (typeof(allItems[sortCol])=='undefined') return;
  //   this.allItems.sort((a,b) => {
  //     if ( a[sortCol] < b[sortCol] ) return -1;
  //     if ( a[sortCol] > b[sortCol] ) return 1;
  //     return 0;
  //   });
  //   if (typeof(filteredItems[sortCol])=='undefined') return;
  //   this.filteredItems.sort((a,b) => {
  //     if ( a[sortCol] < b[sortCol] ) return -1;
  //     if ( a[sortCol] > b[sortCol] ) return 1;
  //     return 0;
  //   });
  // }

//  NOTE::: Currently - changing one filter means (re)apply all that are set to the original item-set
//
  function updateItems() {
    if (allItems.length==0) return;
    var filterResult = [...allItems];
    for (const key of Object.keys(filterOptions)) {
      const filter = filterOptions[key]; 
      if (!filter.show) continue;
      // If the result set is already empty, just bail
      if (filterResult.length==0) break;

      // Check if filter is set
      if ( Array.isArray(filter.value) ) {
        if (filter.value.length == 0) continue;
      } else if (!filter.value) {
        continue;
      }

      // Filter items by a single value
      if (filter.type == 'select' || filter.type == 'text') {
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
//       } else if (filter.type == 'selectObj') {
// console.log('Filter by selectObj still needs work');
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
      console.log('Error deleting:', error);
    }
  }

  async function handleToggleUpdate(id, field, value) {
    let _idx = allItems.findIndex(ii => ii.id == id);
    if (_idx >= 0) {
      let _item = Object.assign({}, allItems[_idx]);
      if (field == 'status') {
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
        } catch (error) {
          console.log('Error updating status', error);
        }
      }
      if (field == 'includeZeros') {
console.log('Handling for includeZeros toggle not written yet');
      }
    }
  }

  async function reportToggleSubmit(data) {
    let a_idx = allItems.findIndex(ii => ii.id == data.id);
    if (a_idx >= 0) {
      let _item = Object.assign({}, allItems[a_idx]);
      // Update the item
      try {
        const response = await ccPost("/api/connections/access",
                                      {id: data.id, rept: data.rept, flags: data.flags});
        if (response.result) {
          _item[data.rept] = {...response.record};
          reptItem.flags = {...data.flags};
          // Update value(s) in the item
          allItems.splice(a_idx,1,_item);
          let f_idx = filteredItems.findIndex(fi => fi.id == data.id);
          if (f_idx >= 0) filteredItems.splice(f_idx,1,_item);
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.log('Error updating report toggle', error);
      }
    }
    reptDialog.value = false;
  }

  // Report toggles work differently for connections and credentials
  // Icons are grayed out if user roles don't allow them to be changed
  // Role-testing boils down to admin rights (either conso-wide, inst-specific,
  // or group-control over the group's member institutions)
  // connections:
  //    * Icon(s) launch the ReportToggle dialog to handle setting/updating
  // credentials:
  //    * Icon(s) launch DataForm component (same as the pencil icon) 
  async function handleReportToggle(id, rept) {
    let _idx = allItems.findIndex(ii => ii.id == id);
    if (_idx >= 0) {
      var theItem = Object.assign({}, allItems[_idx]);
      // Connections emits launch the ReportToggle dialog
      if (props.datasetKey == 'connections') {
        reptItem = { 'id': id, 'rept': rept, 'flags': {...theItem[rept]} };
        // Enable dialog
        reptDialogSubtitle.value = (typeof(theItem.platform) != 'undefined') ? theItem.platform : "";
        reptDialog.value = true;
      // Clicking the report-toggles on the credentials dataset launches the edit() form
      } else if (props.datasetKey == 'credentials') {
        handleEdit(theItem);
      }
    }
  }

  async function handleFormSubmit(updatedValues) {
    if (formDialogType.value=='Edit') {
      try {
        let url = urlRoot.value+'/update/'+editingItem.value.id;
        const response = await ccPatch(url, updatedValues);
        if (response.result) {
          let a_idx = allItems.findIndex(ai => ai.id == editingItem.value.id);
          if (a_idx >= 0) allItems.splice(a_idx,1,response.record);
          let f_idx = filteredItems.findIndex(fi => fi.id == editingItem.value.id);
          if (f_idx >= 0) filteredItems.splice(f_idx,1,response.record);
          success.value = response.msg
          updateItems();
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.log('Error updating:', error);
      }
    } else if (formDialogType.value=='Add') {
      try {
        let url = urlRoot.value+'/store';
        const response = await ccPost(url, updatedValues);
        if (response.result) {
          allItems.push(response.record);
          success.value = response.msg
          // sortItems();
          updateItems();
          dtKey.value++;
        } else {
          failure.value = response.msg
        }
      } catch (error) {
        console.log('Error adding:', error);
      }
    }
    formDialogOpen.value = false;
    editingItem.value = null;
  }

  function handleFormCancel() {
    formDialogOpen.value = false;
    editingItem.value = null;
    reptDialog.value = false;
  }
  const emit = defineEmits(['updateConso','setFilter']);
  onBeforeMount(() => loadDataset(props.datasetKey));
  watch(() => props.datasetKey, (newKey) => loadDataset(newKey));
</script>

<template>
  <v-sheet>
    <DataToolbar v-model="toolbarFilters" :search="search" :showSelectedOnly="showSelectedOnly" :dataset="props.datasetKey"
                 :bulkOptions="bulkOptions" @add="handleAddItem" @setFilter="updateItems" @bulkAction="handleBulk"
                 :selectedRows="selectedRows" @update:search="search=$event" @update:showSelectedOnly="handleToggle"
                 @updateConso="handleChangeConso" />
    <div v-if="success || failure" class="status-message">
      <span v-if="success"      class="good" v-text="success"></span>
      <span v-else-if="failure" class="fail" v-text="failure"></span>
    </div>
    <DataTable v-if="consoKey!=''" :items="filteredItems" :search="search" :dataset="props.datasetKey" :key="dtKey"
               :showSelectedOnly="showSelectedOnly" :headers="headers" :editableFields="editableFields"
               :searchFields="searchFields" :selectedRows="selectedRows" @update:selectedRows="selectedRows = $event"
               @edit="handleEdit" @delete="handleDelete" @update:toggle="handleToggleUpdate"
               @update:report="handleReportToggle"/>

    <v-dialog v-if="editingItem && isEditable" v-model="formDialogOpen" max-width="600px">
      <v-card>
        <v-card-title class="text-indigo-darken-2 pa-6 d-flex justify-space-between align-center">
          <span>{{ formDialogTitle }}</span>
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

    <v-dialog v-model="reptDialog" max-width="600px">
      <v-card>
        <v-card-title class="text-indigo-darken-2 d-flex justify-space-between align-center">
          <span>{{ reptItem.rept }} Report Connection(s)</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="handleFormCancel">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-subtitle v-if="reptDialogSubtitle.length>0" class="d-flex align-center">
          <v-col class="d-flex pa-0 ma-0" cols="10"><strong>{{ reptDialogSubtitle }}</strong></v-col>
          <v-col class="d-flex ma-0 justify-end" cols="2">
            <v-icon title="Platform ID">mdi-crosshairs-gps</v-icon>&nbsp; {{ reptItem.id }}
          </v-col>
        </v-card-subtitle>
        <v-card-text>
          <ReportToggle :item=reptItem :options="allOptions"
                        @submit="reportToggleSubmit" @cancel="handleFormCancel" />
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
