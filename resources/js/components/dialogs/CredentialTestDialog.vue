<!-- components/dialogs/CredentialTestDialog.vue -->
<script setup>
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

defineProps({
  isTesting: { type: Boolean, default: false },
  passed: { type: Boolean, default: null },
  status: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  targetLabel: { type: String, default: '' },
});

const open = defineModel({ type: Boolean, default: false });
</script>

<template>
  <v-dialog v-model="open" max-width="750">
    <v-card>
      <v-card-item>
        <v-card-title class="text-white">
          {{ $t('credentials.live_test_results', 'Live Credential Test') }}
          <span v-if="targetLabel" class="text-body-2 font-weight-regular ms-2">
            ({{ targetLabel }})
          </span>
        </v-card-title>
        <template #append>
          <v-btn icon="mdi-close" @click="open = false" />
        </template>
      </v-card-item>

      <v-card-text>
        <div v-if="isTesting" class="d-flex align-center ga-3 py-4">
          <v-progress-circular indeterminate color="primary" size="24" />
          <span>{{ $t('credentials.testing_connection', 'Testing COUNTER endpoint...') }}</span>
        </div>

        <template v-else>
          <StatusAlerts
            v-if="status"
            mode="modal"
            :success="passed ? status : ''"
            :failure="!passed ? status : ''"
            :closable="false"
            container-class="mb-4"
          />

          <pre
            class="bg-grey-lighten-4 pa-3 rounded-lg text-body-2 overflow-x-auto"
            style="max-height: 360px; white-space: pre-wrap; word-break: break-word;"
          >{{ rows.join('\n') }}</pre>
        </template>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
