<!-- resources/js/components/ui/InstitutionScopeSwitch.vue -->
<template>
  <v-switch
    :model-value="modelValue"
    :color="modelValue ? 'secondary' : 'primary'"
    :base-color="modelValue ? 'secondary' : 'primary'"
    :label="modelValue ? $t('routes.institution_groups', 'Institution Groups') : $t('routes.institutions', 'Institutions')"
    :disabled="disabled"
    @update:model-value="$emit('update:modelValue', Boolean($event))"
  />
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>
