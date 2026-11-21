<!-- components/tables/cells/CredentialCell.vue -->

<template>
  <span
    v-if="value === '-required-'"
    class="d-inline-flex align-center justify-center text-warning"
    role="img"
    :aria-label="$t('fields.required', 'Required')"
    :title="$t('fields.required', 'Required')"
  >
    <i class="mdi mdi-asterisk text-warning" style="font-size: 20px; line-height: 1;" aria-hidden="true" />
  </span>

  <button
    v-else-if="value != null && String(value).trim() !== ''"
    type="button"
    class="d-inline-flex align-center justify-center border-0 bg-transparent pa-0 cursor-pointer"
    :aria-label="`${$t(`fields.${field}`)}: ${value} (${$t('common.click_to_copy', 'Click to copy')})`"
    :title="copied ? $t('common.copied', 'Copied!') : `${value} (${$t('common.click_to_copy', 'Click to copy')})`"
    @click.stop="$emit('copy')"
  >
    <v-icon
      :icon="copied ? 'mdi-check' : 'mdi-key-chain'"
      :color="copied ? 'primary' : 'success'"
      :size="24"
      aria-hidden="true"
    />
  </button>

  <span v-else class="text-medium-emphasis">&mdash;</span>
</template>

<script setup>
defineProps({
  field: { type: String, required: true },
  value: { type: [String, Number], default: '' },
  copied: { type: Boolean, default: false },
});

defineEmits(['copy']);
</script>
