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
    selectedRows: { type: Array, required: true },
    editableFields: { type: Array, required: true }
  });

  // Add actions column to header props
  const computedHeaders = computed(() => [
    ...props.headers,
    { title: 'Actions', key: 'actions', align: 'end' },
  ]);

  const internalSelectedRows = computed({
    get: () => props.selectedRows,
    set: (val) => emit('update:selectedRows', val),
  });
  const selectedIds = computed(() => internalSelectedRows.value.map(row => row.id));
  const hasStatusColumn = computed(() => props.headers.some(h => h.key === 'status'));
  const hasConnectedColumn = computed(() => props.headers.some(h => h.key === 'connected'));
  const hasIncludeZerosColumn = computed(() => props.headers.some(h => h.key === 'includeZeros'));
  const filteredItems = computed(() => {
    const searchTerm = (props.search ?? '').trim().toLowerCase();
    if (props.showSelectedOnly) {
      return props.items.filter(item => selectedIds.value.includes(item.id));
    }
    if (!searchTerm) {
      return props.items;
    }
    return props.items.filter(item =>
      props.searchFields.some(field => {
        const value = item[field];
        return typeof value === 'string' && value.toLowerCase().includes(searchTerm);
      })
    );
  });
  var isLoading = ref(true);

  const emit = defineEmits(['update:selectedRows', 'edit', 'delete']);

  // Emit edit event
  function emitEdit(item) {
    emit('edit', item);
  }

  // Choose component type based on field
  function getFieldComponent(field) {
    if (field === 'status') return ToggleIcon;
    if (field === 'password') return 'v-text-field';
    return 'v-text-field';
  }

  // Optional: map field labels
  function getFieldLabel(field) {
    const labels = {
      username: 'Username',
      email: 'Email',
      institution: 'Institution',
      consortiumKey: 'Consortium Key',
      role: 'Role',
      status: 'Status',
      password: 'Password',
    };
    return labels[field] || field;
  }
  // onMounted(() => isLoading.value=false);
  onUpdated(() => isLoading.value=false);
</script>

<template>
  <div>
    <div style="margin-bottom: 8px; font-weight: bold">
      ✅ {{ selectedRows.length }} item{{ selectedRows.length=== 1 ? '' : 's' }} selected
    </div>
    <v-data-table v-model="internalSelectedRows" :headers="computedHeaders" :items="filteredItems"
                  item-key="id" item-value="id" show-select return-object :loading="isLoading"  >

      <!-- Dynamic editable fields -->
      <!-- <template v-for="field in props.editableFields" #[`item.${field}`]="{ item }">
        <component :is="getFieldComponent(field)" v-model="item[field]" :label="getFieldLabel(field)"
                   density="compact" hide-details variant="plain" class="editable-cell"
                   @blur="emitEdit(item)"/>
      </template> -->

      <!-- Preserved custom slots -->
      <template v-if="hasStatusColumn" #item.status="{ item }">
        <ToggleIcon v-model="item.status" :toggleable="true" @change="emitEdit(item)" />
      </template>

      <template v-if="hasConnectedColumn" #item.connected="{ item }">
        <ToggleIcon v-model="item.connected" :toggleable="true" @change="emitEdit(item)" />
      </template>

      <template v-if="hasIncludeZerosColumn" #item.includeZeros="{ item }">
        <ToggleIcon v-model="item.includeZeros" :toggleable="true" @change="emitEdit(item)" />
      </template>

      <!-- PR Slot -->
      <template #item.PR="{ item }">
        <ToggleIcon v-model="item.PR" :toggleable="true" @change="emitEdit(item)" />
      </template>

      <!-- DR Slot -->
      <template #item.DR="{ item }">
        <ToggleIcon v-model="item.DR" :toggleable="true" @change="emitEdit(item)" />
      </template>

      <!-- TR Slot -->
      <template #item.TR="{ item }">
        <ToggleIcon v-model="item.TR" :toggleable="true" @change="emitEdit(item)" />
      </template>

      <!-- IR Slot -->
      <template #item.IR="{ item }">
        <ToggleIcon v-model="item.IR" :toggleable="true" @change="emitEdit(item)" />
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
      <!-- <template #item.email="{ item }">
        <span v-if="!item.email.includes('@')">{{ item.email }}</span>
        <span v-else><a :href="'mailto:'+item.email">{{ item.email }}</a></span>
      </template> -->

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
