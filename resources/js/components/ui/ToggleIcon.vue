<!-- components/ui/ToggleIcon.vue -->
<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';

const props = defineProps({
  modelValue: {
    type: [String, Object, Boolean],
    required: true,
    validator: (value) => value !== undefined
  },
  label: { type: String, default: '' },
  toggleable: { type: Boolean },
  size: { type: [Number, String], default: 32 }
});

const emit = defineEmits(['update:modelValue']);
const authStore = useAuthStore();

const defaultMap = {
  Active:     { icon: 'mdi-toggle-switch',     color: 'success' },
  Enabled:    { icon: 'mdi-toggle-switch',     color: 'success' },
  true:       { icon: 'mdi-toggle-switch',     color: 'success' },
  Inactive:   { icon: 'mdi-toggle-switch-off', color: 'error' },
  Disabled:   { icon: 'mdi-toggle-switch-off', color: 'error' },
  false:      { icon: 'mdi-toggle-switch-off', color: 'error' },
  Incomplete: { icon: 'mdi-toggle-switch-off', color: 'warning' },
  // 💡 OPTIMIZED: Swapped 'medium-emphasis' for an actual framework fallback color token
  Suspended:  { icon: 'mdi-toggle-switch-off', color: 'secondary' } 
};

const isObjectValue = computed(() => typeof props.modelValue === 'object' && props.modelValue !== null);

const can_admin = computed(() => {
  if (!isObjectValue.value) return false;
  const matchInsts = authStore.admin_insts.some(ii => props.modelValue.insts?.includes(ii));
  const matchGroups = authStore.admin_groups.some(gg => props.modelValue.groups?.includes(gg));
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
      color: authStore.is_conso_admin ? 'success' : 'secondary', 
      clickable: authStore.is_conso_admin 
    };
  }

  if (requested) {
    return { 
      icon: 'mdi-checkbox-marked-outline', 
      color: can_admin.value ? 'success' : 'secondary', 
      clickable: can_admin.value 
    };
  }

  return { 
    icon: 'mdi-plus-box-outline', 
    color: authStore.is_admin ? 'success' : 'secondary', 
    clickable: authStore.is_admin 
  };
});

const tooltip = computed(() => {
  if (!isObjectValue.value) return String(props.modelValue);

  const { conso, available, requested } = props.modelValue;
  if (!available) return 'Unavailable';
  if (conso) return 'Connected for Consortium';
  if (requested) return 'Connected for 1+ institutions';
  return 'Click to Request';
});

function handleClick() {
  if (!meta.value.clickable) return;
  
  if (!isObjectValue.value) {
    const valStr = String(props.modelValue);
    const toggles = {
      'Active': 'Inactive', 'Inactive': 'Active',
      'Enabled': 'Disabled', 'Disabled': 'Enabled',
      'true': 'false', 'false': 'true',
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
  <!-- 
    💡 FIXED FOR WCAG:
    1. Replaced native ':disabled' with semantic 'aria-disabled' so screen readers can always focus the element.
    2. Added an explicit 'aria-label' to concatenate the state context string for screen readers cleanly.
  -->
  <button
    type="button"
    :aria-disabled="!meta.clickable"
    :aria-label="props.label ? `${props.label}: ${tooltip}` : tooltip"
    :title="tooltip"
    class="d-inline-flex align-center justify-start text-left border-none bg-transparent pa-0 text-none custom-toggle-btn"
    :class="{ 'clickable-action': meta.clickable, 'read-only-state': !meta.clickable }"
    @click="handleClick"
  >
    <v-icon 
      :icon="meta.icon" 
      :color="meta.color" 
      :size="size" 
      class="flex-shrink-0"
    />
    
    <span v-if="props.label !== ''" class="ms-2 text-body-2 font-weight-medium text-medium-emphasis">
      {{ props.label }}
    </span>
  </button>
</template>

<style scoped>
.custom-toggle-btn {
  outline: none;
  color: inherit;
}
.clickable-action {
  cursor: pointer;
}
.read-only-state {
  cursor: default;
}
/* 💡 ENHANCED FOCUS RING: Enforces a multi-layered accessible focus boundary regardless of backdrop card colors */
.custom-toggle-btn:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
  border-radius: 4px;
}
</style>
