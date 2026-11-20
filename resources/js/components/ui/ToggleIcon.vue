<!-- components/ui/ToggleIcon.vue -->
<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/useAuthStore.js';

const { t } = useI18n();

const props = defineProps({
  modelValue: {
    type: [String, Object, Boolean],
    required: true,
    validator: (value) => value !== undefined,
  },
  label: { type: String, default: '' },
  toggleable: { type: Boolean },
  size: { type: [Number, String], default: 32 },
});

const emit = defineEmits(['update:modelValue']);
const authStore = useAuthStore();

const defaultMap = {
  Active:     { icon: 'mdi-toggle-switch',     color: 'success',   labelKey: 'status.active' },
  Enabled:    { icon: 'mdi-toggle-switch',     color: 'success',   labelKey: 'status.enabled' },
  true:       { icon: 'mdi-toggle-switch',     color: 'success',   labelKey: 'status.yes' },
  Inactive:   { icon: 'mdi-toggle-switch-off', color: 'error',     labelKey: 'status.inactive' },
  Disabled:   { icon: 'mdi-toggle-switch-off', color: 'error',     labelKey: 'status.disabled' },
  false:      { icon: 'mdi-toggle-switch-off', color: 'error',     labelKey: 'status.no' },
  Incomplete: { icon: 'mdi-toggle-switch-off', color: 'warning',   labelKey: 'status.incomplete' },
  Suspended:  { icon: 'mdi-toggle-switch-off', color: 'secondary', labelKey: 'status.suspended' },
};

const isObjectValue = computed(() => typeof props.modelValue === 'object' && props.modelValue !== null);

const can_admin = computed(() => {
  if (!isObjectValue.value) return false;
  const matchInsts = authStore.admin_insts.some((ii) => props.modelValue.insts?.includes(ii));
  const matchGroups = authStore.admin_groups.some((gg) => props.modelValue.groups?.includes(gg));
  return authStore.is_conso_admin || matchInsts || matchGroups;
});

const meta = computed(() => {
  if (!isObjectValue.value) {
    const entry = defaultMap[String(props.modelValue)] || { icon: 'mdi-help-circle', color: 'secondary' };
    return { ...entry, clickable: props.toggleable ?? false };
  }

  const { conso, available, requested } = props.modelValue;

  if (!available) {
    return { icon: 'mdi-minus-box-outline', color: 'secondary', clickable: false };
  }

  if (conso) {
    return {
      icon: 'mdi-checkbox-multiple-marked',
      color: authStore.is_conso_admin ? 'primary' : 'secondary',
      clickable: authStore.is_conso_admin,
    };
  }

  if (requested) {
    return {
      icon: 'mdi-checkbox-marked-outline',
      color: can_admin.value ? 'success' : 'secondary',
      clickable: can_admin.value,
    };
  }

  return {
    icon: 'mdi-plus-box-outline',
    color: authStore.is_admin ? 'success' : 'secondary',
    clickable: authStore.is_admin,
  };
});

const tooltip = computed(() => {
  if (!isObjectValue.value) {
    const entry = defaultMap[String(props.modelValue)];
    return entry?.labelKey ? t(entry.labelKey) : String(props.modelValue);
  }

  const { conso, available, requested } = props.modelValue;
  if (!available) return t('status.unavailable');
  if (conso) return t('status.connected_consortium');
  if (requested) return t('status.connected_institutions');
  return t('status.click_to_request');
});

function handleClick() {
  if (!meta.value.clickable) return;

  if (!isObjectValue.value) {
    const valStr = String(props.modelValue);
    const toggles = {
      Active: 'Inactive',
      Inactive: 'Active',
      Enabled: 'Disabled',
      Disabled: 'Enabled',
      true: 'false',
      false: 'true',
    };
    const next = toggles[valStr];
    if (next !== undefined) {
      const output = next === 'true' ? true : next === 'false' ? false : next;
      emit('update:modelValue', output);
    }
    return;
  }

  emit('update:modelValue', { ...props.modelValue });
}
</script>
<template>
  <button
    type="button"
    :aria-disabled="!meta.clickable"
    :aria-label="props.label ? `${props.label}: ${tooltip}` : tooltip"
    :title="tooltip"
    class="d-inline-flex align-center justify-start text-left border-0 bg-transparent pa-0 text-none rounded"
    :class="meta.clickable ? 'cursor-pointer' : 'cursor-default'"
    @click="handleClick"
  >
    <i
      :class="['mdi', meta.icon, `text-${meta.color}`, 'flex-shrink-0']"
      :style="{ fontSize: typeof size === 'number' ? `${size}px` : size, lineHeight: 1 }"
      aria-hidden="true"
    />

    <span v-if="props.label !== ''" class="ms-2 text-body-2 font-weight-medium text-medium-emphasis">
      {{ props.label }}
    </span>
  </button>
</template>
