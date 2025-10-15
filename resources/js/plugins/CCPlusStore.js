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
      reportDates: '',
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
        this.consortia = [...data.value.records];
        return { success: true }; // Return success status and data
      } catch (error) {
        return { success: false, error: 'Error fetching consortium list: '+error.message };
      }
    }

  },
});

export const fyMonths = [
  { label: 'January', value: '01' },
  { label: 'February', value: '02' },
  { label: 'March', value: '03' },
  { label: 'April', value: '04' },
  { label: 'May', value: '05' },
  { label: 'June', value: '06' },
  { label: 'July', value: '07' },
  { label: 'August', value: '08' },
  { label: 'September', value: '09' },
  { label: 'October', value: '10' },
  { label: 'November', value: '11' },
  { label: 'December', value: '12' },
];

export const timeZones = [
  'America/Los_Angeles',
  'America/Denver',
  'America/Chicago',
  'America/New_York',
  'UTC',
  'Europe/Helsinki',
  'Europe/Istanbul',
  'Asia/Kolkata',
  'Australia/Sydney'
];
  