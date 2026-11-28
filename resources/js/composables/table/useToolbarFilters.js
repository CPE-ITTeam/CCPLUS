// resources/js/composables/table/useToolbarFilters.js

import { ref, computed, watch } from 'vue';

export function useToolbarFilters(filterOptions, dataset, emit) {
  const activeStatusFilters = ref([]);

  watch(dataset, () => {
    activeStatusFilters.value = [];
  });

  const anyFilterSet = computed(() => {
    if (activeStatusFilters.value.length > 0) return true;

    return filterOptions.value.some((row) => {
      const entries = Array.isArray(row?.items)
        ? row.items.map((e) => [e.name, e.filter])
        : Object.entries(row || {});

      return entries.some(([key, filter]) => {
        if (!filter || typeof filter !== 'object') return false;
        if (['no_inst', 'no_plat', 'no_host'].includes(key)) {
          return filter.value === 'Active';
        }
        if (!filter.show) return false;
        if (Array.isArray(filter.value)) return filter.value.length > 0;
        if (key === 'valid_types') {
          const vals = Array.isArray(filter.value) ? filter.value : [filter.value];
          return !(vals.length === 1 && vals === 'Not Validated');
        }
        if (key === 'status_switch') {
          if (dataset.value === 'credentials') {
            const vals = Array.isArray(filter.value) ? filter.value : [filter.value];
            return !(vals.length === 1 && vals === 'Enabled');
          }
          return filter.value !== 'Active' && filter.value !== 'All';
        }
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
      });
    });
  });


  function handleFilter(filt) {
    for (const row of filterOptions.value) {
      if (Array.isArray(row?.items)) {
        const entry = row.items.find((e) => e.name === filt.key);
        if (entry?.filter) {
          entry.filter.value = filt.value;
          break;
        }
      }
      if (row[filt.key] !== undefined) {
        row[filt.key].value = filt.value;
        break;
      }
    }
    emit('setFilter', filt);
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
