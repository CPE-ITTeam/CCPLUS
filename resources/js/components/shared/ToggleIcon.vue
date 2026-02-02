<!-- components/shared/ToggleIcon.vue -->
<script setup>
  import { computed } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';

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
    size: { type: Number, default: 32 }
  });
  const authStore = useAuthStore();
  const is_admin = authStore.is_admin;
  const is_conso_admin = authStore.is_conso_admin;
  const admin_insts = authStore.admin_insts;
  const admin_groups = authStore.admin_groups;
  const emit = defineEmits(['update:modelValue']);

  const defaultMap = {
    Active:     { icon: 'mdi-toggle-switch',     color: '#00dd00' },
    Inactive:   { icon: 'mdi-toggle-switch-off', color: '#dd0000' },
    Suspended:  { icon: 'mdi-toggle-switch-off', color: '#6d6d6d' },
    Enabled:    { icon: 'mdi-toggle-switch',     color: '#00dd00' },
    Disabled:   { icon: 'mdi-toggle-switch-off', color: '#dd0000' },
    Incomplete: { icon: 'mdi-toggle-switch-off', color: '#ff9800' },
    Suspended:  { icon: 'mdi-toggle-switch-off', color: '#6d6d6d' },
    true:       { icon: 'mdi-toggle-switch',     color: '#00dd00' },
    false:      { icon: 'mdi-toggle-switch-off', color: '#dd0000' },
  };

  const isObjectValue = computed(() => typeof props.modelValue === 'object' && props.modelValue !== null);
  const can_admin = computed(() => 
    (is_conso_admin || (admin_insts.filter(ii => props.modelValue.insts.includes(ii)).length>0)
                    || (admin_groups.filter(gg => props.modelValue.groups.includes(gg)).length>0))           
  );

  const meta = computed(() => {
    if (!isObjectValue.value) {
      const entry = defaultMap[props.modelValue] || { icon: 'mdi-help-circle', color: '#999999' };
      return {
        ...entry,
        clickable: props.toggleable ?? false,
      };
    }

    const { conso, available, requested, insts, groups } = props.modelValue;

    if (!available) {
      return { icon: 'mdi-minus-box-outline', color: '#cccccc', clickable: false };
    }

    if (conso) {
      return (is_conso_admin)
             ? { icon: 'mdi-checkbox-multiple-marked', color: '#00dd00', clickable: true }
             : { icon: 'mdi-checkbox-multiple-marked', color: '#555555', clickable: false };
    }

    if (requested) {
      return ( can_admin.value )
             ? { icon: 'mdi-checkbox-marked-outline', color: '#00dd00', clickable: true }
             : { icon: 'mdi-checkbox-marked-outline', color: '#555555', clickable: false };
// IF we decide to set different icons for group-enabled toggles, this might be handy...
//   return { icon: 'mdi-checkbox-multiple-marked-outline', color: '#00dd00', clickable: true };
    }

    if (!conso && !requested) {
      return ( is_admin)
             ?  { icon: 'mdi-plus-box-outline', color: '#00dd00', clickable: true }
             :  { icon: 'mdi-plus-box-outline', color: '#555555', clickable: false };
    }

    return { icon: 'mdi-help-circle', color: '#999999', clickable: false };
  });

  const tooltip = computed(() => {
    if (!isObjectValue.value) return props.modelValue;

    const { conso, available, requested } = props.modelValue;
    if (!available) return 'Unavailable';
    if (conso) return 'Connected for Consortium';
    if (requested) return 'Connected for 1+ institutions';
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
      if (typeof(next)=='undefined') return;
      if (next) emit('update:modelValue', next);
      return;
    }

    // when isObjectValue = true means its a report-toggle, emit it upstairs
    const { conso, available, requested, insts, groups } = props.modelValue;
    emit('update:modelValue', { available, conso, requested, insts, groups });
  }
</script>

<template>
  <v-icon :icon="meta.icon" :color="meta.color" :size="size" class="cursor-pointer"
          @click="handleClick" :title="tooltip" />
</template>
