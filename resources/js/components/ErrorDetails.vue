<template>
  <v-card>
    <v-card-actions>
      <v-icon title="Close" class="close-popup" @click="closeDialog" color="black">
        mdi-close-thick
      </v-icon>
    </v-card-actions>
    <v-card-title>
      <span v-if="error_data.id<9000">COUNTER Error : {{ error_data.id }}</span>
      <span v-else>CC-Plus Error : {{ error_data.id }}</span>
      <v-spacer></v-spacer>
    </v-card-title>
    <v-card-text>
      <v-container grid-list-md>
        <v-row class="d-flex mb-2" no-gutters>
          <v-col class="d-flex">{{ error_data.message }}</v-col>
        </v-row>
        <v-row class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0" cols="3"><strong>Details:</strong></v-col>
          <v-col class="d-flex px-4" cols="9">{{ error_data.explanation }}</v-col>
        </v-row>
        <v-row class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0" cols="3"><strong>Suggestion:</strong></v-col>
          <v-col class="d-flex px-4" cols="9">{{ error_data.suggestion }}</v-col>
        </v-row>
        <v-row v-if="error_data.detail.length>0" class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0" cols="3">
            <strong>{{ error_data.process_step }} Step:</strong>
          </v-col>
          <v-col class="d-flex px-4" cols="9">{{ error_data.detail }}</v-col>
        </v-row>
        <v-row v-if="error_data.help_url.length>0" class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0" cols="3"><strong>Help URL:</strong></v-col>
          <v-col class="d-flex px-4" cols="9">
            <a :href="error_data.help_url" target="_blank">{{ error_data.help_url }}</a>
          </v-col>
        </v-row>
        <v-row v-if="error_data.id<9000" class="d-flex mb-1" no-gutters>
          <v-col class="d-flex pa-0">
            <a href="#" @click="goCOUNTER(error_data.id)">
              Open COUNTER Details <v-icon>mdi-open-in-new</v-icon>
            </a>
          </v-col>
        </v-row>
      </v-container>
    </v-card-text>
  </v-card>
</template>

<script>
export default {
  props: {
    error_data: { type:Object, default: () => {} },
  },
  data () {
    return {
    }
  },
  methods: {
    closeDialog() { this.$emit('close-dialog', true); },
    goCOUNTER(errorId) {
        let features = "";
        let _url = "https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html";
        if ('fragmentDirective' in document) {
          _url += "#:~:text="+errorId.toString();
          features += "noopener";
        }
        window.open(_url, "_blank", features);
    },
  },
  mounted() {
    console.log('Error Details Component mounted.');
  }
}
</script>
<style scoped>
.close-popup {
  position: absolute !important;
  top: 5px;
  right: 5px;
}
</style>
