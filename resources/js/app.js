// resources/js/app.js
import './bootstrap';
import { createApp } from 'vue';
import '../sass/app.scss';
// Setup vuetify
import vuetify from './plugins/vuetify';
// Setup vue-router
import { router, pinia } from './plugins/vue_router.js';
// Build and mount the app
import App from './pages/App.vue';
const app = createApp(App);
app.use(pinia);
app.use(vuetify);
app.use(router);
app.mount('#app');
