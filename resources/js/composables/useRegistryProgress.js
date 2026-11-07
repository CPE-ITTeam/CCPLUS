// resources/js/composables/useRegistryProgress.js
import { ref } from 'vue';

export function useRegistryProgress(apiService) {
  const refreshProgress = ref({
    active: false,
    cancelling: false,
    current: 0, 
    total: 0,   
    name: '',     // 👈 Kept intact
    message: ''   // 👈 Added as a safety fallback property for your HTML template
  });
  
  let registryPollTimer = null;

  function stopRegistryProgress() {
    if (registryPollTimer) {
      clearTimeout(registryPollTimer);
      registryPollTimer = null;
    }
  }

  async function pollRegistryProgress() {
    if (!refreshProgress.value.active) return;

    try {
      const { data } = await apiService.get('/api/registry/progress-status');
      
      if (data) {
        // Sync progress integers
        refreshProgress.value.current = data.current ?? 0;
        refreshProgress.value.total = data.total ?? 0;
        
        // ─── COMPREHENSIVE TEXT MESSAGE MAP ───
        // Capture whichever text key the Laravel backend payload is outputting,
        // and map it to BOTH possible frontend properties simultaneously!
        const activeMessage = data.name || data.message || data.status || '';
        refreshProgress.value.name = activeMessage;
        refreshProgress.value.message = activeMessage;
        
        if (data.completed || data.failed) {
          refreshProgress.value.active = false;
          stopRegistryProgress();
          return;
        }
      }
      
      // Keep polling every 3 seconds if active
      registryPollTimer = setTimeout(pollRegistryProgress, 3000);
    } catch (error) {
      console.error("Registry polling loop failed:", error);
      stopRegistryProgress();
    }
  }

  function startRegistryProgress() {
    stopRegistryProgress();
    refreshProgress.value.active = true;
    refreshProgress.value.cancelling = false;
    refreshProgress.value.current = 0;
    refreshProgress.value.total = 0;
    refreshProgress.value.name = '';
    refreshProgress.value.message = '';
    pollRegistryProgress();
  }

  async function cancelRegistryRefresh() {
    refreshProgress.value.cancelling = true;
    try {
      await apiService.post('/api/registry/cancel-refresh');
      refreshProgress.value.active = false;
      stopRegistryProgress();
    } catch (error) {
      console.error("Failed to cancel registry refresh:", error);
    } finally {
      refreshProgress.value.cancelling = false;
    }
  }

  return {
    refreshProgress,
    startRegistryProgress,
    stopRegistryProgress,
    cancelRegistryRefresh
  };
}
