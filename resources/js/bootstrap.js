import axios from 'axios';
import i18n from './plugins/i18n';

window.axios = axios;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

axios.interceptors.request.use((config) => {
  config.headers['X-Locale'] = i18n.global.locale.value;

  const token = localStorage.getItem('user-token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
