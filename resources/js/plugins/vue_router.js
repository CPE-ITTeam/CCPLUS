// resources/js/plugins/vue_router.js
// Vue Router
import { createRouter, createWebHistory } from 'vue-router';
import { markRaw } from 'vue';
import { useAuthStore } from '../plugins/authStore.js';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import LoginLayout from '@/layouts/LoginLayout.vue';
import Login from '@/pages/Login.vue';
import ForgotPassword from '@/pages/ForgotPassword.vue';
import ResetPassword from '@/pages/ResetPassword.vue';
import InstitutionsTable from '@/components/tables/InstitutionsTable.vue';
import InstitutionTypesTable from '@/components/tables/InstitutionTypesTable.vue';
import InstitutionGroupsTable from '@/components/tables/InstitutionGroupsTable.vue';
import UsersTable from '@/components/tables/UsersTable.vue';
import PlatformsTable from '@/components/tables/PlatformsTable.vue';
import Consortia from '@/components/tables/Consortia.vue';
import ServerSettings from '@/components/panels/ServerSettings.vue';
import MailSettings from '@/components/panels/MailSettings.vue';
import HarvestQueue from '@/components/tables/HarvestQueue.vue';
import HarvestLog from '@/components/tables/HarvestLog.vue';
import ManualHarvest from '@/components/panels/ManualHarvest.vue';
import Reporting from '@/components/panels/Reporting.vue';
import RolesTable from '@/components/tables/RolesTable.vue';
import CredentialsTable from '@/components/tables/CredentialsTable.vue';
import ConnectionsTable from '@/components/tables/ConnectionsTable.vue';
import CCPlusApi from '@/components/panels/CCPlusApi.vue';
import AccountSettings from '@/components/panels/AccountSettings.vue';
import CredentialsAudit from '@/components/tables/CredentialsAudit.vue';
// Pinia datastore
import { createPinia } from 'pinia';
import piniaPluginPersistedState from "pinia-plugin-persistedstate";
export const pinia = createPinia();
pinia.use(piniaPluginPersistedState);
// Routes
const routes = [
    /* ----------------------------------------------------
        Unauthenticated Routes  (LoginLayout)
    ------------------------------------------------------- */
    {
      path: '/', name: 'Home', component: Login, children: [],
      meta: { title: 'Login', layout: LoginLayout, role: 'Guest', level: 0 },
    },
    {
      path: '/login', name: 'Login', component: Login, children: [],
      meta: { title: 'Login', layout: LoginLayout, role: 'Guest', level: 0 },
    },
    {
      path: '/logout', name: 'Logout', component: Login, children: [],
      meta: { title: 'Logout', layout: LoginLayout, role: 'Guest', level: 0 },
    },
    { 
      path: '/forgotPassForm', name: 'ForgotPassword', component: ForgotPassword, children: [],
      meta: { title: 'Forgot Password', layout: LoginLayout, role: 'Guest', level: 0 }
    },
    { 
      path: '/resetPassForm', name: 'ResetPassword', component: ResetPassword, children: [],
      props: route => ({ token: route.query.token, key: route.query.key }),
      meta: { title: 'Reset Password', layout: LoginLayout, role: 'Guest', level: 0 }
    },

    /* ----------------------------------------------------
       Authenticated Layout Routes
    ------------------------------------------------------- */
    // These are excluded from the menu bar with level:0, but authenticatedLayout will tack
    // them onto the top navigation bar after the accessible (by role) other routes.
    {
        path: '/profile', name: 'Profile', component: markRaw(AccountSettings),
        meta: { title: 'Account Settings', layout: AuthenticatedLayout, role: 'Viewer', key:0, level:0 },
    },
    {
        path: '/ccplusApi', name: 'API', component: markRaw(CCPlusApi),
        meta: { title: 'CC-PLUS API', layout: AuthenticatedLayout, role: 'Viewer', key:0, level:0 },
    },
    // Role-based routes (level:1) are displayed in the menu bar, and their children (level:2)
    // are displayed in the sub-menu.
    {
      path: '/consortia', name: 'Consortia', component: markRaw(Consortia), children: [],
      meta: { title: 'Consortia', layout: AuthenticatedLayout, role: 'ServerAdmin', key: 0, level: 1 }
    },
    {
      path: '/institutions', name: 'Institutions', show: 1, panels:[0],
      meta: { title: 'Institutions', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 1 },
      children: [
        { 
          path: 'institutions', name: 'InstitutionsTable', show: 1,
          meta: { title: 'Institutions', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(InstitutionsTable),
        },
        { 
          path: 'types', name: 'InstitutionsTypesTable', show: 1,
          meta: { title: 'Institution Types', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(InstitutionTypesTable),
        },
        { 
          path: 'groups', name: 'InstitutionGroupsTable', show: 1,
          meta: { title: 'Institution Groups', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(InstitutionGroupsTable),
        },
      ]
    },
    {
      path: '/users', name: 'Users', show: 1, panels:[0],
      meta: { title: 'Users', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 1 }, 
      children: [
        { 
          path: 'users', name: 'UserTable', show: 1,
          meta: { title: 'Users', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(UsersTable),
        },
        { 
          path: 'roles', name: 'RolesTable', show: 1,
          meta: { title: 'Permissions', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(RolesTable),
        },
      ]
    },
    {
      path: '/credentials', name: 'Credentials', show: 1, panels: [],
      meta: { title: 'Credentials', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 1 }, 
      children: [
        { 
          path: 'credentials', name: 'CredentialsTable', show: 1,
          meta: { title: 'Credentials', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(CredentialsTable),
        },
        { 
          path: 'audit', name: 'CredentialsAudit', show: 1,
          meta: { title: 'Credentials Audit', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(CredentialsAudit),
        },
      ],
    },
    {
      path: '/platforms', name: 'Platforms', show: 1,
      meta: { title: 'Platforms', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 1 },
      children: [
        { 
          path: 'registry', name: 'PlatformsTable', show: 1,
          meta: { title: 'Registry', layout: AuthenticatedLayout, role: 'ServerAdmin', key: 0, level: 2 }, 
          component: markRaw(PlatformsTable),
        },
        { 
          path: 'connections', name: 'ConnectionsTable', show: 1,
          meta: { title: 'Connections', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(ConnectionsTable),
        },
      ]
    },
    {
      path: '/harvests', name: 'Harvests',
      meta: { title: 'Harvests', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 1 },
      children: [
        { 
          path: 'manual', name: 'ManualHarvest', show: 1,
          meta: { title: 'Manual Harvest', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(ManualHarvest),
        },
        { 
          path: 'queue', name: 'HarvestQueue', show: 1,
          meta: { title: 'Harvest Queue', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(HarvestQueue),
        },
        { 
          path: 'log', name: 'HarvestLog', show: 1,
          meta: { title: 'Harvest Log', layout: AuthenticatedLayout, role: 'Admin', key: 0, level: 2 }, 
          component: markRaw(HarvestLog),
        },
      ]
    },
    {
      path: '/reports', name: 'Reports',
      meta: { title: 'Reports', layout: AuthenticatedLayout, role: 'Viewer', key: 0, level: 1 },
      children: [], component: markRaw(Reporting),
    },
    // ServerAdmin Routes
    { 
      path: '/server', name: 'Server',
      meta: { title: 'Server', layout: AuthenticatedLayout, role: 'ServerAdmin', key: 0, level: 1 }, 
      children: [
        { 
          path: 'settings', name: 'ServerSettings', show: 1,
          meta: { title: 'Server Settings', layout: AuthenticatedLayout, role: 'ServerAdmin', key: 0, level: 2 }, 
          component: markRaw(ServerSettings),
        },
        { 
          path: 'mailsettings', name: 'MailSettings', show: 1,
          meta: { title: 'Mail Settings', layout: AuthenticatedLayout, role: 'ServerAdmin', key: 0, level: 2 }, 
          component: markRaw(MailSettings),
        },
      ]
    },
];

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

// Add router to pinia stores
pinia.use( ( {store} ) => {
  store.router = markRaw(router);
});

// Navigation guard
router.beforeEach(async (to, from, next ) => {
  const authStore = useAuthStore(pinia);
  document.title = `${to.name}`;
  if (to.meta.role != 'Guest' && !authStore.isAuthenticated) {
    return { name: 'Login' }
  }
  // Not authenticated and requesting level>0 re-route to login
  if (to.meta.level>0 && !authStore.user) {
    next('/api/login');
  }
  // logout via authStore logout action 
  if (to.name == 'Logout') {
    const { logout } = useAuthStore();
    await logout();
  }
  next();
});

// Navigation guard
router.afterEach( to => {
  document.title = "CC-Plus";
  const ptitle = (to.meta.title) ? to.meta.title : '';
  document.title += (ptitle.length>0) ? ' - '+ptitle : '';
});