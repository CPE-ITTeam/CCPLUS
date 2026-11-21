// resources/js/utils/datasetHelpers.js

export function parseIdList(raw) {
  if (!raw) return [];
  if (Array.isArray(raw)) return raw.map(Number);
  return String(raw)
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
    .map(Number);
}

export function normalizeFiscalYearInput(rawVal, fiscalYrOptions = []) {
  if (rawVal === undefined || rawVal === null || !Array.isArray(fiscalYrOptions)) {
    return rawVal;
  }
  const normalized = String(rawVal).trim().toLowerCase();
  const match = fiscalYrOptions.find(
    (opt, idx) =>
      opt.name.toLowerCase() === normalized ||
      opt.fullName.toLowerCase() === normalized ||
      String(idx + 1) === normalized
  );
  return match ? match.label : rawVal;
}

export function resolveFiscalYearNumber(fiscalYrVal, fyMonths) {
  if (typeof fiscalYrVal !== 'string') return fiscalYrVal;
  const idx = fyMonths.indexOf(fiscalYrVal);
  return idx >= 0 ? idx + 1 : fiscalYrVal;
}

export function syncRecordInPools(id, updatedRecord, ...pools) {
  pools.forEach((pool) => {
    if (!Array.isArray(pool)) return;
    const idx = pool.findIndex((item) => item.id == id);
    if (idx >= 0) {
      Object.keys(updatedRecord).forEach((key) => {
        if (key !== 'id') {
          pool[idx][key] = updatedRecord[key];
        }
      });
    }
  });
}

export function buildDefaultStateMap(optionList, fallbackKeys) {
  if (Array.isArray(optionList)) {
    return optionList.reduce((acc, item) => {
      acc[item.name] = false;
      return acc;
    }, {});
  }
  return fallbackKeys.reduce((acc, key) => {
    acc[key] = false;
    return acc;
  }, {});
}

export function hydrateEditItem(item, datasetKey, config, fiscalYrOptions = []) {
  const localizedItem = { ...item };

  config.fields.forEach((fld) => {
    if (fld.type === 'select' && fld.name === 'types') {
      localizedItem.types = localizedItem.type_id;
      localizedItem.reset_type = false;
    }
    if (fld.type === 'select' && fld.name === 'type') {
      localizedItem.type = localizedItem.type_id;
    }
    if (fld.type === 'select' && fld.name === 'institutions') {
      localizedItem.institutions = localizedItem.inst_id;
    }
  });

  if (datasetKey === 'institutions') {
    localizedItem.groups = parseIdList(localizedItem.group_ids);
  }

  if (datasetKey === 'groups') {
    localizedItem.institutions = parseIdList(localizedItem.member_ids);
  }

  if (localizedItem.fiscalYr !== undefined && localizedItem.fiscalYr !== null) {
    localizedItem.fiscalYr = normalizeFiscalYearInput(localizedItem.fiscalYr, fiscalYrOptions);
  }

  return localizedItem;
}

export function buildNewItemDefaults(config, options = {}, defaultFiscalYr = 1) {
  const item = {};

  config.fields.forEach((fld) => {
    if (fld.type === 'toggle') {
      item[fld.name] = fld.addValue ?? 'Inactive';
    } else if (fld.type === 'mselect' || fld.type === 'array') {
      item[fld.name] = [];
    } else if (fld.name === 'report_state') {
      item[fld.name] = buildDefaultStateMap(options.master_reports, ['PR', 'DR', 'TR', 'IR']);
    } else if (fld.name === 'connector_state') {
      item[fld.name] = buildDefaultStateMap(options.all_connectors, [
        'customer_id',
        'requestor_id',
        'api_key',
        'extra_args',
      ]);
    } else if (fld.name === 'fiscalYr') {
      const fyOptions = options.fiscalYr || [];
      const matchedOpt = fyOptions.find((opt) => opt.id == defaultFiscalYr || opt.name === defaultFiscalYr);
      item[fld.name] = matchedOpt ? matchedOpt.id : (fyOptions?.id ?? null);
    } else {
      item[fld.name] = null;
    }
  });

  return item;
}
