// resources/js/composablesdataset/useDatasetLoader.js

import api from '@/services/api.js';

const GLOBAL_DATASETS = new Set([
  'platforms',
  'publishers',
  'providers',
  'registries',
  'titles',
  'databases',
  'items',
  'datahosts',
  'errors',
]);

const POST_GET_DATASETS = new Set(['harvests', 'jobs', 'audit']);

export function useDatasetLoader({
  datasetConfig,
  consoKey,
  isAdmin,
  dtLoading,
  truncated,
  allItems,
  filteredItems,
  allOptions,
  filterOptions,
  selectedRows,
  applySchema,
  updateItems,
}) {
  function serializeFilters() {
    const serialized = {};
    Object.keys(filterOptions).forEach((key) => {
      if (filterOptions[key]?.type === 'ymrange') {
        const val = filterOptions[key].value || {};
        serialized.fromYM = val.fromYM || '';
        serialized.toYM = val.toYM || '';
      } else {
        serialized[key] = filterOptions[key].value;
      }
    });
    return serialized;
  }

  function syncOptions(incomingOptions) {
    if (!incomingOptions) return;

    Object.keys(allOptions).forEach((key) => {
      if (!(key in incomingOptions)) {
        delete allOptions[key];
      }
    });

    Object.assign(allOptions, incomingOptions);

    Object.keys(incomingOptions).forEach((key) => {
      if (Array.isArray(incomingOptions[key])) {
        allOptions[key] = [...incomingOptions[key]];
      }
    });
  }

  async function prefetchMasterGroups() {
    try {
      const { data } = await api.get('/api/groups/get');
      if (data?.records) {
        allOptions.groups = data.records.map((g) => ({
          id: Number(g.id),
          name: g.name,
        }));
      }
    } catch (err) {
      console.error('[DatasetLoader] Background master group pre-fetch failed:', err);
    }
  }
  const isCanceledError = (error) =>
    error?.code === 'ERR_CANCELED' ||
    error?.name === 'CanceledError' ||
    error?.message === 'Request aborted';

  async function loadDataset(datasetKey) {
    const config = datasetConfig[datasetKey];
    if (!config) return;
    if (consoKey.value === '' && datasetKey !== 'consortia') return;

    dtLoading.value = true;
    try {
      let itemsUrl = `${config.urlRoot}/get`;
      if (datasetKey === 'institutions') {
        itemsUrl += isAdmin ? '/admin' : '/viewer';
      }

      const { data } = await api.get(itemsUrl);
      truncated.value = Boolean(data.truncated);
      allItems.splice(0, allItems.length, ...(data.records || []));
      filteredItems.splice(0, filteredItems.length, ...(data.records || []));

      syncOptions(data?.options);

      if (datasetKey === 'institutions') {
        await prefetchMasterGroups();
      }
    } catch (error) {
      if (!isCanceledError(error)) {
        console.error(`Error fetching records for ${datasetKey}:`, error);
      }
    } finally {
      applySchema(datasetKey);
      if (datasetKey === 'audit') {
        await refreshData(datasetKey);
      } else {
        updateItems();
      }
      dtLoading.value = false;
    }


  }

  async function refreshData(datasetKey) {
    const config = datasetConfig[datasetKey];
    if (!config) return;
    if (consoKey.value === '' && datasetKey !== 'consortia') return;

    dtLoading.value = true;
    const filters = serializeFilters();

    try {
      let response;
      if (config.urlRoot.includes('/global') || GLOBAL_DATASETS.has(datasetKey)) {
        const res = await api.get(config.urlRoot, { params: { filters, type: datasetKey } });
        response = res.data;
      } else if (POST_GET_DATASETS.has(datasetKey)) {
        const res = await api.post(`${config.urlRoot}/getItems`, { filters, type: datasetKey });
        response = res.data || res;
      } else {
        const res = await api.get(`${config.urlRoot}/get`, { params: { filters, type: datasetKey } });
        response = res.data;
      }

      if (response?.result) {
        truncated.value = Boolean(response.truncated);
        allItems.splice(0, allItems.length, ...(response.records || []));
        filteredItems.splice(0, filteredItems.length, ...(response.records || []));
        selectedRows.value = [];
        updateItems();
      }
    } catch (error) {
      if (!isCanceledError(error)) {
        console.error(`Error refreshing records for ${datasetKey}:`, error);
      }
    } finally {
      dtLoading.value = false;
    }
  }

  return {
    loadDataset,
    refreshData,
  };
}
