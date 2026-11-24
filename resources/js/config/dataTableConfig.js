// resources/js/config/dataTableConfig.js
import i18n from "@/plugins/i18n";
import { fyMonths } from "@/stores/useCCPlusStore.js";
import { CONSORTIUM_THEMES } from "@/plugins/vuetify.js";

const t = (key) => i18n.global.t(key);

// ─────────────────────────────────────────────────────────────────────────
// STEP 1: THE DEFAULTS DEFINITION
// ─────────────────────────────────────────────────────────────────────────
const FIELD_DEFAULTS = {
  type: "text",
  header: "start",
  editable: true, // Accepts: true | false | ['Add'] | ['Edit']
  editableBy: null, // Accepts: null | ['ConsoAdmin', 'ServerAdmin'] | ['ServerAdmin']
  readOnlyFormat: null, // Accepts: null | 'lines'
  visible: true, // Accepts: true | false | ['Add'] | ['Edit']
  searchable: true,
  icon: "",
  helperText: "",
  options: null,
  truncate: 30, // Accepts: number (character limit) | null | false (disable truncation)
  cols: 12,
};

// ─────────────────────────────────────────────────────────────────────────
// STEP 2: SCHEMA PERMISSION HELPERS
// ─────────────────────────────────────────────────────────────────────────
export const isFieldVisible = (field, mode) => {
  if (Array.isArray(field.visible)) return field.visible.includes(mode);
  return Boolean(field.visible);
};

export const isFieldEditable = (field, mode) => {
  if (Array.isArray(field.editable)) return field.editable.includes(mode);
  return Boolean(field.editable);
};

// ─────────────────────────────────────────────────────────────────────────
// STEP 3: RAW DATASET CONFIGURATIONS
// ─────────────────────────────────────────────────────────────────────────
const rawTableSetup = {
  consortia: {
    urlRoot: "/api/consortia",
    get title() {
      return t("datasets.consortia.title");
    },
    dataset: "consortia",
    required: ["name", "ccp_key", "email", "admin_pass", "admin_confirm_pass"],
    fields: [
      {
        name: "status",
        get label() {
          return t("fields.status");
        },
        type: "toggle",
        options: "fromURL",
        addValue: "Active",
        editableBy: ["ServerAdmin"],
        header: "center",
        searchable: false,
        cols: 12,
      },
      {
        name: "name",
        get label() {
          return t("fields.consortium_name");
        },
        cols: 8,
      },
      {
        name: "ccp_key",
        get label() {
          return t("fields.database_key");
        },
        cols: 4,
        editable: ["Add"],
      },
      {
        name: "admin_name",
        get label() {
          return t("fields.admin_contact");
        },
        cols: 6,
      },
      {
        name: "email",
        get label() {
          return t("fields.consortium_email");
        },
        type: "email",
        cols: 6,
      },
      {
        name: "fiscalYr",
        get label() {
          return t("fields.fiscal_year_start");
        },
        type: "select",
        options: fyMonths.map((m) => {
          const val = typeof m === "object" ? m.value : m;
          return { id: val, name: val };
        }),
        optVal: "id",
        optTxt: "name",
        searchable: false,
        cols: 4,
      },
      {
        name: "language",
        get label() {
          return t("fields.default_language");
        },
        type: "select",
        options: ["en", "de", "es", "fi", "fr", "hu"].map((code) => ({
          id: code,
          name: code.toUpperCase(),
        })),
        optVal: "id",
        optTxt: "name",
        searchable: false,
        cols: 4,
      },
      {
        name: "theme",
        get label() {
          return t("fields.color_theme");
        },
        type: "select",
        options: CONSORTIUM_THEMES.map((item) => ({
          id: item.id,
          get name() {
            return t(`account.theme_${item.id}`);
          },
        })),
        optVal: "id",
        optTxt: "name",
        addValue: "light",
        searchable: false,
        cols: 4,
      },

      {
        name: "admin_pass",
        get label() {
          return t("fields.admin_password");
        },
        type: "password",
        visible: ["Add"],
        header: null,
        cols: 6,
      },
      {
        name: "admin_confirm_pass",
        get label() {
          return t("fields.confirm_password");
        },
        type: "passconf",
        visible: ["Add"],
        header: null,
        cols: 6,
      },
    ],
    static: [],
    bulkOptions: ["Set Active", "Set Inactive", "Delete"],
    sortby: "name",
  },

  institutions: {
    urlRoot: "/api/institutions",
    get title() {
      return t("datasets.institutions.title");
    },
    fields: [
      // Row 1: Toggles
      {
        name: "id",
        get label() {
          return t("fields.ccplus_system_id");
        },
        type: "number",
        editable: false,
        visible: false,
        header: null,
        searchable: false,
      },
      {
        name: "status",
        get label() {
          return t("fields.status");
        },
        type: "toggle",
        options: "fromURL",
        addValue: "Active",
        editableBy: ["ConsoAdmin", "ServerAdmin"],
        header: "center",
        searchable: false,
        cols: 6,
      },
      {
        name: "creds",
        get label() {
          return t("fields.add_cred_placeholders");
        },
        type: "toggle",
        options: ["Inactive", "Active"],
        addValue: "Active",
        visible: ["Add"],
        header: null,
        searchable: false,
        cols: 6,
      },

      // Row 2: Name & Local ID
      {
        name: "name",
        get label() {
          return t("fields.institution");
        },
        cols: 8,
      },
      {
        name: "local_id",
        get label() {
          return t("fields.local_id");
        },
        cols: 4,
        header: null,
      },

      // Row 3: Type & FTE
      {
        name: "type",
        get label() {
          return t("fields.type");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        editableBy: ["ConsoAdmin", "ServerAdmin"],
        isFilter: true,
        filterCol: "type_id",
        cols: 8,
      },
      {
        name: "fte",
        get label() {
          return t("fields.fte");
        },
        type: "number",
        header: null,
        searchable: false,
        cols: 4,
      },
      {
        name: "type_id",
        get label() {
          return t("fields.type_id");
        },
        type: "number",
        editable: false,
        visible: false,
        header: null,
        searchable: false,
      },

      // Row 4: Groups
      {
        name: "group_string",
        get label() {
          return t("fields.groups");
        },
        editable: false,
        visible: false,
      },
      {
        name: "group_ids",
        get label() {
          return t("fields.group_assignments");
        },
        type: "array",
        editable: false,
        visible: false,
        header: null,
        searchable: false,
      },
      {
        name: "groups",
        get label() {
          return t("fields.group_assignments");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        editableBy: ["ConsoAdmin", "ServerAdmin"],
        readOnlyFormat: "lines",
        isFilter: true,
        filterCol: "group_ids",
        header: null,
        searchable: false,
        cols: 12,
      },

      // Row 5: Notes
      {
        name: "notes",
        get label() {
          return t("fields.notes");
        },
        type: "textarea",
        header: null,
        cols: 12,
      },
    ],

    required: ["status", "name", "local_id", "type", "creds"],
    bulkOptions: [
      "Set Active",
      "Set Inactive",
      "Assign Type",
      "Create New Group",
      "Add to Existing Group",
      "Delete",
    ],
    exportFields: [
      "id",
      "local_id",
      "name",
      "type_id",
      "group_ids",
      "status",
      "fte",
      "notes",
    ],

    sortby: ["type", "group_string", "name"],
  },

  types: {
    urlRoot: "/api/types",
    get title() {
      return t("datasets.types.title");
    },
    fields: [
      {
        name: "id",
        get label() {
          return t("fields.id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "name",
        get label() {
          return t("fields.institution_type");
        },
        cols: 8,
      },
      {
        name: "count",
        get label() {
          return t("fields.member_count");
        },
        header: "center",
        editable: false,
        visible: ["Edit"],
        cols: 4,
      },
    ],

    required: ["name"],
    static: ["id"],
    bulkOptions: ["Set Active", "Set Inactive", "Delete"],
    exportFields: ["id", "name"],
    sortby: "name",
  },

  groups: {
    urlRoot: "/api/groups",
    get title() {
      return t("datasets.groups.title");
    },
    fields: [
      {
        name: "id",
        get label() {
          return t("fields.id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "name",
        get label() {
          return t("fields.group");
        },
        cols: 12,
      },
      {
        name: "institutions",
        get label() {
          return t("fields.member_institutions");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        header: null,
        searchable: false,
        cols: 12,
      },
      {
        name: "member_ids",
        get label() {
          return t("fields.members");
        },
        type: "array",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },

      {
        name: "type_string",
        get label() {
          return t("fields.inst_type_restriction");
        },
        editable: false,
        visible: false,
      },
      {
        name: "types",
        get label() {
          return t("fields.inst_type_restriction");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "type_id",
        header: null,
        cols: 8,
      },
      {
        name: "count",
        get label() {
          return t("fields.member_count");
        },
        header: "center",
        editable: false,
        visible: ["Edit"],
        cols: 4,
      },
      {
        name: "type_id",
        get label() {
          return t("fields.type_id");
        },
        type: "number",
        addValue: 1,
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
    ],

    required: ["name", "institutions"],
    static: ["id", "type_id", "type_string", "member_ids"],
    bulkOptions: ["Set Active", "Set Inactive", "Delete"],
    exportFields: ["id", "name", "type_id", "member_ids"],
    sortby: "name",
  },

  users: {
    urlRoot: "/api/users",
    get title() {
      return t("datasets.users.title");
    },
    fields: [
      // Hidden / Metadata Columns
      {
        name: "id",
        get label() {
          return t("fields.id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "inst_id",
        get label() {
          return t("fields.institution_id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "institution.name",
        get label() {
          return t("fields.home_institution");
        },
        editable: false,
        visible: false,
      },
      {
        name: "user_role",
        get label() {
          return t("fields.role");
        },
        editable: false,
        visible: false,
      },

      // Row 1: Status
      {
        name: "status",
        get label() {
          return t("fields.status");
        },
        type: "toggle",
        options: "fromURL",
        addValue: "Active",
        header: "center",
        searchable: false,
        cols: 12,
      },

      // Row 2: Name & Email
      {
        name: "name",
        get label() {
          return t("fields.user_name");
        },
        cols: 6,
      },
      {
        name: "email",
        get label() {
          return t("fields.email_address");
        },
        type: "email",
        cols: 6,
      },

      // Row 3: Phone & Fiscal Year
      {
        name: "phone",
        get label() {
          return t("fields.phone_number");
        },
        header: null,
        cols: 6,
      },
      {
        name: "fiscalYr",
        get label() {
          return t("fields.fiscal_year_start");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        searchable: false,
        cols: 6,
      },

      // Row 5: Passwords
      {
        name: "password",
        get label() {
          return t("fields.password");
        },
        type: "password",
        header: null,
        searchable: false,
        cols: 6,
      },
      {
        name: "confirm_pass",
        get label() {
          return t("fields.confirm_password");
        },
        type: "passconf",
        header: null,
        searchable: false,
        cols: 6,
      },

      // Row 6: Edit-only read-only metadata
      {
        name: "inst_roles",
        get label() {
          return t("fields.inst_roles");
        },
        type: "array",
        header: null,
        editable: false,
        visible: ["Edit"],
        searchable: false,
        cols: 6,
      },
      {
        name: "group_roles",
        get label() {
          return t("fields.group_roles");
        },
        type: "array",
        header: null,
        editable: false,
        visible: ["Edit"],
        searchable: false,
        cols: 6,
      },
      {
        name: "last_login",
        get label() {
          return t("fields.last_login");
        },
        type: "date",
        header: "center",
        editable: false,
        visible: ["Edit"],
        searchable: false,
        cols: 12,
      },

      // ─── Row 4 Form Fields & Toolbar Filters (Home Institution & Role) ───
      {
        name: "institutions",
        get label() {
          return t("fields.home_institution");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
        cols: 6,
      },
      {
        name: "uroles",
        get label() {
          return t("fields.role");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "user_role",
        header: null,
        searchable: false,
        cols: 6,
      },
    ],

    required: ["status", "name", "email", "institutions"],
    static: [
      "id",
      "institution.name",
      "inst_id",
      "uroles",
      "inst_roles",
      "group_roles",
    ],
    bulkOptions: ["Set Active", "Set Inactive", "Delete"],
    exportFields: [
      "id",
      "email",
      "password",
      "name",
      "phone",
      "status",
      "inst_id",
      "inst_roles",
      "group_roles",
    ],
    sortby: "email",
  },

  roles: {
    urlRoot: "/api/roles",
    get title() {
      return t("datasets.roles.title");
    },
    fields: [
      // Hidden IDs
      {
        name: "role_id",
        label: "role_id",
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "user_id",
        label: "user_id",
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "inst_id",
        label: "inst_id",
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "group_id",
        label: "group_id",
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      // Table-only display columns (hidden in form since dropdowns below represent them)
      {
        name: "name",
        get label() {
          return t("fields.username");
        },
        editable: false,
        visible: false,
      },
      {
        name: "email",
        get label() {
          return t("fields.email_address");
        },
        editable: false,
        visible: false,
      },
      {
        name: "role_string",
        get label() {
          return t("fields.role");
        },
        editable: false,
        visible: false,
      },
      {
        name: "scope",
        get label() {
          return t("fields.scope");
        },
        editable: false,
        visible: false,
      },
      {
        name: "role",
        get label() {
          return t("fields.role");
        },
        type: "select",
        options: "fromURL",
        isFilter: true,
        filterCol: "role_string",
        header: null,
        visible: false,
        searchable: false,
      },
      // Form fields (Clean 2-column grid)
      {
        name: "conso",
        get label() {
          return t("fields.entire_consortium");
        },
        type: "toggle",
        options: ["Inactive", "Active"],
        addValue: "Inactive",
        header: null,
        searchable: false,
        cols: 12,
      },
      {
        name: "user",
        get label() {
          return t("fields.user");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "user_id",
        header: null,
        searchable: false,
        cols: 6,
      },
      {
        name: "user_role",
        get label() {
          return t("fields.role");
        },
        type: "select",
        options: "fromURL",
        header: null,
        searchable: false,
        cols: 6,
      },
      {
        name: "institution",
        get label() {
          return t("fields.institution");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
        cols: 6,
      },
      {
        name: "group",
        get label() {
          return t("fields.group");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "group_id",
        header: null,
        searchable: false,
        cols: 6,
      },
    ],
    required: ["user_role", "user", "conso", "institution", "group"],
    static: ["role", "user_id", "role_id", "inst_id", "group_id"],
    bulkOptions: ["Delete"],
    exportFields: [],
    sortby: "email",
  },

  platforms: {
    urlRoot: "/api/platforms",
    get title() {
      return t("datasets.platforms.title");
    },
    fields: [
      // ─── TABLE COLUMNS ───
      {
        name: "id",
        get label() {
          return t("fields.id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "legacy_id",
        get label() {
          return t("fields.legacy_id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "status",
        get label() {
          return t("fields.status");
        },
        type: "toggle",
        options: "",
        addValue: "Active",
        header: "center",
        searchable: false,
      },
      {
        name: "is_selected",
        get label() {
          return t("fields.status");
        },
        type: "toggle",
        options: "",
        header: null,
        searchable: false,
      },
      {
        name: "name",
        get label() {
          return t("fields.platform_name");
        },
        cols: 8,
      },
      {
        name: "abbrev",
        get label() {
          return t("fields.abbreviation");
        },
        header: "center",
        cols: 4,
      },
      {
        name: "cur_release",
        get label() {
          return t("fields.release");
        },
        header: "center",
      },
      {
        name: "data_host",
        get label() {
          return t("fields.data_host");
        },
        truncate: 20,
      },

      {
        name: "day_of_month",
        get label() {
          return t("fields.harvest_day");
        },
        type: "number",
        addValue: 15,
        header: "center",
      },
      {
        name: "instance_count",
        get label() {
          return t("fields.connected_instances");
        },
        header: "center",
        editable: false,
      },
      {
        name: "updated",
        get label() {
          return t("fields.last_updated");
        },
        type: "date",
        editable: false,
      },
      {
        name: "reg_releases",
        get label() {
          return t("fields.counter_releases");
        },
        type: "array",
        header: null,
      },
      {
        name: "connector_state",
        get label() {
          return t("fields.connection_fields");
        },
        type: "array",
        header: null,
      },
      {
        name: "report_state",
        get label() {
          return t("fields.supported_reports");
        },
        type: "array",
        header: null,
      },
      {
        name: "service_url",
        get label() {
          return t("fields.service_url");
        },
        header: null,
      },
      {
        name: "platform_parm",
        get label() {
          return t("fields.platform_parameter");
        },
        header: null,
      },
      {
        name: "content_provider",
        get label() {
          return t("fields.content_provider");
        },
        header: null,
        cols: 6,
      },
      {
        name: "registry_id",
        get label() {
          return t("fields.registry_id");
        },
        header: null,
        searchable: false,
      },
      {
        name: "refreshable",
        get label() {
          return t("fields.refreshable");
        },
        type: "toggle",
        addValue: "Inactive",
        header: null,
        searchable: false,
      },
      {
        name: "customer_id",
        get label() {
          return t("fields.customer_id");
        },
        header: null,
        searchable: false,
      },
      {
        name: "requestor_id",
        get label() {
          return t("fields.requestor_id");
        },
        header: null,
        searchable: false,
      },
      {
        name: "api_key",
        get label() {
          return t("fields.api_key");
        },
        header: null,
        searchable: false,
      },
      {
        name: "refresh_result",
        get label() {
          return t("fields.refresh_result");
        },
        header: null,
        editable: false,
      },
      {
        name: "refresh_text",
        get label() {
          return t("fields.refresh_result_tooltip");
        },
        header: null,
        editable: false,
      },
      {
        name: "refresh_icon",
        get label() {
          return t("fields.refresh_result_icon");
        },
        header: null,
        editable: false,
      },
      {
        name: "refresh_color",
        get label() {
          return t("fields.refresh_result_color");
        },
        header: null,
        editable: false,
      },

      // ─── TOOLBAR FILTERS ───
      {
        name: "is_conso",
        get label() {
          return t("fields.consortial", "Consortial");
        },
        type: "toggle",
        options: "",
        isFilter: true,
        filterCol: "is_conso",
        header: null,
        searchable: false,
      },
      {
        name: "releases",
        get label() {
          return t("fields.counter_releases");
        },
        type: "mselect",
        options: "fromURL",
        isFilter: true,
        filterCol: "reg_releases",
        header: null,
      },
      {
        name: "results",
        get label() {
          return t("fields.filter_refresh_result");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "value",
        isFilter: true,
        filterCol: "refresh_result",
        header: null,
      },
      {
        name: "statuses",
        get label() {
          return t("fields.filter_status");
        },
        type: "mselect",
        options: "fromURL",
        isFilter: true,
        filterCol: "status",
        header: null,
      },
    ],
    required: [
      "name",
      "status",
      "abbrev",
      "cur_release",
      "service_url",
      "connector_state",
      "report_state",
      "platform_parm",
      "day_of_month",
      "refreshable",
      "registry_id",
    ],
    static: [
      "id",
      "legacy_id",
      "is_conso",
      "instance_count",
      "updated",
      "PR",
      "DR",
      "TR",
      "IR",
      "is_selected",
      "reg_releases",
      "customer_id",
      "requestor_id",
      "api_key",
      "connector_state",
      "report_state",
      "data_host",
      "refresh_result",
      "refresh_text",
      "refresh_icon",
      "refresh_color",
    ],
    bulkOptions: [
      "Set Active",
      "Set Inactive",
      "Refresh from Registry",
      "Delete",
    ],
    exportFields: [
      "id",
      "legacy_id",
      "registry_id",
      "name",
      "release",
      "status",
      "service_url",
      "refreshable",
      "day_of_month",
      "PR",
      "DR",
      "TR",
      "IR",
      "customer_id",
      "requestor_id",
      "api_key",
      "platform_parm",
    ],
    sortby: "name",
  },

  connections: {
    urlRoot: "/api/connections",
    get title() {
      return t("datasets.connections.title");
    },
    fields: [
      {
        name: "id",
        get label() {
          return t("fields.id");
        },
        type: "number",
        header: null,
        editable: false,
        searchable: false,
      },
      {
        name: "platform",
        get label() {
          return t("fields.platform_name");
        },
        editable: false,
      },
      {
        name: "PR",
        get label() {
          return t("fields.pr_report");
        },
        type: "toggle",
        header: "center",
        searchable: false,
      },
      {
        name: "pr_insts",
        get label() {
          return t("fields.pr_inst_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "pr_groups",
        get label() {
          return t("fields.pr_group_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "DR",
        get label() {
          return t("fields.dr_report");
        },
        type: "toggle",
        header: "center",
        searchable: false,
      },
      {
        name: "dr_insts",
        get label() {
          return t("fields.dr_inst_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "dr_groups",
        get label() {
          return t("fields.dr_group_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "TR",
        get label() {
          return t("fields.tr_report");
        },
        type: "toggle",
        header: "center",
        searchable: false,
      },
      {
        name: "tr_insts",
        get label() {
          return t("fields.tr_inst_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "tr_groups",
        get label() {
          return t("fields.tr_group_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "IR",
        get label() {
          return t("fields.ir_report");
        },
        type: "toggle",
        header: "center",
        searchable: false,
      },
      {
        name: "ir_insts",
        get label() {
          return t("fields.ir_inst_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "ir_groups",
        get label() {
          return t("fields.ir_group_ids");
        },
        type: "array",
        header: null,
      },
      {
        name: "result",
        get label() {
          return t("fields.last_harvest");
        },
        editable: false,
      },
      {
        name: "results",
        get label() {
          return t("fields.last_harvest");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "result",
        optTxt: "result",
        isFilter: true,
        filterCol: "result",
        header: null,
        searchable: false,
      },
      {
        name: "conso",
        get label() {
          return t("fields.entire_consortium");
        },
        type: "toggle",
        options: ["Inactive", "Active"],
        addValue: "Inactive",
        header: null,
        searchable: false,
      },
      {
        name: "institutions",
        get label() {
          return t("fields.institutions");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        header: null,
        searchable: false,
      },
      {
        name: "groups",
        get label() {
          return t("fields.groups_multi");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        header: null,
        searchable: false,
      },
    ],
    required: ["id"],
    static: [
      "id",
      "results",
      "institutions",
      "groups",
      "pr_insts",
      "pr_groups",
      "dr_insts",
      "dr_groups",
      "tr_insts",
      "tr_groups",
      "ir_insts",
      "ir_groups",
    ],
    bulkOptions: [],
    exportFields: [
      "id",
      "platform",
      "pr_insts",
      "pr_groups",
      "dr_insts",
      "dr_groups",
      "tr_insts",
      "tr_groups",
      "ir_insts",
      "ir_groups",
    ],
    sortby: "platform",
  },

  credentials: {
    urlRoot: "/api/credentials",
    get title() {
      return t("datasets.credentials.title");
    },
    fields: [
      // ─── TABLE COLUMNS ───
      {
        name: "status",
        get label() {
          return t("fields.status");
        },
        type: "toggle",
        options: "",
        addValue: "Enabled",
        header: "center",
        searchable: false,
      },
      {
        name: "institution",
        get label() {
          return t("fields.institution");
        },
        editable: false,
      },
      {
        name: "inst_id",
        get label() {
          return t("fields.institution_id");
        },
        type: "number",
        header: null,
        editable: false,
        searchable: false,
      },
      {
        name: "local_id",
        get label() {
          return t("fields.local_id");
        },
        header: null,
      },
      {
        name: "platform",
        get label() {
          return t("fields.platform");
        },
        editable: false,
      },
      {
        name: "prov_id",
        get label() {
          return t("fields.platform_id");
        },
        type: "number",
        header: null,
        editable: false,
        searchable: false,
      },
      {
        name: "legacy_prov_id",
        get label() {
          return t("fields.legacy_platform_id", "Legacy Platform ID");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "groups",
        get label() {
          return t("fields.groups_multi");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        header: null,
        searchable: false,
      },
      {
        name: "PR",
        label: "PR",
        type: "toggle",
        searchable: false,
      },
      {
        name: "DR",
        label: "DR",
        type: "toggle",
        searchable: false,
      },
      {
        name: "TR",
        label: "TR",
        type: "toggle",
        searchable: false,
      },
      {
        name: "IR",
        label: "IR",
        type: "toggle",
        searchable: false,
      },
      {
        name: "customer_id",
        get label() {
          return t("fields.customer_id");
        },
        shortLabel: "Cust ID",
      },
      {
        name: "requestor_id",
        get label() {
          return t("fields.requestor_id");
        },
        shortLabel: "Req ID",
      },
      {
        name: "api_key",
        get label() {
          return t("fields.api_key");
        },
        shortLabel: "API Key",
      },
      {
        name: "result",
        get label() {
          return t("fields.last_harvest");
        },
        type: "date",
        header: "center",
        editable: false,
      },

      // ─── TOOLBAR FILTERS ───
      {
        name: "is_conso",
        get label() {
          return t("fields.consortial", "Consortial");
        },
        type: "toggle",
        options: "",
        isFilter: true,
        filterCol: "is_conso",
        header: null,
        searchable: false,
      },
      {
        name: "platforms",
        get label() {
          return t("fields.platforms");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "prov_id",
        header: null,
        searchable: false,
      },
      {
        name: "institutions",
        get label() {
          return t("fields.institutions");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
      },
      {
        name: "statuses",
        get label() {
          return t("fields.status");
        },
        type: "mselect",
        options: "fromURL",
        isFilter: true,
        filterCol: "status",
        header: null,
        searchable: false,
      },
      {
        name: "results",
        get label() {
          return t("fields.last_harvest");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "result",
        optTxt: "result",
        isFilter: true,
        filterCol: "result",
        header: null,
        searchable: false,
      },
    ],
    required: [
      "institutions",
      "platforms",
      "customer_id",
      "requestor_id",
      "api_key",
    ],
    static: [
      "is_conso",
      "groups",
      "statuses",
      "results",
      "inst_id",
      "prov_id",
      "legacy_prov_id",
      "local_id",
    ],
    bulkOptions: ["Enable", "Disable", "Delete"],
    exportFields: [
      "inst_id",
      "local_id",
      "institution",
      "prov_id",
      "legacy_prov_id",
      "platform",
      "customer_id",
      "requestor_id",
      "api_key",
    ],
    sortby: "platform",
  },

  badCredentials: {
    urlRoot: "/api/harvests/bad-credentials",
    title: "Bad Credentials",
    fields: [
      {
        name: "inst_name",
        get label() {
          return t("fields.institution");
        },
        searchable: true,
        editable: false,
      },
      {
        name: "prov_name",
        get label() {
          return t("fields.platform");
        },
        searchable: true,
        editable: false,
      },
      {
        name: "error",
        get label() {
          return t("fields.result");
        },
        editable: false,
      },
      {
        name: "updated",
        get label() {
          return t("fields.result_date");
        },
        type: "date",
        editable: false,
      },
   {
     name: "actions",
     get label() {
       return t("tables.actions");
     },
     header: "center",
     sortable: false,
     editable: false,
   },

    ],
    required: [],
    static: ["id"],
    bulkOptions: [],
    exportFields: ["inst_name", "prov_name", "d_status", "updated"],
    sortby: "inst_name",
  },

  audit: {
    urlRoot: "/api/audit",
    title: "",
    fields: [
      // ─── TABLE COLUMNS ───
      {
        name: "plat_name",
        get label() {
          return t("fields.platform");
        },
        editable: false,
      },
      {
        name: "plat_id",
        get label() {
          return t("fields.platform_id");
        },
        type: "number",
        header: null,
        editable: false,
        searchable: false,
      },
      {
        name: "plat_valid",
        get label() {
          return t("fields.platform_valid");
        },
        type: "toggle",
        header: "center",
        searchable: false,
      },
      {
        name: "json_item",
        get label() {
          return t("fields.json_platform_item");
        },
        editable: false,
      },
      {
        name: "json_host",
        get label() {
          return t("fields.data_host");
        },
        editable: false,
      },
      {
        name: "inst_name",
        get label() {
          return t("fields.institution");
        },
        editable: false,
      },
      {
        name: "inst_id",
        get label() {
          return t("fields.institution_id");
        },
        type: "number",
        header: null,
        editable: false,
        searchable: false,
      },
      {
        name: "inst_valid",
        get label() {
          return t("fields.institution_valid");
        },
        type: "toggle",
        header: "center",
        searchable: false,
      },
      {
        name: "json_inst",
        get label() {
          return t("fields.json_institution");
        },
        editable: false,
      },
      {
        name: "group_ids",
        get label() {
          return t("fields.group_ids");
        },
        type: "array",
        header: null,
        editable: false,
        searchable: false,
      },

      // ─── TOOLBAR FILTERS ───
      {
        name: "is_conso",
        get label() {
          return t("fields.consortial", "Consortial");
        },
        type: "toggle",
        options: "",
        isFilter: true,
        filterCol: "is_conso",
        header: null,
        searchable: false,
      },
      {
        name: "platforms",
        get label() {
          return t("fields.platforms");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "plat_id",
        header: null,
        searchable: false,
      },
      {
        name: "institutions",
        get label() {
          return t("fields.institutions");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
      },
      {
        name: "groups",
        get label() {
          return t("fields.groups");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "group_ids",
        header: null,
        searchable: false,
      },
      {
        name: "valid_types",
        get label() {
          return t("fields.validation");
        },
        type: "select",
        options: "fromURL",
        isFilter: true,
        filterCol: "valid_state",
        header: null,
        searchable: false,
      },
      {
        name: "no_inst",
        get label() {
          return t("fields.hide_no_json_inst");
        },
        type: "toggle",
        options: ["Inactive", "Active"],
        isFilter: true,
        filterCol: "inst_set",
        header: null,
        searchable: false,
      },
      {
        name: "no_plat",
        get label() {
          return t("fields.hide_no_json_plat");
        },
        type: "toggle",
        options: ["Inactive", "Active"],
        isFilter: true,
        filterCol: "plat_set",
        header: null,
        searchable: false,
      },
      {
        name: "no_host",
        get label() {
          return t("fields.hide_no_json_host");
        },
        type: "toggle",
        options: ["Inactive", "Active"],
        isFilter: true,
        filterCol: "host_set",
        header: null,
        searchable: false,
      },
    ],
    required: [],
    static: ["inst_id", "plat_id", "statuses", "valid_types", "group_ids"],
    bulkOptions: [
      "Set Institution Validated",
      "Set Platform Validated",
      "Clear Validation",
    ],
    exportFields: [
      "plat_name",
      "inst_name",
      "json_host",
      "json_item",
      "json_inst",
    ],
    sortby: "plat_name",
  },

  jobs: {
    urlRoot: "/api/jobs",
    title: "",
    fields: [
      // ─── TABLE COLUMNS ───
      {
        name: "inst_name",
        get label() {
          return t("fields.institution");
        },
        editable: false,
      },
      {
        name: "prov_name",
        get label() {
          return t("fields.platform");
        },
        editable: false,
      },
      {
        name: "release",
        get label() {
          return t("fields.release");
        },
        header: "center",
        editable: false,
      },
      {
        name: "yearmon",
        get label() {
          return t("fields.usage_date");
        },
        header: "center",
        editable: false,
      },
      {
        name: "report_name",
        get label() {
          return t("fields.report");
        },
        header: "center",
        editable: false,
      },
      {
        name: "harvest_id",
        get label() {
          return t("fields.harvest_id");
        },
        header: "end",
        editable: false,
      },
      {
        name: "d_status",
        get label() {
          return t("fields.status");
        },
        editable: false,
      },
      {
        name: "error",
        label: "Last Result",
        editable: false,
      },
      {
        name: "created",
        get label() {
          return t("fields.created");
        },
        type: "date",
        editable: false,
      },
      {
        name: "rawfile",
        get label() {
          return t("fields.raw_datafile");
        },
        header: null,
        editable: false,
      },
      {
        name: "retryUrl",
        get label() {
          return t("fields.retry_url");
        },
        header: null,
        editable: false,
      },

      // ─── TOOLBAR FILTERS ───
      {
        name: "is_conso",
        get label() {
          return t("fields.consortial", "Consortial");
        },
        type: "toggle",
        options: "",
        isFilter: true,
        filterCol: "is_conso",
        header: null,
        searchable: false,
      },
      {
        name: "platforms",
        get label() {
          return t("fields.platforms");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "prov_id",
        header: null,
        searchable: false,
      },
      {
        name: "institutions",
        get label() {
          return t("fields.institutions");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
      },
      {
        name: "groups",
        get label() {
          return t("fields.institution_groups");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
      },
      {
        name: "reports",
        get label() {
          return t("fields.reports");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "report_id",
        header: null,
        searchable: false,
      },
      {
        name: "ymrange",
        get label() {
          return t("fields.usage_dates");
        },
        type: "ymrange",
        isFilter: true,
        filterCol: "yearmon",
        header: null,
        searchable: false,
      },
      {
        name: "codes",
        get label() {
          return t("fields.error_codes");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "error_id",
        header: null,
        searchable: false,
      },
      {
        name: "statuses",
        get label() {
          return t("fields.statuses");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "d_status",
        header: null,
        searchable: false,
      },
    ],
    required: [],
    static: ["id", "is_conso", "rawfile", "retryUrl"],
    bulkOptions: ["Pause", "Restart", "Kill"],
    exportFields: [],
    sortby: "created",
  },

  harvests: {
    urlRoot: "/api/harvests",
    title: "",
    fields: [
      // ─── TABLE COLUMNS ───
      {
        name: "inst_name",
        get label() {
          return t("fields.institution");
        },
        editable: false,
      },
      {
        name: "prov_name",
        get label() {
          return t("fields.platform");
        },
        editable: false,
      },
      {
        name: "release",
        get label() {
          return t("fields.release");
        },
        header: "center",
        editable: false,
      },
      {
        name: "yearmon",
        get label() {
          return t("fields.usage_date");
        },
        header: "center",
        editable: false,
      },
      {
        name: "report_name",
        get label() {
          return t("fields.report");
        },
        header: "center",
        editable: false,
      },
      {
        name: "harvest_id",
        get label() {
          return t("fields.harvest_id");
        },
        header: "end",
        editable: false,
      },
      {
        name: "error",
        get label() {
          return t("fields.result");
        },
        editable: false,
      },
      {
        name: "updated",
        get label() {
          return t("fields.result_date");
        },
        type: "date",
        editable: false,
      },
      {
        name: "rawfile",
        get label() {
          return t("fields.raw_datafile");
        },
        header: null,
        editable: false,
      },
      {
        name: "retryUrl",
        get label() {
          return t("fields.retry_url");
        },
        header: null,
        editable: false,
      },

      // ─── TOOLBAR FILTERS ───
      {
        name: "is_conso",
        get label() {
          return t("fields.consortial", "Consortial");
        },
        type: "toggle",
        options: "",
        isFilter: true,
        filterCol: "is_conso",
        header: null,
        searchable: false,
      },
      {
        name: "platforms",
        get label() {
          return t("fields.platforms");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "prov_id",
        header: null,
        searchable: false,
      },
      {
        name: "institutions",
        get label() {
          return t("fields.institutions");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
      },
      {
        name: "groups",
        get label() {
          return t("fields.institution_groups");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "inst_id",
        header: null,
        searchable: false,
      },
      {
        name: "reports",
        get label() {
          return t("fields.reports");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "report_id",
        header: null,
        searchable: false,
      },
      {
        name: "updates",
        get label() {
          return t("fields.updated");
        },
        type: "select",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        filterCol: "timeframes",
        isFilter: true,
        header: null,
        searchable: false,
      },
      {
        name: "ymrange",
        get label() {
          return t("fields.usage_dates");
        },
        type: "ymrange",
        isFilter: true,
        filterCol: "yearmon",
        header: null,
        searchable: false,
      },
      {
        name: "codes",
        get label() {
          return t("fields.error_codes");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "error_id",
        header: null,
        searchable: false,
      },
      {
        name: "statuses",
        get label() {
          return t("fields.statuses");
        },
        type: "mselect",
        options: "fromURL",
        optVal: "id",
        optTxt: "name",
        isFilter: true,
        filterCol: "d_status",
        header: null,
        searchable: false,
      },
    ],
    required: [],
    static: ["id", "is_conso", "rawfile", "retryUrl"],
    bulkOptions: ["Restart", "Restart as r5", "Restart as r5.1", "Delete"],
    exportFields: [],
    sortby: "updated",
  },

  savedreports: {
    urlRoot: "/api/savedreports",
    get title() {
      return t("datasets.savedreports.title");
    },
    fields: [
      {
        name: "id",
        get label() {
          return t("fields.id");
        },
        type: "number",
        header: null,
        editable: false,
        visible: false,
        searchable: false,
      },
      {
        name: "title",
        get label() {
          return t("fields.report_title");
        },
        cols: 12,
      },
      {
        name: "report_name",
        get label() {
          return t("fields.report");
        },
        options: "fromURL",
        editable: false,
        visible: ["Edit"],
        cols: 6,
      },
      {
        name: "last_harvest",
        get label() {
          return t("fields.last_run_date");
        },
        type: "date",
        editable: false,
        visible: ["Edit"],
        cols: 6,
      },
      {
        name: "date_range",
        get label() {
          return t("fields.date_range");
        },
        type: "select",
        cols: 6,
        get options() {
          return [
            { title: t("options.date_range.fYTD"), value: "fYTD" },
            { title: t("options.date_range.priorFy"), value: "priorFy" },
            { title: t("options.date_range.cYTD"), value: "cYTD" },
            { title: t("options.date_range.Custom"), value: "Custom" },
          ];
        },
        isFilter: true,
      },
      {
        name: "format",
        get label() {
          return t("fields.format");
        },
        type: "select",
        options: ["Compact", "COUNTER"],
        isFilter: true,
        cols: 6,
      },
      {
        name: "exclude_zeros",
        get label() {
          return t("fields.include_zeros");
        },
        type: "select",
        options: ["true", "false"],
        cols: 6,
      },
    ],
    required: ["title"],
    static: ["id"],
    bulkOptions: ["Delete"],
    exportFields: [],
    sortby: "title",
  },
};

// ─────────────────────────────────────────────────────────────────────────
// STEP 4: THE AUTOMATED LOOPER (Preserves live getters & wires required)
// ─────────────────────────────────────────────────────────────────────────
export const tableSetup = Object.keys(rawTableSetup).reduce(
  (acc, datasetKey) => {
    const dataset = rawTableSetup[datasetKey];
    const requiredList = dataset.required || [];
    const mergedFields = dataset.fields.map((field) => {
      const merged = { ...FIELD_DEFAULTS };
      Object.defineProperties(merged, Object.getOwnPropertyDescriptors(field));
      merged.required = requiredList.includes(field.name);
      return merged;
    });
    const derivedStatic =
      dataset.static ??
      mergedFields
        .filter((f) => f.editable === false || f.visible === false)
        .map((f) => f.name);
    const mergedDataset = {};
    Object.defineProperties(
      mergedDataset,
      Object.getOwnPropertyDescriptors(dataset),
    );
    mergedDataset.fields = mergedFields;
    mergedDataset.static = derivedStatic;
    acc[datasetKey] = mergedDataset;
    return acc;
  },
  {},
);
