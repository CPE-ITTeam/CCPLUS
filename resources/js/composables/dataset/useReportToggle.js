// resources/js/composablesdataset/useReportToggle.js
// resources/js/composables/dataset/useReportToggle.js
import { ref } from 'vue';
import api from '@/services/api.js';

const EMPTY_FLAGS = {
  available: true,
  conso: false,
  groups: [],
  insts: [],
  requested: false,
  sortval: 1,
};

export function useReportToggle({
  datasetKey,
  allItems,
  filteredItems,
  dtKey,
  ccPlusStore,
  t,
}) {
  const reptDialog = ref(false);
  const reptItem = ref({});
  const reptDialogSubtitle = ref('Item');

  function addConnection() {
    reptItem.value = {
      type: 'Add',
      id: null,
      rept: '',
      flags: { ...EMPTY_FLAGS },
      connections: [...allItems],
    };
    reptDialog.value = true;
  }

  async function reportToggleSubmit(data) {
    const a_idx = allItems.findIndex((ii) => ii.id == data.id);
    if (a_idx >= 0) {
      const _item = { ...allItems[a_idx] };
      try {
        const response = await api.post('/api/connections/access', {
          id: data.id,
          rept: data.rept,
          flags: data.flags,
        });
        const resData = response.data || response;
        if (resData.result) {
          _item[data.rept] = { ...resData.record };
          reptItem.value.flags = { ...data.flags };
          allItems.splice(a_idx, 1, _item);
          const f_idx = filteredItems.findIndex((fi) => fi.id == data.id);
          if (f_idx >= 0) filteredItems.splice(f_idx, 1, _item);
          dtKey.value++;
        } else {
          ccPlusStore.notifyError(resData.msg);
        }
      } catch (error) {
        console.error('Error updating report toggle', error);
        ccPlusStore.notifyError(t('messages.unexpected_server_error'));
      }
    }
    reptDialog.value = false;
  }

  async function handleReportToggle(id, rept) {
    const activeKey = datasetKey();
    const _idx = allItems.findIndex((ii) => ii.id == id);
    if (_idx < 0) return;

    const theItem = { ...allItems[_idx] };

    if (activeKey == 'connections') {
      reptItem.value = { type: 'Edit', id, rept, flags: { ...theItem[rept] } };
      reptDialogSubtitle.value = typeof theItem.platform !== 'undefined' ? theItem.platform : '';
      reptDialog.value = true;
      return;
    }

    if (activeKey == 'credentials') {
      const currentFlag = theItem[rept];
      if (!currentFlag || !currentFlag.available || currentFlag.conso) return;

      const nextFlag = { ...currentFlag, requested: !currentFlag.requested };
      const optimisticItem = { ...theItem, [rept]: nextFlag };
      allItems.splice(_idx, 1, optimisticItem);
      const f_idx = filteredItems.findIndex((fi) => fi.id == id);
      if (f_idx >= 0) filteredItems.splice(f_idx, 1, optimisticItem);

      try {
        const response = await api.patch(`/api/credentials/update/${id}`, {
          id,
          report_only: true,
          [rept]: nextFlag,
        });
        const resData = response?.data !== undefined ? response.data : response;
        if (resData?.result && resData?.record) {
          const updated = { ...theItem, ...resData.record };
          allItems.splice(_idx, 1, updated);
          if (f_idx >= 0) filteredItems.splice(f_idx, 1, updated);
        } else {
          allItems.splice(_idx, 1, theItem);
          if (f_idx >= 0) filteredItems.splice(f_idx, 1, theItem);
          ccPlusStore.notifyError(resData?.msg || t('messages.server_rejected'));
        }
      } catch (error) {
        allItems.splice(_idx, 1, theItem);
        if (f_idx >= 0) filteredItems.splice(f_idx, 1, theItem);
        console.error('Error toggling credential report:', error);
        ccPlusStore.notifyError(t('messages.unexpected_server_error'));
      }
    }
  }

  function resetReportDialog() {
    reptDialog.value = false;
  }

  return {
    reptDialog,
    reptItem,
    reptDialogSubtitle,
    addConnection,
    reportToggleSubmit,
    handleReportToggle,
    resetReportDialog,
  };
}
