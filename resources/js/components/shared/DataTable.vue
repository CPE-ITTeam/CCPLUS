<!-- components/shared/DataTable.vue -->
<script setup>
  import { ref, computed } from 'vue';
  import ToggleIcon from './ToggleIcon.vue';
  import DeleteConfirm from '../dialogs/DeleteConfirm.vue';
  import ErrorDetails from '../dialogs/ErrorDetails.vue';

  const props = defineProps({
    headers: { type: Array, required: true },
    items: { type: Array, required: true },
    search: { type: String, required: true },
    dataset: { type: String, required: true },
    showSelectedOnly: { type: Boolean, required: true },
    searchFields: { type: Array, required: true },
    selectedRows: { type: Array, required: true },
    editableFields: { type: Array, required: true },
    isLoading: { type: Boolean, required:true },
    truncated: { type: Boolean, required:true }
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
  const deleteDialog = ref(false);
  var curItem = ref({});
  const errorDialog = ref(false);
  var current_error = ref({id:null, message:'', explanation:'', detail:'', process_step:'', help_url:''});

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

  function enableDeleteDialog(item) {
    curItem.value = {...item};
    deleteDialog.value = true;
  }
  function closeDeleteDialog() {
    curItem.value = {};
    deleteDialog.value = false;
  }
  function enableErrorDialog(error) {
      current_error.value = { ...error };
      errorDialog.value = true;
  }
  function closeErrorDialog() {
    current_error.value = {id:null, message:'', explanation:'', detail:'', process_step:'', help_url:''};
    errorDialog.value = false;
  }
  </script>

<template #top>
  <div>
    <div class="d-flex flex-column ga-1 mb-2">
      <v-row v-if="truncated" class="text-red font-weight-bold" no-gutters>
        <span>Available records truncated to 500 items</span>
      </v-row>
      <div class="font-weight-bold" style="margin-bottom: 8px; font-weight: bold">
        ✅ {{ selectedRows.length }} item{{ selectedRows.length=== 1 ? '' : 's' }} selected
      </div>
    </div>
    <v-data-table v-model="internalSelectedRows" :headers="computedHeaders" :items="filteredItems"
                  item-key="id" item-value="id" show-select return-object :loading="props.isLoading"
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
        <ToggleIcon v-model="item.connected" :toggleable="true"
                    @update:modelValue="$emit('update:toggle',item.id,'connected',item.status)" />
      </template>

      <template v-if="hasIncludeZerosColumn" #item.includeZeros="{ item }">
        <ToggleIcon v-model="item.includeZeros" :toggleable="true"
                    @update:modelValue="$emit('update:toggle',item.id,'includeZeros',item.status)" />
      </template>

      <!-- PR Slot -->
      <template #item.PR="{ item }">
        <ToggleIcon v-model="item.PR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'PR')" />
      </template>

      <!-- DR Slot -->
      <template #item.DR="{ item }">
        <ToggleIcon v-model="item.DR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'DR')" />
      </template>

      <!-- TR Slot -->
      <template #item.TR="{ item }">
        <ToggleIcon v-model="item.TR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'TR')" />
      </template>

      <!-- IR Slot -->
      <template #item.IR="{ item }">
        <ToggleIcon v-model="item.IR" :toggleable="true"
                    @update:modelValue="$emit('update:report',item.id,'IR')" />
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

      <!-- statusDot Key (icon) -->
      <template v-slot:item.statusDot="{ item }">
        <span >
          <v-icon :title="item.d_status" :color="item.error.color">mdi-record</v-icon>
        </span>
      </template>

      <!-- Error Key -->
      <template #item.error="{ item }">
        <span v-if="item.error.id>0">
          {{ item.error.id }}
          <v-icon title="View Error Details" @click="enableErrorDialog(item.error)"
                  :color="item.error.color">mdi-dots-vertical</v-icon>
        </span>
        <span v-else >Success</span>
      </template>

      <!-- Email Key -->
      <!-- <template #item.email="{ item }">
        <span v-if="!item.email.includes('@')">{{ item.email }}</span>
        <span v-else><a :href="'mailto:'+item.email">{{ item.email }}</a></span>
      </template> -->

      <!-- Actions column -->
      <template #item.actions="{ item }">
        <div v-if="item.can_edit || item.can_delete" class="d-flex ga-2 justify-end">
          <v-tooltip v-if="item.can_edit" text="Edit" location="top">
            <template v-slot:activator="{ props }">
              <v-icon icon="mdi-pencil" color="medium-emphasis" v-bind="props"
                      @click="$emit('edit', item)" />
            </template>
          </v-tooltip>
          <v-tooltip v-if="item.can_delete" text="Delete" location="top">
            <template v-slot:activator="{ props }">
              <v-icon icon="mdi-delete" color="medium-emphasis" v-bind="props"
                      @click="enableDeleteDialog(item)"/>
            </template>
          </v-tooltip>
        </div>
      </template>
    </v-data-table>
    <v-dialog v-model="deleteDialog">
      <DeleteConfirm :item="curItem" :dataset="props.dataset" @confirm="$emit('delete', curItem.id)"
                     @close="closeDeleteDialog" />
    </v-dialog>
    <v-dialog v-model="errorDialog">
      <ErrorDetails :error="current_error" @close="closeErrorDialog" />
    </v-dialog>
  </div>
</template>
<style scoped>
  .required-field { color: orange; font-style: italic; }
</style>
