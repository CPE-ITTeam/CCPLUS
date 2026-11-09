// resources/js/stores/authStore.js
import { defineStore } from "pinia";
import { useLocalStorage } from "@vueuse/core";
import axios from "axios";

export const useAuthStore = defineStore("useAuthStore", {
  state: () => ({
    isAuthenticated: false,
    ccp_key: "",
    user: { id: null, name: "", inst_id: null, fiscalYr: "" },
    roles: [],
    adminInsts: [],
    adminGroups: [],
    token: useLocalStorage("user-token", null),
    authErrorMessage: null,
    authSuccessMessage: null,
  }),

  persist: {
    storage: sessionStorage,
  },

  getters: {
    is_serveradmin: (state) => {
      return (
        state.isAuthenticated &&
        state.roles.some((r) => r.name === "ServerAdmin")
      );
    },
    is_conso_admin: (state) => {
      return (
        state.isAuthenticated &&
        state.roles.some(
          (r) => r.name === "ServerAdmin" || (r.name === "Admin" && r.inst_id === 1)
        )
      );
    },
    is_group_admin: (state) => {
      return (
        state.isAuthenticated &&
        (state.roles.some(
          (r) =>
            r.name === "ServerAdmin" ||
            (r.name === "Admin" && (r.inst_id === 1 || r.group_id > 0))
        ) ||
          state.roles.filter((r) => r.name === "Admin").length > 1)
      );
    },
    is_admin: (state) => {
      return (
        state.isAuthenticated &&
        state.roles.some((r) => r.name === "Admin" || r.name === "ServerAdmin")
      );
    },
    user_inst_id: (state) => {
      return state.isAuthenticated ? state.user.inst_id : null;
    },
    user_FYMo: (state) => {
      return state.isAuthenticated ? state.user.fiscalYr : "";
    },
    user_FYmm: (state) => {
      return state.isAuthenticated && state.user?.fiscalYr
        ? new Date(Date.parse(state.user.fiscalYr + " 1, 2019")).getMonth() + 1
        : 1;
    },
    admin_insts: (state) => state.adminInsts,
    admin_groups: (state) => state.adminGroups,
  },

  actions: {
    // Direct login submission bridging backend validation parameters
    async login(credentials) {
      try {
        this.clearLoginError();
        const response = await axios.post("/api/login", {
          email: credentials.email,
          consortium: credentials.consortium,
          conso_id: credentials.conso_id,
          password: credentials.password,
        });

        if (response.data.success) {
          this.handleLoginSuccess(response.data.data);
        } else {
          this.setLoginError(
            response.data.message || "Unknown error during log in!"
          );
        }
      } catch (error) {
        this.setLoginError(
          error.response?.data?.message || "Server authentication connection failed."
        );
      }
    },

    // Bridge alias resolving legacy template handler reference calls system-wide
    async logout() {
      try {
        await axios.get("/api/logout");
      } catch (error) {
        console.error("Remote session drop failed:", error);
      } finally {
        this.logoutStateClear();
      }
    },

    // Auth success responses update state and manage routing targets
    handleLoginSuccess(payload) {
      this.token = payload.token;
      this.user = { ...payload.user };
      this.roles = [...payload.roles];
      this.adminInsts = [...payload.adminInsts];
      this.adminGroups = [...payload.adminGroups];
      this.ccp_key = payload.consoKey || "con_template";
      this.isAuthenticated = true;
      
      localStorage.setItem("user-token", this.token);
      this.authErrorMessage = null;
      this.authSuccessMessage = null;

      // Direct users based on permission scope
      if (this.is_serveradmin) {
        this.router.push({ name: "Consortia" });
      } else if (this.is_admin) {
        this.router.push({ name: "InstitutionsTable" });
      } else {
        this.router.push({ name: "Reports" });
      }
    },

    clearToken() {
      this.token = null;
      this.user = { id: null, name: "", inst_id: null, fiscalYr: "" };
      this.roles = []; // Fixed array baseline type prevents layout runtime exceptions
      this.adminInsts = [];
      this.adminGroups = [];
      this.isAuthenticated = false;
      localStorage.removeItem("user-token");
    },

    logoutStateClear() {
      this.clearToken();
      if (typeof this.router !== "undefined") {
        this.router.push({ name: "Login" });
      }
    },

    setConsoKey(ccp_key) {
      if (!this.is_serveradmin) return;
      this.ccp_key = ccp_key || "con_template";
    },

    setLoginError(message) { this.authErrorMessage = message; },
    clearLoginError() { this.authErrorMessage = null; },
    setSuccessMessage(message) { this.authSuccessMessage = message; },
    clearSuccessMessage() { this.authSuccessMessage = null; },

    // Core session context driver tracking active connection tenant databases
    async setConso(id, ccp_key) {
      if (!this.is_serveradmin) return false;
      this.ccp_key = ccp_key || "con_template";
      try {
        const token = this.token || localStorage.getItem("user-token");
        
        // 💡 CHANGED: Passed a flat object payload to keep Laravel session storage from dropping
        await axios.post(
          "/api/updateSession",
          { conso_id: id, ccp_key: this.ccp_key }, 
          {
            headers: {
              Authorization: `Bearer ${token}`,
              Accept: "application/json",
            },
            withCredentials: true,
          }
        );
      } catch (error) {
        console.error("Failed to update active tenant database scope:", error);
      }
    },

  },
});
