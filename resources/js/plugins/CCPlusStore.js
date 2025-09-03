// Pinia store : plugins/CCPlusStore.js
import { defineStore } from 'pinia'
import { useFetch } from '@vueuse/core';
export const useCCPlusStore = defineStore('useCCPlusStore', {
  state: () => {
    return {
      selectedReport: null,
      selectedView: null,
      reportConfig: null,
      reportData: [],
      consortia: [],
    }
  },
  persist: {  // persist for the session
    storage: sessionStorage,
  },
  getters: {
    report_data: (state) => { return state.reportData },
    consd_data: state => {  state.consortia },
  },
  actions: {
    
    updateReportData(data) {
      this.$state.reportData = data;
    },

    async getConsortia() {
      try {
        const { data } = await useFetch("/api/consoList").get().json();
        this.consortia = [...data.value.consortia];
        return { success: true }; // Return success status and data
      } catch (error) {
        return { success: false, error: 'Error fetching consortium list: '+error.message };
      }
    }

  },
});
