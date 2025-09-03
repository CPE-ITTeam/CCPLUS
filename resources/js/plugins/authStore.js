// Pinia store : plugins/authStore.js
import { defineStore } from 'pinia';
import { useLocalStorage, createFetch } from '@vueuse/core';
import axios from 'axios';

export const useAuthStore = defineStore('useAuthStore', {
  state: () => ({
    isAuthenticated: false,
    user: {'id': null, 'name': '', 'inst_id': null},
    roles: [],
    token: useLocalStorage('user-token', null),
    authErrorMessage: null,
    authSuccessMessage: null,
  }),

  persist: {  // persist for the session
    storage: sessionStorage,
  },
  getters: {
    is_serveradmin: (state) => {
        return (state.isAuthenticated && state.roles.some( r => r.name == 'ServerAdmin'));
    },
    is_conso_admin: (state) => {
      return (state.isAuthenticated &&
              state.roles.some( r => (r.name == 'ServerAdmin' || (r.name=='Admin' && r.inst_id==1)) ));
    },
    is_admin: (state) => {
        return (state.isAuthenticated &&
                state.roles.some( r => (r.name == 'Admin' || r.name == 'ServerAdmin') ));
    },
    user_inst_id: state => {
      return (state.isAuthenticated) ? state.user.inst_id : null;
    },
    user_FYmo: state => {
      return (state.isAuthenticated) ? state.user.fiscalYr : '';
    },
    getToken: (state) => state.token,
    getToken: (state) => state.token,
    isLoggedIn: (state) => state.isAuthenticated,
  },
  actions: {
    // Possible action to check if token exists on startup
    // initAuth() {
    //   const storedToken = localStorage.getItem('user-token');
    //   if (storedToken) {
    //     this.setToken(storedToken);
    //   }
    // }
    async login(credentials) {
      try {
        const response = await axios.post('/api/login', {
          email: credentials.email,
          consortium: credentials.consortium,
          password: credentials.password,
        });
        if (response.data.success) {
          this.token = response.data.data.token;
          this.user = { ...response.data.data.user };
          this.roles = [ ...response.data.data.roles ];
          this.isAuthenticated = true;
          if ( this.is_admin ) {
            this.router.push('/admin');
          } else {
            this.router.push('/reports');
          }
          this.authErrorMessage = null;
          this.authSuccessMessage = null;
        } else {
          this.authErrorMessage = (response.data.message) ? response.data.message
                                                          : "Unknown error during log in!";
        }
      } catch {}
    },
    async forgotPass(input) {
      try {
        const response = await axios.post('/api/forgotPass', {
          email: input.email,
          consortium: input.consortium,
        });
        if (response.data.success) {
          this.authSuccessMessage = response.data.message;
          this.router.push('/api/login');
        } else {
          this.authErrorMessage = 'Failed to send email instructions: ' . response.data.message;
        }
      } catch {
        this.authErrorMessage = 'Failed to send email instructions!';
      }
    },
    async resetPass(input) {
      try {
        const response = await axios.post('/api/resetPass', {
          email: input.email,
          password: input.password,
          password_confirmation: input.password_confirmation,
          consortium: input.consortium,
          token: input.token,
        });
        if (response.data.success) {
          this.authSuccessMessage = response.data.message;;
          this.resetToken = null;
          this.router.push('/api/login');
        } else {
          this.authErrorMessage = 'Password change failed: ' . response.data.message;
        }
      } catch {
        this.authErrorMessage = 'Password change failed!';
      }
    },
    clearToken() {
      this.token = null;
      this.user = null;
      this.roles = null;
      this.isAuthenticated = false;
    },

    async logout() {
      try {
        await axios.get('/api/logout');
        this.user = null;
        this.roles = null;
        this.isAuthenticated = false;
        this.router.push('/api/login');
      } catch (error) {
        console.error('Logout attempt failed:', error);
      }
    },
    setLoginError(message) {
      this.authErrorMessage = message;
    },
    clearLoginError() {
      this.authErrorMessage = null;
    },
    setSuccessMessage(message) {
      this.authSuccessMessage = message;
    },
    clearSuccessMessage() {
      this.authSuccessMessage = null;
    },
    ccGet(url) {
      return axios({ method: 'get', url: url,
        headers: {
          Authorization: 'Bearer ' + this.token,
          Accept: "application/json",
        }
      });
    },
    // ccPost(url, postData) {
    //   return axios({ method: 'get', url: 'url', data: {...postData},
    //     headers: {
    //       Authorization: 'Bearer ' + this.token,
    //       Accept: "application/json",
    //     }
    //   });
    // }
  }
});