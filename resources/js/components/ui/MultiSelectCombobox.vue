<!-- components/ui/MultiSelectCombobox.vue -->
<script setup>
import { computed, watch } from 'vue';
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
  shouldShowNoneOfTheAboveType: { type: Boolean, required: false, default: false },
  shouldShowNoneOfTheAboveGroup: { type: Boolean, required: false, default: false },
});

defineEmits(['update:selected']);

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

const allItemsText = computed(() => t('common.all_items', { label: data_label.value }));

const allSelected = computed(() => {
  return props.items.length > 0 && model.value.length === props.items.length;
});

const selectAllLabel = computed(() => {
  return allSelected.value ? t('tables.clear_all') : t('common.select_all');
});
const labelCache = new Map();

watch(
  () => props.items,
  (list) => {
    if (!Array.isArray(list) || isPrimitiveItems.value || !item_value.value || !item_title.value) return;
    list.forEach((entry) => {
      if (entry && typeof entry === 'object' && entry[item_value.value] !== undefined) {
        labelCache.set(String(entry[item_value.value]), String(entry[item_title.value]));
      }
    });
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
    model.value = [];
  } else if (!isPrimitiveItems.value && item_value.value && typeof props.items[0]?.[item_value.value] !== 'undefined') {
    model.value = props.items.map((item) => item[item_value.value]);
  } else {
    model.value = [...props.items];
  }
}
</script>

<template>
  <v-autocomplete
    v-model="model"
    :items="items"
    multiple
    clearable
    :return-object="false"
    :placeholder="allItemsText"
    persistent-placeholder
    :item-title="item_title"
    :item-value="item_value"
    :color="model.length > 0 ? 'primary' : undefined"
    :menu-props="{ maxWidth: 360 }"
    bg-color="surface"
    class="w-100 multi-select-box"
    @click:clear="model = []"
  >
    <template #label>
      {{ data_label }}
    </template>

    <template #prepend-item>
      <v-list-item @click.stop="toggleSelectAll">
        <template #prepend>
          <v-checkbox-btn
            :model-value="allSelected"
            :indeterminate="model.length > 0 && !allSelected"
            color="primary"
            class="me-3"
            tabindex="-1"
          />
        </template>
        <v-list-item-title class="font-weight-medium">
          {{ selectAllLabel }}
        </v-list-item-title>
      </v-list-item>
      <v-divider class="mt-1" />
    </template>

    <template #item="{ props: itemProps }">
      <v-list-item v-bind="itemProps" title="">
        <template #prepend="{ isSelected }">
          <v-checkbox-btn :model-value="isSelected" color="primary" class="me-3" tabindex="-1" />
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
        <v-list-item
          :title="$t('common.none_of_the_above')"
          value="No type assigned"
          @click="$emit('update:selected', ['No type assigned'])"
        />
      </template>
      <template v-else-if="shouldShowNoneOfTheAboveGroup">
        <v-divider class="my-0 py-0" />
        <v-list-item
          :title="$t('common.none_of_the_above')"
          value="No groups assigned"
          @click="$emit('update:selected', ['No groups assigned'])"
        />
      </template>
    </template>
  </v-autocomplete>
</template>

<style scoped>
.multi-select-box :deep(.v-field__input) {
  flex-wrap: nowrap;
  overflow: hidden;
}

.multi-select-box :deep(input::placeholder) {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  opacity: 1 !important;
}
</style>
