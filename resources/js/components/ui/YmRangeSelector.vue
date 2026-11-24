<!-- components/ui/YmRangeSelector.vue -->
<template>
  <v-row v-if="inline" dense align="center" class="w-100">
    <v-col cols="12" sm="6">
      <v-select
        v-model="preset"
        :label="label || $t('fields.date_range')"
        :items="rangeOptions"
        item-title="title"
        item-value="value"
        class="w-100"
        @update:model-value="onPresetChange"
      />
    </v-col>
    <v-col cols="12" sm="6">
      <v-row dense>
        <v-col cols="6">
          <YmInput
            :model-value="modelValue.fromYM"
            :label="$t('reports.start_month')"
            class="w-100"
            @update:model-value="onMonthChange('fromYM', $event)"
          />
        </v-col>
        <v-col cols="6">
          <YmInput
            :model-value="modelValue.toYM"
            :label="$t('reports.end_month')"
            :minYM="modelValue.fromYM || undefined"
            class="w-100"
            @update:model-value="onMonthChange('toYM', $event)"
          />
        </v-col>
      </v-row>
    </v-col>
  </v-row>

  <div v-else class="d-flex flex-column ga-3">
    <v-select
      v-model="preset"
      :label="label || $t('fields.date_range')"
      :items="rangeOptions"
      item-title="title"
      item-value="value"
      @update:model-value="onPresetChange"
    />
    <div class="d-flex flex-row ga-2">
      <YmInput
        :model-value="modelValue.fromYM"
        :label="$t('reports.start_month')"
        style="flex: 1 1 0; min-width: 0;"
        @update:model-value="onMonthChange('fromYM', $event)"
      />
      <YmInput
        :model-value="modelValue.toYM"
        :label="$t('reports.end_month')"
        :minYM="modelValue.fromYM || undefined"
        style="flex: 1 1 0; min-width: 0;"
        @update:model-value="onMonthChange('toYM', $event)"
      />
    </div>
  </div>
</template>


<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useReportingDates } from '@/composables/useReportingDates.js';
import YmInput from '@/components/ui/YmInput.vue';

const props = defineProps({
  label: { type: String, default: '' },
  fyStart: { type: [Number, String], default: null },
  inline: { type: Boolean, default: false },
  defaultPreset: { type: String, default: null },
});

const modelValue = defineModel({
  type: Object,
  default: () => ({ fromYM: '', toYM: '' }),
});

const { t } = useI18n();
const { fromToDates } = useReportingDates();

const preset = ref(null);
const isApplyingPreset = ref(false);

const rangeOptions = computed(() => [
  { title: t('reports.c_ytd', 'Calendar YTD'), value: 'cYTD' },
  { title: t('reports.f_ytd', 'Fiscal YTD'), value: 'fYTD' },
  { title: t('reports.prior_fy', 'Prior Fiscal Year'), value: 'priorFy' },
  { title: t('reports.prior_cy', 'Prior Calendar Year'), value: 'priorCy' },
  { title: t('reports.last_12', 'Last 12 Months'), value: 'last12Months' },
  { title: t('reports.custom', 'Custom'), value: 'Custom' },
]);

watch(
  [() => modelValue.value, () => props.fyStart],
  ([val], [, oldFy]) => {
    if (!val || (!val.fromYM && !val.toYM)) {
      if (props.defaultPreset) {
        preset.value = props.defaultPreset;
        onPresetChange(props.defaultPreset);
      } else {
        preset.value = null;
      }
    } else if (props.fyStart !== oldFy && preset.value && preset.value !== 'Custom') {
      onPresetChange(preset.value);
    }
  },
  { immediate: true, deep: true }
);

function onPresetChange(val) {
  if (!val) {
    modelValue.value = { fromYM: '', toYM: '' };
    return;
  }
  if (val === 'Custom') {
    modelValue.value = {
      fromYM: modelValue.value?.fromYM || '',
      toYM: modelValue.value?.toYM || '',
    };
    return;
  }
  isApplyingPreset.value = true;
  const dates = fromToDates(val, props.fyStart);
  modelValue.value = { fromYM: dates.from, toYM: dates.to };
  setTimeout(() => {
    isApplyingPreset.value = false;
  }, 0);
}

function onMonthChange(bound, val) {
  if (!isApplyingPreset.value) {
    preset.value = 'Custom';
  }
  modelValue.value = {
    ...(modelValue.value || { fromYM: '', toYM: '' }),
    [bound]: val || '',
  };
}
</script>
