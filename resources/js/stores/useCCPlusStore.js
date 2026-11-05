// resources/js/stores/useCCPlusStore.js
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
      // 💡 NEW STATE FIELDS: Track table messaging contexts cleanly
      tableErrorMessage: null,
      tableSuccessMessage: null,
    }
  },
  persist: {  // persist for the session
    storage: sessionStorage,
  },
  getters: {
    report_data: (state) => { return state.reportData },
    // 💡 FIXED GETTER: Added explicit return statement so it stops returning undefined!
    consd_data: (state) => { return state.consortia },
  },
  actions: {
    updateReportData(data) {
      this.$state.reportData = data;
    },
    updateReportDates(data) {
      this.$state.reportDates = data;
    },
    async getConsortia() {
      try {
        const { data } = await useFetch("/api/consoList").get().json();
        this.consortia = [...data.value.records];
        return { success: true };
      } catch (error) {
        return { success: false, error: 'Error fetching consortium list: ' + error.message };
      }
    },

    // 💡 NEW ACTION: Handles sending new consortia to your backend controller!
    async addConsortium(payload) {
      this.tableErrorMessage = null;
      this.tableSuccessMessage = null;
      
      try {
        // Send a POST request to your Laravel store endpoint
        const response = await fetch('/api/consortia', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        });

        const resData = await response.json();

        // Check the 'result' property returned by ConsortiumController
        if (response.ok && resData.result) {
          this.tableSuccessMessage = resData.msg || "Consortium created successfully.";
          await this.getConsortia(); // Automatically refresh your table rows!
          return { success: true };
        } else {
          // Captures custom 'msg' errors or database replication alerts from your controller
          this.tableErrorMessage = resData.msg || resData.message || "Failed to create consortium.";
          return { success: false, error: this.tableErrorMessage };
        }
      } catch (error) {
        this.tableErrorMessage = "Server connection lost. Unable to build database container.";
        return { success: false, error: error.message };
      }
    },

    // 💡 FIXED FOR DATAFORM ALERTS: Clean up table state contexts on close or navigation
    clearTableError() {
      this.tableErrorMessage = null;
    },
    clearTableSuccess() {
      this.tableSuccessMessage = null;
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
