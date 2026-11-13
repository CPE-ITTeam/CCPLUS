// resources/js/plugins/vue_router.js
import { createRouter, createWebHistory } from "vue-router";
import { markRaw } from "vue";
import { useAuthStore } from "@/stores/useAuthStore.js";
import i18n from "@/plugins/i18n.js";

const t = (key) => i18n.global.t(key);

// Global Layout Sheets Scaffolds
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import LoginLayout from "@/layouts/LoginLayout.vue";

// 1. AUTH FLOW VIEWS
import Login from "@/views/auth/Login.vue";
import ForgotPassword from "@/views/auth/ForgotPassword.vue";
import ResetPassword from "@/views/auth/ResetPassword.vue";

// 2. DATASET SPREADSHEET MONITORING VIEWS
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

// 3. SYSTEM INFRASTRUCTURE & SETTINGS PANELS
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
    meta: {
      ...GUEST_META,
      get title() {
        return t("routes.login");
      },
    },
  },
  {
    path: "/logout",
    name: "Logout",
    component: Login,
    meta: {
      ...GUEST_META,
      get title() {
        return t("routes.logout");
      },
    },
  },
  {
    path: "/forgotPassForm",
    name: "ForgotPassword",
    component: ForgotPassword,
    meta: {
      ...GUEST_META,
      get title() {
        return t("routes.forgot_password");
      },
    },
  },
  {
    path: "/resetPassForm",
    name: "ResetPassword",
    component: ResetPassword,
    props: (route) => ({ token: route.query.token, key: route.query.key }),
    meta: {
      ...GUEST_META,
      get title() {
        return t("routes.reset_password");
      },
    },
  },

  /* ----------------------------------------------------
       Authenticated Layout Routes
  ------------------------------------------------------- */
  {
    path: "/consortia",
    name: "Consortia",
    component: markRaw(Consortia),
    children: [],
    meta: {
      ...AUTH_BASE,
      role: "ServerAdmin",
      level: 1,
      get title() {
        return t("routes.consortia");
      },
    },
  },
  {
    path: "/institutions",
    name: "Institutions",
    show: 1,
    panels: [],
    meta: {
      ...AUTH_BASE,
      role: "Admin",
      level: 1,
      get title() {
        return t("routes.institutions");
      },
    },
    children: [
      {
        path: "institutions",
        name: "InstitutionsTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.institutions");
          },
        },
        component: markRaw(Institutions),
      },
      {
        path: "types",
        name: "InstitutionsTypesTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.institution_types");
          },
        },
        component: markRaw(InstitutionTypes),
      },
      {
        path: "groups",
        name: "InstitutionGroupsTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.institution_groups");
          },
        },
        component: markRaw(InstitutionGroups),
      },
    ],
  },
  {
    path: "/users",
    name: "Users",
    show: 1,
    panels: [],
    meta: {
      ...AUTH_BASE,
      role: "Admin",
      level: 1,
      get title() {
        return t("routes.users");
      },
    },
    children: [
      {
        path: "users",
        name: "UserTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.users");
          },
        },
        component: markRaw(Users),
      },
      {
        path: "roles",
        name: "RolesTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.permissions");
          },
        },
        component: markRaw(Roles),
      },
    ],
  },
  {
    path: "/credentials",
    name: "Credentials",
    show: 1,
    panels: [],
    meta: {
      ...AUTH_BASE,
      role: "Admin",
      level: 1,
      get title() {
        return t("routes.credentials");
      },
    },
    children: [
      {
        path: "credentials",
        name: "CredentialsTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.credentials");
          },
        },
        component: markRaw(Credentials),
      },
      {
        path: "audit",
        name: "CredentialsAudit",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.credentials_audit");
          },
        },
        component: markRaw(CredentialsAudit),
      },
    ],
  },
  {
    path: "/platforms",
    name: "Platforms",
    show: 1,
    meta: {
      ...AUTH_BASE,
      role: "Admin",
      level: 1,
      get title() {
        return t("routes.platforms");
      },
    },
    children: [
      {
        path: "registry",
        name: "PlatformsTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "ServerAdmin",
          level: 2,
          get title() {
            return t("routes.registry");
          },
        },
        component: markRaw(Platforms),
      },
      {
        path: "connections",
        name: "ConnectionsTable",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.connections");
          },
        },
        component: markRaw(Connections),
      },
    ],
  },
  {
    path: "/harvests",
    name: "Harvests",
    meta: {
      ...AUTH_BASE,
      role: "Admin",
      level: 1,
      get title() {
        return t("routes.harvests");
      },
    },
    children: [
      {
        path: "manual",
        name: "ManualHarvest",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.manual_harvest");
          },
        },
        component: markRaw(ManualHarvest),
      },
      {
        path: "queue",
        name: "HarvestQueue",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.harvest_queue");
          },
        },
        component: markRaw(HarvestQueue),
      },
      {
        path: "log",
        name: "HarvestLog",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.harvest_log");
          },
        },
        component: markRaw(HarvestLog),
      },
    ],
  },
  {
    path: "/reports",
    name: "Reports",
    meta: {
      ...AUTH_BASE,
      role: "Viewer",
      level: 1,
      get title() {
        return t("routes.reports");
      },
    },
    children: [],
    component: markRaw(Reporting),
  },
  // ServerAdmin Config Routes
  {
    path: "/server",
    name: "Server",
    meta: {
      ...AUTH_BASE,
      role: "ServerAdmin",
      level: 1,
      get title() {
        return t("routes.server");
      },
    },
    children: [
      {
        path: "settings",
        name: "ServerSettings",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "ServerAdmin",
          level: 2,
          get title() {
            return t("routes.server_settings");
          },
        },
        component: markRaw(ServerSettings),
      },
      {
        path: "mailsettings",
        name: "MailSettings",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "ServerAdmin",
          level: 2,
          get title() {
            return t("routes.mail_settings");
          },
        },
        component: markRaw(MailSettings),
      },
      {
        path: "api",
        name: "CCPlusApi",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "ServerAdmin",
          level: 2,
          get title() {
            return t("routes.api_management");
          },
        },
        component: markRaw(CCPlusApi),
      },
      {
        path: "account",
        name: "AccountSettings",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Admin",
          level: 2,
          get title() {
            return t("routes.account_settings");
          },
        },
        component: markRaw(AccountSettings),
      },
    ],
  },
];

// Separate routes that AuthenticatedLayout tacks onto the application routes in the top navigation bar
const profileRoute = {
  path: "/profile",
  name: "Profile",
  meta: {
    get title() {
      return t("routes.account_settings");
    },
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
    get title() {
      return t("routes.ccplus_api");
    },
    layout: AuthenticatedLayout,
    role: "Viewer",
    key: 0,
    level: 0,
  },
  component: markRaw(CCPlusApi),
};

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    ...routes,
    profileRoute,
    apiRoute,
    {
      path: "/:pathMatch(.*)*",
      name: "NotFound",
      redirect: { name: "Login" },
    },
  ],
});

// Add router reference context directly to Pinia stores
pinia.use(({ store }) => {
  store.router = markRaw(router);
});

// Structural Navigation Guard
router.beforeEach(async (to, from) => {
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
    await authStore.logout();
  }
});

// Sync Tab Layout Text Post-render
router.afterEach((to) => {
  const baseTitle = import.meta.env.VITE_APP_NAME || "CCPLUS";
  const ptitle = to.meta.title || "";

  let finalTitle = ptitle.length > 0 ? `${ptitle} | ${baseTitle}` : baseTitle;

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
