<!-- components/shared/DataTable.vue -->
<script setup>
  import { ref, computed, onUpdated  } from 'vue';
  import ToggleIcon from './ToggleIcon.vue';

  const props = defineProps({
    headers: { type: Array, required: true },
    items: { type: Array, required: true },
    search: { type: String, required: true },
    showSelectedOnly: { type: Boolean, required: true },
    searchFields: { type: Array, required: true },
    selectedRows: { type: Array, required: true }
  });
  var isLoading = ref(true);
  const selectedRows = ref([...props.selectedRows]);
  const hasStatusColumn = computed(() => props.headers.some(h => h.key === 'status'));
  const hasConnectedColumn = computed(() => props.headers.some(h => h.key === 'connected'));

  const filteredItems = computed(() => {
    // Add your filtering logic here if needed
    return props.items;
  });

// TODO: Use props.searchFields 

  function updateSelected () {
    emit('update:selectedRows', selectedRows.value);
  }
  onUpdated(() => isLoading.value=false);
</script>

<template>
  <div>
    <div style="margin-bottom: 8px; font-weight: bold">
      ✅ {{ selectedRows.length }} item{{ selectedRows.length=== 1 ? '' : 's' }} selected
    </div>
    <v-data-table :headers="props.headers" :items="filteredItems" item-value="id" show-select
                  return-object v-model="selectedRows" :loading="isLoading" @change="updateSelected">
      <!-- Status -->
      <template v-if="hasStatusColumn" #item.status="{ item }">
        <ToggleIcon v-model="item.status" :toggleable="true" />
      </template>

      <!-- Connected -->
      <template v-if="hasConnectedColumn" #item.connected="{ item }">
        <ToggleIcon v-model="item.connected" :toggleable="true" />
      </template>

      <!-- PR Slot -->
      <template #item.PR="{ item }">
        <ToggleIcon v-model="item.PR" :toggleable="true" />
      </template>

      <!-- DR Slot -->
      <template #item.DR="{ item }">
        <ToggleIcon v-model="item.DR" :toggleable="true" />
      </template>

      <!-- TR Slot -->
      <template #item.TR="{ item }">
        <ToggleIcon v-model="item.TR" :toggleable="true" />
      </template>

      <!-- IR Slot -->
      <template #item.IR="{ item }">
        <ToggleIcon v-model="item.IR" :toggleable="true" />
      </template>

      <!-- Customer ID -->
      <template #item.customerId="{ item }">
        <span :class="{ 'required-field': item.customerId === 'required' }">
          {{ item.customerId }}
        </span>
      </template>

      <!-- Requestor ID -->
      <template #item.requestorId="{ item }">
        <span :class="{ 'required-field': item.requestorId === 'required' }">
          {{ item.requestorId }}
        </span>
      </template>

      <!-- API Key -->
      <template #item.apiKey="{ item }">
        <span :class="{ 'required-field': item.apiKey === 'required' }">
          {{ item.apiKey }}
        </span>
      </template>

      <!-- Email Key -->
      <template #item.email="{ item }">
        <span v-if="!item.email.includes('@')">{{ item.email }}</span>
        <span v-else><a :href="'mailto:'+item.email">{{ item.email }}</a></span>
      </template>

      <!-- Actions column -->
      <template #item.actions="{ item }">
        <div class="d-flex ga-2 justify-end">
          <v-tooltip text="Edit" location="top">
            <template v-slot:activator="{ props }">
              <v-icon icon="mdi-pencil" color="medium-emphasis" v-bind="props"
                      @click="$emit('edit', item)" />
            </template>
          </v-tooltip>

          <v-tooltip text="Delete" location="top">
            <template v-slot:activator="{ props }">
              <v-icon icon="mdi-delete" color="medium-emphasis" v-bind="props"
                      @click="$emit('delete', item)" />
            </template>
          </v-tooltip>
        </div>
      </template>
    </v-data-table>
  </div>
</template>
<style scoped>
  .required-field {
    color: orange;
    font-style: italic;
  }
</style>
