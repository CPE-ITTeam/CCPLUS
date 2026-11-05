<!-- components/ui/MultiSelectCombobox.vue  -->
<script setup>
import { computed } from 'vue';
const model = defineModel({ type: Array, default: () => [] });
const props = defineProps({
  label: { type: String, required: true },
  items: { type: Array, required: true },
  dataName: { type: String, required: false },
  itemTitle: { type: String, required: false },
  itemValue: { type: String, required: false },
  shouldShowNoneOfTheAboveType: { type: Boolean, required: false, default: false },
  shouldShowNoneOfTheAboveGroup: { type: Boolean, required: false, default: false }
});
const item_title = props.itemTitle ?? 'label';
const item_value = props.itemValue ?? 'value';
const data_label = props.dataName ?? props.label;
const allSelected = computed(() => {
  return (model.value.length > 0 && model.value.length === props.items.length);
});
const selectAllLabel = computed(() => { return (allSelected.value) ? "Clear All" : "Select All"; });
const selectionString = computed(() => {
  if (allSelected.value) return "All " + data_label;
  if (model.value.length == 0) return "";
  // Something isn't right... should not happen?
  if (typeof (props.items[0]['id']) == 'undefined' &&
    typeof (model.value) != 'array' && typeof (model.value) != 'object') {
    return 'Some ' + data_label;
  }
  let _string = model.value[0]; // default for when items have no 'id' field.
  // If items have an 'id' field, use model.value to lookup first title based on id
  if (typeof (props.items[0]['id']) != 'undefined') {
    let _item = props.items.find(ii => ii.id == model.value[0]);
    _string = _item[item_title];
  }
  _string += (model.value.length <= 1) ? "" : " +" + (model.value.length - 1).toString() + " more";
  return _string;
});
async function toggleSelectAll() {
  if (allSelected.value) {  // clear all
    model.value = [];
  } else {  // select all
    // If items have an item_value field, use that for the value, otherwise use the items
    if (typeof (props.items[0][item_value]) != 'undefined') {
      model.value = props.items.map(item => item[item_value]);
    } else {
      model.value = [...props.items];
    }
  }
}
</script>
<!-- shared/MultiSelectCombobox.vue -->
<template>
  <!-- 
    1. REMOVED: persistent-placeholder (This is what was forcing the expanded height layout mode).
    2. Swapped :placeholder back to standard format, matching your other autocomplete components.
  -->
  <v-combobox 
    v-model="model" 
    :items="items" 
    multiple 
    :return-object="false"
    :placeholder="'Select ' + data_label" 
    :item-title="item_title" 
    :item-value="item_value"
  >
    <template v-slot:label>
      {{ data_label }}
    </template>

    <template v-slot:prepend-item>
      <v-list-item @click.stop="toggleSelectAll">{{ selectAllLabel }}</v-list-item>
      <v-divider class="mt-1"></v-divider>
    </template>
    
    <template v-slot:selection="{ item, index }">
      <span v-if="index == 0">{{ selectionString }}</span>
    </template>
    
    <template v-slot:append-item>
      <template v-if="data_label === 'Institution Types' && shouldShowNoneOfTheAboveType">
        <v-divider class="my-0 py-0 no-pointer" />
        <v-list-item title="None of the above" value="No type assigned"
                    @click="$emit('update:selected', ['No type assigned'])" />
      </template>
      <template v-else-if="data_label === 'Institution Groups' && shouldShowNoneOfTheAboveGroup">
        <v-divider class="my-0 py-0 no-pointer" />
        <v-list-item title="None of the above" value="No groups assigned"
                    @click="$emit('update:selected', ['No groups assigned'])" />
      </template>
    </template>
  </v-combobox>
</template>


<style scoped>
.selectAll {
  padding-left: 16px;
}

.no-pointer {
  pointer-events: auto;
}
</style>
