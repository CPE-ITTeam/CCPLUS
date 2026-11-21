// resources/js/composablesdataset/useDatasetForm.js
// resources/js/composables/dataset/useDatasetForm.js
import { ref } from 'vue';
import { fyMonths } from '@/components/ui/YmInput.vue';
import { setAppLocale } from '@/plugins/i18n.js';
import {
  hydrateEditItem,
  buildNewItemDefaults,
  resolveFiscalYearNumber,
  syncRecordInPools,
} from '@/utils/datasetHelpers.js';

export function useDatasetForm({
  datasetKey,
  datasetConfig,
  allItems,
  filteredItems,
  allOptions,
  formSchema,
  saveItem,
  loadDataset,
  updateItems,
  dtKey,
  authStore,
  ccPlusStore,
  t,
  onAddConnection,
}) {
  const formDialogOpen = ref(false);
  const formDialogType = ref('');
  const formDialogTitle = ref('Item');
  const editingItem = ref(null);

  function handleEdit(item) {
    const activeKey = datasetKey();
    const config = datasetConfig[activeKey];
    formDialogType.value = 'Edit';
    formDialogTitle.value = t('dialogs.edit_item', { item: config.title });
    editingItem.value = hydrateEditItem(item, activeKey, config, allOptions.fiscalYr);
    formDialogOpen.value = true;
  }

  function handleAddItem() {
    const activeKey = datasetKey();
    const config = datasetConfig[activeKey];
    if (activeKey == 'connections') {
      onAddConnection?.();
      return;
    }

    formDialogType.value = 'Add';
    formDialogTitle.value = t('dialogs.add_new_item', { item: config.title });

    const defaultFy =
      formSchema.value.options?.defaultFiscalYr ||
      formSchema.value.defaultFiscalYr ||
      ccPlusStore?.fiscalYr ||
      authStore?.fiscalYr ||
      1;

    editingItem.value = buildNewItemDefaults(config, formSchema.value.options, defaultFy);
    formDialogOpen.value = true;
  }

  async function handleFormSubmit(updatedValues) {
    const activeKey = datasetKey();
    ccPlusStore.clearAlerts();

    const formPayload = JSON.parse(JSON.stringify(updatedValues));
    formPayload.fiscalYr = resolveFiscalYearNumber(formPayload.fiscalYr, fyMonths);

    const isEdit = formDialogType.value === 'Edit';
    const targetId = isEdit ? editingItem.value?.id : undefined;

    const result = await saveItem(formPayload, formDialogType.value, targetId);

    if (!result.success) {
      ccPlusStore.notifyError(result.msg);
      return;
    }

    ccPlusStore.notifySuccess(result.msg);
    formDialogOpen.value = false;

    if (isEdit) {
      const finalizedRecord = {
        ...editingItem.value,
        ...formPayload,
        ...(result.record || {}),
      };
      syncRecordInPools(targetId, finalizedRecord, allItems, filteredItems);
      updateItems();
      dtKey.value++;

      if (activeKey === 'consortia' && finalizedRecord.ccp_key === authStore.ccp_key) {
        if (finalizedRecord.theme) {
          authStore.setTheme(finalizedRecord.theme);
        }
        if (finalizedRecord.language && !authStore.user?.language) {
          setAppLocale(finalizedRecord.language);
        }
      }
    } else {
      await loadDataset(activeKey);
      dtKey.value++;
    }
  }

  function resetFormDialog() {
    formDialogOpen.value = false;
    editingItem.value = null;
  }

  return {
    formDialogOpen,
    formDialogType,
    formDialogTitle,
    editingItem,
    handleEdit,
    handleAddItem,
    handleFormSubmit,
    resetFormDialog,
  };
}
