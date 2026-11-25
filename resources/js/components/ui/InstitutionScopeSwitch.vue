<!-- resources/js/components/ui/InstitutionScopeSwitch.vue -->
<template>
  <v-switch
    :model-value="modelValue"
    :color="modelValue ? 'primary' : 'secondary'"
    :base-color="modelValue ? 'primary' : 'secondary'"
    :label="modelValue ? $t('routes.institution_groups', 'Institution Groups') : $t('routes.institutions', 'Institutions')"
    :disabled="disabled"
    hide-details
    @update:model-value="$emit('update:modelValue', Boolean($event))"
  />
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>
