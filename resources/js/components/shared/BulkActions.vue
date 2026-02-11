<!-- components/shared/BulkActions -->
<script setup>
  import { ref } from 'vue';
  const bulkOptions = defineModel({type: Object, required: true});
  const emit = defineEmits(['bulkAction']);
  var option = ref(null);
  var groupDialog = ref(false);
  var deleteDialog = ref(false);
  var groupingType = ref(null);
  var addToGroupID = ref(null);
  var newGroupName = ref('');

  function cancelDialog() {
    deleteDialog.value = false;
    groupDialog.value = false;
  }
  // Handle bulkOption change
  function changeOption() {
    // Create new or Add-to Group loads dialog for additional inputs
    if (option.value == 'Create New Group' || option.value == 'Add to Existing Group') {
      groupingType.value = (option.value=='Create New Group') ? 'Create' : 'Add';
      groupDialog.value = true;
    } else if (option.value == 'Delete') {
      deleteDialog.value = true;
    // No dialog or extra data to return, emit just the seleted option
    } else {
      emit('bulkAction', { 'action': option.value });
    }
    option.value = null;
  }

  function groupFormSubmit() {
    if (groupingType.value == 'Add') {
       emit('bulkAction',{ 'action': option.value, 'group_id': addToGroupID.value });
    } else {  // Create
      emit('bulkAction',{ 'action': option.value, 'name': newGroupName.value });
    }
    groupDialog.value = false;
  }
  function confirmDelete() {
    emit('bulkAction',{ 'action': 'Delete' });
    deleteDialog.value = false;
  }
</script>

<template>
  <v-autocomplete v-model="option" label="Bulk Actions" :items="bulkOptions.items" density="compact"
                  @update:modelValue="changeOption" />

  <!-- Institutions bulk dialog to prompt for existing group (for Add-To) or name (for New-Group) -->
  <v-dialog v-model="groupDialog" max-width="500px">
    <v-card>
      <v-card-title class="text-indigo-darken-2 pa-6 d-flex justify-space-between align-center">
        <span v-if="groupingType=='Create'">Create a New Institution Group</span>
        <span v-if="groupingType=='Add'">Add Institutions to An Existing Group</span>
        <v-tooltip text="Cancel" location="bottom">
          <template #activator="{ props }">
            <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="cancelDialog">
              <v-icon size="18">mdi-close</v-icon>
            </v-btn>
          </template>
        </v-tooltip>
      </v-card-title>
      <v-form @submit.prevent="groupFormSubmit">
        <v-card-text>
          <v-container grid-list-md>
            <div v-if="groupingType=='Create'">
                <v-text-field v-model="newGroupName" label="Name" outlined></v-text-field>
            </div>
            <div v-if="groupingType=='Add'">
              <v-select :items="bulkOptions.groups" v-model="addToGroupID" item-title="name" item-value="id"
                        label="Institution Group" hint="Add institutions to this group"
              ></v-select>
            </div>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn class='btn' x-small color="primary" type="submit">Submit</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="cancelDialog">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
  <v-dialog v-model="deleteDialog" max-width="500px">
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
          <strong>You are about to delete <strong>multiple</strong> {{ bulkOptions.dataset }} !</strong>
        </p>
        <p>&nbsp;</p>
        <h3> Are you Sure?</h3>
        <p>&nbsp;</p>
        <v-row>
          <v-col cols="12" class="text-left">
            <v-btn color="primary" @click="confirmDelete">Yes, Delete Them</v-btn>
            <v-btn variant="text" class="ml-2" @click="cancelDialog">Cancel</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>