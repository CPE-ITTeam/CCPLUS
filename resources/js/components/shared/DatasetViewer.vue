<!-- components/shared/DatasetViewer.vue -->
<script setup>
  import { ref, reactive, watch, onBeforeMount, computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';
  import { fyMonths } from '@/plugins/CCPlusStore.js';
  import { tableSetup } from '@/composables/DataTableConfig.js';
  import DataToolbar from './DataToolbar.vue';
  import DataTable from './DataTable.vue';
  import DataForm from './DataForm.vue';
  import PlatformDialog from '../dialogs/PlatformDialog.vue';
  import CredentialsDialog from '../dialogs/CredentialsDialog.vue';
  import ReportToggle from '../dialogs/ReportToggle.vue';
  import * as XLSX from 'xlsx';
  import Swal from 'sweetalert2';

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
  var newType = reactive({id:null, name:''});
  var platformExportItems = reactive([]);
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
  const typeDialog = ref(false);
  const editableFields = ref([]);
  const urlRoot = ref('');
  const emptyFlags = { 'available': true, 'conso': false, 'groups': [], 'insts': [],
                       'requested': false, 'sortval': 1 };
  var dtLoading = ref(false);
  var truncated = ref(false);
  var success = ref('');
  var failure = ref('');

  const isEditable = computed(() => {
    const config = datasetConfig[props.datasetKey];
    return (editingItem.value && config && editableFields.value.length>0);
  });
  const selectableRows = computed( () => {
    if (typeof(bulkOptions.value.items) == 'undefined') return false;
    return (bulkOptions.value.items.length>0);
  })

  // toolbarFilters holds pre-chunks rows of at-most 4 filters per-row
  const toolbarFilters = computed(() => {
    var rows = [];
    const keys = Object.keys(filterOptions).filter( key => filterOptions[key]['show'] );
    for (let i=0; i<keys.length; i+=4) {
      const row = {};
      const rKeys = keys.slice(i, i+4);
      rKeys.forEach( key => { row[key] = {...filterOptions[key]}; });
      rows.push({...row});
    }
    return rows;
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

  // Load/Refresh dataset items, with filters applied (uses POST route)
  const refreshData = async (datasetKey) => {
    const config = datasetConfig[datasetKey];
    dtLoading.value = true;
    // Setup filters to be passed
    let _filters = {};
    Object.keys(filterOptions).forEach( key => {
      _filters[key] = filterOptions[key]['value'];
    });
    try {
      if (consoKey.value=='') return;
      let itemsUrl = config.urlRoot+'/getItems';
      const response = await ccPost(itemsUrl, { filters: _filters, type: datasetKey });
      if (response.result) {
        truncated = response.truncated;
        allItems = [ ...response.records ];
        filteredItems = [ ...response.records ];
        // Clear selected rows    
        selectedRows.value = [];
        dtKey.value++;
      }
    } catch (error) {
      console.log('Error refreshing records for '+datasetKey+' : ', error);
    }
    dtLoading.value = false;
  };

  // Load dataset
  const loadDataset = async (datasetKey) => {
    const config = datasetConfig[datasetKey];
    try {
      if (consoKey.value=='') return;
      dtLoading.value = true;
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
    if (config.bulkOptions.length>0) bulkOptions.value.items = [...config.bulkOptions];
    // institutions dataset needs groups for "add-to-group" action
    if (props.datasetKey == 'institutions' && typeof(allOptions.groups) != 'undefined') {
      bulkOptions.value['groups'] = [...allOptions.groups];
    }
    // set datatable header, display, and editor options
    headers.value = [{ title: "", key: "" }];
    config.fields.forEach( (fld, idx) => {
      if (fld.header != null) headers.value.push({title: fld.label, key: fld.name, align:fld.header});
      if (fld.name == 'conso' && !is_conso_admin) return;
      config.fields[idx].static = config.static.includes(fld.name);
      // Set filterOptions for select(s) and toggle
      //   options will be limited by values in allItems, but only when there are items
      //   (so harvests tables can show options without item records)
      const _options = (typeof(fld.options)!='undefined') ? fld.options : [];
      var f_options = (typeof(allOptions[fld.name])!='undefined') ? allOptions[fld.name] : _options;
      if ( (fld.type == 'select' || fld.type == 'mselect' || fld.type == 'selectObj' || fld.type == 'toggle') &&
           fld.options == 'fromURL' && typeof(allOptions[fld.name]) != 'undefined' ) {
        if (Array.isArray(allOptions[fld.name]) && ((props.datasetKey=='roles' && fld.name=='role') ||
            typeof(fld.optTxt) == 'undefined' || typeof(fld.optVal) == 'undefined')) {
          if (allItems.length>0) {
            f_options = allOptions[fld.name].filter(
              opt => allItems.flatMap( itm => itm[fld.filterCol] ).includes(opt[fld.optVal])
            );
          }
          filterOptions[fld.name] = {
            'name': fld.name, 'label': fld.label, 'type': 'text', 'show': fld.isFilter, 'col': fld.filterCol,
            'items': [...f_options], 'value': null
          };
        } else {
          let initVal = (fld.type == 'mselect') ? [] : null;
          if (allItems.length>0) {
            f_options = allOptions[fld.name].filter(
              opt => allItems.flatMap( itm => itm[fld.filterCol] ).includes(opt[fld.optVal])
            );
          }
          filterOptions[fld.name] = {
            'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
            'show': fld.isFilter, 'col': fld.filterCol, 'items': [...f_options], 'value': initVal
          };
        }
      } else if (fld.isFilter && (fld.type == 'text' || fld.type == 'mtext')) {
          filterOptions[fld.name] = {
            'name': fld.name, 'label': fld.label, 'type': fld.type, 'show': true, 'col': fld.filterCol
          };
          filterOptions[fld.name]['value'] = (fld.type == 'text') ? null : [];
          if (allItems.length>0 && Array.isArray(allOptions[fld.name])) {
            f_options = allOptions[fld.name].filter(
              opt => allItems.flatMap( itm => itm[fld.filterCol] ).includes(opt[fld.optVal])
            );
          }
          filterOptions[fld.name]['items'] = (fld.options=='fromURL' && Array.isArray(allOptions[fld.name]))
                                             ? [...allOptions[fld.name]] : [...fld.options];
      } else if (Array.isArray(fld.options)) {
        if (allItems.length>0) {
          f_options = fld.options.filter(
            opt => allItems.flatMap( itm => itm[fld.filterCol] ).includes(opt[fld.optVal])
          );
        }
        filterOptions[fld.name] = {
          'name': fld.name, 'label': fld.label, 'type': fld.type, 'val': fld.optVal, 'txt': fld.optTxt,
          'show': fld.isFilter, 'col': fld.filterCol, 'items': [...f_options], 'value': null
        };
      } else if (fld.name == 'fiscalYr') {
        allOptions['fiscalYr'] = [...fyMonths];
      }
    });
    // Set arrays for searchable and editable fields
    searchFields.value = config.fields.filter(fld => fld.searchable).map(f => f.name);
    editableFields.value = config.fields.filter(fld => fld.editable).map(f => f.name);
    urlRoot.value = config.urlRoot;
    dtLoading.value = false;
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

  const capDataset = computed(() => {
    return (props.datasetKey) ? props.datasetKey.charAt(0).toUpperCase() + props.datasetKey.slice(1) : "";
  });

  const exportable = computed(() => {
    const config = datasetConfig[props.datasetKey];
    return (config.exportFields.length>0);
  });

  const exportInstScope = computed(() => {
    if (typeof(filterOptions.institutions)=='undefined' && typeof(filterOptions.groups)=='undefined') return "";
    // For groups dataset, scope is All or type-restriction
    if ((props.datasetKey=='groups')) {
      let _scope = "_AllGroups";
      if (typeof(filterOptions.types)!='undefined') {
        var ftype = filterOptions.types.items.find( t => t.id == filterOptions.types['value']);
        if (typeof(ftype)!='undefined') _scope = "_"+ftype.name;
      }
      return _scope;
    }
    // If groups filter has one+more values, return the group(s) and ignore institutions
    if (typeof(filterOptions.groups) != 'undefined') {
      let group_name = "";
      if (filterOptions.groups['value'].length==0 && typeof(filterOptions.institutions)=='undefined') {
        group_name = (props.datasetKey=='institutions') ? "_AllInstitutions" : "_AllGroups";
      } else if (filterOptions.groups['value'].length==1) {
        var _grp = filterOptions.groups.items.find( g => g.id == filterOptions.groups['value'][0]);
        if (typeof(_grp)!='undefined') group_name = "_"+_grp.name;
      } else if (filterOptions.groups['value'].length>1) {
        group_name = "_SomeGroups";
      }
      if (group_name != "") {
        return group_name;
      }
    }
    // If institutions filter has one+more values
    let inst_name = ""; 
    if (typeof(filterOptions.institutions) != 'undefined') {
      if (Array.isArray(filterOptions.institutions['value'])) {
        if (filterOptions.institutions['value'].length==0) {
          inst_name = "_AllInstitutions";
        } else if (filterOptions.institutions['value'].length==1) {
          var _inst = filterOptions.institutions.items.find( g => g.id == filterOptions.institutions['value'][0]);
          if (typeof(_inst)!='undefined') inst_name = "_"+_inst.name
        } else if (filterOptions.institutions['value'].length>1) {
          inst_name = "_SomeInstitutions";
        }
      } else {
        if (filterOptions.institutions['value']=='' || filterOptions.institutions['value']==null) {
          inst_name = "_AllInstitutions";
        } else {
          var _inst = filterOptions.institutions.items.find( g => g.id == filterOptions.institutions['value']);
          inst_name = (typeof(_inst)!='undefined') ? "_"+_inst.name : "_"+filterOptions.institutions['value'];
        }
      }
    }
    return inst_name;
  });

  const exportPlatScope = computed(() => {
    if (typeof(filterOptions.platforms) == 'undefined') return '';
    let plat_name = ""; 
    if (Array.isArray(filterOptions.platforms['value'])) {
      if (filterOptions.platforms['value'].length==0) {
        plat_name = "_AllPlatforms";
      } else if (filterOptions.platforms['value'].length==1) {
        var _plat = filterOptions.platforms.items.find( g => g.id == filterOptions.platforms['value'][0]);
        if (typeof(_plat)!='undefined') plat_name = _plat.name
      } else {
        plat_name = "_SomePlatforms";
      }
    } else {
      plat_name = (filterOptions.platforms['value']!='' && filterOptions.platforms['value']!=null)
                  ? "_"+filterOptions.platforms['value'] : "_AllPlatforms";
    }
    return plat_name;
  });

  const exportStatus = computed(() => {
    if (typeof(filterOptions.statuses) == 'undefined') return '';
    let stat_name = "";
    if (Array.isArray(filterOptions.statuses['value'])) {
      if (filterOptions.statuses['value'].length <= 1) {
        stat_name = (filterOptions.statuses['value'].length==1) ? "_"+filterOptions.statuses['value'][0] : ""; 
      } else {
        stat_name = "_SomeStatuses";
      }
    } else {
      if (filterOptions.statuses['value']!='' && filterOptions.statuses['value']!=null) {
        stat_name = "_"+filterOptions.statuses['value'];
      }
    }
    return stat_name;
  });

  const exportDataRows = computed(() => {
    const config = datasetConfig[props.datasetKey];
    if (config.exportFields.length==0) return [];
    let exportItems = (config.title == 'Platform') ? [...platformExportItems] : [...filteredItems];
    // Limit rows to selection, if set
    if ( selectedRows.value.length>0 ) {
      exportItems = exportItems.filter( itm => selectedRows.value.some( row => row.id == itm.id) );
    }
    // Filter out static and isFilter columns from filteredItems
    return exportItems.map( row => {
      // Use reduce to build a new object with only the desired keys
      return config.exportFields.reduce( (newRow, col) => {
        let fld = config.fields.find( f => f.name==col);
        let label = (typeof(fld)!='undefined') ? fld.label : col;
        if (col in row) {
          if (Array.isArray(row[col])) {  // Convert arrays to a comma-separated string
            newRow[label] = row[col].join();
          } else {  // not an array, just set in output row
            newRow[label] = row[col];
          }
        }
        return newRow;
      }, {});
    });
  });

  async function handleExport() {
    const config = datasetConfig[props.datasetKey];
    // Setup base filename, default (data) sheet name, and workbook
    let fileName = "CCplus_"+consoKey.value;
    if (props.datasetKey == 'connections') {
      if (filterOptions.results['value'].length <= 1) {
        let _val = (filterOptions.results['value'].length==1) ? "_"+filterOptions.results['value'][0] : ""; 
        fileName += _val.replaceAll(' ', '');
      } else {
        fileName += "_SomeResults";
      }
    } else {
      fileName += (exportInstScope.value != '') ? exportInstScope.value : '';
      fileName += (exportPlatScope.value != '') ? exportPlatScope.value : '';
      fileName += (exportStatus.value != '') ? exportStatus.value : '';
    }
    let sheet_name = (config.title=='Credentials') ? 'Credentials' : config.title+'s';
    const workbook = XLSX.utils.book_new();
    // Audit doesn't need/provide a HowTo tab
    if (props.datasetKey == 'audit') {
      fileName += "_COUNTERAudit.xlsx";
      sheet_name = 'Credentials Audit';
    // Pull HowTo sheet from template in the the public folder and add to the workbook
    } else {
      let publicPath = '/exportTemplates/'+props.datasetKey+'.xlsx';
      try {
        // Fetch template file from the public directory as an ArrayBuffer
        const response = await fetch(publicPath);
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        const arrayBuffer = await response.arrayBuffer();
        // Get the template workbook from the buffer and extract the HowToImport sheet
        const templateBook = XLSX.read(arrayBuffer, { type: 'array' });
        const howToSheet = templateBook.Sheets['HowToImport'];
        if (!howToSheet) {
          throw new Error(`HowToImport Sheet not found in the template for : `+props.datasetKey);
        }
        // Add the HowToImport tab to the workbook
        XLSX.utils.book_append_sheet(workbook, howToSheet, 'How to Import');
      } catch (error) {
        console.error("Error during file processing or download: ", error);
      }
      fileName += "_"+capDataset.value+".xlsx";
    }
    // Add items to a new tab in the workbook and send it
    if (config.title == 'Platform') {
        const { data } = await ccGet('/api/platforms/exportData');
        platformExportItems = [...data.records];
    }
    const itemsSheet = XLSX.utils.json_to_sheet(exportDataRows.value);
    XLSX.utils.book_append_sheet(workbook, itemsSheet, sheet_name);
    XLSX.writeFile(workbook, fileName);
  }

  // data was imported... reload the dataset and update the datatable
  function handleImported(data) {
    if (data.length>0) {
      success.value = data;
    }
    loadDataset(props.datasetKey);
    dtKey.value++;
  }

  function handleEdit(item) {
    const config = datasetConfig[props.datasetKey];
    formDialogType.value = "Edit";
    formDialogTitle = "Edit "+config.title;
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
    if (props.datasetKey == 'connections') {
      addConnection();
      return;
    }
    formDialogType.value = "Add";
    formDialogTitle = "Add New "+config.title;
    editingItem.value = {};
    config.fields.forEach( (fld, idx) => {
      // Skip fields not required for Add
      if (!config.required.includes(fld.name)) return;
      // Toggles should have addValue set in DataTableConfig
      if (fld.type == 'toggle') {
        editingItem.value[fld.name] = fld.addValue;
      } else {
        if (fld.name == 'report_state') {
          if (typeof(formSchema.value.options['master_reports'])!='undefined') {
            let _state = {};
            formSchema.value.options['master_reports'].forEach(rpt => _state[rpt.name]=false);
            editingItem.value[fld.name] = _state;
          } else {
            editingItem.value[fld.name] = {PR:false, DR:false, TR:false, IR:false};
          }
        } else if (fld.name == 'connector_state') {
          if (typeof(formSchema.value.options['all_connectors'])!='undefined') {
            let _state = {};
            formSchema.value.options['all_connectors'].forEach(cnx => _state[cnx.name]=false);
            editingItem.value[fld.name] = _state;
          } else {
            editingItem.value[fld.name] = {customer_id:false, requestor_id:false, api_key:false, extra_args:false};
          }
        } else {
          editingItem.value[fld.name] = null;
        }
      }
      config.fields[idx]['visible'] = true;
    });
    formDialogOpen.value = true;
  }

  // Connections dataset uses the reportToggle component instead of DataForm
  function addConnection(id, rept) {
    // Initialize the reptItem prop - pass 'connections' holding allItems
    reptItem = { 'type': 'Add', 'id': null, 'rept': '', 'flags': emptyFlags, 'connections': [...allItems] };
    // Enable dialog
    reptDialog.value = true;
  }

  async function handleBulk(data) {
    // Bulk institution type assign loads a dialog (comes back with different action to apply the value)
    if (data.action == 'Assign Type') {
      newType = { id: null, name: '' };
      typeDialog.value = true;
      return;
    }
    var bulkUrl = urlRoot.value;
    bulkUrl += (data.action=='Refresh Registry' || data.action=='Full Refresh') ? '/refresh' : '/bulk';
    if (data.action != 'Full Refresh') {
      var args = {ids: selectedRows.value.map(ii => ii.id)};
      Object.keys(data).forEach( (key) => { args[key] = data[key]; });
    } else {
      var args = {ids: 'ALL'};
    }
    // Update the items
    try {
      dtLoading.value = true;
      const response = await ccPost(bulkUrl, args);

      if (response.result && allItems.length>0) {
        // Active/Inactive actions update 'is_active' and 'status' keys in allItems
        if (data.action == 'Set Active' || data.action == 'Set Inactive') {
          if (response.affectedIds.length>0) {
            allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => {
              if ( typeof(itm.is_active) != 'undefined' ) itm.is_active = (data.action == 'Set Active') ? 1 : 0;
              if ( typeof(itm.status) != 'undefined' ) itm.status = (data.action == 'Set Active') ? 'Active' : 'Inactive';
            });
          }
        // Grouping actions (institution dataset) return an extra key for the group
        } else if (data.action == 'Create New Group' || data.action=='Add to Existing Group') {
          if (response.affectedIds.length>0 && typeof(allItems[0]['group_string'] != 'undefined')) {
            if (data.action == 'Create New Group') {
              filterOptions.groups.items.push({'id': response.group.id, 'name': response.group.name});
            }
            allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => {
              itm.group_string += (itm.group_string.length>0) ? ',' : '';
              itm.group_string += response.group.name;
              if (Array.isArray(itm.group_ids)) {
                itm.group_ids.push(response.group.id);
              }
            });
          }
        // Enable action (credentials dataset) should return affectedItems containing
        // [ {'id': <int>, 'status': <string>}, {}, ...]
        } else if (data.action == 'Enable') {
          if (response.affectedItems.length>0 && typeof(allItems[0]['status']) != 'undefined') {
            response.affectedItems.forEach( ritm => {
              var idx = allItems.findIndex( itm => itm.id == ritm.id);
              if (idx >= 0) allItems[idx]['status'] = ritm.status;
            });
          }
        // Disable action (credentials dataset) sets 'status' key in allItems to 'Disabled'
        } else if (data.action == 'Disable') {
          if (response.affectedIds.length>0 && typeof(allItems[0]['status']) != 'undefined') {
            allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => { itm.status = 'Disabled'; });
          }
        // Delete action sets removes records from allItems
        } else if (data.action == 'Delete' || data.action == 'Kill') {
          if (response.affectedIds.length>0) {
            response.affectedIds.forEach( itemId => { allItems.splice(allItems.findIndex( ii => ii.id == itemId),1); });
          }
        // Refresh Registry sends back replacement data for allItems
        } else if ((data.action == 'Refresh Registry' || data.action == 'Full Refresh')) {
          if (response.affectedItems.length>0) {
            let new_platforms = false;
            response.affectedItems.forEach( plat => {
              let _idx = allItems.findIndex( itm => itm.id == plat.id);
              if ( _idx < 0) {  // did the refresh send back something new?
                  allItems.push(plat);
                  new_platforms = true;
              } else {
                  Object.keys(plat).forEach( (key) =>  { allItems[_idx][key] = plat[key]; });
              }
            });
            dtLoading.value = false;
            // Display the summary
            if (response.summary != "") {
                Swal.fire({
                  title: 'Refresh Results', html: response.summary, icon: 'info', showCancelButton: false,
                  confirmButtonColor: '#3085d6', confirmButtonText: 'Close'
                });
            }
            // Resort allItems if we just added some
            if (new_platforms) {
              allItems.sort( (a,b) => {
                  return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
              });
            }
          }
        } else if (data.action == 'Set Institution Type') {
          if (response.affectedIds.length>0 && newType.id != null) {
            allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => {
              itm.type = newType.name;
              itm.type_id = newType.id;
            });
            typeDialog.value = false;
          }
        // (Restart and Pause actions for HarvestQueue and HarvestLogs return arrays of full items),
        } else if (data.action == 'Restart' || data.action == 'Pause') {
          response.affectedItems.forEach( newItem => {
            allItems.splice(allItems.findIndex(ii => ii.id == newItem.id),1,newItem);
          });
          dtLoading.value = false;
        // Bulk Set/Clear validation status in credential audit
        } else if (data.action == 'Set Validated' || data.action == 'Clear Validated') {
            allItems.filter(aitm => response.affectedIds.includes(aitm.id)).forEach( itm => {
              itm.status = (data.action == 'Set Validated') ? 'Active' :  'Inactive';
              itm.validated = (data.action == 'Set Validated') ? 'Validated' :  'Not Validated';
            });
        // Any other/future actions that are added can be caught here also
        // } else if (data.action == 'Some other Action') {
        }
        dtLoading.value = false;
        success.value = response.msg
        // Records changed above, update the datatable and filteredItems
        updateItems();
        dtKey.value++;
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

  // Handle toolbar filter emits
  function handleFilter(filt) {
    // if key=='reset', clear all set filter values
    if (filt.key == 'reset') {
      for (const key of Object.keys(filterOptions)) {
        // restore visibility of group(s)/institution(s) if they've been suppressed
        if (key.includes('institution')) {
          if (typeof(filterOptions.group) != 'undefined') filterOptions.group.show = true;
          if (typeof(filterOptions.groups) != 'undefined') filterOptions.groups.show = true;
        }
        if (key.includes('group')) {
          if (typeof(filterOptions.institution) != 'undefined') filterOptions.institution.show = true;
          if (typeof(filterOptions.institutions) != 'undefined') filterOptions.institutions.show = true;
        }
        if (!filterOptions[key]['show']) continue;
        if ( Array.isArray(filterOptions[key]['value']) ) {
          if (filterOptions[key]['value'].length > 0) filterOptions[key]['value'] = [];
        } else if (filterOptions[key]['value']) {
          filterOptions[key]['value'] = null;
        }
      }
      updateItems();
      dtKey.value++;
    // Otherwise, update a specific filter key
    } else if (typeof(filterOptions[filt.key]['value']) != 'undefined') {
      filterOptions[filt.key]['value'] = filt.value;
      // Setting/clearing 'institution(s)' or 'group(s)' suppresses/reveals the other
      if (filt.key.includes('institution') || filt.key.includes('group')) {
        let cleared = false;
        if (Array.isArray(filterOptions[filt.key]['value'])) {
          cleared = (filterOptions[filt.key]['value'].length==0);
        } else {
          cleared = (filterOptions[filt.key]['value']==null || filterOptions[filt.key]['value']=="");
        }
        if (filt.key.includes('institution')) {
          if (typeof(filterOptions.group) != 'undefined') filterOptions.group.show = cleared;
          if (typeof(filterOptions.groups) != 'undefined') filterOptions.groups.show = cleared;
        }
        if (filt.key.includes('group')) {
          if (typeof(filterOptions.institution) != 'undefined') filterOptions.institution.show = cleared;
          if (typeof(filterOptions.institutions) != 'undefined') filterOptions.institutions.show = cleared;
        }
      }
      updateItems();
      dtKey.value++;
    }
  }

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
        // Filter value against an item column holding an array of values
        if ( Array.isArray(allItems[0][filter.col]) ) {
          filterResult = filterResult.filter( item => item[filter.col].some(f => f==filter.value) );
        // Filter value against single item column values
        } else {
          filterResult = filterResult.filter( item => item[filter.col]==filter.value );
        }
      // Filter items with a multi-select array
      } else if (filter.type == 'mselect' || filter.type == 'mtext') {
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
  }

  async function handleDelete(id) {
    let destroyUrl = urlRoot.value+'/delete/'+id;
    try {
      const response = await ccDestroy(destroyUrl);
      if (response.result) {
        let a_idx = allItems.findIndex( ii => ii.id == id);
        let f_idx = filteredItems.findIndex( ii => ii.id == id);
        // Update connecton records, don't remove them
        if (props.datasetKey == 'connections') {
          let cnx = {...allItems[a_idx]};
          cnx.can_delete = false;
          allOptions['reports'].forEach( rpt =>{
            let _key = rpt.name.toLowerCase()+'_insts';
            cnx[_key] = [];
            _key = rpt.name.toLowerCase()+'_groups';
            cnx[_key] = [];
            cnx[rpt.name]['conso'] = false;
            cnx[rpt.name]['insts'] = [];
            cnx[rpt.name]['groups'] = [];
            cnx[rpt.name]['requested'] = false;
          });
          allItems.splice(a_idx,1,cnx);
          filteredItems.splice(f_idx,1,cnx);
        } else {
          allItems.splice(a_idx,1);
          filteredItems.splice(f_idx,1);
        }
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
  function handleReportToggle(id, rept) {
    let _idx = allItems.findIndex(ii => ii.id == id);
    if (_idx >= 0) {
      var theItem = Object.assign({}, allItems[_idx]);
      // Connections emits launch the ReportToggle dialog
      if (props.datasetKey == 'connections') {
        reptItem = { 'type': 'Edit', 'id': id, 'rept': rept, 'flags': {...theItem[rept]} };
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
    typeDialog.value = false;
  }
  const emit = defineEmits(['updateConso','setFilter']);
  onBeforeMount(() => loadDataset(props.datasetKey));
  watch(() => props.datasetKey, (newKey) => loadDataset(newKey));
</script>

<template>
  <v-sheet>
    <DataToolbar v-model="toolbarFilters" :search="search" :showSelectedOnly="showSelectedOnly" :dataset="props.datasetKey"
                 :bulkOptions="bulkOptions" :selectedRows="selectedRows" :hideExport="!exportable || filteredItems.length==0"
                 @updateConso="handleChangeConso" @export="handleExport" @setFilter="handleFilter" @bulkAction="handleBulk"
                 @add="handleAddItem" @update:search="search=$event" @update:showSelectedOnly="handleToggle"
                 @refreshRecords="refreshData(props.datasetKey)" @imported="handleImported"/>
    <div v-if="success || failure" class="status-message">
      <span v-if="success"      class="good" v-text="success"></span>
      <span v-else-if="failure" class="fail" v-text="failure"></span>
    </div>
    <DataTable v-if="consoKey!=''" :items="filteredItems" :key="dtKey" :search="search" :headers="headers" :isLoading="dtLoading"
               :dataset="props.datasetKey" :showSelectedOnly="showSelectedOnly" :editableFields="editableFields"
               :truncated="truncated" :searchFields="searchFields" :selectedRows="selectedRows" :selectableRows="selectableRows"
               @edit="handleEdit" @delete="handleDelete" @update:selectedRows="selectedRows = $event"
               @update:toggle="handleToggleUpdate" @update:report="handleReportToggle"/>

    <v-dialog v-if="editingItem && isEditable" v-model="formDialogOpen">
      <v-card>
        <v-card-title class="pa-2 d-flex justify-space-between align-center">
          <span>{{ formDialogTitle }}</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="handleFormCancel">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-text v-if="props.datasetKey=='platforms'">
          <PlatformDialog :schema="formSchema" :initialValues="editingItem"
                          @submit="handleFormSubmit" @cancel="handleFormCancel" />
        </v-card-text>
        <v-card-text v-else-if="props.datasetKey=='credentials'">
          <CredentialsDialog :schema="formSchema" :initialValues="editingItem"
                          @submit="handleFormSubmit" @cancel="handleFormCancel" />
        </v-card-text>
        <v-card-text v-else>
          <DataForm :schema="formSchema" :initialValues="editingItem"
                    @submit="handleFormSubmit" @cancel="handleFormCancel" />
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-dialog v-model="reptDialog">
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          <span v-if="reptItem.type=='Add'">Add Report Connection(s)</span>
          <span v-else>{{ reptItem.rept }} Report Connection(s)</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="handleFormCancel">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-subtitle v-if="reptItem.type=='Edit' && reptDialogSubtitle.length>0" class="d-flex align-center">
          <v-col class="d-flex pa-0 ma-0" cols="10"><strong>{{ reptDialogSubtitle }}</strong></v-col>
          <v-col v-if="reptItem.id!==null" class="d-flex ma-0 justify-end" cols="2">
            <v-icon title="Platform ID">mdi-crosshairs-gps</v-icon>&nbsp; {{ reptItem.id }}
          </v-col>
        </v-card-subtitle>
        <v-card-text>
          <ReportToggle :item=reptItem :options="allOptions"
                        @submit="reportToggleSubmit" @cancel="handleFormCancel" />
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-dialog v-model="typeDialog">
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          <span>Update Institution Type</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="$emit('close')">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-subtitle class="d-flex align-center">
          <v-col class="d-flex pa-0"><strong>Updating Type For {{ selectedRows.length }} Institutions</strong></v-col>
        </v-card-subtitle>
        <v-card-text>
          <v-autocomplete v-model="newType" label="Type" :items="allOptions.type" item-title="name" item-value="id"
                          density="compact" return-object/>
        </v-card-text>
        <v-row class="d-flex mt-2" no-gutters>
          <v-col cols="12" class="text-left">
            <v-btn color="primary" @click="handleBulk({action: 'Set Institution Type', type_id: newType.id})" >Save</v-btn>
            <v-btn variant="text" class="ml-2" @click="handleFormCancel">Cancel</v-btn>
          </v-col>
        </v-row>
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
