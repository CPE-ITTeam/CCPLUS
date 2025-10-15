<!-- components/shared/MultiSelectCombobox.vue  -->
<script setup>
  import { ref, computed, watch, nextTick } from 'vue';
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
  const selectionString = ref('');
  var selectAllLabel = ref("Select All");
  var allSelected = false;
  function toggleSelectAll() {
    nextTick(() => {
      // just update the model, watch gets the rest
      model.value = (allSelected) ? [] : props.items.map(item => item[item_value]);
    });
  }
  // Watch model
  watch(model, (newValue, oldValue) => {
    allSelected = ( newValue.length>0 && newValue.length === props.items.length &&
                    newValue.every(val => props.items.map(i => i.id).includes(val)) );
    selectAllLabel.value = (allSelected) ? "Clear All" : "Select All";
    if (allSelected) {
      selectionString.value = "All ";
      selectionString.value += props.dataName ?? props.label;
    } else if (model.value.length>0) {
      selectionString.value = model.value[0].name;
      selectionString.value += (newValue.length<=1) ? "" : " +"+(newValue.length-1).toString()+" more";
    } else {
      selectionString.value = '';
    }
  });
</script>
<template>
      <v-combobox v-model="model" :items="items" :label="label" multiple clearable
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
          <v-divider class="my-0 py-0" style="pointer-events: none" />
          <v-list-item title="None of the above" value="No type assigned"
                      @click="$emit('update:selected', ['No type assigned'])" />
        </template>
        <template v-else-if="label === 'Institution Groups' && shouldShowNoneOfTheAboveGroup">
          <v-divider class="my-0 py-0" style="pointer-events: none" />
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
  .non-selectable {
    pointer-events: none;
  }
  .non-selectable .v-checkbox {
    pointer-events: auto;
  }
</style>
