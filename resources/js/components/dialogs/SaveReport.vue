<!-- components/dialogs/SaveReport.vue -->
<script setup>
  import { ref } from 'vue'
  const props = defineProps({
    saved_reports: { type:Array, required: true },
  });
  const emit = defineEmits(['save','cancel']);
  var failure = ref('');
  var saveId = ref(null);
  var saveTitle = ref('');
  var saveType = ref('Create');

  function chooseExisting() {
    let rec = props.saved_reports.find( rpt => rpt.id == saveId.value);
    if ( typeof(rec) != 'undefined') {
      saveTitle.value = rec.title;
    }
  }

  // Save emits the ID and Title string
  function saveConfig() {
    if (saveTitle.value=='' && saveId.value==null) {
      failure.value = 'A name is required to save the configuration';
      return;
    }
    emit('save', { 'id': saveId.value, 'title': saveTitle.value });
  }
</script>
<template>
  <div>
    <v-form>
      <v-container>
        <v-row class="ma-0 py-1 justify-center" no-gutters>
          <v-col class="d-flex px-2"><h3>Save Report Configuration</h3></v-col>
        </v-row>
        <v-row class="d-flex pa-2" no-gutters>
          <v-col class="d-flex px-2">
            <v-radio-group label="Save Type" v-model="saveType" inline>
              <v-radio label="Create a new SavedReport" value='Create'></v-radio>
              <v-radio label="Update an existing SavedReport" value='Update'></v-radio>
            </v-radio-group>
          </v-col>
        </v-row>
        <v-row v-if="saveType=='Create' || saveTitle.length>0" class="d-flex pa-2" no-gutters>
          <v-text-field v-model="saveTitle" label="Title" outlined></v-text-field>
        </v-row>
        <v-row v-if="props.saved_reports.length>0 && saveType=='Update'" class="d-flex pa-2" no-gutters>
          <v-col>
            <v-autocomplete :items='props.saved_reports' v-model='saveId' label="Saved Report" item-text="title"
                      item-value="id" @update:modelValue="chooseExisting()"></v-autocomplete>
          </v-col>
        </v-row>
        <v-row v-if="failure" class="d-flex pa-2" no-gutters>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </v-row>
        <v-spacer></v-spacer>
        <v-row class="d-flex justify-center" no-gutters>
          <v-col class="d-flex px-2" cols="3">
            <v-btn color="primary" @click="saveConfig">Save</v-btn>
          </v-col>
          <v-col class="d-flex px-2" cols="3">
            <v-btn color="primary" @click="emit('cancel')">Cancel</v-btn>
          </v-col>
        </v-row>
      </v-container>
    </v-form>
  </div>
</template>
