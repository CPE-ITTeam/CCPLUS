<!-- components/ui/StatusAlerts.vue -->
<script setup>
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';

const props = defineProps({
  mode: {
    type: String,
    default: 'page', // 'page' | 'modal'
    validator: (v) => ['page', 'modal'].includes(v),
  },
  containerClass: {
    type: String,
    default: '',
  },
  global: {
    type: Boolean,
    default: false,
  },
  closable: {
    type: Boolean,
    default: true,
  },
});

const success = defineModel('success', { type: [String, Array], default: '' });
const failure = defineModel('failure', { type: [String, Array], default: '' });
const warning = defineModel('warning', { type: [String, Array], default: '' });
const info = defineModel('info', { type: [String, Array], default: '' });

const ccPlusStore = useCCPlusStore();
const {
  tableSuccessMessage,
  tableErrorMessage,
  tableWarningMessage,
  tableInfoMessage,
} = storeToRefs(ccPlusStore);

const isModal = computed(() => props.mode === 'modal');

const alertDefaults = computed(() => ({
  closable: props.closable,
  density: isModal.value ? 'compact' : 'default',
  class: props.containerClass || (isModal.value ? 'mb-2' : 'my-3 mx-4'),
}));

const formatText = (val) => (Array.isArray(val) ? val.filter(Boolean).join(' ') : (val || ''));

const activeAlerts = computed(() => {
  const entries = [
    {
      key: 'failure',
      type: 'error',
      text: formatText(failure.value || (props.global ? tableErrorMessage.value : '')),
      clear: () => {
        failure.value = '';
        if (props.global) tableErrorMessage.value = null;
      },
    },
    {
      key: 'warning',
      type: 'warning',
      text: formatText(warning.value || (props.global ? tableWarningMessage.value : '')),
      clear: () => {
        warning.value = '';
        if (props.global) tableWarningMessage.value = null;
      },
    },
    {
      key: 'success',
      type: 'success',
      text: formatText(success.value || (props.global ? tableSuccessMessage.value : '')),
      clear: () => {
        success.value = '';
        if (props.global) tableSuccessMessage.value = null;
      },
    },
    {
      key: 'info',
      type: 'info',
      text: formatText(info.value || (props.global ? tableInfoMessage.value : '')),
      clear: () => {
        info.value = '';
        if (props.global) tableInfoMessage.value = null;
      },
    },
  ];

  return entries.filter((item) => Boolean(item.text));
});
</script>

<template>
  <template v-for="alert in activeAlerts" :key="alert.key">
    <v-alert
      :type="alert.type"
      :text="alert.text"
      v-bind="alertDefaults"
      @click:close="alert.clear"
    />
  </template>
</template>
