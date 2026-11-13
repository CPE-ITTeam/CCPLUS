// resources/js/plugins/i18n.js
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.js';
import fi from '@/locales/fi.js';

const supportedLocales = ['en', 'fi'];

function detectLocale() {
  const saved = localStorage.getItem('ccplus_locale');
  if (saved && supportedLocales.includes(saved)) {
    return saved;
  }
  // Always default to English unless the user explicitly selected another locale
  return 'en';
}

const i18n = createI18n({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'en',
  messages: {
    en,
    fi,
  },
});

export function setAppLocale(locale) {
  if (!supportedLocales.includes(locale)) return;
  i18n.global.locale.value = locale;
  localStorage.setItem('ccplus_locale', locale);
  document.documentElement.setAttribute('lang', locale);
}

export default i18n;
