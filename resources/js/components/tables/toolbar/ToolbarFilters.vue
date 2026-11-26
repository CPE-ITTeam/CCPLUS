<!-- components/tables/toolbar/ToolbarFilters.vue -->
<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDataTableHelpers } from '@/composables/table/useDataTableHelpers.js';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import YmRangeSelector from '@/components/ui/YmRangeSelector.vue';
import InstitutionScopeSwitch from '@/components/ui/InstitutionScopeSwitch.vue';
import PlatformScopeSwitch from '@/components/ui/PlatformScopeSwitch.vue';

const { t } = useI18n();
const { formatTruncatedDisplay } = useDataTableHelpers();

const props = defineProps({
  filters: { type: Object, required: true },
});

const emit = defineEmits(['setFilter']);

const AUDIT_JSON_KEYS = ['no_inst', 'no_plat', 'no_host'];
const SWITCH_ONLY_KEYS = new Set([
  'scope_mode',
  'is_conso',
  'inst_active_only',
  'status_switch',
  'valid_types',
  'valid_target',
  'releases',
  ...AUDIT_JSON_KEYS,
]);
const PLATFORM_ENTRY_KEYS = new Set(['platforms', 'platform', 'plat_id']);
const INST_GROUP_ENTRY_KEYS = new Set([
  'institutions',
  'institution',
  'inst_id',
  'groups',
  'group',
  'group_id',
]);

const scopeModeFilter = computed(() =>
  props.filters?.scope_mode?.show ? props.filters.scope_mode : null
);
const consoFilter = computed(() => props.filters?.is_conso || null);
const instActiveFilter = computed(() => props.filters?.inst_active_only || null);

const auditJsonSwitches = computed(() =>
  AUDIT_JSON_KEYS.map((k) => props.filters?.[k]).filter((f) => f?.show)
);

const hasCol2Switches = computed(() =>
  Boolean((props.filters?.status_switch && !isCredentialsStatus.value) || props.filters?.releases)
);



const isPlatformEntry = (name) => PLATFORM_ENTRY_KEYS.has(name);
const isInstOrGroupEntry = (name) => INST_GROUP_ENTRY_KEYS.has(name);

const SCOPE_KEYS = new Set([
  'consortia',
  'conso_id',
  'platforms',
  'plat_id',
  'institutions',
  'institution',
  'inst_id',
  'institution_groups',
  'groups',
  'group',
  'group_id',
  'users',
  'user',
  'user_id',
  'types',
  'type_id',
]);

const PERIOD_KEYS = new Set([
  'reports',
  'master_reports',
  'report_id',
  'ymrange',
  'yearmon',
  'yearmonths',
  'status',
  'statuses',
  'valid_types',
]);

const filterColumns = computed(() => {
  const scope = [];
  const period = [];
  const other = [];

  Object.entries(props.filters || {}).forEach(([name, filter]) => {
    if (!filter?.show || SWITCH_ONLY_KEYS.has(name)) return;
    const entry = { name, filter };
    if (SCOPE_KEYS.has(name) || SCOPE_KEYS.has(filter.key)) {
      scope.push(entry);
    } else if (PERIOD_KEYS.has(name) || PERIOD_KEYS.has(filter.key) || filter.type === 'ymrange') {
      period.push(entry);
    } else {
      other.push(entry);
    }
  });

  const hasPeriodCol = period.length > 0 || (hasCol2Switches.value && (scope.length > 0 || other.length > 0));

  if (hasPeriodCol && other.length > 0) {
    return [
      { key: 'scope', items: scope },
      { key: 'period', items: period },
      { key: 'other', items: other },
    ];
  }
  if (hasPeriodCol) {
    return [
      { key: 'scope', items: scope },
      { key: 'period', items: period },
    ];
  }
  if (other.length > 0) {
    return [
      { key: 'scope', items: scope },
      { key: 'other', items: other },
    ];
  }
  if (scope.length > 0) {
    return [{ key: 'scope', items: scope }];
  }
  return hasCol2Switches.value ? [{ key: 'period', items: [] }] : [];
});

const isCredentialsStatus = computed(() =>
  Boolean(
    props.filters?.status_switch &&
    (Array.isArray(props.filters.status_switch.value) ||
      ['Enabled', 'Incomplete', 'Suspended', 'Disabled'].includes(props.filters.status_switch.value) ||
      (props.filters?.statuses !== undefined && !props.filters?.releases))
  )
);


const statusSwitchState = computed(() => {
  const val = props.filters?.status_switch?.value || 'All';
  if (val === 'Enabled') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'success',
      label: t('fields.enabled_credentials_only', 'Enabled credentials only'),
    };
  }
  if (val === 'Incomplete') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'warning',
      label: t('fields.incomplete_credentials_only', 'Incomplete credentials only'),
    };
  }
  if (val === 'Active') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'success',
      label: t('fields.active_only', 'Active Only'),
    };
  }
  return {
    modelValue: false,
    indeterminate: isCredentialsStatus.value,
    color: 'secondary',
    label: t('fields.all_statuses', 'All Credentials Statuses'),
  };
});

function cycleStatusSwitch() {
  const current = props.filters?.status_switch?.value || 'All';
  let next;
  if (isCredentialsStatus.value) {
    next = current === 'Enabled' ? 'Incomplete' : current === 'Incomplete' ? 'All' : 'Enabled';
    if (next !== 'All' && props.filters?.statuses?.value?.length > 0) {
      handleFilter('statuses', []);
    }
  } else {
    next = current === 'Active' ? 'All' : 'Active';
  }
  handleFilter('status_switch', next);
}

function formatJsonToggleLabel(sw) {
  const isHiding = sw.value !== 'Active' && sw.value !== true;
  const clean = String(sw.label || '').replace(/^(Show|Hide)\s+/i, '');
  return `${isHiding ? 'Hide' : 'Show'} ${clean}`;
}

const validStatusSwitchState = computed(() => {
  const val = props.filters?.valid_types?.value || 'All';
  if (val === 'Validated') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'success',
      label: t('fields.credentials_validated', 'Credentials Validated'),
    };
  }
  if (val === 'Not Validated') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'warning',
      label: t('fields.credentials_not_validated', 'Credentials Not Validated'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'secondary',
    label: t('fields.all_credentials', 'All Credentials'),
  };
});

function cycleValidStatusSwitch() {
  const current = props.filters?.valid_types?.value || 'All';
  const next = current === 'All' ? 'Validated' : current === 'Validated' ? 'Not Validated' : 'All';
  handleFilter('valid_types', next);
  if (next !== 'Not Validated' && props.filters?.valid_target) {
    handleFilter('valid_target', 'All');
  }
}

const validTargetSwitchState = computed(() => {
  const val = props.filters?.valid_target?.value || 'All';
  if (val === 'Platform') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'secondary',
      label: t('fields.platform_not_validated', 'Platform Not Validated'),
    };
  }
  if (val === 'Institution') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'secondary',
      label: t('fields.institution_not_validated', 'Institution Not Validated'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'secondary',
    label: t('fields.all_unvalidated', 'All Unvalidated'),
  };
});

function cycleValidTargetSwitch() {
  const current = props.filters?.valid_target?.value || 'All';
  const next = current === 'All' ? 'Platform' : current === 'Platform' ? 'Institution' : 'All';
  handleFilter('valid_target', next);
}

const releaseSwitchState = computed(() => {
  const val = props.filters?.releases?.value || 'All';
  if (val === '5.1') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'primary',
      label: t('fields.release_5_1', '5.1'),
    };
  }
  if (val === '5') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'info',
      label: t('fields.release_5', '5'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'success',
    label: t('fields.all_releases', 'All Releases'),
  };
});

function cycleReleaseSwitch() {
  const current = props.filters?.releases?.value || 'All';
  const next = current === 'All' ? '5.1' : current === '5.1' ? '5' : 'All';
  handleFilter('releases', next);
}

function formatToggleLabel(entry) {
  const isOn =
    props.filters[entry.name]?.value === 'Active' ||
    props.filters[entry.name]?.value === true;
  const raw = String(entry.filter.label || '');

  if (isOn) {
    return raw.replace(/^Hide\b/i, 'Show');
  }
  return raw.replace(/^Show\b/i, 'Hide');
}

function handleFilter(keyName, newValue) {
  emit('setFilter', { key: keyName, value: newValue });
}
</script>

<!-- components/tables/toolbar/ToolbarFilters.vue -->
<template>
  <v-row v-if="filterColumns.length > 0" align="start" class="w-100 pt-2 mt-0">
    <v-col v-for="col in filterColumns" :key="col.key" cols="12" md="4" class="d-flex flex-column ga-3 pt-1">
      <!-- Column 2 Switches (Status / Releases) -->
      <div v-if="hasCol2Switches && (col.key === 'period' || filterColumns.length === 1)"
        class="d-flex flex-column align-start ga-2 w-100 col-switch-stack">
        <!-- Primary Status / Release Switches -->
        <div v-if="(props.filters.status_switch && !isCredentialsStatus) || props.filters.releases"
          class="d-flex flex-column align-start ga-1 w-100">
          <div class="d-flex flex-wrap align-center ga-x-4 ga-y-1">
            <v-switch v-if="props.filters.status_switch && !isCredentialsStatus" :model-value="statusSwitchState.modelValue"
              :indeterminate="statusSwitchState.indeterminate" :color="statusSwitchState.color"
              :base-color="statusSwitchState.color" :label="statusSwitchState.label" hide-details
              @click.prevent="cycleStatusSwitch" />

            <v-switch v-if="props.filters.releases" :model-value="releaseSwitchState.modelValue"
              :indeterminate="releaseSwitchState.indeterminate" :color="releaseSwitchState.color"
              :base-color="releaseSwitchState.color" :label="releaseSwitchState.label" hide-details
              @click.prevent="cycleReleaseSwitch" />
          </div>
        </div>
      </div>

      <!-- Column Filter Inputs -->
      <template v-for="entry in col.items" :key="entry.name">
        <!-- Grouped Platform Filter + PlatformScopeSwitch Card -->
        <v-sheet v-if="isPlatformEntry(entry.name) && consoFilter" rounded="lg" border
          class="pa-2 d-flex flex-column ga-2 filter-group-card filter-group-card--success">
          <PlatformScopeSwitch :model-value="consoFilter.value || 'All'"
            :use-connected="Boolean(consoFilter.useConnected)" @update:modelValue="handleFilter('is_conso', $event)" />
          <MultiSelectCombobox v-if="entry.filter.type == 'mselect'" v-model="props.filters[entry.name].value"
            :label="entry.filter.label" multiple :items="entry.filter.items" :item-title="entry.filter.txt"
            :item-value="entry.filter.val" :truncate="entry.filter.truncate"
            @update:modelValue="handleFilter(entry.name, $event)" />
        </v-sheet>

        <!-- Grouped Institution / Group Filter + InstitutionScopeSwitch Card -->
        <v-sheet v-else-if="isInstOrGroupEntry(entry.name) && (scopeModeFilter || instActiveFilter)" rounded="lg" border
          class="pa-2 d-flex flex-column ga-2 filter-group-card filter-group-card--primary">
          <div class="d-flex flex-wrap align-center ga-3">
            <InstitutionScopeSwitch v-if="scopeModeFilter" :model-value="Boolean(scopeModeFilter.value)"
              @update:modelValue="handleFilter('scope_mode', $event)" />
            <v-switch v-if="instActiveFilter && !Boolean(scopeModeFilter?.value)"
              :model-value="instActiveFilter.value === 'Active'" color="success"
              :label="$t('fields.active_institutions_only', 'Active institutions only')" hide-details
              @click.prevent="handleFilter('inst_active_only', instActiveFilter.value === 'Active' ? 'All' : 'Active')" />
          </div>
          <MultiSelectCombobox v-if="entry.filter.type == 'mselect'" v-model="props.filters[entry.name].value"
            :label="entry.filter.label" multiple :items="entry.filter.items" :item-title="entry.filter.txt"
            :item-value="entry.filter.val" :truncate="entry.filter.truncate" :group-by="entry.filter.groupBy || null"
            @update:modelValue="handleFilter(entry.name, $event)" />
        </v-sheet>

        <!-- Year-Month Range Filter -->
        <YmRangeSelector v-else-if="entry.filter.type == 'ymrange'" v-model="props.filters[entry.name].value"
          :label="entry.filter.label" @update:modelValue="handleFilter(entry.name, $event)" />

        <!-- Toggle filter case -->
        <div v-else-if="entry.filter.type == 'toggle'" class="d-flex align-center pl-1 flex-grow-0">
          <v-switch
            :model-value="props.filters[entry.name].value === 'Active' || props.filters[entry.name].value === true"
            :label="formatToggleLabel(entry)" color="warning" base-color="success"
            @update:modelValue="handleFilter(entry.name, $event ? 'Active' : 'Inactive')" />
        </div>

        <!-- Multi-select combobox case -->
        <MultiSelectCombobox v-else-if="entry.filter.type == 'mselect'" v-model="props.filters[entry.name].value"
          :label="entry.filter.label" multiple :items="entry.filter.items" :item-title="entry.filter.txt"
          :item-value="entry.filter.val" :truncate="entry.filter.truncate" :group-by="entry.filter.groupBy || null"
          @update:modelValue="handleFilter(entry.name, $event)" />


        <!-- Standard Autocomplete case -->
        <v-autocomplete v-else-if="entry.filter.type == 'select'" v-model="props.filters[entry.name].value"
          :label="entry.filter.label" :items="entry.filter.items" :item-title="entry.filter.txt || 'name'"
          :item-value="entry.filter.val || 'id'" @update:modelValue="handleFilter(entry.name, $event)">
          <template #item="{ props: itemProps }">
            <v-list-item v-bind="itemProps" title="">
              <template #prepend="{ isSelected }">
                <v-icon size="small" :color="isSelected ? 'primary' : 'medium-emphasis'">
                  {{ isSelected ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank' }}
                </v-icon>
              </template>
              <v-list-item-title class="text-truncate" :title="itemProps.title">
                {{ entry.filter.truncate ? formatTruncatedDisplay(itemProps.title, entry.filter.truncate) :
                  itemProps.title }}
              </v-list-item-title>
            </v-list-item>
          </template>
          <template #selection="{ item }">
            <span :title="item.title" class="text-truncate">
              {{ entry.filter.truncate ? formatTruncatedDisplay(item.title, entry.filter.truncate) : item.title }}
            </span>
          </template>
        </v-autocomplete>

        <!-- Multi-text select case -->
        <MultiSelectCombobox v-else-if="entry.filter.type == 'mtext'" v-model="props.filters[entry.name].value"
          :label="entry.filter.label" :items="entry.filter.items" :truncate="entry.filter.truncate" multiple
          @update:modelValue="handleFilter(entry.name, $event)" />
        <!-- Standard Dropdown select case -->
        <v-select v-else-if="entry.filter.type == 'text'" v-model="props.filters[entry.name].value"
          :label="entry.filter.label" :items="entry.filter.items" @update:modelValue="handleFilter(entry.name, $event)">
          <template #item="{ props: itemProps }">
            <v-list-item v-bind="itemProps" title="">
              <template #prepend="{ isSelected }">
                <v-icon size="small" :color="isSelected ? 'primary' : 'medium-emphasis'">
                  {{ isSelected ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank' }}
                </v-icon>
              </template>
              <v-list-item-title class="text-truncate" :title="itemProps.title">
                {{ entry.filter.truncate ? formatTruncatedDisplay(itemProps.title, entry.filter.truncate) :
                  itemProps.title }}
              </v-list-item-title>
            </v-list-item>
          </template>
          <template #selection="{ item }">
            <span :title="item.title" class="text-truncate">
              {{ entry.filter.truncate ? formatTruncatedDisplay(item.title, entry.filter.truncate) : item.title }}
            </span>
          </template>
        </v-select>
      </template>
    </v-col>
  </v-row>
</template>

<style scoped>
.filter-group-card--success {
  background-color: rgba(var(--v-theme-success), 0.06) !important;
  border-color: rgba(var(--v-theme-secondary), 0.25) !important;
}

.filter-group-card--primary {
  background-color: rgba(var(--v-theme-primary), 0.06) !important;
  border-color: rgba(var(--v-theme-secondary), 0.25) !important;
}

.filter-group-card :deep(.v-selection-control) {
  min-height: 28px;
}
</style>
