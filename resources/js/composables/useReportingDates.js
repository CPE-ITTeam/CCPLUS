// resources/js/composables/useReportingDates.js
import { useAuthStore } from '@/stores/useAuthStore';

const MONTH_NAMES = [
  'january', 'february', 'march', 'april', 'may', 'june',
  'july', 'august', 'september', 'october', 'november', 'december',
];

function normalizeFiscalStartMonth(val) {
  if (val === undefined || val === null || val === '') return null;
  if (Array.isArray(val)) return normalizeFiscalStartMonth(val[0]);
  const asNum = Number(val);
  if (!Number.isNaN(asNum) && asNum >= 1 && asNum <= 12) return asNum;
  const idx = MONTH_NAMES.indexOf(String(val).trim().toLowerCase());
  return idx >= 0 ? idx + 1 : null;
}

export function useReportingDates() {
  const authStore = useAuthStore();

  function fromToDates(range, overrideFyStart = null) {
    let startDate = '';
    let endDate = '';

    const today = new Date();
    const currentMonth = today.getMonth(); // 0 = Jan, 11 = Dec
    const currentYear = today.getFullYear();

    // Calculate the last fully completed month boundary
    const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
    const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;
    const lastMonthFormatted = `${lastMonthYear}-${String(lastMonth + 1).padStart(2, '0')}`;

    // Use explicit override if provided, otherwise fall back to authStore user FY
    const fiscalStartMonth =
      normalizeFiscalStartMonth(overrideFyStart) ??
      normalizeFiscalStartMonth(authStore.user?.user_FYmm || authStore.user_FYmm) ??
      7;

    if (range === 'cYTD') {
      startDate = `${currentYear}-01`;
      endDate = lastMonthFormatted;
    } else if (range === 'fYTD') {
      const fyStartYear = lastMonth + 1 < fiscalStartMonth ? currentYear - 1 : currentYear;
      startDate = `${fyStartYear}-${String(fiscalStartMonth).padStart(2, '0')}`;
      endDate = lastMonthFormatted;
    } else if (range === 'priorFy') {
      const currentFyStartYear = lastMonth + 1 < fiscalStartMonth ? currentYear - 1 : currentYear;
      const priorFyStartYear = currentFyStartYear - 1;
      startDate = `${priorFyStartYear}-${String(fiscalStartMonth).padStart(2, '0')}`;

      const fyEndMonth = fiscalStartMonth === 1 ? 12 : fiscalStartMonth - 1;
      const fyEndYear = fiscalStartMonth === 1 ? priorFyStartYear : currentFyStartYear;
      endDate = `${fyEndYear}-${String(fyEndMonth).padStart(2, '0')}`;
    } else if (range === 'priorCy') {
      startDate = `${currentYear - 1}-01`;
      endDate = `${currentYear - 1}-12`;
    } else if (range === 'last12Months') {
      const rollingStartMonth = currentMonth;
      const rollingStartYear = currentYear - 1;
      startDate = `${rollingStartYear}-${String(rollingStartMonth + 1).padStart(2, '0')}`;
      endDate = lastMonthFormatted;
    } else if (range === 'Custom') {
      startDate = '';
      endDate = '';
    }

    return { from: startDate, to: endDate };
  }

  return { fromToDates };
}
