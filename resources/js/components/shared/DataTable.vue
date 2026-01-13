<!-- components/shared/DataTable.vue -->
<script setup>
  import { ref, computed, onMounted } from 'vue';
  import ToggleIcon from './ToggleIcon.vue';

  const props = defineProps({
    headers: { type: Array, required: true },
    items: { type: Array, required: true },
    search: { type: String, required: true },
    dataset: { type: String, required: true },
    showSelectedOnly: { type: Boolean, required: true },
    searchFields: { type: Array, required: true },
    selectedRows: { type: Array, required: true },
    editableFields: { type: Array, required: true }
  });

  const computedHeaders = computed(() => {
    const hdrs = [...props.headers];
    if (props.items.length > 0) {
      if (props.items.some( (itm) => (itm.can_edit || itm.can_delete))) {
        hdrs.push({ title: 'Actions', key: 'actions', align: 'end' });
      }
    }
    return hdrs;
  });

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
  const confirmDialog = ref(false);
  var curItem = ref({});
  var isLoading = ref(true);

  const emit = defineEmits(['update:selectedRows', 'update:toggle', 'update:report', 'edit', 'delete']);

  // Emit edit event
  function emitEdit(item) {
    emit('edit', item);
  }

  // reportSort compares 'sortval' values in items
  const reportSort = (a, b) => {
    return a.sortval - b.sortval;
  };

  // Define custom sort functions for specific keys
  // (columns not included here sort as-usual)
  const customKeySort = {
    DR: (a, b) => reportSort(a, b),
    PR: (a, b) => reportSort(a, b),
    TR: (a, b) => reportSort(a, b),
    IR: (a, b) => reportSort(a, b),
  };

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
  function enableDialog(item) {
    curItem.value = {...item};
    confirmDialog.value = true;
  }
  function cancelDialog() {
    curItem.value = {};
    confirmDialog.value = false;
  }
  function confirmDelete() {
    emit('delete', curItem.value.id);
    curItem.value = {};
    confirmDialog.value = false;
  }

  onMounted(() => isLoading.value=false);
</script>

<template>
  <div>
    <div style="margin-bottom: 8px; font-weight: bold">
      ✅ {{ selectedRows.length }} item{{ selectedRows.length=== 1 ? '' : 's' }} selected
    </div>
    <v-data-table v-model="internalSelectedRows" :headers="computedHeaders" :items="filteredItems"
                  item-key="id" item-value="id" show-select return-object :loading="isLoading"
                  :custom-key-sort="customKeySort">

      <!-- Dynamic editable fields -->
      <!-- <template v-for="field in props.editableFields" #[`item.${field}`]="{ item }">
        <component :is="getFieldComponent(field)" v-model="item[field]" :label="getFieldLabel(field)"
                   density="compact" hide-details variant="plain" class="editable-cell"
                   @blur="emitEdit(item)"/>
      </template> -->

      <!-- Preserved custom slots -->
      <template v-if="hasStatusColumn" #item.status="{ item }">
        <ToggleIcon v-model="item.status" :toggleable="true"
                    @update:modelValue="$emit('update:toggle',item.id,'status',item.status)" />
      </template>

      <template v-if="hasConnectedColumn" #item.connected="{ item }">
        <!-- <ToggleIcon v-model="item.connected" :toggleable="true" @change="emitEdit(item)" /> -->
        <ToggleIcon v-model="item.connected" :toggleable="true"
                    @update:modelValue="$emit('update:toggle',item.id,'connected',item.status)" />
      </template>

      <template v-if="hasIncludeZerosColumn" #item.includeZeros="{ item }">
        <!-- <ToggleIcon v-model="item.includeZeros" :toggleable="true" @change="emitEdit(item)" /> -->
        <ToggleIcon v-model="item.includeZeros" :toggleable="true"
                    @update:modelValue="$emit('update:toggle',item.id,'includeZeros',item.status)" />
      </template>

      <!-- PR Slot -->
      <template #item.PR="{ item }">
        <!-- <ToggleIcon v-model="item.PR" :toggleable="true" @change="emitEdit(item)" /> -->
        <ToggleIcon v-model="item.PR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'PR',item.PR)" />
      </template>

      <!-- DR Slot -->
      <template #item.DR="{ item }">
        <!-- <ToggleIcon v-model="item.DR" :toggleable="true" @change="emitEdit(item)" /> -->
        <ToggleIcon v-model="item.DR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'DR',item.DR)" />
      </template>

      <!-- TR Slot -->
      <template #item.TR="{ item }">
        <!-- <ToggleIcon v-model="item.TR" :toggleable="true" @change="emitEdit(item)" /> -->
        <ToggleIcon v-model="item.TR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'TR',item.TR)" />
      </template>

      <!-- IR Slot -->
      <template #item.IR="{ item }">
        <!-- <ToggleIcon v-model="item.IR" :toggleable="true" @change="emitEdit(item)" /> -->
        <ToggleIcon v-model="item.IR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'IR',item.IR)" />
      </template>

      <!-- Customer ID -->
      <template #item.customer_id="{ item }">
        <span :class="{ 'required-field': item.customer_id === 'required' }">
          {{ item.customer_id }}
        </span>
      </template>

      <!-- Requestor ID -->
      <template #item.requestor_id="{ item }">
        <span :class="{ 'required-field': item.requestor_id === 'required' }">
          {{ item.requestor_id }}
        </span>
      </template>

      <!-- API Key -->
      <template #item.api_key="{ item }">
        <span :class="{ 'required-field': item.api_key === 'required' }">
          {{ item.api_key }}
        </span>
      </template>

      <!-- Email Key -->
      <!-- <template #item.email="{ item }">
        <span v-if="!item.email.includes('@')">{{ item.email }}</span>
        <span v-else><a :href="'mailto:'+item.email">{{ item.email }}</a></span>
      </template> -->

      <!-- Actions column -->
      <template #item.actions="{ item }">
        <div v-if="item.can_edit || item.can_delete" class="d-flex ga-2 justify-end">
<!--
NOTE:: !"can_edit" hides icon; a gray/disable icon could be added instead of "nothing"
-->        
          <v-tooltip v-if="item.can_edit" text="Edit" location="top">
            <template v-slot:activator="{ props }">
              <v-icon icon="mdi-pencil" color="medium-emphasis" v-bind="props"
                      @click="$emit('edit', item)" />
            </template>
          </v-tooltip>
<!--
NOTE:: !"can_delete" hides icon; a gray/disable icon could be added instead of "nothing"
-->        
          <v-tooltip v-if="item.can_delete" text="Delete" location="top">
            <template v-slot:activator="{ props }">
              <!-- <v-icon icon="mdi-delete" color="medium-emphasis" v-bind="props"
                      @click="$emit('delete', item)"/> -->
              <v-icon icon="mdi-delete" color="medium-emphasis" v-bind="props"
                      @click="enableDialog(item)"/>
            </template>
          </v-tooltip>
        </div>
      </template>
    </v-data-table>
    <v-dialog v-model="confirmDialog" max-width="600px">
      <v-card>
        <v-card-title class="text-indigo-darken-2 pa-6 d-flex justify-space-between align-center">
          <span>Confirm Delete</span>
          <v-tooltip text="Cancel" location="bottom">
            <template #activator="{ props }">
              <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="cancelDialog">
                <v-icon size="18">mdi-close</v-icon>
              </v-btn>
            </template>
          </v-tooltip>
        </v-card-title>
        <v-card-text>
          <p>&nbsp;</p>
          <p>
            <strong>You are about to delete {{ curItem.name }} from {{ props.dataset }}</strong>
          </p>
          <p>&nbsp;</p>
          <h3> Are you Sure?</h3>
          <p>&nbsp;</p>
          <v-row>
            <v-col cols="12" class="text-left">
              <v-btn color="primary" @click="confirmDelete">Yes, Delete it</v-btn>
              <v-btn variant="text" class="ml-2" @click="cancelDialog">Cancel</v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>
<style scoped>
  .required-field {
    color: orange;
    font-style: italic;
  }
</style>
