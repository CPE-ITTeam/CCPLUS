// resources/js/composables/useCredentialTest.js
import { ref } from 'vue';
import api from '@/services/api.js';

export function useCredentialTest() {
  const isTesting = ref(false);
  const testingRowId = ref(null);
  const testDialog = ref(false);
  const testPassed = ref(null);
  const testStatus = ref('');
  const testRows = ref([]);
  const testTargetLabel = ref('');

  async function runCredentialTest(payload, label = '') {
    isTesting.value = true;
    testingRowId.value = payload.credentials_id ?? payload.id ?? null;
    testTargetLabel.value = label;
    testPassed.value = null;
    testStatus.value = '';
    testRows.value = [];
    testDialog.value = true;

    try {
      const res = await api.post('/api/credentials/test', payload);
      const data = res?.data ?? res;
      testPassed.value = Boolean(data?.result);
      testStatus.value = data?.msg || '';
      const rows = Array.isArray(data?.rows)
        ? data.rows
        : data?.rows
          ? [String(data.rows)]
          : [];
      testRows.value = rows.length > 0 ? rows : [data?.result ? 'OK' : 'Test Failed'];
    } catch (e) {
      testPassed.value = false;
      testStatus.value = e?.response?.data?.msg || e.message || 'Test request failed';
      testRows.value = [testStatus.value];
    } finally {
      isTesting.value = false;
      testingRowId.value = null;
    }
  }

  return {
    isTesting,
    testingRowId,
    testDialog,
    testPassed,
    testStatus,
    testRows,
    testTargetLabel,
    runCredentialTest,
  };
}
