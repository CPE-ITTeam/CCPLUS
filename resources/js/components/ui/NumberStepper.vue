<!-- resources/js/components/ui/NumberStepper.vue -->
<script setup>
const modelValue = defineModel({ type: [Number, String, null], default: 0 });

defineProps({
  label: { type: String, required: true },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 9999 },
  step: { type: Number, default: 1 },
  rules: { type: Array, default: () => [] },
  displayText: { type: String, default: '' },
  clearable: { type: Boolean, default: undefined },
});

const emit = defineEmits(['click:clear']);
</script>

<template>
  <div class="number-stepper-wrap" :class="{ 'has-display-text': !!displayText }">
    <v-number-input
      v-model="modelValue"
      :label="label"
      :min="min"
      :max="max"
      :step="step"
      :rules="rules"
      :clearable="clearable"
      control-variant="split"
      @keydown="displayText ? $event.preventDefault() : null"
      @click:clear="emit('click:clear')"
    />
    <span v-if="displayText" class="number-stepper-display text-body-1">
      {{ displayText }}
    </span>
  </div>
</template>

<style scoped>
.number-stepper-wrap {
  position: relative;
}

.number-stepper-wrap.has-display-text :deep(input) {
  color: transparent !important;
  caret-color: transparent !important;
  user-select: none;
}

.number-stepper-display {
  position: absolute;
  left: 48px;
  right: 72px;
  top: 50%;
  transform: translateY(-50%);
  text-align: center;
  pointer-events: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
