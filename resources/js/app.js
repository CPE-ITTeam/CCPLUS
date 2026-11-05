// resources/js/app.js
import './bootstrap';
import { createApp } from 'vue';

// Local Icon stylesheet (Keep this so your layout buttons and toggles display correctly)
import '@mdi/font/css/materialdesignicons.css';

// Setup vuetify
import vuetify from './plugins/vuetify';
// Setup vue-router
import { router, pinia } from './plugins/vue_router.js';
// Build and mount the app
import App from './App.vue';

const app = createApp(App);
app.use(pinia);
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
      const textContext = anchor.innerText || el.getAttribute('title') || 'External link';
      
      if (!anchor.getAttribute('aria-label')) {
        anchor.setAttribute('aria-label', `${textContext.trim()} (opens in a new tab)`);
      }
    }
  }
});

app.mount('#app');
