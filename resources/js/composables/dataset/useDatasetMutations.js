// resources/js/composablesdataset/useDatasetMutations.js

// resources/js/composables/dataset/useDatasetMutations.js
import { syncRecordInPools } from '@/utils/datasetHelpers.js';

export function useDatasetMutations({
  datasetKey,
  allItems,
  filteredItems,
  allOptions,
  filterOptions,
  dtLoading,
  dtKey,
  toggleStatusField,
  deleteItem,
  executeBulkAction,
  updateItems,
  ccPlusStore,
  t,
}) {
  async function handleToggleUpdate(id, field, value) {
    const currentItem = allItems.find((item) => item.id === id);
    if (!currentItem) return;

    const result = await toggleStatusField(id, field, value, currentItem);
    if (result.success) {
      syncRecordInPools(id, result.record, allItems, filteredItems);
      updateItems();
      dtKey.value++;
    } else if (result.msg) {
      ccPlusStore.notifyError(result.msg);
    }
  }

  async function handleDelete(id) {
    const result = await deleteItem(id);
    if (result.success) {
      ccPlusStore.notifySuccess(result.msg);
      if (datasetKey() === 'connections') {
        const idx = allItems.findIndex((ii) => ii.id == id);
        if (idx >= 0) allItems[idx].can_delete = false;
      } else {
        const aIdx = allItems.findIndex((ii) => ii.id == id);
        if (aIdx >= 0) allItems.splice(aIdx, 1);
      }
      updateItems();
      dtKey.value++;
    } else {
      ccPlusStore.notifyError(result.msg);
    }
  }

  async function handleBulk(data) {
    dtLoading.value = true;
    ccPlusStore.clearAlerts();

    const result = await executeBulkAction(data);

    if (result && result.success) {
      if (result.delegateGroupUi) {
        const { resData, data: originalData } = result;

        if (!allOptions.groups) {
          allOptions.groups = [];
        }

        const newGroupObj = { id: resData.group.id, name: resData.group.name };

        if (originalData.action === 'Create New Group') {
          if (filterOptions.groups && Array.isArray(filterOptions.groups.items)) {
            filterOptions.groups.items.push(newGroupObj);
          }
          if (Array.isArray(allOptions.groups)) {
            allOptions.groups.push(newGroupObj);
          }
        }

        allItems
          .filter((aitm) => resData.affectedIds.includes(aitm.id))
          .forEach((itm) => {
            const currentGroups = itm.group_string ? String(itm.group_string).split(',') : [];
            if (!currentGroups.includes(resData.group.name)) {
              currentGroups.push(resData.group.name);
            }
            itm.group_string = currentGroups.join(',');

            if (Array.isArray(itm.group_ids)) {
              if (!itm.group_ids.includes(resData.group.id)) {
                itm.group_ids.push(resData.group.id);
              }
            } else {
              itm.group_ids = [resData.group.id];
            }
          });
      }

      updateItems();
      dtKey.value++;
      ccPlusStore.notifySuccess(result.msg || t('messages.bulk_assign_success'));
    } else if (result && !result.handledByUi) {
      ccPlusStore.notifyError(result.msg || t('messages.bulk_assign_failed'));
    }

    dtLoading.value = false;
  }

  return {
    handleToggleUpdate,
    handleDelete,
    handleBulk,
  };
}
