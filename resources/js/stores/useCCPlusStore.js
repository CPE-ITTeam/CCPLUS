// resources/js/stores/useCCPlusStore.js
import { defineStore } from 'pinia';
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
      tableErrorMessage: null,
      tableSuccessMessage: null,
      tableWarningMessage: null,
      tableInfoMessage: null,
    };
  },
  persist: {
    storage: sessionStorage,
  },
  getters: {
    report_data: (state) => state.reportData,
    consd_data: (state) => state.consortia,
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
        const { data } = await useFetch('/api/consoList').get().json();
        this.consortia = [...data.value.records];
        return { success: true };
      } catch (error) {
        return { success: false, error: 'Error fetching consortium list: ' + error.message };
      }
    },

    async addConsortium(payload) {
      this.clearAlerts();

      try {
        const response = await fetch('/api/consortia', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(payload),
        });

        const resData = await response.json();

        if (response.ok && resData.result) {
          this.notifySuccess(resData.msg || 'Consortium created successfully.');
          await this.getConsortia();
          return { success: true };
        } else {
          this.notifyError(resData.msg || resData.message || 'Failed to create consortium.');
          return { success: false, error: this.tableErrorMessage };
        }
      } catch (error) {
        this.notifyError('Server connection lost. Unable to build database container.');
        return { success: false, error: error.message };
      }
    },

    notifySuccess(msg) {
      this.clearAlerts();
      this.tableSuccessMessage = msg;
    },
    notifyError(msg) {
      this.clearAlerts();
      this.tableErrorMessage = msg;
    },
    notifyWarning(msg) {
      this.clearAlerts();
      this.tableWarningMessage = msg;
    },
    notifyInfo(msg) {
      this.clearAlerts();
      this.tableInfoMessage = msg;
    },
    clearAlerts() {
      this.tableErrorMessage = null;
      this.tableSuccessMessage = null;
      this.tableWarningMessage = null;
      this.tableInfoMessage = null;
    },
    clearTableError() {
      this.tableErrorMessage = null;
    },
    clearTableSuccess() {
      this.tableSuccessMessage = null;
    },
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
  'Australia/Sydney',
];
