// resources/js/composables/useReportingDates.js
import { useAuthStore } from '@/stores/useAuthStore';

export function useReportingDates() {
  const authStore = useAuthStore();

  function fromToDates(range) {
    let startDate = "";
    let endDate = "";
    
    const today = new Date();
    const currentMonth = today.getMonth(); // 0 = Jan, 11 = Dec
    const currentYear = today.getFullYear();
    
    // Calculate the last fully completed month boundary
    const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
    const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;
    const lastMonthFormatted = `${lastMonthYear}-${String(lastMonth + 1).padStart(2, "0")}`;
    
    // Fallback to July (7) if account configuration is missing
    const fiscalStartMonth = Number(authStore.user_FYmm) || 7;

    if (range === "cYTD") {
      // 📅 Calendar YTD
      startDate = `${currentYear}-01`;
      endDate = lastMonthFormatted;

    } else if (range === "fYTD") {
      // 🏛️ Fiscal YTD
      const fyStartYear = (lastMonth + 1) < fiscalStartMonth ? currentYear - 1 : currentYear;
      startDate = `${fyStartYear}-${String(fiscalStartMonth).padStart(2, "0")}`;
      endDate = lastMonthFormatted;

    } else if (range === "priorFy") {
      // 🏛️ Last Fiscal Year (Handles January-start overlaps smoothly)
      const currentFyStartYear = (lastMonth + 1) < fiscalStartMonth ? currentYear - 1 : currentYear;
      const priorFyStartYear = currentFyStartYear - 1;
      
      startDate = `${priorFyStartYear}-${String(fiscalStartMonth).padStart(2, "0")}`;
      
      const fyEndMonth = fiscalStartMonth === 1 ? 12 : fiscalStartMonth - 1;
      const fyEndYear = fiscalStartMonth === 1 ? priorFyStartYear : currentFyStartYear;
      endDate = `${fyEndYear}-${String(fyEndMonth).padStart(2, "0")}`;

    } else if (range === "priorCy") {
      // 📅 Last Calendar Year
      startDate = `${currentYear - 1}-01`;
      endDate = `${currentYear - 1}-12`;

    } else if (range === "last12Months") {
      // 🔄 Rolling Last 12 Months
      const rollingStartMonth = currentMonth; 
      const rollingStartYear = currentYear - 1;
      startDate = `${rollingStartYear}-${String(rollingStartMonth + 1).padStart(2, "0")}`;
      endDate = lastMonthFormatted;

    } else if (range === "Custom") {
      startDate = "";
      endDate = "";
    }

    return { from: startDate, to: endDate };
  }

  return { fromToDates };
}
