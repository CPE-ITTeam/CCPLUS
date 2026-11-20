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
import SavedReports from "@/views/datasets/SavedReports.vue";
import Users from "@/views/datasets/Users.vue";

// 3. SYSTEM INFRASTRUCTURE & SETTINGS PANELS
import MailSettingsPanel from "@/components/panels/MailSettingsPanel.vue";
import ManualHarvestPanel from "@/components/panels/ManualHarvestPanel.vue";
import Reporting from "@/components/panels/ReportingPanel.vue";
import ServerSettingsPanel from "@/components/panels/ServerSettingsPanel.vue";
import ApiSettingsPanel from "@/components/panels/ApiSettingsPanel.vue";
import QueueWorkersPanel from "@/components/panels/QueueWorkersPanel.vue";


// 4. USER ACCOUNT VIEWS
import AccountSettings from "@/views/account/AccountSettings.vue";

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
      role: "MultiInst",
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
          role: "MultiInst",
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
          role: "ConsoAdmin",
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
          role: "MultiInst",
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
          role: "Admin",
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
          role: "ConsoAdmin",
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
    redirect: { name: "HarvestQueue" },
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
        component: markRaw(ManualHarvestPanel),
      },
    ],
  },

  {
    path: "/reports",
    name: "Reports",
    show: 1,
    meta: {
      ...AUTH_BASE,
      role: "Viewer",
      level: 1,
      get title() {
        return t("routes.reports");
      },
    },
    children: [
      {
        path: "create",
        name: "CreateReport",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Viewer",
          level: 2,
          get title() {
            return t("routes.create_report");
          },
        },
        component: markRaw(Reporting),
      },
      {
        path: "saved",
        name: "SavedReports",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "Viewer",
          level: 2,
          get title() {
            return t("routes.saved_reports");
          },
        },
        component: markRaw(SavedReports),
      },
    ],
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
        component: markRaw(ServerSettingsPanel),
      },
      {
        path: "workers",
        name: "QueueWorkers",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "ServerAdmin",
          level: 2,
          get title() {
            return t("routes.queue_workers", "Queue Workers");
          },
        },
        component: markRaw(QueueWorkersPanel),
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
        component: markRaw(MailSettingsPanel),
      },
      {
        path: "api",
        name: "ServerApi",
        show: 1,
        meta: {
          ...AUTH_BASE,
          role: "ServerAdmin",
          level: 2,
          get title() {
            return t("routes.api_management");
          },
        },
        component: markRaw(ApiSettingsPanel),
      },
    ],
  },


];

// Account Route Group with Subtabs (Profile, Home Institution, API)
const accountRoute = {
  path: "/account",
  name: "Account",
  redirect: "/account/profile",
  meta: {
    ...AUTH_BASE,
    role: "Viewer",
    level: 1,
    get title() {
      return t("routes.account_settings");
    },
  },
  children: [
    {
      path: "profile",
      name: "Profile",
      show: 1,
      meta: {
        ...AUTH_BASE,
        role: "Viewer",
        level: 2,
        tab: "profile",
        get title() {
          return t("routes.my_profile");
        },
      },
      component: markRaw(AccountSettings),
    },
    {
      path: "institution",
      name: "HomeInstitution",
      show: 1,
      meta: {
        ...AUTH_BASE,
        role: "Viewer",
        level: 2,
        tab: "institution",
        get title() {
          return t("routes.home_institution");
        },
      },
      component: markRaw(AccountSettings),
    },
    {
      path: "api",
      name: "API",
      show: 1,
      meta: {
        ...AUTH_BASE,
        role: "Viewer",
        level: 2,
        tab: "api",
        get title() {
          return t("routes.ccplus_api");
        },
      },
      component: markRaw(AccountSettings),
    },
  ],
};

// Backward-compatible redirects for legacy routes
const legacyAccountRedirects = [
  { path: "/profile", redirect: "/account/profile" },
  { path: "/ccplusApi", redirect: "/account/api" },
  { path: "/server/api", redirect: "/account/api" },
];

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    accountRoute,
    ...routes,
    ...legacyAccountRedirects,
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

  // 3. Redirect ServerAdmin away from tenant Account tabs to Server Settings
  if (to.matched.some((r) => r.name === "Account") && authStore.is_serveradmin) {
    return { name: "ServerSettings" };
  }

  // 4. Intercept and Handle Logout Routines
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
