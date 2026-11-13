// resources/js/app.js
import './bootstrap';
import { createApp } from 'vue';

// Local Icon stylesheet (Keep this so your layout buttons and toggles display correctly)
import '@mdi/font/css/materialdesignicons.css';

// Setup i18n
import i18n from './plugins/i18n';
// Setup vuetify
import vuetify from './plugins/vuetify';
// Setup vue-router
import { router, pinia } from './plugins/vue_router.js';
// Build and mount the app
import App from './App.vue';

const app = createApp(App);
app.use(pinia);
app.use(i18n);
app.use(vuetify);
app.use(router);

// GLOBAL WCAG & SECURITY EXTERNAL DIRECTIVE
app.directive('external', {
  mounted(el) {
    // Find the anchor element (Vuetify often wraps interactive items inside inner links)
    const anchor = el.tagName === 'A' ? el : el.querySelector('a');
    
    if (anchor) {
      anchor.setAttribute('target', '_blank');
      anchor.setAttribute('rel', 'noopener noreferrer');
      
      // Extract the title attribute or inner text context to pass to the screen reader label
      const fallbackText = i18n.global.te('common.external_link')
        ? i18n.global.t('common.external_link')
        : 'External link';
      const opensNewTab = i18n.global.te('common.opens_new_tab')
        ? i18n.global.t('common.opens_new_tab')
        : 'opens in a new tab';
      const textContext = anchor.innerText || el.getAttribute('title') || fallbackText;
      
      if (!anchor.getAttribute('aria-label')) {
        anchor.setAttribute('aria-label', `${textContext.trim()} (${opensNewTab})`);
      }
    }
  }
});

app.mount('#app');
