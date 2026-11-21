<!-- components/tables/DataTable.vue -->
<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import DeleteConfirm from '@/components/dialogs/DeleteConfirm.vue';
import ErrorDetails from '@/components/dialogs/ErrorDetails.vue';
import { tableSetup } from '@/config/dataTableConfig.js';
import { sortDatasetItems } from '@/utils/datasetSort.js';
import { useExternalLinks } from '@/composables/useExternalLinks.js';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';
import { useHarvestDownload } from '@/composables/useHarvestDownload.js';


import { useCredentialTest } from '@/composables/useCredentialTest.js';

const {
  isTesting,
  testingRowId,
  testDialog,
  testPassed,
  testStatus,
  testRows,
  testTargetLabel,
  runCredentialTest,
} = useCredentialTest();

const { openUrl, openRegistryPlatform } = useExternalLinks();

const { ccRawJson } = useHarvestDownload();
const { t } = useI18n();
const authStore = useAuthStore();

const emit = defineEmits(['update:selectedRows', 'update:toggle', 'update:report', 'edit', 'delete', 'update:toggleSelectedOnly']);

const copiedCell = ref('');

async function copyCellValue(key, value) {
  if (value == null || value === '') return;
  const text = String(value);
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }
    copiedCell.value = key;
    setTimeout(() => {
      if (copiedCell.value === key) copiedCell.value = '';
    }, 1500);
  } catch (e) {
    console.error('Copy failed:', e);
  }
}

const props = defineProps({
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  search: { type: String, required: true },
  dataset: { type: String, required: true },
  showSelectedOnly: { type: Boolean, required: true },
  searchFields: { type: Array, required: true },
  selectedRows: { type: Array, required: true },
  editableFields: { type: Array, required: true },
  isLoading: { type: Boolean, required: true },
  loadingColor: { type: String, default: 'info' },
  truncated: { type: Boolean, required: true },
  selectableRows: { type: Boolean, required: true },
});

const showActions = computed(() => {
  if (props.items.length > 0) {
    return props.items.some((itm) => typeof itm.can_edit !== 'undefined' || typeof itm.can_delete !== 'undefined');
  }
  return false;
});

const hasSingleInstitution = computed(() => {
  if (authStore.inst_count === 1) return true;
  if (authStore.is_consoadmin || authStore.is_serveradmin) return false;
  const distinctInsts = new Set(props.items.map((r) => r.inst_id).filter(Boolean));
  return distinctInsts.size === 1;
});

const selectedIdSet = computed(() => new Set(props.selectedRows.map((row) => row.id)));
const currentPage = ref(1);
const itemsPerPage = ref(10);

const visibleItems = computed(() => {
  if (itemsPerPage.value === -1) return filteredItems.value;
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredItems.value.slice(start, start + itemsPerPage.value);
});

const areAllVisibleSelected = computed(
  () => visibleItems.value.length > 0 && visibleItems.value.every((row) => selectedIdSet.value.has(row.id))
);

function selectVisibleRows() {
  const next = new Set(selectedIdSet.value);
  visibleItems.value.forEach((row) => next.add(row.id));
  emit(
    'update:selectedRows',
    filteredItems.value.filter((row) => next.has(row.id))
  );
}

function selectAllFilteredRows() {
  emit('update:selectedRows', [...filteredItems.value]);
}

function clearRowSelection() {
  emit('update:selectedRows', []);
}


const filteredItems = computed(() => {
  const searchTerm = (props.search ?? '').trim().toLowerCase();
  let list = props.items;

  if (props.showSelectedOnly) {
    const idSet = selectedIdSet.value;
    list = list.filter((item) => idSet.has(item.id));
  } else if (searchTerm) {
    list = list.filter((item) =>
      props.searchFields.some((field) => {
        const value = item[field];
        return typeof value === 'string' && value.toLowerCase().includes(searchTerm);
      })
    );
  }

  return sortDatasetItems(list, tableSetup[props.dataset]?.sortby);
});

const isAllSelected = computed(
  () => filteredItems.value.length > 0 && selectedIdSet.value.size === filteredItems.value.length
);

const isSomeSelected = computed(
  () => selectedIdSet.value.size > 0 && selectedIdSet.value.size < filteredItems.value.length
);

function toggleSelectAllRows(checked) {
  emit('update:selectedRows', checked ? [...filteredItems.value] : []);
}

function toggleSingleRow(item) {
  const next = new Set(selectedIdSet.value);
  if (next.has(item.id)) {
    next.delete(item.id);
  } else {
    next.add(item.id);
  }
  emit(
    'update:selectedRows',
    filteredItems.value.filter((row) => next.has(row.id))
  );
}

const computedHeaders = computed(() => {
  const rawHeaders = props.headers.filter((h) => {
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
            ? `font-weight-bold bg-${props.loadingColor} text-white ps-10`
            : `font-weight-bold bg-${props.loadingColor} text-white`,
        },
    };
  });

  if (props.selectableRows) {
    hdrs.unshift({
      title: '',
      key: 'row_select',
      sortable: false,
      width: '44px',
      headerProps: { class: `bg-${props.loadingColor} text-white px-2 text-center` },
      cellProps: { class: 'px-2 text-center' },
    });
  }

  if (showActions.value) {
    hdrs.push({
      title: t('tables.actions'),
      key: 'actions',
      align: 'end',
      headerProps: { class: `font-weight-bold bg-${props.loadingColor} text-white` },
    });
  }

  return hdrs;
});

function resolveActiveTimeZone() {
  return (
    authStore.timezone ||
    authStore.user?.timezone ||
    Intl.DateTimeFormat().resolvedOptions().timeZone
  );
}

function formatDateDisplay(dateVal) {
  if (!dateVal) return '';
  const d = new Date(dateVal);
  if (isNaN(d.getTime())) return String(dateVal);

  return new Intl.DateTimeFormat('en-CA', {
    timeZone: resolveActiveTimeZone(),
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(d);
}

function formatDateTimeTooltip(dateVal) {
  if (!dateVal) return '';
  const d = new Date(dateVal);
  if (isNaN(d.getTime())) return String(dateVal);

  return new Intl.DateTimeFormat('en-CA', {
    timeZone: resolveActiveTimeZone(),
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
    .format(d)
    .replace(',', '');
}

const deleteDialog = ref(false);
const curItem = ref({});
const errorDialog = ref(false);
const current_error = ref({ id: null, message: '', explanation: '', detail: '', process_step: '', help_url: '' });

const reportSort = (a, b) => a.sortval - b.sortval;

const customKeySort = {
  DR: (a, b) => reportSort(a, b),
  PR: (a, b) => reportSort(a, b),
  TR: (a, b) => reportSort(a, b),
  IR: (a, b) => reportSort(a, b),
};

const refreshDataset = computed(() => {
  return props.dataset === 'harvests' || props.dataset === 'jobs' || props.dataset === 'audit';
});

const noDataMessage = computed(() => {
  if (!refreshDataset.value) return t('tables.no_data_default');
  return props.dataset === 'jobs'
    ? t('tables.no_data_jobs')
    : t('tables.no_data_refresh');
});

function formatTruncatedDisplay(value, limit) {
  if (value == null || value === '' || value === '-required-') return value ?? '';
  const str = String(value);
  return str.length > limit ? `${str.slice(0, limit)}...` : str;
}

function enableDeleteDialog(item) {
  curItem.value = { ...item };
  deleteDialog.value = true;
}

function closeDeleteDialog() {
  curItem.value = {};
  deleteDialog.value = false;
}

function enableErrorDialog(error) {
  current_error.value = { ...error };
  errorDialog.value = true;
}

function closeErrorDialog() {
  current_error.value = { id: null, message: '', explanation: '', detail: '', process_step: '', help_url: '' };
  errorDialog.value = false;
}
</script>

<!-- components/tables/DataTable.vue -->
<template>
  <div class="w-100">

    <!-- Top Table Header Utility Bar Area -->
    <div class="d-flex flex-column ga-2 mb-4">
      <StatusAlerts v-if="truncated" mode="page" container-class="font-weight-bold mb-0"
        :info="$t('tables.truncated_warning')" @update:warning="truncated = false" />

      <!-- Selection count indicator chip & selection filter toggle button -->
        <div v-if="props.selectableRows && (selectedRows.length > 0 || props.showSelectedOnly)" class="align-self-start ms-4">
        <v-tooltip :text="props.showSelectedOnly ? $t('tables.show_all') : $t('tables.show_selected_only')"
          location="top">
          <template #activator="{ props: tooltipProps }">
            <v-chip v-bind="tooltipProps" color="primary" :variant="props.showSelectedOnly ? 'flat' : 'tonal'"
              prepend-icon="mdi-check-circle" :append-icon="props.showSelectedOnly ? 'mdi-filter-off' : 'mdi-filter'"
              class="font-weight-bold cursor-pointer"
              :aria-label="props.showSelectedOnly ? $t('tables.show_all') : $t('tables.show_selected_only')"
              @click="$emit('update:toggleSelectedOnly', !props.showSelectedOnly)">
              {{ $t('tables.items_selected', { count: selectedRows.length }, selectedRows.length) }}
            </v-chip>
          </template>
        </v-tooltip>
      </div>
    </div>

    <!-- Core Interactive Data Grid Matrix -->
    <v-data-table v-model:page="currentPage" v-model:items-per-page="itemsPerPage" :headers="computedHeaders"
      :header-props="{ class: `bg-${props.loadingColor} text-white` }" :items="filteredItems" item-key="id"
      item-value="id" :loading="props.isLoading ? props.loadingColor : false" :custom-key-sort="customKeySort"
      class="elevation-4 bg-surface rounded-lg overflow-hidden">
      <template #loader="{ isActive }">
        <v-progress-linear :active="isActive" :color="props.loadingColor" height="2" indeterminate />
      </template>

      <template #no-data>
        <v-alert type="info" variant="tonal" density="compact" icon="mdi-alert-circle-outline" class="ma-4"
          :text="noDataMessage" />
      </template>

      <!-- UNIFIED SELECTION CELL ARCHITECTURE -->
      <template #header.row_select>
        <v-menu v-if="itemsPerPage !== -1 && filteredItems.length > visibleItems.length" location="bottom start">
          <template #activator="{ props: menuProps }">
            <button v-bind="menuProps" type="button"
              class="d-inline-flex align-center justify-center border-0 bg-transparent pa-0 cursor-pointer text-white"
              style="margin-inline-start: 18px;" :aria-label="$t('tables.selection_options', 'Selection options')"
              :title="$t('tables.selection_options', 'Selection options')">
              <i :class="[
                'mdi',
                isAllSelected
                  ? 'mdi-checkbox-marked'
                  : isSomeSelected
                    ? 'mdi-minus-box'
                    : 'mdi-checkbox-blank-outline',
              ]" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
              <i class="mdi mdi-menu-down" style="font-size: 16px; line-height: 1;" aria-hidden="true" />
            </button>
          </template>

          <v-list density="compact" min-width="200">
            <v-list-item prepend-icon="mdi-checkbox-marked-outline"
              :title="$t('tables.select_visible', { count: visibleItems.length }, `Select visible (${visibleItems.length})`)"
              :disabled="areAllVisibleSelected" @click="selectVisibleRows" />
            <v-list-item prepend-icon="mdi-checkbox-multiple-marked"
              :title="$t('tables.select_all_count', { count: filteredItems.length }, `Select all (${filteredItems.length})`)"
              :disabled="isAllSelected" @click="selectAllFilteredRows" />
            <template v-if="selectedIdSet.size > 0">
              <v-divider class="my-1" />
              <v-list-item prepend-icon="mdi-close-box-outline" :title="$t('tables.clear_all', 'Clear selection')"
                @click="clearRowSelection" />
            </template>
          </v-list>
        </v-menu>

        <input v-else type="checkbox" :checked="isAllSelected" :indeterminate.prop="isSomeSelected"
          :aria-label="$t('common.select_all', 'Select All')" class="cursor-pointer"
          style="width: 18px; height: 18px; accent-color: white;"
          @change="toggleSelectAllRows($event.target.checked)" />
      </template>


      <template #item.row_select="{ item }">
        <input type="checkbox" :checked="selectedIdSet.has(item.id)" :aria-label="$t('tables.select_row', 'Select row')"
          class="cursor-pointer" style="width: 18px; height: 18px; accent-color: rgb(var(--v-theme-primary));"
          @change="toggleSingleRow(item)" />
      </template>

      <!-- FAILSAFE SLOT OVERLAY FOR ROOT DATASETS -->
      <template v-if="props.dataset === 'consortia' || props.dataset === 'consortium'" #header.id>
        <div class="d-flex align-center justify-center h-100 bg-info text-white rounded-tl-lg ps-4"
          style="box-shadow: inset 0 0 0 10px var(--v-theme-info); min-height: 56px;">
          <input type="checkbox" :checked="isAllSelected" :indeterminate.prop="isSomeSelected"
            :aria-label="$t('common.select_all', 'Select All')" class="cursor-pointer"
            style="width: 18px; height: 18px; accent-color: white;"
            @change="toggleSelectAllRows($event.target.checked)" />
        </div>
      </template>

      <!-- 1. REUSABLE TOGGLE ENGINE LOOP -->
      <template v-for="col in ['status', 'connected', 'includeZeros', 'inst_valid', 'plat_valid']"
        #[`item.${col}`]="{ item }">
        <ToggleIcon v-model="item[col]" :toggleable="col === 'status' ? (props.dataset !== 'audit') : true"
          @update:modelValue="$emit('update:toggle', item.id, col, $event)" />
      </template>

      <!-- 2. COUNTER REPORT SLOT ENGINE LOOP -->
      <template v-for="report in ['PR', 'DR', 'TR', 'IR']" #[`item.${report}`]="{ item }">
        <ToggleIcon v-model="item[report]" :toggleable="true" :size="24"
          @update:modelValue="$emit('update:report', item.id, report)" />
      </template>

      <!-- 3. ACCESSIBILITY INPUT VALIDATOR LOOP -->
      <template v-for="field in ['customer_id', 'requestor_id', 'api_key']" :key="field" #[`item.${field}`]="{ item }">
        <span v-if="item[field] === '-required-'" class="d-inline-flex align-center justify-center text-warning"
          role="img" :aria-label="$t('fields.required', 'Required')" :title="$t('fields.required', 'Required')">
          <i class="mdi mdi-asterisk text-warning" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
        </span>
<button v-else-if="item[field] != null && String(item[field]).trim() !== ''" type="button"
  class="d-inline-flex align-center justify-center border-0 bg-transparent pa-0 cursor-pointer"
  :aria-label="`${$t(`fields.${field}`)}: ${item[field]} (${$t('common.click_to_copy', 'Click to copy')})`"
  :title="copiedCell === `${item.id}-${field}` ? $t('common.copied', 'Copied!') : `${item[field]} (${$t('common.click_to_copy', 'Click to copy')})`"
  @click.stop="copyCellValue(`${item.id}-${field}`, item[field])">
  <v-icon
    :icon="copiedCell === `${item.id}-${field}` ? 'mdi-check' : 'mdi-key-chain'"
    :color="copiedCell === `${item.id}-${field}` ? 'primary' : 'success'"
    :size="24"
    aria-hidden="true"
  />
</button>

        <span v-else class="text-medium-emphasis">&mdash;</span>
      </template>

      <!-- 4. INACTIVE RECORD ENTRIES & TRUNCATED NAMES LOOP -->
      <template v-for="key in ['platform', 'prov_name', 'institution']" #[`item.${key}`]="{ item }">
        <span :title="item[key]"
          :class="{ 'text-disabled font-italic': (key === 'platform' || key === 'prov_name') ? item.plat_active === 0 : item.inst_active === 0 }">
          {{ formatTruncatedDisplay(item[key], 30) }}
        </span>
      </template>

      <!-- Harvest ID Data Field Control Box -->
      <template #item.harvest_id="{ item }">
        <div class="d-flex align-center justify-end ga-1">
          <span>{{ item.harvest_id }}</span>

          <v-btn v-if="item.rawfile != null" icon="mdi-code-json" variant="text" size="small" density="comfortable"
            color="primary" :title="$t('tables.download_json')" :aria-label="$t('tables.download_json')"
            @click="ccRawJson(item.harvest_id)" />

          <v-btn icon="mdi-barley" variant="text" size="small" density="comfortable" color="info"
            :title="$t('tables.manual_retry_title')" :aria-label="$t('tables.manual_retry_aria')"
            @click="openUrl(item.retryUrl)" />
        </div>
      </template>

      <!-- Error / Result Validation Matrix Column -->
      <template #item.error="{ item }">
        <span v-if="item.error?.id > 0" class="d-flex align-center ga-1" :class="`text-${item.error.color || 'error'}`">
          {{ item.error.id }}
          <v-btn icon="mdi-dots-vertical" variant="text" size="small" density="comfortable" :color="item.error.color"
            :title="$t('tables.view_error_details')" :aria-label="$t('tables.view_error_details')"
            @click="enableErrorDialog(item.error)" />
        </span>
        <v-icon v-else-if="item.status === 'Success'" color="success" size="small" :title="item.d_status">
          mdi-check-circle
        </v-icon>
      </template>

      <!-- Dynamic Date Interceptor Loop -->
      <template v-for="header in computedHeaders.filter(h => h.type === 'date')" :key="header.key"
        #[`item.${header.key}`]="{ value }">
        <span v-if="value" :title="formatDateTimeTooltip(value)">{{ formatDateDisplay(value) }}</span>
      </template>

      <!-- Dynamic Truncated Interceptor Loop -->
      <template
        v-for="header in computedHeaders.filter(h => h.truncate > 0 && !['customer_id', 'requestor_id', 'api_key'].includes(h.key))"
        :key="header.key" #[`item.${header.key}`]="{ value }">
        <span v-if="value === '-required-'" class="d-inline-flex align-center text-warning" role="img"
          :aria-label="$t('fields.required', 'Required')" :title="$t('fields.required', 'Required')">
          <i class="mdi mdi-asterisk text-warning" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
        </span>
        <span v-else :title="value">{{ formatTruncatedDisplay(value, header.truncate) }}</span>
      </template>
      <!-- Row Level Operations Action Matrix Column -->
      <template #item.actions="{ item }">
        <div
          v-if="showActions || props.dataset === 'platforms' || props.dataset === 'credentials' || props.dataset === 'badCredentials'"
          class="d-flex ga-1 justify-end"
        >
          <!-- Live Credentials Test Button (credentials & badCredentials) -->
          <v-btn
            v-if="props.dataset === 'credentials' || props.dataset === 'badCredentials'"
            icon="mdi-connection"
            color="info"
            variant="text"
            size="small"
            density="comfortable"
            :loading="isTesting && testingRowId === (props.dataset === 'credentials' ? item.id : item.credentials_id)"
            :title="$t('credentials.test_live', 'Test Live Credentials')"
            :aria-label="$t('credentials.test_live', 'Test Live Credentials')"
            @click="runCredentialTest(
              { type: 'test', credentials_id: props.dataset === 'credentials' ? item.id : item.credentials_id },
              `${item.inst_name || item.institution || ''} — ${item.prov_name || item.platform || ''}`
            )"
          />

          <!-- Condition A: Platform refresh action configurations -->
          <v-tooltip
            v-if="props.dataset === 'platforms' && (item.refresh_result === 'orphan' || item.refresh_result == null)"
            :text="item.refresh_text">
            <template #activator="{ props: tooltipProps }">
              <v-btn :icon="item.refresh_icon" :color="item.refresh_color" variant="text" size="small"
                density="comfortable" v-bind="tooltipProps" :aria-label="$t('tables.platform_refresh_state_aria')" />
            </template>
          </v-tooltip>
          <!-- Condition B: Platform refresh targeting registry index records -->
          <v-tooltip v-else-if="item.refresh_result != null" :text="item.refresh_text">
            <template #activator="{ props: tooltipProps }">
              <v-btn :icon="item.refresh_icon" :color="item.refresh_color" variant="text" size="small"
                density="comfortable" v-bind="tooltipProps" :aria-label="$t('tables.platform_refresh_state_aria')"
                @click="openRegistryPlatform(item.registry_id)" />
            </template>
          </v-tooltip>

          <button v-if="item.can_edit" type="button"
            class="d-inline-flex align-center justify-center border-0 bg-transparent pa-1 rounded cursor-pointer text-medium-emphasis"
            :title="$t('tables.edit_item_aria')" :aria-label="$t('tables.edit_item_aria')" @click="$emit('edit', item)">
            <i class="mdi mdi-pencil" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
          </button>

          <button type="button" :disabled="!item.can_delete"
            class="d-inline-flex align-center justify-center border-0 bg-transparent pa-1 rounded"
            :class="item.can_delete ? 'cursor-pointer text-error' : 'text-disabled'"
            :title="item.can_delete ? $t('tables.delete_item_aria') : $t('tables.delete_action_disabled')"
            :aria-label="item.can_delete ? $t('tables.delete_item_aria') : $t('tables.delete_action_disabled')"
            @click="enableDeleteDialog(item)">
            <i class="mdi mdi-delete" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
          </button>
        </div>
      </template>

    </v-data-table>

    <v-dialog v-model="deleteDialog" width="auto">
      <DeleteConfirm :item="curItem" :dataset="props.dataset" @confirm="$emit('delete', curItem.id)"
        @close="closeDeleteDialog" />
    </v-dialog>

    <v-dialog v-model="errorDialog" width="auto">
      <ErrorDetails :error="current_error" @close="closeErrorDialog" />
    </v-dialog>
        <v-dialog v-model="testDialog" max-width="750">
      <v-card>
        <v-card-item>
          <v-card-title class="text-white">
            {{ $t('credentials.live_test_results', 'Live Credential Test') }}
            <span v-if="testTargetLabel" class="text-body-2 font-weight-regular ms-2">
              ({{ testTargetLabel }})
            </span>
          </v-card-title>
          <template #append>
            <v-btn icon="mdi-close" @click="testDialog = false" />
          </template>
        </v-card-item>

        <v-card-text>
          <div v-if="isTesting" class="d-flex align-center ga-3 py-4">
            <v-progress-circular indeterminate color="primary" size="24" />
            <span>{{ $t('credentials.testing_connection', 'Testing COUNTER endpoint...') }}</span>
          </div>

          <template v-else>
            <v-alert
              v-if="testStatus"
              :type="testPassed ? 'success' : 'error'"
              class="mb-4"
            >
              {{ testStatus }}
            </v-alert>

            <pre
              class="bg-grey-lighten-4 pa-3 rounded-lg text-body-2 overflow-x-auto"
              style="max-height: 360px; white-space: pre-wrap; word-break: break-word;"
            >{{ testRows.join('\n') }}</pre>
          </template>
        </v-card-text>
      </v-card>
    </v-dialog>

  </div>
</template>

<style scoped>
:deep(th.compact-col),
:deep(td.compact-col) {
  padding-inline: 0 !important;
  text-align: center !important;
}

:deep(th.report-group-col),
:deep(td.report-group-col) {
  width: 52px !important;
  min-width: 52px !important;
  max-width: 52px !important;
}

:deep(th.cred-group-col),
:deep(td.cred-group-col) {
  width: 68px !important;
  min-width: 68px !important;
  max-width: 68px !important;
}

:deep(th.compact-col) {
  position: relative;
}

:deep(th.compact-col .v-data-table-header__content) {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  margin: 0 !important;
}

:deep(th.compact-col .v-data-table-header__content > span) {
  display: block;
  width: 100%;
  text-align: center;
  white-space: nowrap;
}

:deep(th.compact-col .v-data-table-header__sort-icon) {
  position: absolute;
  right: 1px;
  font-size: 14px;
  pointer-events: none;
}

:deep(.group-border-start) {
  border-inline-start: 1px solid rgba(var(--v-border-color), 0.28) !important;
}

:deep(.group-border-end) {
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.28) !important;
}

:deep(th.group-border-start) {
  border-inline-start: 1px solid rgba(255, 255, 255, 0.4) !important;
}

:deep(th.group-border-end) {
  border-inline-end: 1px solid rgba(255, 255, 255, 0.4) !important;
}

:deep(th.cred-group-col) {
  background-color: color-mix(in srgb, rgb(var(--v-theme-success)) 85%, black 15%) !important;
}

:deep(th.report-group-col) {
  background-color: color-mix(in srgb, rgb(var(--v-theme-secondary)) 85%, black 15%) !important;
}

:deep(td.report-group-col) {
  background-color: rgba(var(--v-theme-secondary), 0.04);
}

:deep(td.cred-group-col) {
  background-color: rgba(var(--v-theme-success), 0.04);
}

:deep(.v-data-table__tbody > tr) {
  content-visibility: auto;
  contain-intrinsic-size: 44px;
}

:deep(.v-data-table-footer__items-per-page .v-field__clearable) {
  display: none !important;
}
</style>
