<!-- components/tables/toolbar/AuditStatusBar.vue -->

<template>
  <div class="d-flex flex-wrap align-center ga-2 ms-auto">
    <!-- Validation / Status Chips -->
    <v-chip
      v-for="item in validationChips"
      :key="item.value"
      size="small"
      :variant="activeValidTypes.includes(item.value) ? 'flat' : 'outlined'"
      :color="item.color"
      :prepend-icon="activeValidTypes.includes(item.value) ? 'mdi-check' : 'mdi-filter-outline'"
      class="font-weight-medium cursor-pointer"
      :title="`Toggle ${item.label} filter`"
      @click="$emit('setValidType', item.value)"
    >
      {{ item.label }}: <strong class="ms-1">{{ item.count }}</strong>
    </v-chip>

    <!-- Sub-target Chips when Not Validated is active -->
    <template v-if="targetChips.length > 0">
      <v-chip
        v-for="tgt in targetChips"
        :key="tgt.value"
        size="x-small"
        :variant="activeValidTargets.includes(tgt.value) ? 'flat' : 'outlined'"
        :color="tgt.color || 'warning'"
        :prepend-icon="activeValidTargets.includes(tgt.value) ? 'mdi-check' : undefined"
        class="font-weight-medium cursor-pointer"
        @click="$emit('setValidTarget', tgt.value)"
      >
        {{ tgt.label }}: <strong class="ms-1">{{ tgt.count }}</strong>
      </v-chip>
    </template>


    <!-- JSON Record Visibility Chips (Only shown when Not Validated is active) -->
    <template v-if="showJsonChips && jsonChips.length > 0">
      <v-divider vertical class="mx-1" />
      <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
        JSON:
      </span>
      <v-chip
        v-for="item in jsonChips"
        :key="item.key"
        size="small"
        :variant="activeJsonFilters.includes(item.key) ? 'flat' : 'outlined'"
        color="secondary"
        :prepend-icon="activeJsonFilters.includes(item.key) ? 'mdi-eye-outline' : 'mdi-eye-off-outline'"
        class="font-weight-medium cursor-pointer"
        :title="activeJsonFilters.includes(item.key) ? `Hide ${item.label} JSON records` : `Show ${item.label} JSON records`"
        @click="$emit('toggleJson', item.key)"
      >
        {{ item.label }}: <strong class="ms-1">{{ item.count }}</strong>
      </v-chip>
    </template>
  </div>
</template>

<script setup>
defineProps({
  validationChips: { type: Array, default: () => [] },
  targetChips: { type: Array, default: () => [] },
  jsonChips: { type: Array, default: () => [] },
  showJsonChips: { type: Boolean, default: false },
  activeValidTypes: { type: Array, default: () => ['Not Validated'] },
  activeValidTargets: { type: Array, default: () => [] },
  activeJsonFilters: { type: Array, default: () => [] },
});

defineEmits(['setValidType', 'setValidTarget', 'toggleJson']);
</script>
