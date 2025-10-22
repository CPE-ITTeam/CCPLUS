// resources/js/plugins/vue_router.js
// Vue Router
import { createRouter, createWebHistory } from 'vue-router';
import { markRaw } from 'vue';
import { useAuthStore } from '../plugins/authStore.js';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import LoginLayout from '@/layouts/LoginLayout.vue';
import Login from '@/pages/Login.vue';
import ForgotPassword from '@/pages/ForgotPassword.vue'
import ResetPassword from '@/pages/ResetPassword.vue'
import PlaceHolder from '@/pages/PlaceHolder.vue';
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
import SavedReports from '@/components/tables/SavedReports.vue';
import ManualHarvest from '@/components/panels/ManualHarvest.vue';
import CreateReport from '@/components/panels/CreateReport.vue';
import RolesTable from '@/components/tables/RolesTable.vue';
import ConnectionsTable from '@/components/tables/ConnectionsTable.vue';
// import CredentialsAudit from '@/components/panels/CredentialsAudit.vue';
// import PermissionsTable from '@/components/tables/PermissionsTable.vue';
// import ReportPreview from '@/components/panels/ReportPreview.vue';
// Pinia datastore
import { createPinia } from 'pinia';
import piniaPluginPersistedState from "pinia-plugin-persistedstate"
export const pinia = createPinia();
pinia.use(piniaPluginPersistedState);

// Routes and pages
export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  linkActiveClass: 'active',
  routes: [
    {
      path: '/', name: 'Home', component: Login, children: [],
      meta: { title: 'Login', layout: LoginLayout, role: 'Guest', level:0 },
    },
    {
      path: '/login', name: 'Login', component: Login, children: [],
      meta: { title: 'Login', layout: LoginLayout, role: 'Guest', level:0 },
    },
    {
      path: '/logout', name: 'Logout', component: Login, children: [],
      meta: { title: 'Logout', layout: LoginLayout, role: 'Guest', level:0 },
    },
    { path: '/forgotPassForm', name: 'ForgotPassword', component: ForgotPassword, children: [],
      meta: { title: 'Forgot Password', layout: LoginLayout, role: 'Guest', level:0 }
    },
    { path: '/resetPassForm', name: 'ResetPassword', component: ResetPassword, children: [],
      props: route => ({ token: route.query.token, key: route.query.key }),
      meta: { title: 'Reset Password', layout: LoginLayout, role: 'Guest', level:0 }
    },
    {
      path: '/admin', name: 'Admin', meta: { title: 'Admin', layout: AuthenticatedLayout, role: 'Admin', level:1 },
      children: [
        { path: '/institutions', name: 'Institutions',
          meta: { title: 'Institutions', layout: AuthenticatedLayout, role: 'Admin', level:2 },
          children: [
            { path: '/admin/institutions', name: 'InstitutionsTable',
              meta: { title: 'Institutions', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(InstitutionsTable),
            },
            { path: '/admin/institutiontypes', name: 'InstitutionsTypesTable',
              meta: { title: 'Institution Types', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(InstitutionTypesTable),
            },
            { path: '/admin/institutiongroups', name: 'InstitutionGroupsTable',
              meta: { title: 'Institution Groups', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(InstitutionGroupsTable),
            },
          ]
        },
        { path: '/users', name: 'Users', meta: { title: 'Users', layout: AuthenticatedLayout, role: 'Admin', level:2 }, 
          children: [
            { path: '/admin/users', name: 'UserTable',
              meta: { title: 'Users', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(UsersTable),
            },
            { path: '/admin/roles', name: 'RolesTable',
              meta: { title: 'Roles', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(RolesTable),
            },
            { path: '/admin/roles', name: 'PermissionsTable',
              meta: { title: 'Permissions', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(PlaceHolder),
            },
          ]
        },
        { path: '/admin/credentials', name: 'Credentials',
          meta: { title: 'Credentials', layout: AuthenticatedLayout, role: 'Admin', level:2 }, 
          children: [
            { path: '/admin/connections', name: 'ConnectionsTable',
              meta: { title: 'Connections and Credentials', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(ConnectionsTable),
            },
            { path: '/admin/credentialsaudit', name: 'CredentialsAudit',
              meta: { title: 'Credentials Audit', layout: AuthenticatedLayout, role: 'Admin', level:3 }, 
              component: markRaw(PlaceHolder),
            },
          ],
        },
      ]  
    },
    {
      path: '/harvests', name: 'Harvests',
      meta: { title: 'Harvests', layout: AuthenticatedLayout, role: 'Admin', level:1 },
      children: [
        { path: '/harvests/manual', name: 'ManualHarvest',
          meta: { title: 'Manual Harvest', layout: AuthenticatedLayout, role: 'Admin', level:2 }, 
          component: markRaw(ManualHarvest),
        },
        { path: '/harvests/queue', name: 'HarvestQueue',
          meta: { title: 'Harvest Queue', layout: AuthenticatedLayout, role: 'Admin', level:2 }, 
          component: markRaw(HarvestQueue),
        },
        { path: '/harvests/log', name: 'HarvestLog',
          meta: { title: 'Harvest Log', layout: AuthenticatedLayout, role: 'Admin', level:2 }, 
          component: markRaw(HarvestLog),
        },
      ]
    },
    {
      path: '/reports', name: 'Reports',
      meta: { title: 'Reports', layout: AuthenticatedLayout, role: 'Viewer', level:1 },
      children: [
        { path: '/reports/create', name: 'CreateReport',
          meta: { title: 'Create a Report', layout: AuthenticatedLayout, role: 'Viewer', level:2 }, 
          component: markRaw(CreateReport),
        },
        { path: '/reports/saved', name: 'SavedReports',
          meta: { title: 'Saved Reports', layout: AuthenticatedLayout, role: 'Viewer', level:2 }, 
          component: markRaw(SavedReports),
        },
        { path: '/reports/preview', name: 'ReportPreview',
          meta: { title: 'Preview and Export', layout: AuthenticatedLayout, role: 'Viewer', level:2 }, 
          component: markRaw(PlaceHolder),
        },
      ]
    },
    { path: '/admin/serveradmin', name: 'Server',
      meta: { title: 'Server', layout: AuthenticatedLayout, role: 'ServerAdmin', level:1 }, 
      children: [
        { path: '/admin/consortia', name: 'Consortia',
          meta: { title: 'Consortia', layout: AuthenticatedLayout, role: 'ServerAdmin', level:2 }, 
          component: markRaw(Consortia),
        },
        { path: '/admin/platforms', name: 'PlatformsTable',
          meta: { title: 'Platforms', layout: AuthenticatedLayout, role: 'Admin', level:2 }, 
          component: markRaw(PlatformsTable),
        },
        { path: '/admin/serveradmin', name: 'ServerAdmin',
          meta: { title: 'Server', layout: AuthenticatedLayout, role: 'ServerAdmin', level:2 }, 
          children: [
            { path: '/admin/serversettings', name: 'ServerSettings',
              meta: { title: 'Server Settings', layout: AuthenticatedLayout, role: 'ServerAdmin', level:3 }, 
              component: markRaw(ServerSettings),
            },
            { path: '/admin/mailsettings', name: 'MailSettings',
              meta: { title: 'Mail Settings', layout: AuthenticatedLayout, role: 'ServerAdmin', level:3 }, 
              component: markRaw(MailSettings),
            },
          ]
        },
      ]
    },
  ],
});

// Add router to pinia stores
pinia.use( ( {store} ) => {
  store.router = markRaw(router);
});

// Navigation guard
router.beforeEach(async (to, from ) => {
  const authStore = useAuthStore(pinia);
  document.title = `${to.name}`;
  if (to.meta.role != 'Guest' && !authStore.isAuthenticated) {
    return { name: 'Login' }
  }
  if (authStore.isAuthenticated && (to.name == 'Home' || to.name == 'Login')) {
    return (authStore.is_admin || authStore.is_serveradmin) ? { name: 'Admin' } : { name: 'Reports' }
  }
  if (to.name == 'Logout') {
    const { logout } = useAuthStore();
    await logout();
  }
});
