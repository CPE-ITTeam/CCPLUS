<!-- components/ui/MultiSelectCombobox.vue -->
<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
  label: { type: String, required: true },
  items: { type: Array, required: true },
  dataName: { type: String, required: false },
  itemTitle: { type: String, required: false },
  itemValue: { type: String, required: false },
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

const allSelected = computed(() => {
  return model.value.length > 0 && model.value.length === props.items.length;
});

const selectAllLabel = computed(() => {
  return allSelected.value ? t('tables.clear_all') : t('common.select_all');
});

const selectionString = computed(() => {
  if (allSelected.value) return t('common.all_items', { label: data_label.value });
  if (model.value.length === 0) return '';

  if (
    typeof props.items[0]?.['id'] === 'undefined' &&
    !Array.isArray(model.value) &&
    typeof model.value !== 'object'
  ) {
    return t('common.some_items', { label: data_label.value });
  }

  let _string = model.value[0];
  if (!isPrimitiveItems.value && item_value.value) {
    const _item = props.items.find((ii) => ii[item_value.value] == model.value[0]);
    if (_item && item_title.value) _string = _item[item_title.value];
  }


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
  <v-combobox
    v-model="model"
    :items="items"
    multiple
    :return-object="false"
    :placeholder="$t('common.select_item', { label: data_label })"
    :item-title="item_title"
    :item-value="item_value"
    class="flex-grow-0"
  >    :placeholder="$t('common.select_item', { label: data_label })" :item-title="item_title" :item-value="item_value">
    <template #label>
      {{ data_label }}
    </template>

    <template #prepend-item>
      <v-list-item @click.stop="toggleSelectAll">{{ selectAllLabel }}</v-list-item>
      <v-divider class="mt-1" />
    </template>

    <template #selection="{ index }">
      <span v-if="index === 0">{{ selectionString }}</span>
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
  </v-combobox>
</template>
