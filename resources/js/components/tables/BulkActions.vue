<!-- components/tables/BulkActions.vue -->
<script setup>
import { ref } from 'vue';

const bulkOptions = defineModel({ type: Object, required: true });
const emit = defineEmits(['bulkAction']);

const option = ref(null);
const groupDialog = ref(false);
const deleteDialog = ref(false);
const groupingType = ref(null);
const addToGroupID = ref(null);
const newGroupName = ref('');
const activeActionContext = ref(null);

function cancelDialog() {
  deleteDialog.value = false;
  groupDialog.value = false;
  activeActionContext.value = null;
}

function changeOption() {
  if (!option.value) return;
  activeActionContext.value = option.value;

  if (option.value === 'Create New Group' || option.value === 'Add to Existing Group') {
    groupingType.value = (option.value === 'Create New Group') ? 'Create' : 'Add';
    groupDialog.value = true;
  } else if (option.value === 'Delete') {
    deleteDialog.value = true;
  } else {
    emit('bulkAction', { 'action': option.value });
    activeActionContext.value = null;
  }
  option.value = null;
}

function groupFormSubmit() {
  if (groupingType.value === 'Add') {
    emit('bulkAction', { 'action': activeActionContext.value, 'group_id': addToGroupID.value });
    addToGroupID.value = null;
  } else {
    emit('bulkAction', { 'action': activeActionContext.value, 'name': newGroupName.value });
    newGroupName.value = '';
  }
  groupDialog.value = false;
  activeActionContext.value = null;
}

function confirmDelete() {
  emit('bulkAction', { 'action': 'Delete' });
  deleteDialog.value = false;
  activeActionContext.value = null;
}
</script>
<template>
  <!-- 💡 FIXED: Wrapped inside a single top-level element container to prevent Fragment Attribute Inheritance crashes -->
  <div>
    <v-autocomplete 
      v-model="option" 
      label="Bulk Actions" 
      :items="bulkOptions.items"       
      @update:modelValue="changeOption" 
    />

    <!-- LAYOUT PANEL: INSTITUTION GROUPS OPERATOR -->
    <v-dialog v-model="groupDialog" width="500">
      <v-card class="pa-6">
        <div class="d-flex justify-space-between align-center mb-4">
          <span class="text-h6 font-weight-bold">
            {{ groupingType === 'Create' ? 'Create a New Institution Group' : 'Add Institutions to An Existing Group' }}
          </span>
          <v-btn icon="mdi-close" variant="text" @click="cancelDialog" />
        </div>

        <v-form @submit.prevent="groupFormSubmit">
          <div class="mb-6">
            <v-text-field 
              v-if="groupingType === 'Create'"
              v-model="newGroupName" 
              label="Name" 
              required 
            />
            <v-select 
              v-if="groupingType === 'Add'"
              v-model="addToGroupID" 
              :items="bulkOptions.groups" 
              item-title="name" 
              item-value="id"
              label="Institution Group" 
              required
            />
          </div>

          <div class="d-flex ga-3 justify-end">
            <v-btn color="primary" class="font-weight-bold text-none" @click="cancelDialog">Cancel</v-btn>
            <v-btn type="submit" class="font-weight-bold text-none px-6">Submit</v-btn>
          </div>
        </v-form>
      </v-card>
    </v-dialog>

    <!-- LAYOUT PANEL: BULK DELETION VERIFIER -->
    <v-dialog v-model="deleteDialog" width="450">
      <v-card class="pa-6 text-center">
        <div class="d-flex justify-space-between align-center mb-4">
          <span class="text-h6 font-weight-bold text-left">Confirm Delete</span>
          <v-btn icon="mdi-close" variant="text" size="small" @click="cancelDialog" />
        </div>

        <p class="text-body-1 mb-2">
          You are about to delete <strong>multiple</strong> records from the <strong>{{ bulkOptions.dataset }}</strong> index panel!
        </p>
        <h3 class="text-h5 font-weight-bold text-error mb-6">Are you absolutely sure?</h3>
        
        <div class="d-flex ga-3 justify-center">
          <v-btn class="font-weight-bold text-none" @click="cancelDialog">Cancel</v-btn>
          <v-btn color="error" variant="flat" class="font-weight-bold text-none px-6" @click="confirmDelete">Yes, Delete Them</v-btn>
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>
