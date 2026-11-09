// resources/js/composables/useRegistryProgress.js
import { ref } from 'vue';
import api from '@/services/api.js';

export function useRegistryProgress() {
  const refreshProgress = ref({ active: false, cancelling: false, current: 0, total: 0, name: '', message: '' });
  let registryPollTimer = null;

  function stopRegistryProgress() {
    if (registryPollTimer) {
      clearTimeout(registryPollTimer);
      registryPollTimer = null;
    }
    refreshProgress.value.active = false;
  }

  async function pollRegistryProgress() {
    if (!refreshProgress.value.active) return;
    try {
      const { data } = await api.get('/api/global/registries/progress');
      if (!refreshProgress.value.active) return;

      if (data.status === 'running') {
        refreshProgress.value.current = data.current ?? 0;
        refreshProgress.value.total = data.total ?? 0;
        const activeMessage = data.name || data.message || '';
        refreshProgress.value.name = activeMessage;
        refreshProgress.value.message = activeMessage;
      } else if (data.status === 'done' || data.status === 'cancelled') {
        stopRegistryProgress();
        return;
      }
    } catch { }

    if (refreshProgress.value.active) {
      registryPollTimer = setTimeout(pollRegistryProgress, 1500);
    }
  }

  function startRegistryProgress() {
    stopRegistryProgress();
    refreshProgress.value.active = true;
    refreshProgress.value.cancelling = false;
    refreshProgress.value.current = 0;
    refreshProgress.value.total = 0;
    refreshProgress.value.name = 'Fetching registry list...';
    refreshProgress.value.message = 'Fetching registry list...';
    registryPollTimer = setTimeout(pollRegistryProgress, 1200);
  }

  async function cancelRegistryRefresh() {
    refreshProgress.value.cancelling = true;
    try {
      await api.get('/api/global/registries/cancel');
    } catch (error) {
      console.error("Failed to cancel registry refresh:", error);
    } finally {
      refreshProgress.value.cancelling = false;
      stopRegistryProgress();
    }
  }

  return { refreshProgress, startRegistryProgress, stopRegistryProgress, cancelRegistryRefresh };
}
