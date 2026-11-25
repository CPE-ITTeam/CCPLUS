// resources/js/composablesdataset/useDataseSchema.js

import { fyMonths } from "@/components/ui/YmInput.vue";

const REFRESHABLE_DATASETS = new Set(["harvests", "jobs", "audit"]);
const ENTITY_FILTER_FIELDS = new Set([
  "consortia",
  "institutions",
  "platforms",
]);
const STATUS_SWITCH_DATASETS = new Set([
  "consortia",
  "institutions",
  "platforms",
  "credentials",
]);

export function isActiveFilterOption(opt, fldName) {
  if (!ENTITY_FILTER_FIELDS.has(fldName)) return true;
  if (!opt || typeof opt !== "object") return true;
  if ("is_active" in opt && Number(opt.is_active) === 0) return false;
  if ("status" in opt && String(opt.status).toLowerCase() === "inactive")
    return false;
  return true;
}

export function buildFiscalYearOptions() {
  return fyMonths.map((month, index) => {
    const fullMonthName = new Date(2000, index, 1).toLocaleString("en-US", {
      month: "long",
    });
    return {
      id: Number(index + 1),
      name: month,
      label: month,
      fullName: fullMonthName,
      value: month,
      title: month,
    };
  });
}

export function buildHeaderEntry(fld) {
  const isCompactReportCol = ["PR", "DR", "TR", "IR"].includes(fld.name);
  const isCompactCredCol = ["customer_id", "requestor_id", "api_key"].includes(
    fld.name,
  );
  const isCompactCol = isCompactReportCol || isCompactCredCol;
  const colWidth = isCompactReportCol
    ? "52px"
    : isCompactCredCol
      ? "68px"
      : fld.width;

  let groupClass = "";
  if (isCompactReportCol) {
    groupClass = "compact-col report-group-col";
    if (fld.name === "PR") groupClass += " group-border-start";
    if (fld.name === "IR") groupClass += " group-border-end";
  } else if (isCompactCredCol) {
    groupClass = "compact-col cred-group-col";
    if (fld.name === "api_key") groupClass += " group-border-end";
  }

  const entry = {
    title: fld.shortLabel || fld.label,
    fullTitle: fld.label,
    key: fld.name,
    align: isCompactCol ? undefined : fld.header,
    type: fld.type,
    truncate: fld.truncate,
    ...(colWidth ? { width: colWidth, maxWidth: colWidth } : {}),
    ...(isCompactCol
      ? {
          headerProps: {
            class: `bg-info text-white ${groupClass}`,
            title: fld.label,
          },
          cellProps: { class: groupClass },
        }
      : {}),
  };

  if (fld.name === "error") {
    entry.sort = (a, b) => Number(a.id) - Number(b.id);
  }

  return entry;
}

// resources/js/composablesdataset/useDataseSchema.js

function resolvePresentOptionItems(rawOptions, fld, allItems) {
  if (!Array.isArray(rawOptions) || allItems.length === 0) return [];
  const presentValues = new Set(
    allItems
      .flatMap((itm) => {
        const val = itm[fld.filterCol];
        if (Array.isArray(val)) return val;
        if (typeof val === "string") return val.split(",");
        return val != null ? [val] : [];
      })
      .map((v) => String(v).trim())
      .filter(Boolean),
  );
  return rawOptions
    .map((opt) => (typeof opt === "string" ? opt.trim() : opt))
    .filter((opt) => {
      const optValue =
        fld.optVal && typeof opt === "object" && opt !== null
          ? opt[fld.optVal]
          : opt;
      return presentValues.has(String(optValue).trim());
    });
}

export function useDatasetSchema({
  datasetKey,
  datasetConfig,
  allItems,
  allOptions,
  filterOptions,
  headers,
  searchFields,
  editableFields,
  bulkOptions,
  urlRoot,
  isConsoAdmin,
}) {
  function applySchema(activeKey = datasetKey()) {
    const rawConfig = datasetConfig[activeKey];
    if (!rawConfig) return;

    const clonedFields = rawConfig.fields.map((f) => ({ ...f }));
    datasetConfig[activeKey] = {
      ...rawConfig,
      fields: clonedFields,
      required: [...(rawConfig.required || [])],
      static: [...(rawConfig.static || [])],
      bulkOptions: Array.isArray(rawConfig.bulkOptions)
        ? [...rawConfig.bulkOptions]
        : [],
      exportFields: Array.isArray(rawConfig.exportFields)
        ? [...rawConfig.exportFields]
        : [],
    };
    const config = datasetConfig[activeKey];

    bulkOptions.value = { dataset: activeKey, items: [...config.bulkOptions] };
    if (activeKey === "institutions" && Array.isArray(allOptions.groups)) {
      bulkOptions.value.groups = [...allOptions.groups];
    }

    Object.keys(filterOptions).forEach((key) => {
      delete filterOptions[key];
    });

    headers.value = [{ title: "", key: "" }];

config.fields.forEach((fld, idx) => {
      if (fld.name === "fiscalYr") {
        allOptions.fiscalYr = buildFiscalYearOptions();
        fld.optVal = "label";
        fld.optTxt = "label";
      }

      if (fld.header !== null) {
        headers.value.push(buildHeaderEntry(fld));
      }

      if (fld.name === "conso" && !isConsoAdmin) return;
      config.fields[idx].static = config.static.includes(fld.name);

      const fallbackOptions = fld.options ?? [];
      const sourceOptions = allOptions[fld.name] ?? fallbackOptions;
      const isSelectLike = [
        "select",
        "mselect",
        "selectObj",
        "toggle",
      ].includes(fld.type);
      if (
        isSelectLike &&
        (fld.options === "fromURL" || fld.name === "fiscalYr") &&
        allOptions[fld.name] !== undefined
      ) {
        const urlOptions = Array.isArray(allOptions[fld.name])
          ? allOptions[fld.name].filter((opt) =>
              isActiveFilterOption(opt, fld.name),
            )
          : allOptions[fld.name];
        const isMultiTypeFilter =
          fld.name === "type" ||
          fld.name === "types" ||
          (activeKey === "users" &&
            (fld.name === "institutions" || fld.name === "uroles")) ||
          (activeKey === "roles" &&
            ["role", "user", "institution", "group"].includes(fld.name));
        const isPrimitiveArray =
          Array.isArray(urlOptions) &&
          urlOptions.length > 0 &&
          typeof urlOptions[0] !== "object";
        const effectiveType = isMultiTypeFilter
          ? (isPrimitiveArray ? "mtext" : "mselect")
          : fld.type;

        const isSinglePrimitive =
          Array.isArray(urlOptions) &&
          effectiveType !== "mselect" &&
          effectiveType !== "mtext" &&
          (fld.optTxt === undefined || fld.optVal === undefined);

        let items = [];
        let show = false;
        if (REFRESHABLE_DATASETS.has(activeKey)) {
          items = [...urlOptions];
          show = items.length > 0 && fld.isFilter;
        } else if (
          fld.name === "fiscalYr" ||
          (activeKey === "institutions" && fld.name === "groups")
        ) {
          items = [...urlOptions];
          show = fld.isFilter;
        } else {
          items = resolvePresentOptionItems(urlOptions, fld, allItems);
          show = items.length === 0 ? false : fld.isFilter;
        }

        filterOptions[fld.name] = {
          name: fld.name,
          label: fld.label,
          type: isSinglePrimitive ? "text" : effectiveType,
          val: fld.optVal,
          txt: fld.optTxt,
          show: isSinglePrimitive ? fld.isFilter : show,
          col: fld.filterCol,
          truncate: fld.truncate ?? null,
          items,
          value: effectiveType === "mselect" || effectiveType === "mtext" ? [] : null,
        };
      } else if (
        fld.type === "toggle" &&
        fld.isFilter &&
        (sourceOptions.length > 0 || fld.name === "is_conso")
      ) {
        filterOptions[fld.name] = {
          name: fld.name,
          label: fld.label,
          type: "toggle",
          show: fld.isFilter,
          col: fld.filterCol,
          truncate: fld.truncate ?? null,
          items: [...sourceOptions],
          value: fld.name === "is_conso" ? "All" : "Inactive",
        };
      } else if (
        fld.isFilter &&
        (fld.type === "text" || fld.type === "mtext")
      ) {
        const items =
          fld.options === "fromURL" && Array.isArray(allOptions[fld.name])
            ? allOptions[fld.name].filter((opt) =>
                isActiveFilterOption(opt, fld.name),
              )
            : [...fld.options];

        filterOptions[fld.name] = {
          name: fld.name,
          label: fld.label,
          type: fld.type,
          show: true,
          col: fld.filterCol,
          truncate: fld.truncate ?? null,
          value: fld.type === "text" ? null : [],
          items,
        };
      } else if (fld.isFilter && fld.type === "ymrange") {
        filterOptions[fld.name] = {
          name: fld.name,
          label: fld.label,
          type: "ymrange",
          show: true,
          col: fld.filterCol,
          truncate: null,
          items: [],
          value: { fromYM: "", toYM: "" },
        };
      } else if (Array.isArray(fld.options)) {
        const items =
          allItems.length > 0
            ? fld.options.filter((opt) =>
                allItems
                  .flatMap((itm) => itm[fld.filterCol])
                  .includes(opt[fld.optVal]),
              )
            : fld.options;

        filterOptions[fld.name] = {
          name: fld.name,
          label: fld.label,
          type: fld.type,
          val: fld.optVal,
          txt: fld.optTxt,
          show: fld.isFilter,
          col: fld.filterCol,
          truncate: fld.truncate ?? null,
          items: [...items],
          value: null,
        };
      }
    });

    const instFilter =
      filterOptions.institutions || filterOptions.institution || filterOptions.inst_id;
    const grpFilter =
      filterOptions.groups || filterOptions.group || filterOptions.group_id;
    if (instFilter && grpFilter && instFilter.items?.length > 0 && grpFilter.items?.length > 0) {
      instFilter.show = true;
      grpFilter.show = false;
      filterOptions.scope_mode = {
        name: "scope_mode",
        label: "Scope Mode",
        type: "scope_switch",
        show: true,
        col: "scope_mode",
        items: [],
        value: false,
      };
    }


    if (STATUS_SWITCH_DATASETS.has(activeKey)) {
      if (filterOptions.statuses) {
        filterOptions.statuses.show = false;
      }
      filterOptions.status_switch = {
        name: "status_switch",
        label: "Status",
        type: "status_switch",
        show: true,
        col: "status",
        items: [],
        value: activeKey === "credentials" ? "Enabled" : "Active",
      };
    }

    if (filterOptions.is_conso) {
      filterOptions.is_conso.show = true;
      if (!filterOptions.is_conso.value) {
        filterOptions.is_conso.value = "All";
      }
    }

    if (activeKey === "platforms") {
      filterOptions.releases = {
        name: "releases",
        label: "Releases",
        type: "release_switch",
        show: true,
        col: "reg_releases",
        items: [],
        value: "All",
      };
    }

    if (activeKey === "audit") {
      filterOptions.is_conso = {
        name: "is_conso",
        label: "Consortial",
        type: "toggle",
        show: true,
        col: "is_conso",
        items: [],
        value: "All",
      };
      filterOptions.valid_types = {
        name: "valid_types",
        label: "Validation",
        type: "valid_switch",
        show: true,
        col: "valid_state",
        items: [],
        value: "Not Validated",
      };
      filterOptions.valid_target = {
        name: "valid_target",
        label: "Unvalidated Scope",
        type: "valid_target_switch",
        show: true,
        col: "valid_state",
        items: [],
        value: "All",
      };
      for (const k of ["no_inst", "no_plat", "no_host"]) {
        if (filterOptions[k]) {
          filterOptions[k].value = "Inactive";
        }
      }
    }

    searchFields.value = config.fields
      .filter((fld) => fld.searchable)
      .map((f) => f.name);
    editableFields.value = config.fields
      .filter((fld) => fld.editable)
      .map((f) => f.name);
    urlRoot.value = config.urlRoot;
  }


  function syncLocaleLabels(activeKey = datasetKey()) {
    const config = datasetConfig[activeKey];
    if (!config) return;

    headers.value = [{ title: "", key: "" }];
    config.fields.forEach((fld) => {
      if (fld.header != null) {
        headers.value.push(buildHeaderEntry(fld));
      }
    });

    Object.keys(filterOptions).forEach((key) => {
      const fld = config.fields.find((f) => f.name === key);
      if (fld) filterOptions[key].label = fld.label;
    });

  }

  return {
    applySchema,
    syncLocaleLabels,
  };
}
