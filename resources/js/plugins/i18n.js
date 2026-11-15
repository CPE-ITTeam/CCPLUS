import { createI18n } from 'vue-i18n';
import en from '@/locales/en.js';
import fi from '@/locales/fi.js';
import es from '@/locales/es.js';
import fr from '@/locales/fr.js';
import de from '@/locales/de.js';
import hu from '@/locales/hu.js';

const supportedLocales = ['en', 'fi', 'es', 'fr', 'de', 'hu'];

function detectLocale() {
  const saved = localStorage.getItem('ccplus_locale');
  if (saved && supportedLocales.includes(saved)) {
    return saved;
  }
  const serverDefault = window.ccplusConfig?.default_locale;
  if (serverDefault && supportedLocales.includes(serverDefault)) {
    return serverDefault;
  }
  return 'en';
}

const initialLocale = detectLocale();
document.documentElement.setAttribute('lang', initialLocale);

const i18n = createI18n({
  legacy: false,
  locale: initialLocale,
  fallbackLocale: 'en',
  messages: {
    en,
    fi,
    es,
    fr,
    de,
    hu,
  },
});

export function setAppLocale(locale, persist = true) {
  if (!supportedLocales.includes(locale)) return;
  i18n.global.locale.value = locale;
  if (persist) {
    localStorage.setItem('ccplus_locale', locale);
  }
  document.documentElement.setAttribute('lang', locale);
}

export default i18n;
