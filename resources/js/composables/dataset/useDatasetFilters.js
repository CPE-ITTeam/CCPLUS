// resources/js/composables/dataset/useDatasetFilters.js

import { toValue } from "vue";
import { isActiveFilterOption } from "@/composables/dataset/useDatasetSchema.js";

export function useDatasetFilters({
  datasetKey,
  filterOptions,
  allOptions,
  allItems,
  filteredItems,
  selectedRows,
  dtKey,
  refreshData,
}) {
  function syncMutualInstitutionGroupVisibility() {
    const instFilter =
      filterOptions.institutions ||
      filterOptions.institution ||
      filterOptions.inst_id;
    const grpFilter =
      filterOptions.groups || filterOptions.group || filterOptions.group_id;
    const platVal =
      filterOptions.platforms?.value ?? filterOptions.platform?.value;
    const hasPlat = Array.isArray(platVal)
      ? platVal.length > 0
      : Boolean(platVal);
    const isAudit = toValue(datasetKey) === "audit";

    if (filterOptions.scope_mode) {
      const useGroups = Boolean(filterOptions.scope_mode.value);
      if (instFilter) {
        instFilter.show =
          !useGroups &&
          (!isAudit || !hasPlat) &&
          instFilter.items?.length > 0;
      }
      if (grpFilter) {
        grpFilter.show =
          useGroups &&
          (!isAudit || !hasPlat) &&
          grpFilter.items?.length > 0;
      }
      if (isAudit && filterOptions.platforms) {
        const instVal = instFilter?.value;
        const grpVal = grpFilter?.value;
        const hasInst = Array.isArray(instVal)
          ? instVal.length > 0
          : Boolean(instVal);
        const hasGrp = Array.isArray(grpVal)
          ? grpVal.length > 0
          : Boolean(grpVal);
        filterOptions.platforms.show =
          !hasInst && !hasGrp && filterOptions.platforms.items?.length > 0;
      }
      return;
    }

    const instVal = instFilter?.value;
    const grpVal = grpFilter?.value;
    const hasInst = Array.isArray(instVal)
      ? instVal.length > 0
      : Boolean(instVal);
    const hasGrp = Array.isArray(grpVal) ? grpVal.length > 0 : Boolean(grpVal);

    if (grpFilter)
      grpFilter.show = !hasInst && grpFilter.items?.length > 0;
    if (instFilter)
      instFilter.show =
        !hasGrp && (!isAudit || !hasPlat) && instFilter.items?.length > 0;
    if (isAudit && filterOptions.platforms)
      filterOptions.platforms.show =
        !hasInst && filterOptions.platforms.items?.length > 0;
  }

  function handleFilter(filt) {
    const dsKey = toValue(datasetKey);
    const shouldServerRefresh =
      filt.refresh || dsKey === "jobs" || dsKey === "harvests";

    if (filt.key === "reset") {
      selectedRows.value = [];
      for (const key of Object.keys(filterOptions)) {
        const f = filterOptions[key];
        if (!f) continue;
        if (key === "scope_mode") {
          f.value = false;
          f.show = true;
          continue;
        }
        if (f.type === "ymrange") {
          f.value = { fromYM: "", toYM: "" };
          f.show = true;
          continue;
        }
        if (key === "valid_types") {
          f.value = "Not Validated";
          f.show = true;
          continue;
        }
        if (
          key === "is_conso" ||
          key === "status_switch" ||
          key === "valid_target" ||
          key === "releases"
        ) {
          f.value = "All";
          f.show = true;
          continue;
        }

        if (Array.isArray(f.items) && f.items.length === 0) {
          f.show = false;
          continue;
        }
        if (Array.isArray(f.value)) {
          f.value = [];
        } else if (f.value !== null && f.value !== undefined) {
          f.value = f.type === "toggle" ? "Inactive" : null;
        }
        f.show = true;
      }
      syncMutualInstitutionGroupVisibility();
      if (shouldServerRefresh) {
        refreshData(dsKey);
      } else {
        updateItems();
      }
      return;
    }

    if (
      !filterOptions[filt.key] &&
      (dsKey === "jobs" || dsKey === "harvests") &&
      filt.key === "statuses"
    ) {
      filterOptions.statuses = {
        name: "statuses",
        type: "mselect",
        col: "d_status",
        show: false,
        items: [],
        value: [],
      };
    }

    const target = filterOptions[filt.key];
    if (!target || target.value === undefined) return;

    target.value = filt.value;

    if (filt.key === "scope_mode") {
      const instFilter =
        filterOptions.institutions ||
        filterOptions.institution ||
        filterOptions.inst_id;
      const grpFilter =
        filterOptions.groups || filterOptions.group || filterOptions.group_id;
      const hadClearedValue = Array.isArray(
        filt.value ? instFilter?.value : grpFilter?.value,
      )
        ? (filt.value ? instFilter.value.length : grpFilter.value.length) > 0
        : Boolean(filt.value ? instFilter?.value : grpFilter?.value);

      if (filt.value && instFilter) {
        instFilter.value = Array.isArray(instFilter.value) ? [] : null;
      } else if (!filt.value && grpFilter) {
        grpFilter.value = Array.isArray(grpFilter.value) ? [] : null;
      }
      syncMutualInstitutionGroupVisibility();

      if (shouldServerRefresh && !hadClearedValue) {
        return;
      }
    } else if (
      filt.key.includes("inst") ||
      filt.key.includes("group") ||
      filt.key.includes("platform")
    ) {
      syncMutualInstitutionGroupVisibility();
    }

    if (shouldServerRefresh) {
      refreshData(dsKey);
    } else {
      updateItems();
    }
  }


  function matchesFilter(item, filter) {
    if (!filter || !filter.show) return true;
    if (Array.isArray(filter.value) && filter.value.length === 0) return true;
    if (!Array.isArray(filter.value) && !filter.value) return true;

    if (filter.name === "status_switch") {
      if (!filter.value || filter.value === "All") return true;
      return (
        String(item[filter.col] || "").toLowerCase() ===
        String(filter.value).toLowerCase()
      );
    }

    if (filter.name === "is_conso") {
      if (filter.value === "All" || filter.value === "Inactive") return true;
      const dsKey = toValue(datasetKey);
      const flag =
        dsKey === "platforms"
          ? Boolean(item.is_connected ?? Number(item.instance_count || 0) > 0)
          : Boolean(item[filter.col] ?? item.is_conso);
      if (filter.value === "Active") return flag;
      if (filter.value === "NonConso") return !flag;
      return true;
    }

    if (filter.name === "releases") {
      if (!filter.value || filter.value === "All") return true;
      const target = String(filter.value).trim();
      const raw = item[filter.col];
      const tokens = Array.isArray(raw)
        ? raw.flatMap((r) => String(r).split(/[\s,;|]+/))
        : raw != null
          ? String(raw).split(/[\s,;|]+/)
          : [];
      const normalized = tokens
        .map((t) => t.trim().replace(/^R/i, ""))
        .filter(Boolean);
      if (target === "5") {
        return normalized.includes("5") && !normalized.includes("5.1");
      }
      return normalized.includes(target);
    }

    if (filter.name === "valid_types") {
      if (!filter.value || filter.value === "All") return true;
      const states = Array.isArray(item[filter.col]) ? item[filter.col] : [];
      if (filter.value === "Validated")
        return states.includes("Fully Validated");
      if (filter.value === "Not Validated")
        return states.includes("Not Fully Validated");
      return states.includes(filter.value);
    }

    if (filter.name === "valid_target") {
      if (filterOptions.valid_types?.value !== "Not Validated") return true;
      if (!filter.value || filter.value === "All") return true;
      const states = Array.isArray(item[filter.col]) ? item[filter.col] : [];
      if (filter.value === "Platform")
        return states.includes("Platform Not Validated");
      if (filter.value === "Institution")
        return states.includes("Institution Not Validated");
      return true;
    }

    if (filter.type === "ymrange") {
      const fromYM = filter.value?.fromYM || "";
      const toYM = filter.value?.toYM || "";
      if (!fromYM && !toYM) return true;
      const ym = String(item[filter.col] || "");
      if (fromYM && ym < fromYM) return false;
      if (toYM && ym > toYM) return false;
      return true;
    }

    if (filter.type === "select" || filter.type === "text") {
      const targetVal =
        typeof filter.value === "object" && filter.value !== null
          ? (filter.value.id ?? filter.value.value ?? filter.value.name)
          : filter.value;

      if (Array.isArray(item[filter.col])) {
        return item[filter.col].some((f) => String(f) === String(targetVal));
      }
      return String(item[filter.col]) === String(targetVal);
    }

    if (filter.type === "toggle") {
      return filter.value === "Active" ? true : Boolean(item[filter.col]);
    }

    if (filter.type === "mselect" || filter.type === "mtext") {
      const targets = Array.isArray(filter.value)
        ? filter.value.map((v) => String(v).trim().toLowerCase())
        : [String(filter.value).trim().toLowerCase()];

      let itemValues = [];
      if (Array.isArray(item[filter.col])) {
        itemValues = item[filter.col].map((v) =>
          String(v).trim().toLowerCase(),
        );
      } else if (item[filter.col] !== undefined && item[filter.col] !== null) {
        itemValues = String(item[filter.col])
          .split(",")
          .map((v) => v.trim().toLowerCase());
      }

      if (filter.col === "group_string" || filter.col === "group_ids") {
        const fallbackIds = Array.isArray(item.group_ids)
          ? item.group_ids.map((v) => String(v).trim())
          : item.group_ids
            ? String(item.group_ids)
                .split(",")
                .map((v) => v.trim())
            : [];
        if (fallbackIds.some((id) => targets.includes(id))) return true;
      }

      return itemValues.some((val) => targets.includes(val));
    }

    return true;
  }

  function syncFilterOptionItems() {
    if (allItems.length === 0) return;

    const dsKey = toValue(datasetKey);
    const isServerFiltered = dsKey === "jobs" || dsKey === "harvests";

    for (const key of Object.keys(filterOptions)) {
      const filter = filterOptions[key];
      if (!filter) continue;
      if (
        filter.type === "ymrange" ||
        key === "is_conso" ||
        key === "status_switch" ||
        key === "valid_types" ||
        key === "valid_target" ||
        key === "releases"
      ) {
        filter.show = true;
        continue;
      }

      const rawSource = allOptions[key];
      if (!Array.isArray(rawSource)) continue;
      if (isServerFiltered && key === "statuses") continue;

      const source = rawSource.filter((opt) => isActiveFilterOption(opt, key));

      if (isServerFiltered) {
        filter.items = [...source];
        filter.show = filter.items.length > 0;
        continue;
      }

      const pool = allItems.filter((item) =>
        Object.keys(filterOptions).every((otherKey) =>
          otherKey === key
            ? true
            : matchesFilter(item, filterOptions[otherKey]),
        ),
      );

      const presentValues = new Set(
        pool
          .flatMap((itm) => itm[filter.col] ?? [])
          .map((v) => String(v).trim()),
      );

      filter.items = source
        .map((opt) => (typeof opt === "string" ? opt.trim() : opt))
        .filter((opt) => {
          const optValue =
            filter.val && typeof opt === "object" && opt !== null
              ? opt[filter.val]
              : opt;
          return presentValues.has(String(optValue).trim());
        });

      filter.show = filter.items.length > 0;
    }

    syncMutualInstitutionGroupVisibility();
  }

  function updateItems() {
    if (allItems.length === 0) {
      filteredItems.splice(0, filteredItems.length);
      return;
    }

    const dsKey = toValue(datasetKey);
    const isServerFiltered = dsKey === "jobs" || dsKey === "harvests";

    const filterResult = isServerFiltered
      ? [...allItems]
      : allItems.filter((item) =>
          Object.keys(filterOptions).every((key) =>
            matchesFilter(item, filterOptions[key]),
          ),
        );

    if (
      filterOptions.is_conso &&
      typeof filterOptions.platforms !== "undefined" &&
      Array.isArray(allOptions.platforms)
    ) {
      const activePlatforms = allOptions.platforms.filter((itm) =>
        isActiveFilterOption(itm, "platforms"),
      );
      const mode = filterOptions.is_conso.value;
      filterOptions.platforms.items =
        mode === "Active"
          ? activePlatforms.filter((itm) => Boolean(itm.is_conso))
          : mode === "NonConso"
            ? activePlatforms.filter((itm) => !itm.is_conso)
            : activePlatforms;
    }

    filteredItems.splice(0, filteredItems.length, ...filterResult);
    syncFilterOptionItems();
  }

  return {
    syncMutualInstitutionGroupVisibility,
    handleFilter,
    matchesFilter,
    syncFilterOptionItems,
    updateItems,
  };
}
