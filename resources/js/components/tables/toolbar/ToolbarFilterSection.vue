<!-- components/tables/toolbar/ToolbarFilterSection.vue -->
<template>
  <div v-if="filterOptions && filterOptions.length > 0" class="w-100">
    <!-- Row 1: FILTER Heading + Clear All / Refresh Actions -->
    <div class="d-flex flex-wrap align-center ga-3 mb-1">
      <span class="text-caption text-uppercase font-weight-bold tracking-wider text-medium-emphasis">
        {{ $t('tables.filter', visibleFilterCount) }}
      </span>

      <v-chip v-if="anyFilterSet" size="small" color="error" variant="tonal" prepend-icon="mdi-close-circle-outline"
        class="cursor-pointer font-weight-medium" @click="$emit('clearAll')">
        {{ $t('tables.clear_all') }}
      </v-chip>

      <v-btn v-if="showRefreshButton" size="small" variant="tonal" prepend-icon="mdi-refresh" class="ms-2"
        @click="$emit('refreshRecords')">
        {{ $t('tables.refresh_records') }}
      </v-btn>
    </div>

    <!-- Row 2: Thin Switch Bar Aligned Over Active Filter Columns -->
    <v-row v-if="hasAnySwitch" dense align="start" class="w-100 my-0 switch-bar">
      <!-- Column 1 Switch(es): Scope (Consortial / Active Institutions / Validation / Single-Col Status) -->
      <v-col cols="12" md="4" class="py-0 d-flex flex-column align-start ga-0">
        <div class="d-flex flex-wrap align-center switch-group">
          <v-switch v-if="flatFilterOptions.is_conso" :model-value="consoSwitchState.modelValue"
            :indeterminate="consoSwitchState.indeterminate" :color="consoSwitchState.color"
            :label="consoSwitchState.label" true-icon="mdi-star-circle" false-icon="mdi-home-circle"
            @click.prevent="cycleConsoSwitch" />

          <v-switch v-if="flatFilterOptions.inst_active_only"
            :model-value="flatFilterOptions.inst_active_only.value === 'Active'" color="success"
            :label="$t('fields.active_institutions_only', 'Active institutions only')"
            @click.prevent="toggleInstActiveSwitch" />

          <v-switch v-if="flatFilterOptions.status_switch && statusSwitchCol === 1"
            :model-value="statusSwitchState.modelValue" :indeterminate="statusSwitchState.indeterminate"
            :color="statusSwitchState.color" :base-color="statusSwitchState.color" :label="statusSwitchState.label"
            @click.prevent="cycleStatusSwitch" />
        </div>

        <div v-if="flatFilterOptions.valid_types" class="d-flex flex-wrap align-center switch-group">
          <v-switch :model-value="validStatusSwitchState.modelValue"
            :indeterminate="validStatusSwitchState.indeterminate" :color="validStatusSwitchState.color"
            :base-color="validStatusSwitchState.color" :label="validStatusSwitchState.label"
            @click.prevent="cycleValidStatusSwitch" />

          <v-switch v-if="flatFilterOptions.valid_types.value === 'Not Validated' && flatFilterOptions.valid_target"
            :model-value="validTargetSwitchState.modelValue" :indeterminate="validTargetSwitchState.indeterminate"
            :color="validTargetSwitchState.color" :label="validTargetSwitchState.label"
            @click.prevent="cycleValidTargetSwitch" />
        </div>
      </v-col>

      <!-- Column 2 Switch(es): Status / Credentials OR Releases OR Audit JSON Switches -->
      <v-col v-if="statusSwitchCol >= 2 || auditJsonSwitches.length > 0 || flatFilterOptions.releases" cols="12" md="4"
        class="py-0 d-flex flex-column align-start ga-0">
        <div v-if="(flatFilterOptions.status_switch && statusSwitchCol >= 2) || flatFilterOptions.releases"
          class="d-flex flex-wrap align-center switch-group">
          <v-switch v-if="flatFilterOptions.status_switch && statusSwitchCol >= 2"
            :model-value="statusSwitchState.modelValue" :indeterminate="statusSwitchState.indeterminate"
            :color="statusSwitchState.color" :base-color="statusSwitchState.color" :label="statusSwitchState.label"
            @click.prevent="cycleStatusSwitch" />

          <v-switch v-if="flatFilterOptions.releases" :model-value="releaseSwitchState.modelValue"
            :indeterminate="releaseSwitchState.indeterminate" :color="releaseSwitchState.color"
            :base-color="releaseSwitchState.color" :label="releaseSwitchState.label"
            @click.prevent="cycleReleaseSwitch" />
        </div>

        <div v-if="auditJsonSwitches.length > 0" class="d-flex flex-wrap align-center switch-group">
          <v-switch v-for="sw in auditJsonSwitches" :key="sw.name"
            :model-value="sw.value !== 'Active' && sw.value !== true" :label="formatJsonToggleLabel(sw)" color="success"
            base-color="warning"
            @update:modelValue="emit('setFilter', { key: sw.name, value: $event ? 'Inactive' : 'Active' })" />
        </div>
      </v-col>
    </v-row>


    <!-- Row 3: 3-Column Filter Inputs -->
    <ToolbarFilters :filters="mergedVisibleFilters" @setFilter="$emit('setFilter', $event)" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ToolbarFilters from '@/components/tables/toolbar/ToolbarFilters.vue';

const props = defineProps({
  filterOptions: { type: Array, required: true },
  isHarvestDataset: { type: Boolean, default: false },
  anyFilterSet: { type: Boolean, default: false },
  showRefreshButton: { type: Boolean, default: false },
});

const emit = defineEmits(['setFilter', 'clearAll', 'refreshRecords']);

const { t } = useI18n();

const AUDIT_JSON_KEYS = ['no_inst', 'no_plat', 'no_host'];

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
]);

const flatFilterOptions = computed(() => {
  const options = {};
  props.filterOptions.forEach((row) => {
    for (const key of Object.keys(row)) {
      options[key] = row[key];
    }
  });
  return options;
});

const auditJsonSwitches = computed(() =>
  AUDIT_JSON_KEYS.map((k) => flatFilterOptions.value[k]).filter((f) => f?.show)
);

const hasAnySwitch = computed(() =>
  Boolean(
    flatFilterOptions.value.is_conso ||
    flatFilterOptions.value.inst_active_only ||
    flatFilterOptions.value.status_switch ||
    flatFilterOptions.value.valid_types ||
    flatFilterOptions.value.valid_target ||
    flatFilterOptions.value.releases ||
    auditJsonSwitches.value.length > 0
  )
);

const mergedVisibleFilters = computed(() => {
  const merged = {};
  const switchVal = flatFilterOptions.value.status_switch?.value;
  props.filterOptions.forEach((row) => {
    for (const [key, val] of Object.entries(row)) {
      if (
        key === 'is_conso' ||
        key === 'inst_active_only' ||
        key === 'status_switch' ||
        key === 'valid_types' ||
        key === 'valid_target' ||
        key === 'releases' ||
        AUDIT_JSON_KEYS.includes(key)
      ) continue;
      if (props.isHarvestDataset && key === 'statuses') continue;
      if (key === 'statuses' && flatFilterOptions.value.status_switch) {
        if (!isCredentialsStatus.value || switchVal !== 'All') continue;
      }
      merged[key] = val;
    }
  });
  return merged;
});


const visibleFilterCount = computed(() =>
  Object.values(mergedVisibleFilters.value).filter((f) => f?.show).length
);

const statusSwitchCol = computed(() => {
  const visible = Object.entries(mergedVisibleFilters.value).filter(([, f]) => f?.show);
  const hasScope = visible.some(([k, f]) => SCOPE_KEYS.has(k) || SCOPE_KEYS.has(f.key));
  const hasPeriod = visible.some(
    ([k, f]) => PERIOD_KEYS.has(k) || PERIOD_KEYS.has(f.key) || f.type === 'ymrange'
  );
  const hasOther = visible.some(
    ([k, f]) =>
      !SCOPE_KEYS.has(k) &&
      !SCOPE_KEYS.has(f.key) &&
      !PERIOD_KEYS.has(k) &&
      !PERIOD_KEYS.has(f.key) &&
      f.type !== 'ymrange'
  );

  const colCount = (hasScope ? 1 : 0) + (hasPeriod ? 1 : 0) + (hasOther ? 1 : 0);
  if (colCount <= 1) return 1;
  if (colCount === 2) return 2;
  return 3;
});

const isCredentialsStatus = computed(
  () =>
    flatFilterOptions.value.status_switch?.value === 'Enabled' ||
    flatFilterOptions.value.status_switch?.value === 'Incomplete' ||
    (flatFilterOptions.value.status_switch &&
      flatFilterOptions.value.statuses !== undefined &&
      !flatFilterOptions.value.releases)
);

const statusSwitchState = computed(() => {
  const val = flatFilterOptions.value.status_switch?.value || 'All';
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
  const current = flatFilterOptions.value.status_switch?.value || 'All';
  let next;
  if (isCredentialsStatus.value) {
    next = current === 'Enabled' ? 'Incomplete' : current === 'Incomplete' ? 'All' : 'Enabled';
    if (next !== 'All' && flatFilterOptions.value.statuses?.value?.length > 0) {
      emit('setFilter', { key: 'statuses', value: [] });
    }
  } else {
    next = current === 'Active' ? 'All' : 'Active';
  }
  emit('setFilter', { key: 'status_switch', value: next });
}

function toggleInstActiveSwitch() {
  const current = flatFilterOptions.value.inst_active_only?.value || 'Active';
  const next = current === 'Active' ? 'All' : 'Active';
  emit('setFilter', { key: 'inst_active_only', value: next });
}

function formatJsonToggleLabel(sw) {
  const isHiding = sw.value !== 'Active' && sw.value !== true;
  const clean = String(sw.label || '').replace(/^(Show|Hide)\s+/i, '');
  return `${isHiding ? 'Hide' : 'Show'} ${clean}`;
}
const consoSwitchState = computed(() => {
  const sw = flatFilterOptions.value.is_conso;
  const val = sw?.value || 'All';
  const isConnectedMode = Boolean(sw?.useConnected);

  if (val === 'Active') {
    return {
      modelValue: true,
      indeterminate: false,
      color: 'primary',
      label: isConnectedMode
        ? t('fields.connected_only', 'Connected')
        : t('fields.limit_to_consortial', 'Limit to Consortial'),
    };
  }
  if (val === 'NonConso') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'secondary',
      label: isConnectedMode
        ? t('fields.not_connected', 'Not Connected')
        : t('fields.exclude_consortial', 'Exclude Consortial'),
    };
  }
  return {
    modelValue: false,
    indeterminate: true,
    color: 'success',
    label: t('fields.all_platforms', 'All Platforms'),
  };
});

function cycleConsoSwitch() {
  const current = flatFilterOptions.value.is_conso?.value || 'All';
  const next = current === 'All' ? 'Active' : current === 'Active' ? 'NonConso' : 'All';
  emit('setFilter', { key: 'is_conso', value: next });
}

const validStatusSwitchState = computed(() => {
  const val = flatFilterOptions.value.valid_types?.value || 'All';
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
  const current = flatFilterOptions.value.valid_types?.value || 'All';
  const next = current === 'All' ? 'Validated' : current === 'Validated' ? 'Not Validated' : 'All';
  emit('setFilter', { key: 'valid_types', value: next });
  if (next !== 'Not Validated' && flatFilterOptions.value.valid_target) {
    emit('setFilter', { key: 'valid_target', value: 'All' });
  }
}

const validTargetSwitchState = computed(() => {
  const val = flatFilterOptions.value.valid_target?.value || 'All';
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
  const current = flatFilterOptions.value.valid_target?.value || 'All';
  const next = current === 'All' ? 'Platform' : current === 'Platform' ? 'Institution' : 'All';
  emit('setFilter', { key: 'valid_target', value: next });
}

const releaseSwitchState = computed(() => {
  const val = flatFilterOptions.value.releases?.value || 'All';
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
  const current = flatFilterOptions.value.releases?.value || 'All';
  const next = current === 'All' ? '5.1' : current === '5.1' ? '5' : 'All';
  emit('setFilter', { key: 'releases', value: next });
}
</script>

<style scoped>
.switch-group {
  column-gap: 12px;
  row-gap: 0;
}
.switch-bar :deep(.v-selection-control) {
  min-height: 24px;
}
</style>
