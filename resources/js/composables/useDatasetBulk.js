// resources/js/composables/useDatasetBulk.js
import { ref, unref } from "vue";
import api from "@/services/api.js";
import Swal from "sweetalert2";
import i18n from "@/plugins/i18n.js";

const t = (key) => i18n.global.t(key);

export function useDatasetBulk({
  urlRoot,
  allItems,
  filteredItems,
  selectedRows,
  typeDialog,
  newType,
  dtKey,
  updateItems,
  startRegistryProgress,
  stopRegistryProgress,
}) {
  const isBulkProcessing = ref(false);

  async function executeBulkAction(data) {
    // 1. Intercept UI dialog trigger overrides
    if (data.action === "Assign Type") {
      newType.id = null;
      newType.name = "";
      typeDialog.value = true;
      return { handledByUi: true };
    }

    const root = unref(urlRoot);
    const itemsList = unref(allItems) || [];
    const filteredList = unref(filteredItems) || [];
    const selectedList = unref(selectedRows) || [];

    const isRegistryRefresh =
      data.action === "Refresh Registry" || data.action === "Full Refresh";
    const bulkUrl = root + (isRegistryRefresh ? "/refresh" : "/bulk");

    // Aggregate ID array arguments safely
    const args =
      data.action !== "Full Refresh"
        ? { ids: selectedList.map((ii) => ii.id) }
        : { ids: "ALL" };

    Object.keys(data).forEach((key) => {
      args[key] = data[key];
    });

    // Handle background polling triggers natively via bridge hooks
    if (isRegistryRefresh && typeof startRegistryProgress === "function") {
      startRegistryProgress();
    }

    try {
      isBulkProcessing.value = true;
      const response = await api.post(bulkUrl, args);
      const resData = response?.data !== undefined ? response.data : response;

      if (resData?.result && (itemsList.length > 0 || isRegistryRefresh)) {
        // ─── TRANSFORMATION ENGINE SWITCHES ───
        if (data.action === "Set Active" || data.action === "Set Inactive") {
          if (resData.affectedIds?.length > 0) {
            itemsList
              .filter((aitm) => resData.affectedIds.includes(aitm.id))
              .forEach((itm) => {
                if (typeof itm.is_active !== "undefined")
                  itm.is_active = data.action === "Set Active" ? 1 : 0;
                if (typeof itm.status !== "undefined")
                  itm.status =
                    data.action === "Set Active" ? "Active" : "Inactive";
              });
          }
        } else if (
          data.action === "Create New Group" ||
          data.action === "Add to Existing Group"
        ) {
          return { success: true, delegateGroupUi: true, resData, data };
        } else if (data.action === "Enable") {
          if (resData.affectedItems?.length > 0) {
            resData.affectedItems.forEach((ritm) => {
              const idx = itemsList.findIndex((itm) => itm.id == ritm.id);
              if (idx >= 0) itemsList[idx]["status"] = ritm.status;
            });
          }
        } else if (data.action === "Disable") {
          if (resData.affectedIds?.length > 0) {
            itemsList
              .filter((aitm) => resData.affectedIds.includes(aitm.id))
              .forEach((itm) => {
                itm.status = "Disabled";
              });
          }
        } else if (data.action === "Delete" || data.action === "Kill") {
          if (resData.affectedIds?.length > 0) {
            resData.affectedIds.forEach((itemId) => {
              const aIdx = itemsList.findIndex((ii) => ii.id == itemId);
              if (aIdx >= 0) itemsList.splice(aIdx, 1);
              const fIdx = filteredList.findIndex((ii) => ii.id == itemId);
              if (fIdx >= 0) filteredList.splice(fIdx, 1);
            });
            dtKey.value++;
          }
        } else if (
          data.action === "Refresh Registry" ||
          data.action === "Full Refresh"
        ) {
          if (resData.affectedItems?.length > 0) {
            let new_platforms = false;
            resData.affectedItems.forEach((plat) => {
              const _idx = itemsList.findIndex((itm) => itm.id == plat.id);
              if (_idx < 0) {
                itemsList.push(plat);
                new_platforms = true;
              } else {
                itemsList.splice(_idx, 1, plat);

                const targetDate =
                  plat.updated ||
                  (plat.updated_at
                    ? plat.updated_at.substring(0, 16).replace("T", " ")
                    : "");
                if (targetDate) {
                  const rows = document.querySelectorAll("tr");
                  rows.forEach((row) => {
                    if (row.innerText.includes(plat.name)) {
                      const cells = row.querySelectorAll("td");
                      cells.forEach((cell) => {
                        if (
                          cell.innerText.includes("-") &&
                          cell.innerText.includes(":")
                        ) {
                          cell.innerText = targetDate;
                        }
                      });
                    }
                  });
                }
              }
            });

            if (resData.summary) {
              Swal.fire({
                title: t("dialogs.refresh_results"),
                html: resData.summary,
                icon: "info",
                confirmButtonText: t("common.close"),
              });
            }
            if (new_platforms) {
              itemsList.sort((a, b) =>
                a.name.toLowerCase().localeCompare(b.name.toLowerCase()),
              );
            }
          }
          if (typeof updateItems === "function") updateItems();
        } else if (
          data.action === "Assign Type" ||
          data.action === "Set Institution Type"
        ) {
          const targetTypeId = newType?.id ?? resData?.newType?.id;
          const targetTypeName = newType?.name ?? resData?.newType?.name;

          if (resData.affectedIds?.length > 0 && targetTypeId != null) {
            itemsList
              .filter((aitm) => resData.affectedIds.includes(aitm.id))
              .forEach((itm) => {
                itm.type = targetTypeName;
                itm.type_id = targetTypeId;
              });

            typeDialog.value = false;

            if (dtKey && typeof dtKey.value !== "undefined") {
              dtKey.value++;
            }
          }
        } else if (data.action === "Restart" || data.action === "Pause") {
          resData.affectedItems?.forEach((newItem) => {
            const idx = itemsList.findIndex((ii) => ii.id == newItem.id);
            if (idx >= 0) itemsList.splice(idx, 1, newItem);
          });
          if (typeof updateItems === "function") updateItems();
          dtKey.value++;
        } else if (data.action.includes(" Validat")) {
          itemsList
            .filter((aitm) => resData.affectedIds.includes(aitm.id))
            .forEach((itm) => {
              Object.entries(resData.data).forEach(([col, value]) => {
                itm[col] = value == null ? "Inactive" : "Active";
              });
            });
        }

        return { success: true, msg: resData.msg };
      } else {
        return {
          success: false,
          msg: resData?.msg || t("messages.bulk_action_failed"),
        };
      }
    } catch (error) {
      console.error(
        "[BulkActions Engine] Error processing bulk request: ",
        error,
      );
      return { success: false, msg: t("messages.bulk_server_exception") };
    } finally {
      isBulkProcessing.value = false;
      if (typeof stopRegistryProgress === "function") {
        stopRegistryProgress();
      }
    }
  }

  return {
    isBulkProcessing,
    executeBulkAction,
  };
}
