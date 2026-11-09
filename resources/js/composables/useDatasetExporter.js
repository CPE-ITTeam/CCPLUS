// resources/js/composables/useDatasetExporter.js
import { computed } from 'vue';
import { exportDatasetToExcel } from '@/utils/excelJsExporter.js';

export function useDatasetExporter({
  props,
  datasetConfig,
  filterOptions,
  allItems,
  filteredItems,
  platformExportItems,
  selectedRows,
  consoKey,
  capDataset,
  fyMonths
}) {
  
  const exportInstScope = computed(() => {
    if (!filterOptions.institutions && !filterOptions.groups) return "";
    if (props.datasetKey === 'groups') {
      let _scope = "_AllGroups";
      if (filterOptions.types) {
        var ftype = filterOptions.types.items?.find(t => t.id == filterOptions.types['value']);
        if (ftype) _scope = "_" + ftype.name;
      }
      return _scope;
    }
    if (filterOptions.groups) {
      let group_name = "";
      if (filterOptions.groups['value']?.length === 0 && !filterOptions.institutions) {
        group_name = (props.datasetKey === 'institutions') ? "_AllInstitutions" : "_AllGroups";
      } else if (filterOptions.groups['value']?.length === 1) {
        var _grp = filterOptions.groups.items?.find(g => g.id == filterOptions.groups['value'][0]);
        if (_grp) group_name = "_" + _grp.name;
      } else if (filterOptions.groups['value']?.length > 1) {
        group_name = "_SomeGroups";
      }
      if (group_name !== "") return group_name;
    }
    let inst_name = "";
    if (filterOptions.institutions) {
      if (Array.isArray(filterOptions.institutions['value'])) {
        if (filterOptions.institutions['value'].length === 0) {
          inst_name = "_AllInstitutions";
        } else if (filterOptions.institutions['value'].length === 1) {
          var _inst = filterOptions.institutions.items?.find(g => g.id == filterOptions.institutions['value'][0]);
          if (_inst) inst_name = "_" + _inst.name;
        } else if (filterOptions.institutions['value'].length > 1) {
          inst_name = "_SomeInstitutions";
        }
      } else {
        if (filterOptions.institutions['value'] == '' || filterOptions.institutions['value'] == null) {
          inst_name = "_AllInstitutions";
        } else {
          var _inst = filterOptions.institutions.items?.find(g => g.id == filterOptions.institutions['value']);
          inst_name = (_inst) ? "_" + _inst.name : "_" + filterOptions.institutions['value'];
        }
      }
    }
    return inst_name;
  });

  const exportPlatScope = computed(() => {
    if (!filterOptions.platforms) return '';
    let plat_name = "";
    if (Array.isArray(filterOptions.platforms['value'])) {
      if (filterOptions.platforms['value'].length === 0) {
        plat_name = "_AllPlatforms";
      } else if (filterOptions.platforms['value'].length === 1) {
        var _plat = filterOptions.platforms.items?.find(g => g.id == filterOptions.platforms['value'][0]);
        if (_plat) plat_name = _plat.name;
      } else {
        plat_name = "_SomePlatforms";
      }
    } else {
      plat_name = (filterOptions.platforms['value'] != '' && filterOptions.platforms['value'] != null)
        ? "_" + filterOptions.platforms['value'] : "_AllPlatforms";
    }
    return plat_name;
  });

  const exportStatus = computed(() => {
    if (!filterOptions.statuses) return '';
    let stat_name = "";
    if (Array.isArray(filterOptions.statuses['value'])) {
      if (filterOptions.statuses['value'].length <= 1) {
        stat_name = (filterOptions.statuses['value'].length === 1) ? "_" + filterOptions.statuses['value'][0] : "";
      } else {
        stat_name = "_SomeStatuses";
      }
    } else {
      if (filterOptions.statuses['value'] != '' && filterOptions.statuses['value'] != null) {
        stat_name = "_" + filterOptions.statuses['value'];
      }
    }
    return stat_name;
  });

  const exportDataRows = computed(() => {
    const config = datasetConfig[props.datasetKey];
    if (!config || !config.exportFields || config.exportFields.length === 0) return [];

    let exportItems = (config.title === 'Platform') ? [...platformExportItems] : [...filteredItems];

    if (selectedRows.value?.length > 0) {
      exportItems = exportItems.filter(itm => {
        return selectedRows.value.some(row => {
          if (row.id !== undefined && itm.id !== undefined && row.id == itm.id) return true;
          if (config.title === 'Platform') {
            const rowPlatId = row.platform_id || row.id;
            const itmPlatId = itm.platform_id || itm.id;
            if (rowPlatId !== undefined && itmPlatId !== undefined && rowPlatId == itmPlatId) return true;
            if (row.name && itm.name && row.name === itm.name) return true;
          }
          return false;
        });
      });
    }

    return exportItems.map(row => {
      return config.exportFields.reduce((newRow, col) => {
        let fld = config.fields.find(f => f.name === col);
        let label = (fld) ? fld.label : col;
        
        if (col in row) {
          // Normalization check: if it is the fiscalYr field and returns an integer, map to string month name
          if (col === 'fiscalYr' && typeof row[col] === 'number') {
            const monthIdx = row[col] - 1;
            newRow[label] = fyMonths[monthIdx] || row[col];
          } else if (Array.isArray(row[col])) {
            newRow[label] = row[col].join();
          } else {
            newRow[label] = row[col];
          }
        }
        return newRow;
      }, {});
    });
  });

  async function triggerExcelExport(titleConfig) {
    await exportDatasetToExcel({
      datasetKey: props.datasetKey,
      title: titleConfig,
      consoKey: consoKey.value,
      capDataset: capDataset.value,
      exportDataRows: exportDataRows.value,
      filterOptions: filterOptions,
      exportInstScope: exportInstScope.value,
      exportPlatScope: exportPlatScope.value,
      exportStatus: exportStatus.value
    });
  }

  return {
    triggerExcelExport,
    exportDataRows,
    exportInstScope,
    exportPlatScope,
    exportStatus
  };
}
