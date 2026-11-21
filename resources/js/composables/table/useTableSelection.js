// resources/js/composables/table/useTableSelection.JS

import { computed } from 'vue';

export function useTableSelection(filteredItems, visibleItems, selectedRows, onUpdateSelectedRows) {
  const selectedIdSet = computed(() => new Set(selectedRows.value.map((row) => row.id)));

  const areAllVisibleSelected = computed(
    () => visibleItems.value.length > 0 && visibleItems.value.every((row) => selectedIdSet.value.has(row.id))
  );

  const isAllSelected = computed(
    () => filteredItems.value.length > 0 && selectedIdSet.value.size === filteredItems.value.length
  );

  const isSomeSelected = computed(
    () => selectedIdSet.value.size > 0 && selectedIdSet.value.size < filteredItems.value.length
  );

  function selectVisibleRows() {
    const next = new Set(selectedIdSet.value);
    visibleItems.value.forEach((row) => next.add(row.id));
    onUpdateSelectedRows(filteredItems.value.filter((row) => next.has(row.id)));
  }

  function selectAllFilteredRows() {
    onUpdateSelectedRows([...filteredItems.value]);
  }

  function clearRowSelection() {
    onUpdateSelectedRows([]);
  }

  function toggleSelectAllRows(checked) {
    onUpdateSelectedRows(checked ? [...filteredItems.value] : []);
  }

  function toggleSingleRow(item) {
    const next = new Set(selectedIdSet.value);
    if (next.has(item.id)) {
      next.delete(item.id);
    } else {
      next.add(item.id);
    }
    onUpdateSelectedRows(filteredItems.value.filter((row) => next.has(row.id)));
  }

  return {
    selectedIdSet,
    areAllVisibleSelected,
    isAllSelected,
    isSomeSelected,
    selectVisibleRows,
    selectAllFilteredRows,
    clearRowSelection,
    toggleSelectAllRows,
    toggleSingleRow,
  };
}
