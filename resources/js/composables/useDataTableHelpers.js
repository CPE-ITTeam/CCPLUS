// resources/js/composables/useDataTableHelpers.JS

import { ref } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';

export function useDataTableHelpers() {
  const authStore = useAuthStore();
  const copiedCell = ref('');

  async function copyCellValue(key, value) {
    if (value == null || value === '') return;
    const text = String(value);
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
      }
      copiedCell.value = key;
      setTimeout(() => {
        if (copiedCell.value === key) copiedCell.value = '';
      }, 1500);
    } catch (e) {
      console.error('Copy failed:', e);
    }
  }

  function resolveActiveTimeZone() {
    return (
      authStore.timezone ||
      authStore.user?.timezone ||
      Intl.DateTimeFormat().resolvedOptions().timeZone
    );
  }

  function formatDateDisplay(dateVal) {
    if (!dateVal) return '';
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return String(dateVal);

    return new Intl.DateTimeFormat('en-CA', {
      timeZone: resolveActiveTimeZone(),
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(d);
  }

  function formatDateTimeTooltip(dateVal) {
    if (!dateVal) return '';
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return String(dateVal);

    return new Intl.DateTimeFormat('en-CA', {
      timeZone: resolveActiveTimeZone(),
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
      .format(d)
      .replace(',', '');
  }

  function formatTruncatedDisplay(value, limit = 30) {
    if (value == null || value === '' || value === '-required-') return value ?? '';
    const str = String(value);
    return str.length > limit ? `${str.slice(0, limit)}...` : str;
  }

  return {
    copiedCell,
    copyCellValue,
    resolveActiveTimeZone,
    formatDateDisplay,
    formatDateTimeTooltip,
    formatTruncatedDisplay,
  };
}
