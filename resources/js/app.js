// Load bootstrap and Vue
require('./bootstrap');
import Vue from 'vue';

// Plugins and state store
import Vuetify from '@/js/plugins/vuetify';
import { store } from '@/js/plugins/store.js';

// Vue Components
Vue.component('topnav', require('./components/Navbar.vue').default);
Vue.component('flash', require('./components/Flash.vue').default);
Vue.component('user-form', require('./components/UserForm.vue').default);
Vue.component('users-by-inst', require('./components/UsersByInst.vue').default);
Vue.component('user-data-table', require('./components/UserDataTable.vue').default);
Vue.component('all-sushi-by-inst', require('./components/AllSushiByInst.vue').default);
Vue.component('all-sushi-by-prov', require('./components/AllSushiByProv.vue').default);
Vue.component('sushi-setting-form', require('./components/SushiSettingForm.vue').default);
Vue.component('provider-data-table', require('./components/ProviderDataTable.vue').default);
Vue.component('institution-form', require('./components/InstitutionForm.vue').default);
Vue.component('institution-data-table', require('./components/InstitutionDataTable.vue').default);
Vue.component('institution-group-form', require('./components/InstitutionGroupForm.vue').default);
Vue.component('institution-types', require('./components/InstitutionTypes.vue').default);
Vue.component('institution-groups', require('./components/InstitutionGroups.vue').default);
Vue.component('harvesting', require('./components/Harvesting.vue').default);
Vue.component('harvestlog-data-table', require('./components/HarvestlogDataTable.vue').default);
Vue.component('harvestlog-summary-table', require('./components/HarvestlogSummaryTable.vue').default);
Vue.component('failed-harvests', require('./components/FailedHarvests.vue').default);
Vue.component('harvest-attempts', require('./components/HarvestAttempts.vue').default);
Vue.component('harvestqueue-data-table', require('./components/HarvestQueueDataTable.vue').default);
Vue.component('date-range', require('./components/DateRange.vue').default);
Vue.component('view-reports', require('./components/ViewReports.vue').default);
Vue.component('create-report', require('./components/CreateReport.vue').default);
Vue.component('report-preview', require('./components/ReportPreview.vue').default);
Vue.component('reports', require('./components/Reports.vue').default);
Vue.component('saved-reports-data-table', require('./components/SavedReportsDataTable.vue').default);
Vue.component('manual-harvest', require('./components/ManualHarvest.vue').default);
Vue.component('alert-data-table', require('./components/AlertDataTable.vue').default);
Vue.component('alert-summary-table', require('./components/AlertSummaryTable.vue').default);
Vue.component('globaladmin-dashboard', require('./components/GlobalAdminDashboard.vue').default);
Vue.component('global-instances', require('./components/GlobalInstances.vue').default);
Vue.component('global-settings', require('./components/GlobalSettings.vue').default);
Vue.component('global-provider-data-table', require('./components/GlobalProviderDataTable.vue').default);
Vue.component('sushisettings-data-table', require('./components/SushiSettingsDataTable.vue').default);
Vue.component('password-visibility', require('./components/PasswordVisibility.vue').default);
Vue.component('clear-input', require('./components/ClearInput.vue').default);
Vue.component('consoadmin-dashboard', require('./components/ConsoAdminDashboard.vue').default);
Vue.component('institution-dialog', require('./components/InstitutionDialog.vue').default);
Vue.component('provider-dialog', require('./components/ProviderDialog.vue').default);
Vue.component('user-dialog', require('./components/UserDialog.vue').default);
Vue.component('sushi-dialog', require('./components/SushiSettingDialog.vue').default);
Vue.component('error-details', require('./components/ErrorDetails.vue').default);

/**
 * Create a fresh Vue application instance with Vuetify.
 */
const app = new Vue({
    vuetify: Vuetify,
    store,
    el: '#app',
});
