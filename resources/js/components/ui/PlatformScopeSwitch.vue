<!-- resources/js/components/ui/PlatformScopeSwitch.vue -->
<template>
  <v-switch
    :model-value="switchState.modelValue"
    :indeterminate="switchState.indeterminate"
    :color="switchState.color"
    :base-color="switchState.color"
    :label="switchState.label"
    indeterminate-icon="mdi-home-circle"
    false-icon="mdi-star-circle"
    :disabled="disabled"
    hide-details
    @click.prevent="cycleMode"
  />
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  modelValue: { type: String, default: 'All' },
  useConnected: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const switchState = computed(() => {
  if (props.modelValue === 'Active') {
    return {
      modelValue: false,
      indeterminate: false,
      color: 'primary',
      label: props.useConnected
        ? t('fields.connected_only', 'Connected')
        : t('fields.limit_to_consortial', 'Limit to Consortial'),
    };
  }
  if (props.modelValue === 'NonConso') {
    return {
      modelValue: false,
      indeterminate: true,
      color: 'secondary',
      label: props.useConnected
        ? t('fields.not_connected', 'Not Connected')
        : t('fields.exclude_consortial', 'Exclude Consortial'),
    };
  }
  return {
    modelValue: true,
    indeterminate: false,
    color: 'success',
    label: t('fields.all_platforms', 'All Platforms'),
  };
});

function cycleMode() {
  if (props.disabled) return;
  const next =
    props.modelValue === 'All'
      ? 'Active'
      : props.modelValue === 'Active'
        ? 'NonConso'
        : 'All';
  emit('update:modelValue', next);
}
</script>
