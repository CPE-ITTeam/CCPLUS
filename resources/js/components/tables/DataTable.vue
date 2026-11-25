<!-- components/tables/DataTable.vue -->

<script setup>
// Core & Plugins
import { ref, computed, toRef } from 'vue';
import { useI18n } from 'vue-i18n';

// Config & Utils
import { tableSetup } from '@/config/dataTableConfig.js';
import { sortDatasetItems } from '@/utils/datasetSort.js';

// Composables
import { useCredentialTest } from '@/composables/useCredentialTest.js';
import { useExternalLinks } from '@/composables/useExternalLinks.js';
import { useHarvestDownload } from '@/composables/useHarvestDownload.js';
import { useDataTableHelpers } from '@/composables/table/useDataTableHelpers.js';
import { useTableHeaders } from '@/composables/table/useTableHeaders.js';
import { useTableSelection } from '@/composables/table/useTableSelection.js';

// Components
import CredentialTestDialog from '@/components/dialogs/CredentialTestDialog.vue';
import DeleteConfirm from '@/components/dialogs/DeleteConfirm.vue';
import ErrorDetails from '@/components/dialogs/ErrorDetails.vue';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';
import ToggleIcon from '@/components/ui/ToggleIcon.vue';
import CredentialCell from '@/components/tables/cells/CredentialCell.vue';
import HarvestResultCell from '@/components/tables/cells/HarvestResultCell.vue';
import HarvestIdCell from '@/components/tables/cells/HarvestIdCell.vue';
import TableRowActions from '@/components/tables/cells/TableRowActions.vue';
import TableSelectionMenu from '@/components/tables/cells/TableSelectionMenu.vue';


const { t } = useI18n();
const { openUrl, openRegistryPlatform } = useExternalLinks();
const { ccRawJson } = useHarvestDownload();

const {
  copiedCell,
  copyCellValue,
  formatDateDisplay,
  formatDateTimeTooltip,
  formatTruncatedDisplay,
} = useDataTableHelpers();

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

const emit = defineEmits([
  'update:selectedRows',
  'update:toggle',
  'update:report',
  'edit',
  'delete',
  'update:toggleSelectedOnly',
  'update:truncated',
]);

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

const currentPage = ref(1);
const itemsPerPage = ref(10);

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

const visibleItems = computed(() => {
  if (itemsPerPage.value === -1) return filteredItems.value;
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredItems.value.slice(start, start + itemsPerPage.value);
});

const {
  selectedIdSet,
  areAllVisibleSelected,
  isAllSelected,
  isSomeSelected,
  selectVisibleRows,
  selectAllFilteredRows,
  clearRowSelection,
  toggleSelectAllRows,
  toggleSingleRow,
} = useTableSelection(
  filteredItems,
  visibleItems,
  toRef(props, 'selectedRows'),
  (rows) => emit('update:selectedRows', rows)
);

const {
  showActions,
  computedHeaders,
  dateHeaders,
  truncatedHeaders,
} = useTableHeaders(
  toRef(props, 'headers'),
  toRef(props, 'items'),
  toRef(props, 'selectableRows'),
  toRef(props, 'loadingColor')
);

function handleCredentialTest(item) {
  runCredentialTest(
    {
      type: 'test',
      credentials_id: props.dataset === 'credentials' ? item.id : item.credentials_id,
      inst_id: item.inst_id,
      prov_id: item.prov_id,
    },
    `${item.inst_name || item.institution || ''} — ${item.prov_name || item.platform || ''}`
  );
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

<template>
  <div class="w-100">
    <!-- Top Table Header Utility Bar Area -->
    <div class="d-flex flex-column ga-2 mb-4">
      <StatusAlerts
        v-if="truncated"
        mode="page"
        container-class="font-weight-bold mb-0"
        :info="$t('tables.truncated_warning')"
        @update:warning="$emit('update:truncated', false)"
      />

      <div v-if="props.selectableRows && (selectedRows.length > 0 || props.showSelectedOnly)" class="align-self-start ms-4">
        <v-tooltip :text="props.showSelectedOnly ? $t('tables.show_all') : $t('tables.show_selected_only')">
          <template #activator="{ props: tooltipProps }">
            <v-chip
              v-bind="tooltipProps"
              color="primary"
              :variant="props.showSelectedOnly ? 'flat' : 'tonal'"
              prepend-icon="mdi-check-circle"
              :append-icon="props.showSelectedOnly ? 'mdi-filter-off' : 'mdi-filter'"
              class="font-weight-bold cursor-pointer"
              :aria-label="props.showSelectedOnly ? $t('tables.show_all') : $t('tables.show_selected_only')"
              @click="$emit('update:toggleSelectedOnly', !props.showSelectedOnly)"
            >
              {{ $t('tables.items_selected', { count: selectedRows.length }, selectedRows.length) }}
            </v-chip>
          </template>
        </v-tooltip>
      </div>
    </div>

    <!-- Core Interactive Data Grid Matrix -->
    <v-data-table
      v-model:page="currentPage"
      v-model:items-per-page="itemsPerPage"
      :headers="computedHeaders"
      :header-props="{ class: `bg-${props.loadingColor} text-white` }"
      :items="filteredItems"
      item-key="id"
      item-value="id"
      :loading="props.isLoading ? props.loadingColor : false"
      :custom-key-sort="customKeySort"
      class="elevation-4 bg-surface rounded-lg overflow-hidden"
    >
      <template #loader="{ isActive }">
        <v-progress-linear :active="isActive" :color="props.loadingColor" height="2" indeterminate />
      </template>

      <template #no-data>
        <v-alert
          type="info"
          variant="tonal"
          density="compact"
          icon="mdi-alert-circle-outline"
          class="ma-4"
          :text="noDataMessage"
        />
      </template>

      <template #header.row_select>
        <TableSelectionMenu
          :show-menu="itemsPerPage !== -1 && filteredItems.length > visibleItems.length"
          :is-all-selected="isAllSelected"
          :is-some-selected="isSomeSelected"
          :are-all-visible-selected="areAllVisibleSelected"
          :visible-count="visibleItems.length"
          :total-count="filteredItems.length"
          :selected-count="selectedIdSet.size"
          @selectVisible="selectVisibleRows"
          @selectAll="selectAllFilteredRows"
          @clear="clearRowSelection"
          @toggleAll="toggleSelectAllRows"
        />
      </template>

      <template #item.row_select="{ item }">
        <input
          type="checkbox"
          :checked="selectedIdSet.has(item.id)"
          :aria-label="$t('tables.select_row', 'Select row')"
          class="cursor-pointer"
          style="width: 18px; height: 18px; accent-color: rgb(var(--v-theme-primary));"
          @change="toggleSingleRow(item)"
        />
      </template>

      <!-- 1. REUSABLE TOGGLE ENGINE LOOP -->
      <template
        v-for="col in ['status', 'connected', 'includeZeros', 'inst_valid', 'plat_valid']"
        :key="col"
        #[`item.${col}`]="{ item }"
      >
        <ToggleIcon
          v-model="item[col]"
          :toggleable="col === 'status' ? props.dataset !== 'audit' : true"
          @update:modelValue="$emit('update:toggle', item.id, col, $event)"
        />
      </template>

      <!-- 2. COUNTER REPORT SLOT ENGINE LOOP -->
      <template v-for="report in ['PR', 'DR', 'TR', 'IR']" :key="report" #[`item.${report}`]="{ item }">
        <ToggleIcon
          v-model="item[report]"
          :toggleable="true"
          :size="24"
          @update:modelValue="$emit('update:report', item.id, report)"
        />
      </template>

      <!-- 3. ACCESSIBILITY INPUT VALIDATOR LOOP -->
      <template v-for="field in ['customer_id', 'requestor_id', 'api_key']" :key="field" #[`item.${field}`]="{ item }">
        <CredentialCell
          :field="field"
          :value="item[field]"
          :copied="copiedCell === `${item.id}-${field}`"
          @copy="copyCellValue(`${item.id}-${field}`, item[field])"
        />
      </template>

      <!-- 4. INACTIVE RECORD ENTRIES & TRUNCATED NAMES LOOP -->
      <template v-for="key in ['platform', 'prov_name', 'institution']" :key="key" #[`item.${key}`]="{ item }">
        <span
          :title="item[key]"
          :class="{ 'text-disabled font-italic': (key === 'platform' || key === 'prov_name') ? item.plat_active === 0 : item.inst_active === 0 }"
        >
          {{ formatTruncatedDisplay(item[key], 30) }}
        </span>
      </template>      <template #item.harvest_id="{ item }">
        <HarvestIdCell :item="item" @download="ccRawJson" @retry="openUrl" />
      </template>

      <template #item.error="{ item }">
        <HarvestResultCell :item="item" @viewDetails="enableErrorDialog" />
      </template>

      <template #item.result="{ item }">
        <HarvestResultCell :item="item" @viewDetails="enableErrorDialog" />
      </template>


      <!-- Dynamic Date Interceptor Loop -->
      <template v-for="header in dateHeaders" :key="header.key" #[`item.${header.key}`]="{ value }">
        <span v-if="value" :title="formatDateTimeTooltip(value)">{{ formatDateDisplay(value) }}</span>
      </template>

      <!-- Dynamic Truncated Interceptor Loop -->
      <template v-for="header in truncatedHeaders" :key="header.key" #[`item.${header.key}`]="{ value }">
        <span
          v-if="value === '-required-'"
          class="d-inline-flex align-center text-warning"
          role="img"
          :aria-label="$t('fields.required', 'Required')"
          :title="$t('fields.required', 'Required')"
        >
          <i class="mdi mdi-asterisk text-warning" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
        </span>
        <span v-else :title="value">{{ formatTruncatedDisplay(value, header.truncate) }}</span>
      </template>

      <!-- Row Level Operations Action Matrix Column -->
      <template #item.actions="{ item }">
        <TableRowActions
          :item="item"
          :dataset="props.dataset"
          :show-actions="showActions"
          :is-testing="isTesting"
          :testing-row-id="testingRowId"
          @testCredential="handleCredentialTest"
          @openRegistry="openRegistryPlatform"
          @edit="$emit('edit', $event)"
          @delete="enableDeleteDialog"
        />
      </template>
    </v-data-table>

    <v-dialog v-model="deleteDialog" width="auto">
      <DeleteConfirm
        :item="curItem"
        :dataset="props.dataset"
        @confirm="$emit('delete', curItem.id)"
        @close="closeDeleteDialog"
      />
    </v-dialog>

    <v-dialog v-model="errorDialog" width="auto">
      <ErrorDetails :error="current_error" @close="closeErrorDialog" />
    </v-dialog>

    <CredentialTestDialog
      v-model="testDialog"
      :is-testing="isTesting"
      :passed="testPassed"
      :status="testStatus"
      :rows="testRows"
      :target-label="testTargetLabel"
    />
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
</style>
