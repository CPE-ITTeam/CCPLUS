import axios from 'axios';
import { useAuthStore } from '@/stores/useAuthStore.js';

export function useHarvestDownload() {
  const authStore = useAuthStore();

  async function ccRawJson(id) {
    try {
      const token = authStore.token || localStorage.getItem('user-token');
      const response = await axios.get(`/api/harvests/raw/${id}`, {
        responseType: 'blob',
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
          'X-Tenant': authStore.ccp_key,
        },
        withCredentials: true,
      });

      const blob = new Blob([response.data], {
        type: response.headers['content-type'],
      });

      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;

      const disposition = response.headers['content-disposition'];
      const fileName = disposition && disposition.includes('filename=')
        ? disposition.split('filename=')[1].split(';')[0].replace(/"/g, '').trim()
        : `Harvest_${id}_JSON`;

      link.setAttribute('download', fileName);
      document.body.appendChild(link);
      link.click();

      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      if (error.response?.data instanceof Blob) {
        const text = await error.response.data.text();
        try {
          console.error('Server error payload:', JSON.parse(text));
        } catch {
          console.error('Server error text:', text);
        }
      } else {
        console.error('Download failed:', error);
      }
    }
  }

  return {
    ccRawJson,
  };
}
