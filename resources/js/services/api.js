// resources/js/services/api.js
import axios from 'axios';
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
  const tenantKey = authStore.ccp_key || localStorage.getItem('ccp_key') || 'con_template';

  if (tenantKey) {
    config.headers['X-Tenant'] = tenantKey;
  }

  return config;
});

export default api;
