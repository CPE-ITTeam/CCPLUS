<!-- components/ui/SearchAndSelect.vue -->
<script setup>
  import { ref, watch } from 'vue';

  const props = defineProps({
    search: { type: String, required: false, default: null }
  });

  const emit = defineEmits(['update:search']);
  const search = ref(props.search);

  watch(search, val => emit('update:search', val));
</script>

<template>
  <v-text-field 
    v-model="search" 
    label="Search" 
    prepend-inner-icon="mdi-magnify" 
     
    clearable
    
    class="w-100 pt-2"
    @input="$emit('update:search', search)" 
  />
</template>
