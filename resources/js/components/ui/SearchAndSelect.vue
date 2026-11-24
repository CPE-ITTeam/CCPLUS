<!-- components/ui/SearchAndSelect.vue -->
<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  search: { type: String, required: false, default: null },
});

const emit = defineEmits(['update:search']);
const search = ref(props.search);

watch(search, (val) => emit('update:search', val));
</script>

<template>
  <v-text-field
    v-model="search"
    :label="$t('tables.search')"
    prepend-inner-icon="mdi-magnify"
    class="w-100 pt-2"
  />
</template>
