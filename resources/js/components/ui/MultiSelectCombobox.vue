<!-- components/ui/MultiSelectCombobox.vue -->
<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDataTableHelpers } from '@/composables/table/useDataTableHelpers.js';

const { t } = useI18n();
const { formatTruncatedDisplay } = useDataTableHelpers();
const model = defineModel({
  type: Array,
  default: () => [],
  set(val) {
    return Array.isArray(val) ? val : [];
  },
});

const props = defineProps({
  label: { type: String, required: true },
  items: { type: Array, required: true },
  dataName: { type: String, required: false },
  itemTitle: { type: String, required: false },
  itemValue: { type: String, required: false },
  truncate: { type: Number, required: false, default: null },
  defaultAll: { type: Boolean, required: false, default: false },
  groupBy: { type: String, required: false, default: null },
  unrestrictedLabel: { type: String, required: false, default: 'Unrestricted' },
  shouldShowNoneOfTheAboveType: { type: Boolean, required: false, default: false },
  shouldShowNoneOfTheAboveGroup: { type: Boolean, required: false, default: false },
});

defineEmits(['update:selected']);

const userCleared = ref(false);

const isPrimitiveItems = computed(() => {
  return props.items.length > 0 && typeof props.items[0] !== 'object';
});

const item_title = computed(() => {
  if (props.itemTitle) return props.itemTitle;
  return isPrimitiveItems.value ? undefined : 'label';
});

const item_value = computed(() => {
  if (props.itemValue) return props.itemValue;
  return isPrimitiveItems.value ? undefined : 'value';
});

const data_label = computed(() => props.dataName ?? props.label);

const effectiveGroupBy = computed(() => {
  if (props.groupBy) return props.groupBy;
  if (
    !isPrimitiveItems.value &&
    Array.isArray(props.items) &&
    props.items.some((i) => i && typeof i === 'object' && 'type_name' in i)
  ) {
    return 'type_name';
  }
  return null;
});

// Sort items by effectiveGroupBy (alphabetical, empty/null last) then by title
const sortedItems = computed(() => {
  if (!effectiveGroupBy.value || isPrimitiveItems.value) return props.items;
  const gKey = effectiveGroupBy.value;
  const tKey = item_title.value || 'name';
  return [...props.items].sort((a, b) => {
    const ga = String(a?.[gKey] ?? '').trim();
    const gb = String(b?.[gKey] ?? '').trim();
    if ((ga === '') !== (gb === '')) {
      return ga === '' ? 1 : -1;
    }
    const cmpGroup = ga.localeCompare(gb, undefined, { sensitivity: 'base' });
    if (cmpGroup !== 0) return cmpGroup;
    return String(a?.[tKey] ?? '').localeCompare(String(b?.[tKey] ?? ''), undefined, { sensitivity: 'base' });
  });
});

// Map each item's value -> group header label (only for the first item in each group)
const groupHeaderBeforeValue = computed(() => {
  const map = new Map();
  if (!effectiveGroupBy.value || isPrimitiveItems.value || !item_value.value) return map;
  let lastGroup = null;
  sortedItems.value.forEach((item) => {
    const rawGroup = String(item?.[effectiveGroupBy.value] ?? '').trim();
    const label = rawGroup || props.unrestrictedLabel;
    if (label !== lastGroup) {
      map.set(String(item[item_value.value]), label);
      lastGroup = label;
    }
  });
  return map;
});

// Map group header label -> array of item values in that group (for per-group select-all)
const groupValuesMap = computed(() => {
  const map = new Map();
  if (!effectiveGroupBy.value || isPrimitiveItems.value || !item_value.value) return map;
  sortedItems.value.forEach((item) => {
    const rawGroup = String(item?.[effectiveGroupBy.value] ?? '').trim();
    const label = rawGroup || props.unrestrictedLabel;
    if (!map.has(label)) map.set(label, []);
    map.get(label).push(item[item_value.value]);
  });
  return map;
});

function isGroupAllSelected(groupLabel) {
  const vals = groupValuesMap.value.get(groupLabel) || [];
  if (vals.length === 0) return false;
  const selectedSet = new Set(model.value.map(String));
  return vals.every((v) => selectedSet.has(String(v)));
}

function isGroupIndeterminate(groupLabel) {
  const vals = groupValuesMap.value.get(groupLabel) || [];
  if (vals.length === 0) return false;
  const selectedSet = new Set(model.value.map(String));
  const count = vals.filter((v) => selectedSet.has(String(v))).length;
  return count > 0 && count < vals.length;
}

function toggleGroup(groupLabel) {
  const vals = groupValuesMap.value.get(groupLabel) || [];
  const valStrings = new Set(vals.map(String));
  if (isGroupAllSelected(groupLabel)) {
    model.value = model.value.filter((v) => !valStrings.has(String(v)));
  } else {
    const existingSet = new Set(model.value.map(String));
    const toAdd = vals.filter((v) => !existingSet.has(String(v)));
    model.value = [...model.value, ...toAdd];
  }
}

const allItemValues = computed(() => {
  if (!isPrimitiveItems.value && item_value.value && typeof sortedItems.value?.[0]?.[item_value.value] !== 'undefined') {
    return sortedItems.value.map((item) => item[item_value.value]);
  }
  return [...sortedItems.value];
});


const allSelected = computed(() => {
  return sortedItems.value.length > 0 && model.value.length === sortedItems.value.length;
});

const allItemsText = computed(() => t('common.all_items', { label: data_label.value }));

const selectAllLabel = computed(() => {
  return allSelected.value ? t('tables.clear_all') : t('common.select_all');
});

const labelCache = new Map();

watch(
  () => props.items,
  (list) => {
    if (Array.isArray(list) && !isPrimitiveItems.value && item_value.value && item_title.value) {
      list.forEach((entry) => {
        if (entry && typeof entry === 'object' && entry[item_value.value] !== undefined) {
          labelCache.set(String(entry[item_value.value]), String(entry[item_title.value]));
        }
      });
    }
    if (props.defaultAll && !userCleared.value && Array.isArray(list) && list.length > 0 && model.value.length === 0) {
      model.value = [...allItemValues.value];
    }
  },
  { immediate: true }
);

function formatOptionLabel(text) {
  return props.truncate ? formatTruncatedDisplay(text, props.truncate) : text;
}

const selectionString = computed(() => {
  if (allSelected.value) return allItemsText.value;
  if (model.value.length === 0) return '';

  const firstVal = model.value[0];
  let _string = String(firstVal);
  if (!isPrimitiveItems.value && item_value.value && item_title.value) {
    const _item = props.items.find((ii) => String(ii[item_value.value]) === String(firstVal));
    if (_item) {
      _string = _item[item_title.value];
    } else if (labelCache.has(String(firstVal))) {
      _string = labelCache.get(String(firstVal));
    }
  }

  _string = formatOptionLabel(_string);

  if (model.value.length > 1) {
    _string += ` ${t('connections.more_selected', { count: model.value.length - 1 })}`;
  }

  return _string;
});

function toggleSelectAll() {
  if (allSelected.value) {
    userCleared.value = true;
    model.value = [];
  } else {
    userCleared.value = false;
    model.value = [...allItemValues.value];
  }
}

function handleClear() {
  userCleared.value = true;
  model.value = [];
}
</script>



<template>
  <v-autocomplete v-model="model" :items="sortedItems" multiple clearable :return-object="false"
    :placeholder="defaultAll ? $t('common.select_item', { label: data_label }) : allItemsText"
    :persistent-placeholder="!defaultAll" :item-title="item_title" :item-value="item_value"
    :color="model.length > 0 ? 'primary' : undefined" :menu-props="{ maxWidth: 380 }" bg-color="surface"
    class="w-100 multi-select-box" @click:clear="handleClear">
    <template #label>
      {{ data_label }}
    </template>

    <template #prepend-item>
      <v-list-item @click.stop="toggleSelectAll">
        <template #prepend>
          <v-checkbox-btn :model-value="allSelected" :indeterminate="model.length > 0 && !allSelected" color="primary"
            class="me-3" style="pointer-events: none;" tabindex="-1" />
        </template>
        <v-list-item-title class="font-weight-medium">
          {{ selectAllLabel }}
        </v-list-item-title>
      </v-list-item>
      <v-divider class="mt-1" />
    </template>

    <template #item="{ props: itemProps, item }">
      <template v-if="groupHeaderBeforeValue.has(String(itemProps.value ?? item.value))">
        <v-divider v-if="Array.from(groupHeaderBeforeValue.keys())[0] !== String(itemProps.value ?? item.value)"
          class="my-1" />
        <v-list-item density="compact" class="bg-surface-variant/40"
          @click.stop="toggleGroup(groupHeaderBeforeValue.get(String(itemProps.value ?? item.value)))">
          <template #prepend>
            <v-checkbox-btn
              :model-value="isGroupAllSelected(groupHeaderBeforeValue.get(String(itemProps.value ?? item.value)))"
              :indeterminate="isGroupIndeterminate(groupHeaderBeforeValue.get(String(itemProps.value ?? item.value)))"
              color="primary" class="me-3" style="pointer-events: none;" tabindex="-1" />
          </template>
          <v-list-item-title class="text-caption text-uppercase font-weight-bold text-primary">
            {{ groupHeaderBeforeValue.get(String(itemProps.value ?? item.value)) }}
          </v-list-item-title>
        </v-list-item>
      </template>

      <v-list-item v-bind="itemProps" title="" :class="{ 'ps-7': Boolean(effectiveGroupBy) }">
        <template #prepend>
          <v-checkbox-btn :model-value="model.some((v) => String(v) === String(itemProps.value ?? item.value))"
            color="primary" class="me-3" style="pointer-events: none;" tabindex="-1" />
        </template>
        <v-list-item-title class="text-truncate" :title="itemProps.title">
          {{ formatOptionLabel(itemProps.title) }}
        </v-list-item-title>
      </v-list-item>
    </template>

    <template #selection="{ index }">
      <span v-if="index === 0" class="text-truncate d-inline-block" style="max-width: 170px;" :title="selectionString">
        {{ selectionString }}
      </span>
    </template>

    <template #append-item>
      <template v-if="shouldShowNoneOfTheAboveType">
        <v-divider class="my-0 py-0" />
        <v-list-item :title="$t('common.none_of_the_above')" value="No type assigned"
          @click="$emit('update:selected', ['No type assigned'])" />
      </template>
      <template v-else-if="shouldShowNoneOfTheAboveGroup">
        <v-divider class="my-0 py-0" />
        <v-list-item :title="$t('common.none_of_the_above')" value="No groups assigned"
          @click="$emit('update:selected', ['No groups assigned'])" />
      </template>
    </template>
  </v-autocomplete>
</template>

<style scoped>
.multi-select-box :deep(.v-field__input) {
  flex-wrap: nowrap;
  overflow: hidden;
}

.multi-select-box:not(.v-input--dirty) :deep(input::placeholder) {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  opacity: 1 !important;
}
</style>

<style scoped>
.multi-select-box :deep(.v-field__input) {
  flex-wrap: nowrap;
  overflow: hidden;
}

.multi-select-box:not(.v-input--dirty) :deep(input::placeholder) {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  opacity: 1 !important;
}
</style>
