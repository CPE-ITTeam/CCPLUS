// resources/js/composables/useDatasetActions.js
import { ref, unref } from "vue";
import api from '@/services/api.js';

export function useDatasetActions(urlRoot) {
  const isProcessing = ref(false);
  const getBaseUrl = () => unref(urlRoot) || '';

  // Handles both Add and Edit form submission cycles safely
  async function saveItem(updatedValues, formDialogType, itemId = null) {
    isProcessing.value = true;
    const isEdit = formDialogType === "Edit";
    const url = isEdit ? `${getBaseUrl()}/update/${itemId}` : `${getBaseUrl()}/store`;

    try {
      const response = isEdit ? await api.patch(url, updatedValues) : await api.post(url, updatedValues);
      
      // ─── DEFENSIVE UNWRAPPING LAYER ───
      // Extracts payload safely regardless of whether it's wrapped by Axios,
      // a global proxy interceptor, or custom response middleware.
      let payload = response;
      if (response && response.data !== undefined) {
        payload = response.data;
      }

      // Check standard backend boolean parameter structures
      const isSuccess = payload && (payload.result === true || payload.result === 1 || payload.success === true);

      if (isSuccess) {
        return { 
          success: true, 
          record: payload.record || payload.data || updatedValues, // Fallback to local inputs if server returned empty record
          msg: payload.msg || payload.message || "Operation completed successfully." 
        };
      }
      
      return { success: false, msg: payload?.msg || payload?.message || "The server rejected the modification parameters." };
      // ... rest of your try block remains untouched
    } catch (error) {
      console.error(`[DatasetActions] Error during ${formDialogType}:`, error);
      
      if (error.response?.status === 422) {
        const validationErrors = error.response.data.errors;
        
        // DYNAMIC EXTRACTOR: If validation fields exist, grab the first message string dynamically
        if (validationErrors && typeof validationErrors === 'object') {
          const firstKey = Object.keys(validationErrors)[0];
          if (firstKey && validationErrors[firstKey]) {
            const errorPayload = validationErrors[firstKey];
            return {
              success: false,
              msg: Array.isArray(errorPayload) ? errorPayload[0] : errorPayload
            };
          }
        }
        
        const fallbackMsg = error.response.data.message || "Validation constraints check failed.";
        return { success: false, msg: fallbackMsg };
      }
      
      return { success: false, msg: error.response?.data?.message || error.response?.data?.msg || "An unexpected server error occurred." };
    } finally {

      isProcessing.value = false;
    }
  }


  // Manages inline field updates (status toggles, validations)
  async function toggleStatusField(id, field, value, currentItemState) {
    const url = `${getBaseUrl()}/update/${id}`;
    let payload = {};

    if (field === "status") {
      payload.is_active = value === "Active" ? 1 : 0;
    } else if (field === "inst_valid" || field === "plat_valid") {
      payload[field] = value;
    } else {
      return { success: false, msg: "Unsupported column transformation attribute." };
    }

    try {
      const response = await api.patch(url, payload);
      const resData = response?.data !== undefined ? response.data : response;
      if (!resData.result) return { success: false, msg: resData.msg };

      let mutatedRecord = { ...currentItemState };
      if (field === "status") {
        mutatedRecord.is_active = payload.is_active;
        mutatedRecord.status = value;
      } else {
        mutatedRecord[field] = value;
        if (mutatedRecord.inst_valid === "Active" && mutatedRecord.plat_valid === "Active") {
          mutatedRecord.valid_state = ["Fully Validated"];
        } else {
          mutatedRecord.valid_state = [];
          if (mutatedRecord.inst_valid === "Inactive") mutatedRecord.valid_state.push("Institution Not Validated");
          if (mutatedRecord.plat_valid === "Inactive") mutatedRecord.valid_state.push("Platform Not Validated");
        }
      }
      return { success: true, record: mutatedRecord, msg: resData.msg };
    } catch (error) {
      console.error("[DatasetActions] Error syncing validation toggle:", error);
      return { success: false, msg: "Failed to sync status change back to the server." };
    }
  }

  // Row deletion handler
  async function deleteItem(id) {
    const url = `${getBaseUrl()}/delete/${id}`;
    try {
      const response = await api.delete(url);
      const resData = response?.data !== undefined ? response.data : response;
      return { success: !!resData.result, msg: resData.msg };
    } catch (error) {
      console.error("[DatasetActions] Error executing row destruction:", error);
      return { success: false, msg: "Server connection lost while attempting destruction." };
    }
  }

  return { isProcessing, saveItem, toggleStatusField, deleteItem };
}
