// resources/js/composables/table/useToolbarFilters.js

import { ref, computed, watch } from 'vue';

export function useToolbarFilters(filterOptions, dataset, emit) {
  const activeStatusFilters = ref([]);

  watch(dataset, () => {
    activeStatusFilters.value = [];
  });

  const anyFilterSet = computed(() => {
    if (activeStatusFilters.value.length > 0) return true;

    return filterOptions.value.some((row) =>
      Object.entries(row).some(([key, filter]) => {
        if (!filter.show) return false;
        if (Array.isArray(filter.value)) return filter.value.length > 0;
        if (key === 'valid_types') return filter.value !== 'Not Validated';
        if (key === 'is_conso') return filter.value === 'Active' || filter.value === 'NonConso';
        if (filter.type === 'toggle') return filter.value === 'Active';

        const val = filter.value;
        return (
          val !== null &&
          val !== undefined &&
          val !== '' &&
          val !== 0 &&
          val !== '0' &&
          val !== 'all' &&
          val !== 'All'
        );
      })
    );
  });

  function handleFilter(filt) {
    for (const row of filterOptions.value) {
      if (row[filt.key] !== undefined) {
        row[filt.key].value = filt.value;
        emit('setFilter', filt);
        break;
      }
    }
  }

  function toggleStatusChip(label) {
    const idx = activeStatusFilters.value.indexOf(label);
    if (idx >= 0) {
      activeStatusFilters.value.splice(idx, 1);
    } else {
      activeStatusFilters.value.push(label);
    }
    const nextVal = [...activeStatusFilters.value];
    handleFilter({ key: 'statuses', value: nextVal });
    emit('setFilter', { key: 'statuses', value: nextVal, refresh: true });
  }

  function resetStatusChips() {
    activeStatusFilters.value = [];
    handleFilter({ key: 'statuses', value: [] });
    emit('setFilter', { key: 'statuses', value: [], refresh: true });
  }

  function clearAllFilters() {
    activeStatusFilters.value = [];
    emit('setFilter', { key: 'reset', refresh: true });
  }

  return {
    activeStatusFilters,
    anyFilterSet,
    handleFilter,
    toggleStatusChip,
    resetStatusChips,
    clearAllFilters,
  };
}
