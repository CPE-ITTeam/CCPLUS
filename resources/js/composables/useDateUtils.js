// resources/js/composables/useDateUtils.js
import { useAuthStore } from '@/stores/useAuthStore';

export function useDateUtils() {
  const authStore = useAuthStore();

  function fromToDates(range) {
    let startDate = "";
    let endDate = "";
    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();
    const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
    const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;
    const lastMonthFormatted = `${lastMonthYear}-${String(lastMonth + 1).padStart(2, "0")}`;
    
    // Automatically reads your safe, refactored getter parameter!
    const fiscalStartMonth = authStore.user_FYmm || 1;

    if (range === "fYTD") {
      const fyStartYear = lastMonth + 1 < fiscalStartMonth ? currentYear - 1 : currentYear;
      startDate = `${fyStartYear}-${String(fiscalStartMonth).padStart(2, "0")}`;
      endDate = lastMonthFormatted;
    } else if (range === "priorFy") {
      startDate = `${currentYear - 1}-${String(fiscalStartMonth).padStart(2, "0")}`;
      const fyEndMonth = fiscalStartMonth === 1 ? 12 : fiscalStartMonth - 1;
      const fyEndYear = fiscalStartMonth === 1 ? currentYear - 1 : currentYear;
      endDate = `${fyEndYear}-${String(fyEndMonth).padStart(2, "0")}`;
    } else if (range === "cYTD") {
      startDate = `${currentYear}-01`;
      endDate = lastMonthFormatted;
    } else if (range === "Custom") {
      startDate = "";
      endDate = "";
    }
    return { from: startDate, to: endDate };
  }

  return { fromToDates };
}
