<!-- resources/js/components/ui/SettingsPanelShell.vue -->
<script setup>
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: '' },
  submitLabel: { type: String, default: '' },
  success: { type: String, default: '' },
  failure: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});

const valid = defineModel('valid', { type: Boolean, default: true });
defineEmits(['submit']);
</script>

<template>
  <v-form v-model="valid" @submit.prevent="$emit('submit')">
    <v-card class="rounded-lg overflow-hidden">
      <v-card-title class="bg-info text-white font-weight-bold py-3 px-4 d-flex align-center">
        <v-icon v-if="icon" start size="24" class="me-2" aria-hidden="true">{{ icon }}</v-icon>
        {{ title }}
      </v-card-title>

      <v-card-text class="pa-6">
        <slot />

        <!-- Status Banners -->
        <v-row v-if="success || failure" class="mt-4" no-gutters>
          <v-col cols="12">
            <StatusAlerts :success="success" :failure="failure" mode="modal" container-class="mb-0"
              @update:success="$emit('update:success', $event)" @update:failure="$emit('update:failure', $event)" />
          </v-col>
        </v-row>

        <!-- Optional Footer Action Slot or Default Submit Button -->
        <template v-if="submitLabel || $slots.actions">
          <v-divider class="my-5" />
          <div class="d-flex justify-start ga-3">
            <slot name="actions">
              <v-btn type="submit" color="primary" class="px-6" :loading="loading">
                {{ submitLabel }}
              </v-btn>
            </slot>
          </div>
        </template>
      </v-card-text>
    </v-card>
  </v-form>
</template>
