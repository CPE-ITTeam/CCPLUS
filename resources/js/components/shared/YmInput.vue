<script setup>
  import { ref, onBeforeMount } from 'vue';
  const modelValue = defineModel({type: String, required: true, default: ''});
  const props = defineProps({
    label: { type: String, required: true },
    cols: { type: Number, default: 2},
    minYM: { type: String, default: ''}
  });
  var options = ref([]);
  // default start yearmon is '2019-01'
  var minMo = ref(1);
  var minYr = ref(2019);
  const yearMonRegex = /^\d{4}-(0[1-9]|1[0-2])$/;
  const initializeOptions = async () => {
    const today = new Date();
    const cur_yr = today.getFullYear();
    const cur_mo = today.getMonth()+1;
    if (yearMonRegex.test(props.minYM)) {
      let parts = props.minYM.split("-");
      minYr.value = parseInt(parts[0]);
      minMo.value = parseInt(parts[1]);
    }
    let last_yr = (cur_mo == 1) ? cur_yr-1 : cur_yr;
    for ( var yr = last_yr; yr >= minYr.value; yr-- ) {
      let last_mo = (yr < last_yr || cur_mo == 12) ? 12 : cur_mo-1;
      // let first_mo = (yr != cur_yr) ? 1 : minMo.value;
      let first_mo = (yr != minYr.value) ? 1 : minMo.value;
      for ( var mo = last_mo; mo >= first_mo; mo--) {
        options.value.push(yr.toString()+'-'+mo.toString().padStart(2, '0'))
      }
    }
  }
  const emitValue = (event) => {
    emit('update:modelValue', event.target.value);
  };
  onBeforeMount(() => {
    initializeOptions();
  });
</script>
<template>
  <v-col class="d-flex px-2" :cols="cols">
    <v-select v-model="modelValue" :items='options' :label="label" @change="emitValue"></v-select>
  </v-col>
</template>
