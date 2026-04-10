<!-- components/shared/MultiSelectCombobox.vue  -->
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
    return ( model.value.length>0 && model.value.length === props.items.length &&
             model.value.every(val => props.items.map(i => i.id).includes(val)) );
  });
  const selectAllLabel = computed(() => { return (allSelected.value) ? "Clear All" : "Select All"; });
  const selectionString = computed(() => { 
    if (allSelected.value) return "All "+data_label;
    if (model.value.length==0) return "";
    let _item = props.items.find( ii => ii.id == model.value[0]);
    if (typeof(_item) != 'undefined') {
      let _string = _item[item_title];
     _string += (model.value.length<=1) ? "" : " +"+(model.value.length-1).toString()+" more";
      return _string;
    } else { // Getting here means model.value[0] item not found... should not happen?
      return 'Some '+data_label;
    }
  });
  function toggleSelectAll() {
    model.value = (allSelected.value) ? [] : props.items.map(item => item[item_value]);
  }
</script>
<template>
  <v-combobox v-model="model" :items="items" :label="label" multiple clearable :return-object="false"
                persistent-placeholder variant="outlined" :placeholder="'Select '+label" 
                :item-title="item_title" :item-value="item_value">
    <!-- Select All -->
    <template v-slot:prepend-item>
      <v-list-item @click.stop="toggleSelectAll">{{ selectAllLabel }}</v-list-item>
      <v-divider class="mt-1"></v-divider>
    </template>
    <template v-slot:selection="{ item, index }">
      <span v-if="index==0">{{ selectionString }}</span>
    </template>
    <!-- Append "None of the above" for Inst Types and Inst Groups -->
    <template v-slot:append-item>
      <template v-if="label === 'Institution Types' && shouldShowNoneOfTheAboveType">
        <v-divider class="my-0 py-0 no-pointer" />
        <v-list-item title="None of the above" value="No type assigned"
                    @click="$emit('update:selected', ['No type assigned'])" />
      </template>
      <template v-else-if="label === 'Institution Groups' && shouldShowNoneOfTheAboveGroup">
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
