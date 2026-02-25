<!-- components/dialogs/ErrorDetails.vue -->
<script setup>
  const props = defineProps({
    error: { type:Object, required: true },
  });
  const emit = defineEmits(['close']);
  function goCOUNTER(errorId, urlbase) {
    let features = "";
    let _url = urlbase;
    if ('fragmentDirective' in document) {
      _url += "#:~:text="+errorId.toString();
      features += "noopener";
    }
    window.open(_url, "_blank", features);
  }
</script>
<template>
  <v-card>
    <v-card-title class="pa-6 d-flex justify-space-between align-center">
      <span v-if="error.id<9000">COUNTER Error : {{ error.id }}</span>
      <span v-else>CC-Plus Error : {{ error.id }}</span>
      <v-tooltip text="Cancel" location="bottom">
        <template #activator="{ props }">
          <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="$emit('close')">
            <v-icon size="18">mdi-close</v-icon>
          </v-btn>
        </template>
      </v-tooltip>
    </v-card-title>
    <v-card-text>
      <v-container grid-list-md>
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
        <v-row v-if="error.detail.length>0" class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0" cols="3">
            <strong>{{ error.process_step }} Step:</strong>
          </v-col>
          <v-col class="d-flex px-4" cols="9">{{ error.detail }}</v-col>
        </v-row>
        <v-row v-if="error.help_url.length>0" class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0" cols="3"><strong>Help URL:</strong></v-col>
          <v-col class="d-flex px-4" cols="9">
            <a :href="error.help_url" target="_blank">{{ error.help_url }}</a>
          </v-col>
        </v-row>
        <v-row v-if="error.id<9000" class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0">
            <a href="#" @click="goCOUNTER(error.id, error.counter_url)">
              Open COUNTER Details <v-icon>mdi-open-in-new</v-icon>
            </a>
          </v-col>
        </v-row>
      </v-container>
    </v-card-text>
  </v-card>
</template>
