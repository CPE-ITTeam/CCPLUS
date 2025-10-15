// composables/useReportDates
import { ref, computed, watch } from 'vue';
import { useCCPlusStore } from '@/plugins/CCPlusStore.js';
import { useAuthStore } from '@/plugins/authStore.js';

export function useReportDates() {
  const ccplusStore = useCCPlusStore();
  const authStore = useAuthStore();
  const customStartDate = ref('');
  const customEndDate = ref('');
  const reportDates = computed({
    get: () => ccplusStore.reportDates,
    set: (val) => {
      ccplusStore.updateReportDates(val);
    }
  });

  const reportDateOptions = [
    { title: 'Fiscal YTD', value: 'fYTD' },
    { title: 'Prior FY', value: 'priorFy' },
    { title: 'Calendar YTD', value: 'cYTD' },
    { title: 'Custom', value: 'Custom' }
  ];

  const updateDates = () => {
    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();

    const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
    const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;
    const lastMonthFormatted = `${lastMonthYear}-${String(lastMonth + 1).padStart(2, '0')}`;

    const fiscalStartMonth = authStore.user_FYmm || 1;

    if (reportDates.value === 'fYTD') {
      const fyStartYear = lastMonth + 1 < fiscalStartMonth ? currentYear - 1 : currentYear;
      const fyStart = `${fyStartYear}-${String(fiscalStartMonth).padStart(2, '0')}`;
      customStartDate.value = fyStart;
      customEndDate.value = lastMonthFormatted;
    } else if (reportDates.value === 'priorFy') {
      const fyStart = `${currentYear - 1}-${String(fiscalStartMonth).padStart(2, '0')}`;
      const fyEndMonth = fiscalStartMonth === 1 ? 12 : fiscalStartMonth - 1;
      const fyEndYear = fiscalStartMonth === 1 ? currentYear - 1 : currentYear;
      const fyEnd = `${fyEndYear}-${String(fyEndMonth).padStart(2, '0')}`;
      customStartDate.value = fyStart;
      customEndDate.value = fyEnd;
    } else if (reportDates.value === 'cYTD') {
      const cyStart = `${currentYear}-01`;
      customStartDate.value = cyStart;
      customEndDate.value = lastMonthFormatted;
    } else if (reportDates.value === 'Custom') {
      customStartDate.value = '';
      customEndDate.value = '';
    }
  }

  watch(reportDates, updateDates, { immediate: true })

  return { reportDates, reportDateOptions, customStartDate, customEndDate }
}
