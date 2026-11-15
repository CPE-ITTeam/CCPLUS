<!-- resources/js/components/ui/YmInput.vue -->
<script>
export const fyMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
</script>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();
const modelValue = defineModel({ type: String, required: true, default: '' });

const props = defineProps({
  label: { type: String, required: true },
  minYM: { type: String, default: '' },
  rules: { type: Array, default: () => [] },
});

const menuOpen = ref(false);
const yearMonRegex = /^\d{4}-(0[1-9]|1[0-2])$/;

const today = new Date();
const curYr = today.getFullYear();
const curMo = today.getMonth() + 1;
const maxYr = curMo === 1 ? curYr - 1 : curYr;
const maxMo = curMo === 1 ? 12 : curMo - 1;

const minBounds = computed(() => {
  if (yearMonRegex.test(props.minYM)) {
    const [y, m] = props.minYM.split('-').map(Number);
    return { yr: y, mo: m };
  }
  return { yr: 2019, mo: 1 };
});

const pickerYear = ref(maxYr);

watch(
  modelValue,
  (val) => {
    if (yearMonRegex.test(val)) {
      pickerYear.value = Number(val.split('-')[0]);
    } else {
      pickerYear.value = maxYr;
    }
  },
  { immediate: true }
);

const localizedMonths = computed(() => {
  const formatter = new Intl.DateTimeFormat(locale.value || 'en', { month: 'short' });
  return Array.from({ length: 12 }, (_, idx) => {
    const label = formatter.format(new Date(2024, idx, 1));
    return {
      num: idx + 1,
      label: label.charAt(0).toUpperCase() + label.slice(1).replace(/\.$/, ''),
    };
  });
});

function isMonthDisabled(monthNum) {
  if (pickerYear.value < minBounds.value.yr || pickerYear.value > maxYr) return true;
  if (pickerYear.value === minBounds.value.yr && monthNum < minBounds.value.mo) return true;
  if (pickerYear.value === maxYr && monthNum > maxMo) return true;
  return false;
}

function isMonthSelected(monthNum) {
  const ym = `${pickerYear.value}-${String(monthNum).padStart(2, '0')}`;
  return modelValue.value === ym;
}

function selectMonth(monthNum) {
  if (isMonthDisabled(monthNum)) return;
  modelValue.value = `${pickerYear.value}-${String(monthNum).padStart(2, '0')}`;
  menuOpen.value = false;
}
</script>

<template>
  <div>
    <v-menu v-model="menuOpen" :close-on-content-click="false" location="bottom start">
      <template #activator="{ props: menuProps }">
        <v-text-field v-model="modelValue" v-bind="menuProps" :label="label" :rules="rules" :clearable="false"
          prepend-inner-icon="mdi-calendar-month" readonly class="cursor-pointer" />
      </template>

      <v-card width="260" class="pa-3" border>
        <div class="d-flex align-center justify-space-between mb-2">
          <v-btn icon="mdi-chevron-left" variant="text" size="small" density="comfortable"
            :disabled="pickerYear <= minBounds.yr" @click="pickerYear--" />
          <span class="text-subtitle-1 font-weight-bold">{{ pickerYear }}</span>
          <v-btn icon="mdi-chevron-right" variant="text" size="small" density="comfortable"
            :disabled="pickerYear >= maxYr" @click="pickerYear++" />
        </div>

        <v-divider class="mb-2" />

        <v-row dense>
          <v-col v-for="m in localizedMonths" :key="m.num" cols="4">
            <v-btn block size="small" class="text-none" :color="isMonthSelected(m.num) ? 'primary' : undefined"
              :variant="isMonthSelected(m.num) ? 'flat' : 'text'" :disabled="isMonthDisabled(m.num)"
              @click="selectMonth(m.num)">
              {{ m.label }}
            </v-btn>
          </v-col>
        </v-row>
      </v-card>
    </v-menu>
  </div>
</template>
