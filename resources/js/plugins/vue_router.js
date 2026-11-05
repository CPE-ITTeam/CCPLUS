// resources/js/plugins/vue_router.js
// Vue Router
import { createRouter, createWebHistory } from "vue-router";
import { markRaw } from "vue";
import { useAuthStore } from "@/stores/useAuthStore.js"; // Clean absolute alias

// Global Layout Sheets Scaffolds
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import LoginLayout from "@/layouts/LoginLayout.vue";

// 1. AUTH FLOW VIEWS (Moved from pages/)
import Login from "@/views/auth/Login.vue";
import ForgotPassword from "@/views/auth/ForgotPassword.vue";
import ResetPassword from "@/views/auth/ResetPassword.vue";

// 2. DATASET SPREADSHEET MONITORING VIEWS (Moved from components/tables/ and suffixes stripped)
import Connections from "@/views/datasets/Connections.vue";
import Consortia from "@/views/datasets/Consortia.vue";
import CredentialsAudit from "@/views/datasets/CredentialsAudit.vue";
import Credentials from "@/views/datasets/Credentials.vue";
import HarvestLog from "@/views/datasets/HarvestLog.vue";
import HarvestQueue from "@/views/datasets/HarvestQueue.vue";
import InstitutionGroups from "@/views/datasets/InstitutionGroups.vue";
import Institutions from "@/views/datasets/Institutions.vue";
import InstitutionTypes from "@/views/datasets/InstitutionTypes.vue";
import Platforms from "@/views/datasets/Platforms.vue";
import Roles from "@/views/datasets/Roles.vue";
import Users from "@/views/datasets/Users.vue";

// 3. SYSTEM INFRASTRUCTURE & SETTINGS PANELS (Moved from components/panels/)
import AccountSettings from "@/views/admin/AccountSettings.vue";
import CCPlusApi from "@/views/admin/CCPlusApi.vue";
import MailSettings from "@/views/admin/MailSettings.vue";
import ManualHarvest from "@/views/admin/ManualHarvest.vue";
import Reporting from "@/views/admin/Reporting.vue";
import ServerSettings from "@/views/admin/ServerSettings.vue";

// Pinia Datastore Pipeline
import { createPinia } from "pinia";
import piniaPluginPersistedState from "pinia-plugin-persistedstate";

export const pinia = createPinia();
pinia.use(piniaPluginPersistedState);

// Routes configuration layout matrix
/* ----------------------------------------------------
    Shared Layout Constants
------------------------------------------------------- */
const GUEST_META = { layout: LoginLayout, role: "Guest", level: 0 };
const AUTH_BASE = { layout: AuthenticatedLayout, key: 0 };

const routes = [
  /* ----------------------------------------------------
      Unauthenticated Routes  (LoginLayout)
  ------------------------------------------------------- */
  {
    path: "/login",
    alias: "/",
    name: "Login",
    component: Login,
    meta: Object.assign({ title: "Login" }, GUEST_META),
  },
  {
    path: "/logout",
    name: "Logout",
    component: Login,
    meta: Object.assign({ title: "Logout" }, GUEST_META),
  },
  {
    path: "/forgotPassForm",
    name: "ForgotPassword",
    component: ForgotPassword,
    meta: Object.assign({ title: "Forgot Password" }, GUEST_META),
  },
  {
    path: "/resetPassForm",
    name: "ResetPassword",
    component: ResetPassword,
    props: (route) => ({ token: route.query.token, key: route.query.key }),
    meta: Object.assign({ title: "Reset Password" }, GUEST_META),
  },

  /* ----------------------------------------------------
       Authenticated Layout Routes
  ------------------------------------------------------- */
  {
    path: "/consortia",
    name: "Consortia",
    component: markRaw(Consortia),
    children: [],
    meta: Object.assign({ title: "Consortia", role: "ServerAdmin", level: 1 }, AUTH_BASE),
  },
  {
    path: "/institutions",
    name: "Institutions",
    show: 1,
    panels: [],
    meta: Object.assign({ title: "Institutions", role: "Admin", level: 1 }, AUTH_BASE),
    children: [
      {
        path: "institutions",
        name: "InstitutionsTable",
        show: 1,
        meta: Object.assign({ title: "Institutions", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(Institutions),
      },
      {
        path: "types",
        name: "InstitutionsTypesTable",
        show: 1,
        meta: Object.assign({ title: "Institution Types", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(InstitutionTypes),
      },
      {
        path: "groups",
        name: "InstitutionGroupsTable",
        show: 1,
        meta: Object.assign({ title: "Institution Groups", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(InstitutionGroups),
      },
    ],
  },
  {
    path: "/users",
    name: "Users",
    show: 1,
    panels: [],
    meta: Object.assign({ title: "Users", role: "Admin", level: 1 }, AUTH_BASE),
    children: [
      {
        path: "users",
        name: "UserTable",
        show: 1,
        meta: Object.assign({ title: "Users", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(Users),
      },
      {
        path: "roles",
        name: "RolesTable",
        show: 1,
        meta: Object.assign({ title: "Permissions", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(Roles),
      },
    ],
  },
  {
    path: "/credentials",
    name: "Credentials",
    show: 1,
    panels: [],
    meta: Object.assign({ title: "Credentials", role: "Admin", level: 1 }, AUTH_BASE),
    children: [
      {
        path: "credentials",
        name: "CredentialsTable",
        show: 1,
        meta: Object.assign({ title: "Credentials", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(Credentials),
      },
      {
        path: "audit",
        name: "CredentialsAudit",
        show: 1,
        meta: Object.assign({ title: "Credentials Audit", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(CredentialsAudit),
      },
    ],
  },
  {
    path: "/platforms",
    name: "Platforms",
    show: 1,
    meta: Object.assign({ title: "Platforms", role: "Admin", level: 1 }, AUTH_BASE),
    children: [
      {
        path: "registry",
        name: "PlatformsTable",
        show: 1,
        meta: Object.assign({ title: "Registry", role: "ServerAdmin", level: 2 }, AUTH_BASE),
        component: markRaw(Platforms),
      },
      {
        path: "connections",
        name: "ConnectionsTable",
        show: 1,
        meta: Object.assign({ title: "Connections", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(Connections),
      },
    ],
  },
  {
    path: "/harvests",
    name: "Harvests",
    meta: Object.assign({ title: "Harvests", role: "Admin", level: 1 }, AUTH_BASE),
    children: [
      {
        path: "manual",
        name: "ManualHarvest",
        show: 1,
        meta: Object.assign({ title: "Manual Harvest", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(ManualHarvest),
      },
      {
        path: "queue",
        name: "HarvestQueue",
        show: 1,
        meta: Object.assign({ title: "Harvest Queue", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(HarvestQueue),
      },
      {
        path: "log",
        name: "HarvestLog",
        show: 1,
        meta: Object.assign({ title: "Harvest Log", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(HarvestLog),
      },
    ],
  },
  {
    path: "/reports",
    name: "Reports",
    meta: Object.assign({ title: "Reports", role: "Viewer", level: 1 }, AUTH_BASE),
    children: [],
    component: markRaw(Reporting),
  },
  // ServerAdmin Config Routes
  {
    path: "/server",
    name: "Server",
    meta: Object.assign({ title: "Server", role: "ServerAdmin", level: 1 }, AUTH_BASE),
    children: [
      {
        path: "settings",
        name: "ServerSettings",
        show: 1,
        meta: Object.assign({ title: "Server Settings", role: "ServerAdmin", level: 2 }, AUTH_BASE),
        component: markRaw(ServerSettings),
      },
      {
        path: "mailsettings",
        name: "MailSettings",
        show: 1,
        meta: Object.assign({ title: "Mail Settings", role: "ServerAdmin", level: 2 }, AUTH_BASE),
        component: markRaw(MailSettings),
      },
      {
        path: "api",
        name: "CCPlusApi",
        show: 1,
        meta: Object.assign({ title: "API Management", role: "ServerAdmin", level: 2 }, AUTH_BASE),
        component: markRaw(CCPlusApi),
      },
      {
        path: "account",
        name: "AccountSettings",
        show: 1,
        meta: Object.assign({ title: "Account Settings", role: "Admin", level: 2 }, AUTH_BASE),
        component: markRaw(AccountSettings),
      },
    ],
  },
];


// Separate routes that authenticatedLayout tacks onto the application routes in the top navigation bar
const profileRoute = {
  path: "/profile",
  name: "Profile",
  meta: {
    title: "Account Settings",
    layout: AuthenticatedLayout,
    role: "Viewer",
    key: 0,
    level: 0,
  },
  component: markRaw(AccountSettings),
};

const apiRoute = {
  path: "/ccplusApi",
  name: "API",
  meta: {
    title: "CC-PLUS API",
    layout: AuthenticatedLayout,
    role: "Viewer",
    key: 0,
    level: 0,
  },
  component: markRaw(CCPlusApi),
};

// Change this block at the bottom of your vue_router.js file:
export const router = createRouter({
  history: createWebHistory(),
  // Use the JavaScript spread operator (...) to append them right inside the core routes configuration array!
  routes: [
    ...routes, 
    profileRoute, 
    apiRoute,
    // Add the catch-all right here at the absolute bottom of the array to capture blank starts safely
    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      redirect: { name: 'Login' }
    }
  ],
});


// Add router reference context directly to Pinia stores
pinia.use(({ store }) => {
  store.router = markRaw(router);
});// Structural Navigation Guard
router.beforeEach(async (to, from) => {
  //  FIXED: Removed (pinia) so it safely defers instantiation until Vue mounts
  const authStore = useAuthStore();

  // 1. Guest Route Checker
  if (to.meta.role !== "Guest" && !authStore.isAuthenticated) {
    return { name: "Login" };
  }

  // 2. Privilege Level Check
  if (to.meta.level > 0 && !authStore.user) {
    return "/api/login";
  }

  // 3. Intercept and Handle Logout Routines
  if (to.name === "Logout") {
    //  FIXED: Directly use the active authStore instance instead of re-instantiating destructured properties
    await authStore.logout();
  }
});

// Sync Tab Layout Text Post-render
router.afterEach((to) => {
  // Pull your Laravel app name or fallback to a string
  const baseTitle = import.meta.env.VITE_APP_NAME || "CCPLUS";
  const ptitle = to.meta.title || "";

  // Reverse the layout: Page Name first (if it exists), then Project Name
  let finalTitle = ptitle.length > 0 ? `${ptitle} | ${baseTitle}` : baseTitle;

  // Add environment specific host brackets
  const hostname = window.location.hostname;
  if (hostname === "127.0.0.1") {
    finalTitle = `[127] ${finalTitle}`;
  } else if (hostname === "localhost") {
    finalTitle = `[loc] ${finalTitle}`;
  } else if (hostname.startsWith("192.168.")) {
    finalTitle = `[192] ${finalTitle}`;
  }

  document.title = finalTitle;
});

export default router;
