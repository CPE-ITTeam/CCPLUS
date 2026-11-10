<!-- components/dialogs/ErrorDetails.vue -->
<script setup>
import BaseDialogLayout from './BaseDialogLayout.vue';

const props = defineProps({
  error: { type: Object, required: true },
});

defineEmits(['close']);

function goCOUNTER(errorId, urlbase) {
  let features = "";
  let _url = urlbase;
  if ('fragmentDirective' in document) {
    _url += "#:~:text=" + errorId.toString();
    features += "noopener";
  }
  window.open(_url, "_blank", features);
}
</script>

<template>
  <BaseDialogLayout @close="$emit('close')">
    
    <!-- Title Slot with Conditional Text -->
    <template #title>
      <span v-if="error.id < 9000">COUNTER Error : {{ error.id }}</span>
      <span v-else>CC-Plus Error : {{ error.id }}</span>
    </template>

    <!-- Main Content Area -->
    <v-container grid-list-md class="pa-0">
      <v-row class="d-flex mb-2" no-gutters>
        <v-col class="d-flex">{{ error.message }}</v-col>
      </v-row>
      
      <v-row class="d-flex mb-1" no-gutters>
        <v-col class="d-flex pa-0" cols="3"><strong>Details:</strong></v-col>
        <v-col class="d-flex px-4" cols="9">{{ error.explanation }}</v-col>
      </v-row>
      
      <v-row class="d-flex mb-1" no-gutters>
        <v-col class="d-flex pa-0" cols="3"><strong>Suggestion:</strong></v-col>
        <v-col class="d-flex px-4" cols="9">{{ error.suggestion }}</v-col>
      </v-row>
      
      <v-row v-if="error.detail.length > 0" class="d-flex mb-1" no-gutters>
        <v-col class="d-flex pa-0" cols="3">
          <strong>{{ error.process_step }} Step:</strong>
        </v-col>
        <v-col class="d-flex px-4" cols="9">{{ error.detail }}</v-col>
      </v-row>
      
      <v-row v-if="error.help_url.length > 0" class="d-flex mb-1" no-gutters>
        <v-col class="d-flex pa-0" cols="3"><strong>Help URL:</strong></v-col>
        <v-col class="d-flex px-4" cols="9">
          <a :href="error.help_url" target="_blank">{{ error.help_url }}</a>
        </v-col>
      </v-row>
      
      <v-row v-if="error.id < 9000" class="d-flex mb-1" no-gutters>
        <v-col class="d-flex pa-0">
          <a href="#" @click.prevent="goCOUNTER(error.id, error.counter_url)">
            Open COUNTER Details <v-icon size="small">mdi-open-in-new</v-icon>
          </a>
        </v-col>
      </v-row>
    </v-container>

  </BaseDialogLayout>
</template>
