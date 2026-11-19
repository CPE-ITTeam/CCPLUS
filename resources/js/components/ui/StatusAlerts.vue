<!-- components/ui/StatusAlerts.vue -->
<script setup>
import { computed } from 'vue';

const props = defineProps({
  mode: {
    type: String,
    default: 'page', // 'page' (solid main-page banner) | 'modal' (compact tonal dialog/panel banner)
    validator: (v) => ['page', 'modal'].includes(v),
  },
  containerClass: {
    type: String,
    default: '',
  },
});

const success = defineModel('success', { type: String, default: '' });
const failure = defineModel('failure', { type: String, default: '' });
const warning = defineModel('warning', { type: String, default: '' });

const isModal = computed(() => props.mode === 'modal');

const alertProps = computed(() => ({
  closable: true,
  variant: isModal.value ? 'tonal' : 'flat',
  density: isModal.value ? 'compact' : 'default',
  class: props.containerClass || (isModal.value ? 'mb-2' : 'my-3 mx-4'),
}));
</script>

<template>
  <v-alert
    v-if="failure"
    type="error"
    :text="failure"
    v-bind="alertProps"
    @click:close="failure = ''"
  >
    <template #close="{ props: closeProps }">
      <v-btn v-bind="closeProps" icon variant="text" class="opacity-100">
        <v-icon icon="mdi-close-circle" size="default" />
      </v-btn>
    </template>
  </v-alert>

    <v-alert
    v-else-if="warning"
    type="warning"
    :text="warning"
    v-bind="alertProps"
    @click:close="warning = ''"
  >
    <template #close="{ props: closeProps }">
      <v-btn
        v-bind="closeProps"
        icon
        variant="text"
        class="opacity-100"
      >
        <v-icon icon="mdi-close-circle" :size="isModal ? 'default' : 28" />
      </v-btn>
    </template>
  </v-alert>



  <v-alert
    v-else-if="success"
    type="success"
    :text="success"
    v-bind="alertProps"
    @click:close="success = ''"
  >
    <template #close="{ props: closeProps }">
      <v-btn
        v-bind="closeProps"
        icon
        variant="text"
        :color="isModal ? undefined : 'white'"
        class="opacity-100"
      >
        <v-icon icon="mdi-close-circle" size="28" />
      </v-btn>
    </template>
  </v-alert>
</template>
