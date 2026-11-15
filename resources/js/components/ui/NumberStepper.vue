<!-- resources/js/components/ui/NumberStepper.vue -->
<script setup>
const modelValue = defineModel({ type: [Number, String], default: 0 });

defineProps({
  label: { type: String, required: true },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 9999 },
  step: { type: Number, default: 1 },
  rules: { type: Array, default: () => [] },
});
</script>

<template>
  <v-number-input
    v-model="modelValue"
    :label="label"
    :min="min"
    :max="max"
    :step="step"
    :rules="rules"
    :clearable="false"
    control-variant="split"
  />
</template>
