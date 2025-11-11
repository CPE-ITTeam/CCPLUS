<!-- components/shared/ToggleIcon.vue -->
<script setup>
  import { computed } from 'vue';

  const props = defineProps({
    
    modelValue: {
      validator: (value) => { // validator allows modelValue to be string or object
        const isString = typeof value === 'string';
        const isObject = typeof value === 'object' && value !== null;
        if (!isString && !isObject) {
          console.warn('Invalid prop: "modelValue" must be a string or an object.');
        }
        return isString || isObject;
      }
    },
    toggleable: { type: Boolean },
    statusMap: { type: Object },
    size: { type: Number, default: 32 }
  });
  const emit = defineEmits(['update:modelValue']);

  const defaultMap = {
    Active:     { icon: 'mdi-toggle-switch',     color: '#00dd00' },
    Inactive:   { icon: 'mdi-toggle-switch-off', color: '#dd0000' },
    Suspended:  { icon: 'mdi-toggle-switch-off', color: '#6d6d6d' },
    Incomplete: { icon: 'mdi-toggle-switch-off', color: '#ff9800' },
    true:       { icon: 'mdi-toggle-switch',     color: '#00dd00' },
    false:      { icon: 'mdi-toggle-switch-off', color: '#dd0000' },
  };

  const isObjectValue = computed(() => typeof props.modelValue === 'object' && props.modelValue !== null);

  const meta = computed(() => {
    if (!isObjectValue.value) {
      const map = props.statusMap ?? defaultMap;
      const entry = map[props.modelValue] || { icon: 'mdi-help-circle', color: '#999999' };
      return {
        ...entry,
        clickable: props.toggleable ?? false,
      };
    }

    const { conso, available, requested } = props.modelValue;

    if (!available) {
      return { icon: 'mdi-minus-box-outline', color: '#cccccc', clickable: false };
    }

    if (conso) {
      return { icon: 'mdi-checkbox-multiple-marked', color: '#00dd00', clickable: false };
    }

    if (!conso && !requested) {
      return { icon: 'mdi-plus-box-outline', color: '#555555', clickable: true };
    }

    if (!conso && requested) {
      return { icon: 'mdi-checkbox-marked-outline', color: '#00dd00', clickable: true };
    }

    return { icon: 'mdi-help-circle', color: '#999999', clickable: false };
  });

  const tooltip = computed(() => {
    if (!isObjectValue.value) return props.modelValue;

    const { conso, available, requested } = props.modelValue;
    if (!available) return 'Unavailable';
    if (conso) return 'Connected for Consortium';
    if (!conso && !requested) return 'Click to Request';
    if (!conso && requested) return 'Click to Cancel Request';
    return 'Unknown State';
  });

  function handleClick() {
    if (!meta.value.clickable) return;
    if (!isObjectValue.value) {
      if (!props.toggleable) return;
      const toggles = {
        'Active': 'Inactive', 'Inactive': 'Active',
        'connected': 'disconnected', 'disconnected': 'connected',
        'true': false, 'false': true,
      };
      const next = toggles[props.modelValue];
      if (next) emit('update:modelValue', next);
      return;
    }

    const { conso, available, requested } = props.modelValue;
    if (conso) return;

    emit('update:modelValue', { conso, available, requested: !requested, });
  }
</script>

<template>
  <v-icon :icon="meta.icon" :color="meta.color" :size="size" class="cursor-pointer"
          @click="handleClick" :title="tooltip" />
</template>
