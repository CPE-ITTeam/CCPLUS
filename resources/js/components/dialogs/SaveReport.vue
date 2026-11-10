<!-- components/dialogs/SaveReport.vue -->
<script setup>
import { ref } from 'vue'
import BaseDialogLayout from './BaseDialogLayout.vue'

const props = defineProps({
  saved_reports: { type: Array, required: true },
});

const emit = defineEmits(['save', 'cancel']);

const failure = ref('');
const saveId = ref(null);
const saveTitle = ref('');
const saveType = ref('Create');

function chooseExisting() {
  let rec = props.saved_reports.find(rpt => rpt.id == saveId.value);
  if (typeof rec !== 'undefined') {
    saveTitle.value = rec.title;
  }
}

// Save emits the ID and Title string
function saveConfig() {
  if (saveTitle.value === '' && saveId.value === null) {
    failure.value = 'A name is required to save the configuration';
    return;
  }
  emit('save', { 'id': saveId.value, 'title': saveTitle.value });
}
</script>

<template>
  <BaseDialogLayout @close="emit('cancel')">
    
    <!-- Header Title Slot -->
    <template #title>
      Save Report Configuration
    </template>

    <!-- Main Content Area -->
    <v-form @submit.prevent="saveConfig">
      <v-container class="pa-0">
        <v-row class="d-flex" no-gutters>
          <v-col class="d-flex">
            <v-radio-group label="Save Type" v-model="saveType" inline hide-details="auto">
              <v-radio label="Create a new SavedReport" value="Create"></v-radio>
              <v-radio label="Update an existing SavedReport" value="Update"></v-radio>
            </v-radio-group>
          </v-col>
        </v-row>

        <v-row v-if="saveType == 'Create' || saveTitle.length > 0" class="d-flex pt-4" no-gutters>
          <v-col>
            <v-text-field v-model="saveTitle" label="Title" variant="outlined" density="compact" hide-details="auto" />
          </v-col>
        </v-row>

        <v-row v-if="props.saved_reports.length > 0 && saveType == 'Update'" class="d-flex pt-4" no-gutters>
          <v-col>
            <!-- 💡 FIXED: item-text changed to item-title for Vuetify 3 compatibility -->
            <v-autocomplete 
              v-model="saveId"
              :items="props.saved_reports" 
              label="Saved Report" 
              item-title="title"
              item-value="id" 
              variant="outlined"
              density="compact"
              hide-details="auto"
              @update:modelValue="chooseExisting()"
            />
          </v-col>
        </v-row>

        <v-row v-if="failure" class="d-flex pt-3" no-gutters>
          <v-col>
            <span class="text-error text-caption" role="alert" v-text="failure"></span>
          </v-col>
        </v-row>
      </v-container>
      
      <!-- Hidden layout submit anchor for keyboard accessibility -->
      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <!-- Footer Actions Slot -->
    <template #actions>
      <v-spacer />
      <v-btn 
        color="primary" 
        variant="flat" 
        @click="saveConfig"
      >
        Save
      </v-btn>
      <v-btn 
        variant="text" 
        @click="emit('cancel')"
      >
        Cancel
      </v-btn>
    </template>

  </BaseDialogLayout>
</template>
