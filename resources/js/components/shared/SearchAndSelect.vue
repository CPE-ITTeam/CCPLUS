<!-- toolbar/SearchAndSelect -->
<script setup>
  import { ref, watch } from 'vue';

  const props = defineProps({
    search: { type: String, required: false, default: null },
    showSelectedOnly: {type: Boolean, required: true },
  });

  const emit = defineEmits(['update:search', 'update:showSelectedOnly']);
  const search = ref(props.search);
  const showSelectedOnly = ref(props.showSelectedOnly);

  watch(search, val => emit('update:search', val));
  watch(showSelectedOnly, val => emit('update:showSelectedOnly', val));

  const toggleSelectedOnly = () => {
    showSelectedOnly.value = !showSelectedOnly.value;
  };
</script>

<template>
  <v-col cols="auto">
    <v-tooltip :text="showSelectedOnly ? 'Show All' : 'Show Selected'" location="top">
      <template v-slot:activator="{ props }">
        <v-btn v-bind="props" icon color="#0066a1" @click="toggleSelectedOnly"
              :variant="showSelectedOnly ? 'outlined' : 'tonal'">
          <v-icon>
            {{ showSelectedOnly ? 'mdi-filter-off' : 'mdi-filter' }}
          </v-icon>
        </v-btn>
      </template>
    </v-tooltip>
  </v-col>

  <v-col>
    <v-text-field v-model="search" label="Search" @input="$emit('update:search', search)" clearable density="compact"
                  prepend-inner-icon="mdi-magnify" variant="outlined" hide-details single-line/>
  </v-col>
</template>