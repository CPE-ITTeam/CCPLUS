// resources/js/composables/table/useAuditStatus.js

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const JSON_CHIP_DEFS = [
  { key: 'no_inst', col: 'has_inst', label: 'No Institution', color: 'secondary' },
  { key: 'no_plat', col: 'has_plat', label: 'No Platform', color: 'secondary' },
  { key: 'no_host', col: 'has_host', label: 'No Host', color: 'secondary' },
];

const CRED_STATUS_DEFS = [
  { value: 'Enabled', fallbackLabel: 'Enabled', color: 'success', alwaysShow: true },
  { value: 'Incomplete', fallbackLabel: 'Incomplete', color: 'warning', alwaysShow: false },
  { value: 'Suspended', fallbackLabel: 'Suspended', color: 'secondary', alwaysShow: false },
  { value: 'Disabled', fallbackLabel: 'Disabled', color: 'error', alwaysShow: true },
  { value: 'All', fallbackLabel: 'Total', color: 'info', alwaysShow: true },
];

export function useAuditStatus(dataset, items, filterOptions, handleFilter) {
  const { t } = useI18n();

  const isAuditDataset = computed(
    () => dataset.value === 'audit' || dataset.value === 'credentials'
  );

  const filterLookup = computed(() => {
    const map = {};
    for (const row of filterOptions.value) {
      if (Array.isArray(row?.items)) {
        for (const entry of row.items) {
          if (entry?.name && entry?.filter) map[entry.name] = entry.filter;
        }
      }
      for (const [k, v] of Object.entries(row || {})) {
        if (v && typeof v === 'object' && 'value' in v) {
          map[k] = v;
        }
      }
    }
    return map;
  });

  const activeValidTypes = computed(() => {
    if (dataset.value === 'credentials') {
      const raw = filterLookup.value.status_switch?.value;
      if (Array.isArray(raw)) return raw.length > 0 ? raw : ['All'];
      return raw ? [raw] : ['Enabled'];
    }
    const raw = filterLookup.value.valid_types?.value;
    if (Array.isArray(raw)) return raw;
    if (raw === 'All') return ['Validated', 'Not Validated'];
    return raw ? [raw] : ['Not Validated'];
  });

  const activeValidTargets = computed(() => {
    const raw = filterLookup.value.valid_target?.value;
    if (Array.isArray(raw)) return raw;
    if (!raw || raw === 'All') return [];
    return [raw];
  });

  const isNotValidatedActive = computed(
    () =>
      dataset.value === 'audit' &&
      (activeValidTypes.value.includes('Not Validated') ||
        activeValidTypes.value.length === 0)
  );

  const showJsonChips = computed(
    () => dataset.value === 'audit' && isNotValidatedActive.value
  );

  const activeJsonFilters = computed(() => {
    if (!showJsonChips.value) return [];
    return JSON_CHIP_DEFS
      .filter((def) => filterLookup.value[def.key]?.value === 'Active')
      .map((def) => def.key);
  });

  const validationChips = computed(() => {
    if (!isAuditDataset.value) return [];
    const list = Array.isArray(items.value) ? items.value : [];

    if (dataset.value === 'credentials') {
      const col = filterLookup.value.status_switch?.col || 'status';
      return CRED_STATUS_DEFS.map((def) => {
        const count =
          def.value === 'All'
            ? list.length
            : list.filter(
                (itm) =>
                  String(itm[col] || '').toLowerCase() === def.value.toLowerCase()
              ).length;
        return {
          value: def.value,
          label:
            def.value === 'All'
              ? 'Total'
              : t(`statuses.${def.value.toLowerCase()}`, def.fallbackLabel),
          color: def.color,
          count,
          show:
            def.alwaysShow ||
            count > 0 ||
            activeValidTypes.value.includes(def.value),
        };
      }).filter((chip) => chip.show);
    }

    const col = filterLookup.value.valid_types?.col || 'valid_state';
    const visibleList = list.filter((itm) =>
      JSON_CHIP_DEFS.every((def) => {
        const f = filterLookup.value[def.key];
        if (!f || f.value === 'Active') return true;
        return Boolean(itm[f.col || def.col]);
      })
    );

    const validatedCount = visibleList.filter(
      (itm) => Array.isArray(itm[col]) && itm[col].includes('Fully Validated')
    ).length;

    const notValidatedCount = visibleList.filter(
      (itm) => Array.isArray(itm[col]) && itm[col].includes('Not Fully Validated')
    ).length;

    return [
      {
        value: 'Validated',
        label: t('audit.validated', 'Validated'),
        color: 'success',
        count: validatedCount,
      },
      {
        value: 'Not Validated',
        label: t('audit.not_validated', 'Not Validated'),
        color: 'warning',
        count: notValidatedCount,
      },
    ];
  });

  const targetChips = computed(() => {
    if (dataset.value !== 'audit' || !activeValidTypes.value.includes('Not Validated')) {
      return [];
    }
    if (!filterLookup.value.valid_target) return [];

    const list = Array.isArray(items.value) ? items.value : [];
    const col = filterLookup.value.valid_types?.col || 'valid_state';

    const visibleNotValidated = list.filter((itm) => {
      const jsonOk = JSON_CHIP_DEFS.every((def) => {
        const f = filterLookup.value[def.key];
        if (!f || f.value === 'Active') return true;
        return Boolean(itm[f.col || def.col]);
      });
      if (!jsonOk) return false;
      const states = Array.isArray(itm[col]) ? itm[col] : [];
      return states.includes('Not Fully Validated');
    });

    const platformCount = visibleNotValidated.filter(
      (itm) => Array.isArray(itm[col]) && itm[col].includes('Platform Not Validated')
    ).length;

    const institutionCount = visibleNotValidated.filter(
      (itm) => Array.isArray(itm[col]) && itm[col].includes('Institution Not Validated')
    ).length;

    return [
      { value: 'Platform', label: t('fields.platform', 'Platform'), color: 'warning', count: platformCount },
      { value: 'Institution', label: t('fields.institution', 'Institution'), color: 'warning', count: institutionCount },
    ];
  });

  const jsonChips = computed(() => {
    if (!showJsonChips.value) return [];
    const list = Array.isArray(items.value) ? items.value : [];
    const col = filterLookup.value.valid_types?.col || 'valid_state';

    const activeSingleTarget =
      activeValidTargets.value.length === 1 ? activeValidTargets.value.at(0) : null;

    const allowedKeys =
      activeSingleTarget === 'Platform'
        ? new Set(['no_plat', 'no_host'])
        : activeSingleTarget === 'Institution'
          ? new Set(['no_inst'])
          : new Set(['no_inst', 'no_plat', 'no_host']);

    const scopedList = list.filter((itm) => {
      const states = Array.isArray(itm[col]) ? itm[col] : [];
      const matchesValid = activeValidTypes.value.some((v) => {
        if (v === 'Validated') return states.includes('Fully Validated');
        if (v === 'Not Validated') return states.includes('Not Fully Validated');
        return true;
      });
      if (!matchesValid) return false;

      if (activeSingleTarget === 'Platform') {
        return states.includes('Platform Not Validated');
      }
      if (activeSingleTarget === 'Institution') {
        return states.includes('Institution Not Validated');
      }
      return true;
    });

    return JSON_CHIP_DEFS.filter((def) => allowedKeys.has(def.key))
      .map((def) => {
        const f = filterLookup.value[def.key];
        const fCol = f?.col || def.col;
        const count = scopedList.filter((itm) => !itm[fCol]).length;
        return {
          key: def.key,
          label: def.label,
          color: def.color,
          count,
        };
      })
      .filter((chip) => chip.count > 0 || activeJsonFilters.value.includes(chip.key));
  });

  function setValidType(val) {
    if (dataset.value === 'credentials') {
      if (val === 'All') {
        handleFilter({ key: 'status_switch', value: ['All'] });
        return;
      }

      const current = activeValidTypes.value.filter((v) => v !== 'All');
      const idx = current.indexOf(val);
      if (idx >= 0) {
        current.splice(idx, 1);
      } else {
        current.push(val);
      }

      const visibleStatusValues = validationChips.value
        .filter((c) => c.value !== 'All')
        .map((c) => c.value);
      const allSelected =
        visibleStatusValues.length > 0 &&
        visibleStatusValues.every((v) => current.includes(v));

      handleFilter({
        key: 'status_switch',
        value: current.length === 0 || allSelected ? ['All'] : current,
      });
      return;
    }

    const current = [...activeValidTypes.value];
    const idx = current.indexOf(val);
    if (idx >= 0) {
      current.splice(idx, 1);
    } else {
      current.push(val);
    }

    const next = current.length === 0 ? ['Validated', 'Not Validated'] : current;
    handleFilter({ key: 'valid_types', value: next });

    if (!next.includes('Not Validated')) {
      for (const def of JSON_CHIP_DEFS) {
        if (filterLookup.value[def.key]?.value === 'Active') {
          handleFilter({ key: def.key, value: 'Inactive' });
        }
      }
      if (filterLookup.value.valid_target) {
        handleFilter({ key: 'valid_target', value: 'All' });
      }
    }
  }

  function setValidTarget(val) {
    const current = [...activeValidTargets.value];
    const idx = current.indexOf(val);
    if (idx >= 0) {
      current.splice(idx, 1);
    } else {
      current.push(val);
    }
    const nextTarget = current.length === 1 ? current.at(0) : 'All';
    handleFilter({ key: 'valid_target', value: nextTarget });

    if (nextTarget === 'Platform' && filterLookup.value.no_inst?.value === 'Active') {
      handleFilter({ key: 'no_inst', value: 'Inactive' });
    } else if (nextTarget === 'Institution') {
      if (filterLookup.value.no_plat?.value === 'Active') {
        handleFilter({ key: 'no_plat', value: 'Inactive' });
      }
      if (filterLookup.value.no_host?.value === 'Active') {
        handleFilter({ key: 'no_host', value: 'Inactive' });
      }
    }
  }

  function toggleJsonChip(key) {
    const isCurrentlyActive = activeJsonFilters.value.includes(key);
    handleFilter({ key, value: isCurrentlyActive ? 'Inactive' : 'Active' });
  }

  return {
    isAuditDataset,
    validationChips,
    targetChips,
    jsonChips,
    showJsonChips,
    activeValidTypes,
    activeValidTargets,
    activeJsonFilters,
    setValidType,
    setValidTarget,
    toggleJsonChip,
  };
}
