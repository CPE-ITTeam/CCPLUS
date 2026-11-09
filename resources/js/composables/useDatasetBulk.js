// resources/js/composables/useDatasetBulk.js
import { ref } from 'vue';
import api from '@/services/api.js';
import Swal from 'sweetalert2';

export function useDatasetBulk({
  urlRoot,
  allItems,
  filteredItems,
  selectedRows,
  typeDialog,
  newType,
  dtKey,
  updateItems,
  startRegistryProgress,
  stopRegistryProgress
}) {
  const isBulkProcessing = ref(false);

  async function executeBulkAction(data) {
    // 1. Intercept UI dialog trigger overrides
    if (data.action === 'Assign Type') {
      newType.id = null;
      newType.name = '';
      typeDialog.value = true;
      return { handledByUi: true };
    }

    const isRegistryRefresh = (data.action === 'Refresh Registry' || data.action === 'Full Refresh');
    const bulkUrl = urlRoot.value + (isRegistryRefresh ? '/refresh' : '/bulk');
    
    // Aggregate ID array arguments safely
    let args = (data.action !== 'Full Refresh') 
      ? { ids: selectedRows.value.map(ii => ii.id) } 
      : { ids: 'ALL' };
      
    Object.keys(data).forEach((key) => { args[key] = data[key]; });

    // Handle background polling triggers natively via bridge hooks
    if (isRegistryRefresh && typeof startRegistryProgress === 'function') {
      startRegistryProgress();
    }

    try {
      isBulkProcessing.value = true;
      const response = await api.post(bulkUrl, args);
      const resData = response.data || response;

      if (resData.result && allItems.length > 0) {
        
        // ─── TRANSFORMATION ENGINE SWITCHES ───
        if (data.action === 'Set Active' || data.action === 'Set Inactive') {
          if (resData.affectedIds?.length > 0) {
            allItems.filter(aitm => resData.affectedIds.includes(aitm.id)).forEach(itm => {
              if (typeof itm.is_active !== 'undefined') itm.is_active = (data.action === 'Set Active') ? 1 : 0;
              if (typeof itm.status !== 'undefined') itm.status = (data.action === 'Set Active') ? 'Active' : 'Inactive';
            });
          }
        } 
        
        else if (data.action === 'Create New Group' || data.action === 'Add to Existing Group') {
          // Note: Handled on the parent since filterOptions references live on the layout layer
          return { success: true, delegateGroupUi: true, resData, data };
        } 
        
        else if (data.action === 'Enable') {
          if (resData.affectedItems?.length > 0) {
            resData.affectedItems.forEach(ritm => {
              var idx = allItems.findIndex(itm => itm.id == ritm.id);
              if (idx >= 0) allItems[idx]['status'] = ritm.status;
            });
          }
        } 
        
        else if (data.action === 'Disable') {
          if (resData.affectedIds?.length > 0) {
            allItems.filter(aitm => resData.affectedIds.includes(aitm.id)).forEach(itm => { itm.status = 'Disabled'; });
          }
        } 
        
        else if (data.action === 'Delete' || data.action === 'Kill') {
          if (resData.affectedIds?.length > 0) {
            resData.affectedIds.forEach(itemId => {
              let aIdx = allItems.findIndex(ii => ii.id == itemId);
              if (aIdx >= 0) allItems.splice(aIdx, 1);
              let fIdx = filteredItems.findIndex(ii => ii.id == itemId);
              if (fIdx >= 0) filteredItems.splice(fIdx, 1);
            });
            dtKey.value++;
          }
        } 
        
        else if (data.action === 'Refresh Registry' || data.action === 'Full Refresh') {
          if (resData.affectedItems?.length > 0) {
            let new_platforms = false;
            resData.affectedItems.forEach(plat => {
              let _idx = allItems.findIndex(itm => itm.id == plat.id);
              if (_idx < 0) {
                allItems.push(plat);
                new_platforms = true;
              } else {
                allItems.splice(_idx, 1, plat);
                
                // 🚀 NATIVE DOM INJECTION: Find and update row cell dates instantly
                const targetDate = plat.updated || (plat.updated_at ? plat.updated_at.substring(0, 16).replace('T', ' ') : '');
                if (targetDate) {
                  const rows = document.querySelectorAll('tr');
                  rows.forEach(row => {
                    if (row.innerText.includes(plat.name)) {
                      const cells = row.querySelectorAll('td');
                      cells.forEach(cell => {
                        if (cell.innerText.includes('-') && cell.innerText.includes(':')) {
                          cell.innerText = targetDate;
                        }
                      });
                    }
                  });
                }
              }
            });
            
            if (resData.summary !== "") {
              Swal.fire({ title: 'Refresh Results', html: resData.summary, icon: 'info', confirmButtonText: 'Close' });
            }
            if (new_platforms) {
              allItems.sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()));
            }
          }
          if (typeof updateItems === 'function') updateItems();
        } 
        
        else if (data.action === 'Assign Type') {
          if (resData.affectedIds?.length > 0 && newType.id != null) {
            allItems.filter(aitm => resData.affectedIds.includes(aitm.id)).forEach(itm => {
              itm.type = newType.name;
              itm.type_id = newType.id;
            });
            typeDialog.value = false;
          }
        } 
        
        else if (data.action === 'Restart' || data.action === 'Pause') {
          resData.affectedItems?.forEach(newItem => {
            let idx = allItems.findIndex(ii => ii.id == newItem.id);
            if (idx >= 0) allItems.splice(idx, 1, newItem);
          });
          if (typeof updateItems === 'function') updateItems();
          dtKey.value++;
        } 
        
        else if (data.action.includes(' Validat')) {
          allItems.filter(aitm => resData.affectedIds.includes(aitm.id)).forEach(itm => {
            Object.entries(resData.data).forEach(([col, value]) => {
              itm[col] = (value == null) ? 'Inactive' : 'Active';
            });
          });
        }
        
        return { success: true, msg: resData.msg };
      } else {
        return { success: false, msg: resData.msg || "The bulk action configuration failed." };
      }
    } catch (error) {
      console.error('[BulkActions Engine] Error processing bulk request: ', error);
      return { success: false, msg: "Server exception thrown during bulk state execution." };
    } finally {
      isBulkProcessing.value = false;
      if (typeof stopRegistryProgress === 'function') {
        stopRegistryProgress();
      }
    }
  }

  return {
    isBulkProcessing,
    executeBulkAction
  };
}
