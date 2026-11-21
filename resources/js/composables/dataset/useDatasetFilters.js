// resources/js/composablesdataset/useDatasetFilters.js

import { toValue } from 'vue';

export function useDatasetFilters({
  datasetKey,
  filterOptions,
  allOptions,
  allItems,
  filteredItems,
  selectedRows,
  dtKey,
  refreshData,
}) {
  function syncMutualInstitutionGroupVisibility() {
    const instVal = filterOptions.institutions?.value ?? filterOptions.institution?.value;
    const grpVal = filterOptions.groups?.value ?? filterOptions.group?.value;

    const hasInst = Array.isArray(instVal) ? instVal.length > 0 : Boolean(instVal);
    const hasGrp = Array.isArray(grpVal) ? grpVal.length > 0 : Boolean(grpVal);

    if (filterOptions.group) filterOptions.group.show = !hasInst && (filterOptions.group.items?.length > 0);
    if (filterOptions.groups) filterOptions.groups.show = !hasInst && (filterOptions.groups.items?.length > 0);
    if (filterOptions.institution) filterOptions.institution.show = !hasGrp && (filterOptions.institution.items?.length > 0);
    if (filterOptions.institutions) filterOptions.institutions.show = !hasGrp && (filterOptions.institutions.items?.length > 0);
  }

  function handleFilter(filt) {
    const dsKey = toValue(datasetKey);
    const shouldServerRefresh =
      filt.refresh ||
      dsKey === 'jobs' ||
      dsKey === 'harvests';

    if (filt.key === 'reset') {
      selectedRows.value = [];
      for (const key of Object.keys(filterOptions)) {
        const f = filterOptions[key];
        if (!f) continue;
        if (f.type === 'ymrange') {
          f.value = { fromYM: '', toYM: '' };
          f.show = true;
          continue;
        }
        if (key === 'is_conso') {
          f.value = 'All';
          f.show = true;
          continue;
        }
        if (Array.isArray(f.items) && f.items.length === 0) {
          f.show = false;
          continue;
        }
        if (Array.isArray(f.value)) {
          f.value = [];
        } else if (f.value !== null && f.value !== undefined) {
          f.value = f.type === 'toggle' ? 'Inactive' : null;
        }
        f.show = true;
      }
      syncMutualInstitutionGroupVisibility();
      if (shouldServerRefresh) {
        refreshData(dsKey);
      } else {
        updateItems();
        dtKey.value++;
      }
      return;
    }

    if (!filterOptions[filt.key] && (dsKey === 'jobs' || dsKey === 'harvests') && filt.key === 'statuses') {
      filterOptions.statuses = {
        name: 'statuses',
        type: 'mselect',
        col: 'd_status',
        show: false,
        items: [],
        value: [],
      };
    }

    const target = filterOptions[filt.key];
    if (!target || target.value === undefined) return;

    target.value = filt.value;

    if (filt.key.includes('institution') || filt.key.includes('group')) {
      syncMutualInstitutionGroupVisibility();
    }

    if (shouldServerRefresh) {
      refreshData(dsKey);
    } else {
      updateItems();
      dtKey.value++;
    }
  }

  function matchesFilter(item, filter) {
    if (!filter || !filter.show) return true;
    if (Array.isArray(filter.value) && filter.value.length === 0) return true;
    if (!Array.isArray(filter.value) && !filter.value) return true;

    if (filter.name === 'is_conso') {
      if (filter.value === 'All' || filter.value === 'Inactive') return true;
      const isConsoItem = Boolean(item[filter.col] ?? item.is_conso);
      if (filter.value === 'Active') return isConsoItem;
      if (filter.value === 'NonConso') return !isConsoItem;
      return true;
    }

    if (filter.type === 'ymrange') {
      const fromYM = filter.value?.fromYM || '';
      const toYM = filter.value?.toYM || '';
      if (!fromYM && !toYM) return true;
      const ym = String(item[filter.col] || '');
      if (fromYM && ym < fromYM) return false;
      if (toYM && ym > toYM) return false;
      return true;
    }

    if (filter.type === 'select' || filter.type === 'text') {
      const targetVal =
        typeof filter.value === 'object' && filter.value !== null
          ? (filter.value.id ?? filter.value.value ?? filter.value.name)
          : filter.value;

      if (Array.isArray(item[filter.col])) {
        return item[filter.col].some((f) => String(f) === String(targetVal));
      }
      return String(item[filter.col]) === String(targetVal);
    }

    if (filter.type === 'toggle') {
      return filter.value === 'Active' ? Boolean(item[filter.col]) : true;
    }

    if (filter.type === 'mselect' || filter.type === 'mtext') {
      const targets = Array.isArray(filter.value)
        ? filter.value.map((v) => String(v).trim().toLowerCase())
        : [String(filter.value).trim().toLowerCase()];

      let itemValues = [];
      if (Array.isArray(item[filter.col])) {
        itemValues = item[filter.col].map((v) => String(v).trim().toLowerCase());
      } else if (item[filter.col] !== undefined && item[filter.col] !== null) {
        itemValues = String(item[filter.col]).split(',').map((v) => v.trim().toLowerCase());
      }

      if (filter.col === 'group_string' || filter.col === 'group_ids') {
        const fallbackIds = Array.isArray(item.group_ids)
          ? item.group_ids.map((v) => String(v).trim())
          : item.group_ids
            ? String(item.group_ids).split(',').map((v) => v.trim())
            : [];
        if (fallbackIds.some((id) => targets.includes(id))) return true;
      }

      return itemValues.some((val) => targets.includes(val));
    }

    return true;
  }

  function syncFilterOptionItems() {
    if (allItems.length === 0) return;

    const dsKey = toValue(datasetKey);
    const isServerFiltered = dsKey === 'jobs' || dsKey === 'harvests';

    for (const key of Object.keys(filterOptions)) {
      const filter = filterOptions[key];
      if (!filter) continue;
      if (filter.type === 'ymrange' || key === 'is_conso') {
        filter.show = true;
        continue;
      }

      const source = allOptions[key];
      if (!Array.isArray(source)) continue;
      if (isServerFiltered && key === 'statuses') continue;

      if (isServerFiltered) {
        filter.items = [...source];
        filter.show = filter.items.length > 0;
        continue;
      }

      const pool = allItems.filter((item) =>
        Object.keys(filterOptions).every((otherKey) =>
          otherKey === key ? true : matchesFilter(item, filterOptions[otherKey])
        )
      );

      const presentValues = new Set(
        pool.flatMap((itm) => itm[filter.col] ?? []).map((v) => String(v).trim())
      );

      filter.items = source
        .map((opt) => (typeof opt === 'string' ? opt.trim() : opt))
        .filter((opt) => {
          const optValue = filter.val && typeof opt === 'object' && opt !== null ? opt[filter.val] : opt;
          return presentValues.has(String(optValue).trim());
        });

      filter.show = filter.items.length > 0;
    }

    syncMutualInstitutionGroupVisibility();
  }

  function updateItems() {
    if (allItems.length === 0) {
      filteredItems.splice(0, filteredItems.length);
      return;
    }

    const dsKey = toValue(datasetKey);
    const isServerFiltered = dsKey === 'jobs' || dsKey === 'harvests';

    const filterResult = isServerFiltered
      ? [...allItems]
      : allItems.filter((item) =>
          Object.keys(filterOptions).every((key) => matchesFilter(item, filterOptions[key]))
        );

    if (
      filterOptions.is_conso &&
      typeof filterOptions.platforms !== 'undefined' &&
      Array.isArray(allOptions.platforms)
    ) {
      const mode = filterOptions.is_conso.value;
      filterOptions.platforms.items =
        mode === 'Active'
          ? allOptions.platforms.filter((itm) => Boolean(itm.is_conso))
          : mode === 'NonConso'
            ? allOptions.platforms.filter((itm) => !itm.is_conso)
            : [...allOptions.platforms];
    }

    filteredItems.splice(0, filteredItems.length, ...filterResult);
    syncFilterOptionItems();
  }

  return {
    syncMutualInstitutionGroupVisibility,
    handleFilter,
    matchesFilter,
    syncFilterOptionItems,
    updateItems,
  };
}
