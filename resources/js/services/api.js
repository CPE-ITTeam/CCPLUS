// resources/js/services/api.js
import axios from 'axios';
import router from '@/plugins/vue_router.js';
import { useAuthStore } from '@/stores/useAuthStore';
import i18n from '@/plugins/i18n';

const api = axios.create({
  headers: {
    'Accept': 'application/json',
  },
});

// Interceptor injects locale, authorization, and tenancy headers dynamically into every request
api.interceptors.request.use((config) => {
  config.headers['X-Locale'] = i18n.global.locale.value;

  // Safe bypass for unauthenticated check points
  if (config.url && config.url.includes('/login')) {
    return config;
  }

  const authStore = useAuthStore();

  if (authStore.token) {
    config.headers.Authorization = `Bearer ${authStore.token}`;
  }

  // Fallback directly to storage context if memory hasn't hydrated yet
  const tenantKey = authStore.ccp_key || localStorage.getItem('ccp_key') || '';

  if (tenantKey) {
    config.headers['X-Tenant'] = tenantKey;
  }

  return config;
});

// Clear stale auth state and route to Login cleanly on 401/419 session expiration
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;
    if ((status === 401 || status === 419) && !error?.config?.url?.includes('/login')) {
      const authStore = useAuthStore();
      if (authStore.isAuthenticated || authStore.token) {
        authStore.logoutStateClear();
      }
      if (router.currentRoute.value.path !== '/login') {
        router.push('/login');
      }
    }
    return Promise.reject(error);
  }
);

export default api;
