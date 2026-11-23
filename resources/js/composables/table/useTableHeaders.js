// resources/js/composables/table/useTableHeaders.js

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';

const NON_TEXT_TRUNCATE_KEYS = new Set([
  'customer_id',
  'requestor_id',
  'api_key',
  'status',
  'is_active',
  'refreshable',
  'harvest_id',
  'error',
  'PR',
  'DR',
  'TR',
  'IR',
]);

export function useTableHeaders(headers, items, selectableRows, loadingColor) {
  const { t } = useI18n();
  const authStore = useAuthStore();

  const showActions = computed(() => {
    if (items.value.length > 0) {
      return items.value.some(
        (itm) => typeof itm.can_edit !== 'undefined' || typeof itm.can_delete !== 'undefined'
      );
    }
    return false;
  });

  const hasSingleInstitution = computed(() => {
    if (authStore.inst_count === 1) return true;
    if (authStore.is_consoadmin || authStore.is_serveradmin) return false;
    const distinctInsts = new Set(items.value.map((r) => r.inst_id).filter(Boolean));
    return distinctInsts.size === 1;
  });

  const computedHeaders = computed(() => {
    const rawHeaders = headers.value.filter((h) => {
      const keyName = h.key || h.name;

      if (keyName === 'data-table-select' || keyName === 'id') {
        return false;
      }

      if (keyName === 'inst_name' && hasSingleInstitution.value) {
        return false;
      }

      if (h.header === null || h.headerProps?.header === null) {
        return false;
      }

      const titleText = h.title || h.label || '';
      if (titleText.trim() === '') {
        return false;
      }

      return true;
    });

    const hdrs = rawHeaders.map((header) => {
      const isCustomHeader = Boolean(header.headerProps?.class);
      const isCentered = !isCustomHeader && (header.align === 'center' || header.header === 'center');
      return {
        ...header,
        key: header.key || header.name,
        title: header.title || header.label,
        align: isCustomHeader ? header.align : isCentered ? 'center' : (header.align || 'start'),
        headerProps: isCustomHeader
          ? {
              ...header.headerProps,
              class: `font-weight-bold ${header.headerProps.class}`,
            }
          : {
              class: isCentered
                ? `font-weight-bold bg-${loadingColor.value} text-white ps-10`
                : `font-weight-bold bg-${loadingColor.value} text-white`,
            },
      };
    });

    if (selectableRows.value) {
      hdrs.unshift({
        title: '',
        key: 'row_select',
        sortable: false,
        width: '44px',
        headerProps: { class: `bg-${loadingColor.value} text-white px-2 text-center` },
        cellProps: { class: 'px-2 text-center' },
      });
    }

    if (showActions.value) {
      hdrs.push({
        title: t('tables.actions'),
        key: 'actions',
        align: 'end',
        headerProps: { class: `font-weight-bold bg-${loadingColor.value} text-white` },
      });
    }

    return hdrs;
  });

  const dateHeaders = computed(() =>
    computedHeaders.value.filter((h) => h.type === 'date')
  );

  const truncatedHeaders = computed(() =>
    computedHeaders.value.filter(
      (h) =>
        h.truncate > 0 &&
        !NON_TEXT_TRUNCATE_KEYS.has(h.key) &&
        h.type !== 'date' &&
        h.type !== 'toggle'
    )
  );

  return {
    showActions,
    hasSingleInstitution,
    computedHeaders,
    dateHeaders,
    truncatedHeaders,
  };
}
