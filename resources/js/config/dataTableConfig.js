// resources/js/config/dataTableConfig.js
import i18n from '@/plugins/i18n';

const t = (key) => i18n.global.t(key);

// ─────────────────────────────────────────────────────────────────────────
// STEP 1: THE DEFAULTS DEFINITION (Add cols: 12 to the map)
// ─────────────────────────────────────────────────────────────────────────
 const FIELD_DEFAULTS = {
  type: 'text',
  header: 'start', 
  editable: true,   // Accepts: true | false | ['Add'] | ['Edit']
  visible: true,    // Accepts: true | false | ['Add'] | ['Edit']
  searchable: true,
  icon: '',
  helperText: '',
  options: null,
  cols: 12
};
// ─────────────────────────────────────────────────────────────────────────
// STEP 2: SCHEMA PERMISSION HELPERS
// ─────────────────────────────────────────────────────────────────────────
/**
 * Determines if a field should render in the current form mode ('Add' | 'Edit').
 */
export const isFieldVisible = (field, mode) => {
  if (Array.isArray(field.visible)) return field.visible.includes(mode);
  return Boolean(field.visible);
};

/**
 * Determines if a field can be modified in the current form mode ('Add' | 'Edit').
 */
export const isFieldEditable = (field, mode) => {
  if (Array.isArray(field.editable)) return field.editable.includes(mode);
  return Boolean(field.editable);
};

// ─────────────────────────────────────────────────────────────────────────
// STEP 3: DATASETS INDEX MAP
// ─────────────────────────────────────────────────────────────────────────
const rawTableSetup = {
    //
    // Tables that have selectors need options - or something to define where the options come from....
    // either encode them for each table here, or set a flag/pointer to where they should be.. like:
    // maybe "filterOptions" to cause the dataTable to pull from "filterOptions[datasetKey]" ; will need
    // to know right syntax to abstract the variable that way...
    //
    // NOTE:: Fields may need helperText, per the DataForm editting component
    //        Add 'icon' prop to type=text||textarea||select||multiselect)
    //

    consortia: {
      urlRoot: '/api/consortia',
      get title() { return t('datasets.consortia.title'); },
      dataset: 'consortia',

      required: [
        'name',
        'ccp_key',
        'email',
        'admin_user',
        'admin_pass',
        'admin_confirm_pass',
      ],

      fields: [
        { name: 'status',      get label() { return t('fields.status'); },          options: 'fromURL', header: 'center', searchable: false },
        { name: 'name',        get label() { return t('fields.consortium_name'); }, cols: 9 },
        { name: 'ccp_key',     get label() { return t('fields.database_key'); },    cols: 3 },
        { name: 'admin_name',  get label() { return t('fields.admin_contact'); } },
        { name: 'email',       get label() { return t('fields.email_address'); } },

        // Form-only visibility fields required by your backend controller
        { name: 'admin_user',         get label() { return t('fields.admin_username_email'); }, type: 'text',     header: null },
        { name: 'admin_pass',         get label() { return t('fields.admin_password'); },       type: 'password', header: null },
        { name: 'admin_confirm_pass', get label() { return t('fields.confirm_password'); },     type: 'passconf', header: null },

        { name: 'displayName', get label() { return t('fields.display_name'); }, header: null },
        { name: 'firstName',   get label() { return t('fields.first_name'); },   header: null },
        { name: 'lastName',    get label() { return t('fields.last_name'); },    header: null },
      ],
      static: [],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'],
      sortby: 'name',
    },

    institutions: {
      urlRoot: '/api/institutions',
      get title() { return t('datasets.institutions.title'); },
      fields: [
        // 1. SYSTEM KEYS
        { name: 'id',           get label() { return t('fields.ccplus_system_id'); }, type: 'number',   editable: false, visible: ['Edit'], header: null, searchable: false },
        { name: 'status',       get label() { return t('fields.status'); },           type: 'toggle',   options: 'fromURL', addValue: 'Active', header: 'center', searchable: false },

        // 2. PRIMARY TEXT DESCRIPTORS
        { name: 'name',         get label() { return t('fields.institution'); },      cols: 8 },
        { name: 'local_id',     get label() { return t('fields.local_id'); },         cols: 4,          header: null },

        // 3. COMPLEX RELATIONAL PIPELINES
        { name: 'type',         get label() { return t('fields.type'); },             type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'type_id' },
        { name: 'type_id',      get label() { return t('fields.type_id'); },          type: 'number',   editable: false, visible: false, header: null, searchable: false },

        { name: 'group_string', get label() { return t('fields.groups'); },                             editable: false, visible: false },
        { name: 'group_ids',    get label() { return t('fields.group_assignments'); }, type: 'array',   editable: false, visible: false, header: null, searchable: false },
        { name: 'groups',       get label() { return t('fields.group_assignments'); }, type: 'mselect', options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'group_ids', header: null, searchable: false },

        // 4. METRICS, TEXTAREAS & OPT-INS
        { name: 'fte',          get label() { return t('fields.fte'); },              type: 'number',   header: null, searchable: false },
        { name: 'notes',        get label() { return t('fields.notes'); },            type: 'textarea', header: null },
        { name: 'creds',        get label() { return t('fields.add_cred_placeholders'); }, type: 'toggle', options: ['Inactive', 'Active'], addValue: 'Active', visible: ['Add'], header: null, searchable: false },
      ],

      required: ['status', 'name', 'local_id', 'fte', 'type', 'creds'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Assign Type', 'Create New Group', 'Add to Existing Group', 'Delete'],
      exportFields: ['id', 'local_id', 'name', 'status', 'fte', 'group_ids', 'notes'],
      sortby: 'name',
    },

    types: {
      urlRoot: '/api/types',
      get title() { return t('datasets.types.title'); },
      fields: [
        { name: 'id',   get label() { return t('fields.id'); },               type: 'number', header: null, editable: false, searchable: false },
        { name: 'name', get label() { return t('fields.institution_type'); } },
      ],

      required: ['name'],
      static: ['id'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'],
      exportFields: ['id', 'name'],
      sortby: 'name',
    },

    groups: {
      urlRoot: '/api/groups',
      get title() { return t('datasets.groups.title'); },
      fields: [
        { name: 'id',           get label() { return t('fields.id'); },                        type: 'number',  header: null, editable: false, searchable: false },
        { name: 'name',         get label() { return t('fields.group'); } },

        { name: 'institutions', get label() { return t('fields.member_institutions'); },       type: 'mselect', options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },
        { name: 'member_ids',   get label() { return t('fields.members'); },                   type: 'array',   header: null, editable: false, searchable: false },
        { name: 'count',        get label() { return t('fields.member_count'); },                               header: 'center', editable: false },

        { name: 'type_string',  get label() { return t('fields.inst_type_restriction'); },                      editable: false },
        { name: 'types',        get label() { return t('fields.inst_type_restriction'); },     type: 'select',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'type_id', header: null },
        { name: 'type_id',      get label() { return t('fields.type_id'); },                   type: 'number',  addValue: 1, header: null, editable: false, searchable: false },
      ],

      required: ['name', 'institutions'],
      static: ['id', 'type_id', 'type_string', 'member_ids'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'],
      exportFields: ['id', 'name', 'member_ids'],
      sortby: 'name',
    },

    users: {
      urlRoot: '/api/users',
      get title() { return t('datasets.users.title'); },
      fields: [
        // 1. IDENTITY & PRIMARY USER FIELDS
        { name: 'id',               get label() { return t('fields.id'); },               type: 'number',   header: null, editable: false, searchable: false },
        { name: 'status',           get label() { return t('fields.status'); },           type: 'toggle',   options: 'fromURL', addValue: 'Active', header: 'center', searchable: false },
        { name: 'name',             get label() { return t('fields.user_name'); } },
        { name: 'email',            get label() { return t('fields.email_address'); } },
        { name: 'phone',            get label() { return t('fields.phone_number'); },                                        header: null },

        // 2. INSTITUTIONAL LINK RELATIONSHIPS
        { name: 'institution.name', get label() { return t('fields.home_institution'); },                                                    editable: false },
        { name: 'institutions',     get label() { return t('fields.home_institution'); }, type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'inst_id',          get label() { return t('fields.institution_id'); },   type: 'number',   header: null, editable: false, searchable: false },

        // 3. SECURE AUTH & OPERATING PARAMETERS
        { name: 'user_role',        get label() { return t('fields.role'); },                                                                editable: false },
        { name: 'uroles',           get label() { return t('fields.role'); },             type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'user_role', header: null, searchable: false },
        { name: 'inst_roles',       get label() { return t('fields.inst_roles'); },       type: 'array',    header: null, editable: false, searchable: false },
        { name: 'group_roles',      get label() { return t('fields.group_roles'); },      type: 'array',    header: null, editable: false, searchable: false },

        // 4. METADATA & RE-AUTHENTICATION STRINGS
        { name: 'password',         get label() { return t('fields.password'); },         type: 'password', header: null, searchable: false },
        { name: 'confirm_pass',     get label() { return t('fields.confirm_password'); }, type: 'passconf', header: null, searchable: false },
        {
          name: 'fiscalYr',
          get label() { return t('fields.fiscal_year_start'); },
          type: 'select',
          options: 'fromURL',
          optVal: 'id',
          optTxt: 'name',
          header: 'left',
          editable: true,
          searchable: false,
        },
        { name: 'last_login',       get label() { return t('fields.last_login'); },       type: 'date',     header: 'center', editable: false, searchable: false },
      ],

      required: ['status', 'name', 'email', 'institutions', 'password', 'confirm_pass'],
      static: ['id', 'institution.name', 'inst_id', 'uroles', 'inst_roles', 'group_roles'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'],
      exportFields: ['id', 'email', 'password', 'name', 'phone', 'status', 'inst_id', 'inst_roles', 'group_roles'],
      sortby: 'email',
    },

    roles: {
      urlRoot: '/api/roles',
      get title() { return t('datasets.roles.title'); },
      fields: [
        // 1. PRIMARY SYSTEM ID CROSS-REFERENCES
        { name: 'role_id',     label: 'role_id',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'user_id',     label: 'user_id',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'inst_id',     label: 'inst_id',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'group_id',    label: 'group_id',            type: 'number',   header: null, editable: false, searchable: false },

        // 2. READ-ONLY PROFILE MONITORING STRINGS (Implicitly searchable)
        { name: 'name',        get label() { return t('fields.username'); },                                             editable: false },
        { name: 'email',       get label() { return t('fields.email_address'); },                                        editable: false },
        { name: 'role_string', get label() { return t('fields.role'); },                                                 editable: false },
        { name: 'scope',       get label() { return t('fields.scope'); },                                                editable: false },

        // 3. EDITABLE ASSIGNMENT RELATIONSHIPS & FILTERS
        { name: 'role',        get label() { return t('fields.role'); },              type: 'select',   options: 'fromURL', isFilter: true, filterCol: 'role_string', header: null, searchable: false },
        { name: 'user_role',   get label() { return t('fields.role'); },              type: 'select',   options: 'fromURL', header: null, searchable: false },
        { name: 'user',        get label() { return t('fields.user'); },              type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'user_id', header: null, searchable: false },
        { name: 'conso',       get label() { return t('fields.entire_consortium'); }, type: 'toggle',   options: ['Inactive', 'Active'], addValue: 'Inactive', header: null, searchable: false },
        { name: 'institution', get label() { return t('fields.institution'); },       type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'group',       get label() { return t('fields.group'); },             type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'group_id', header: null, searchable: false },
      ],

      required: ['user_role', 'user', 'conso', 'institution', 'group'],
      static: ['role', 'user_id', 'role_id', 'inst_id', 'group_id'],
      bulkOptions: ['Delete'],
      exportFields: [],
      sortby: 'email',
    },

    platforms: {
      urlRoot: '/api/platforms',
      get title() { return t('datasets.platforms.title'); },
      fields: [
        // 1. PRIMARY SYSTEM CODES & TOGGLES
        { name: 'id',              get label() { return t('fields.id'); },                    type: 'number',   header: null, editable: false, searchable: false },
        { name: 'status',          get label() { return t('fields.status'); },                type: 'toggle',   options: '', addValue: 'Active', header: 'center', searchable: false },
        { name: 'is_selected',     get label() { return t('fields.status'); },                type: 'toggle',   options: '', header: null, searchable: false },

        // 2. CORE VIEWABLE LOG DESCRIPTORS (Implicitly text and searchable)
        { name: 'name',            get label() { return t('fields.platform_name'); },         cols: 8 },
        { name: 'abbrev',          get label() { return t('fields.abbreviation'); },          header: 'center', cols: 4 },
        { name: 'cur_release',     get label() { return t('fields.release'); },               header: 'center' },
        { name: 'data_host',       get label() { return t('fields.data_host'); } },
        { name: 'day_of_month',    get label() { return t('fields.harvest_day'); },           type: 'number',   header: 'center' },
        { name: 'instance_count',  get label() { return t('fields.connected_instances'); },   header: 'center', editable: false },
        { name: 'updated',         get label() { return t('fields.last_updated'); },          type: 'date',     editable: false },

        // 3. DIALOG DATA ARRAYS & CONFIGURATION REPOSITORIES
        { name: 'reg_releases',    get label() { return t('fields.counter_releases'); },      type: 'array',    header: null },
        { name: 'connector_state', get label() { return t('fields.connection_fields'); },     type: 'array',    header: null },
        { name: 'report_state',    get label() { return t('fields.supported_reports'); },     type: 'array',    header: null },
        { name: 'service_url',     get label() { return t('fields.service_url'); },           header: null },
        { name: 'platform_parm',   get label() { return t('fields.platform_parameter'); },    header: null },
        { name: 'registry_id',     get label() { return t('fields.registry_id'); },           header: null, searchable: false },
        { name: 'refreshable',     get label() { return t('fields.refreshable'); },           type: 'toggle',   addValue: 'Inactive', header: null, searchable: false },

        // 4. SUSHI CREDENTIAL PATHING OVERLAY STRINGS
        { name: 'customer_id',     get label() { return t('fields.customer_id'); },           header: null, searchable: false },
        { name: 'requestor_id',    get label() { return t('fields.requestor_id'); },          header: null, searchable: false },
        { name: 'api_key',         get label() { return t('fields.api_key'); },               header: null, searchable: false },

        // 5. TRANSIENT DIAGNOSTIC LOGS & REFRESH STATUS METADATA
        { name: 'refresh_result',  get label() { return t('fields.refresh_result'); },        header: null, editable: false },
        { name: 'refresh_text',    get label() { return t('fields.refresh_result_tooltip'); }, header: null, editable: false },
        { name: 'refresh_icon',    get label() { return t('fields.refresh_result_icon'); },   header: null, editable: false },
        { name: 'refresh_color',   get label() { return t('fields.refresh_result_color'); },  header: null, editable: false },

        // 6. SHARED TABLE FILTER MULTI-SELECT MAPPINGS
        { name: 'releases',        get label() { return t('fields.counter_releases'); },      type: 'mselect',  options: 'fromURL', isFilter: true, filterCol: 'reg_releases', header: null },
        { name: 'results',         get label() { return t('fields.filter_refresh_result'); }, type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'value', isFilter: true, filterCol: 'refresh_result', header: null },
        { name: 'statuses',        get label() { return t('fields.filter_status'); },         type: 'mselect',  options: 'fromURL', isFilter: true, filterCol: 'status', header: null },
      ],

      required: ['name', 'status', 'abbrev', 'cur_release', 'service_url', 'connector_state', 'report_state', 'platform_parm',
                 'day_of_month', 'refreshable', 'registry_id'],
      static: ['id', 'instance_count', 'updated', 'PR', 'DR', 'TR', 'IR', 'is_selected', 'reg_releases', 'customer_id',
               'requestor_id', 'api_key', 'connector_state', 'report_state', 'data_host', 'refresh_result', 'refresh_text',
               'refresh_icon', 'refresh_color'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Refresh Registry', 'Delete'],
      // These are set by GlobalProviderController export() method
      exportFields: ['registry_id', 'name', 'release', 'status', 'service_url', 'refreshable', 'day_of_month', 'PR', 'DR', 'TR', 'IR',
                     'customer_id', 'requestor_id', 'api_key', 'platform_parm'],
      sortby: 'name',
    },

    connections: {
      urlRoot: '/api/connections',
      get title() { return t('datasets.connections.title'); },
      fields: [
        // 1. PRIMARY PLATFORM IDENTITY KEYS
        { name: 'id',           get label() { return t('fields.id'); },                type: 'number',   header: null, editable: false, searchable: false },
        { name: 'platform',     get label() { return t('fields.platform_name'); },                                   editable: false },

        // 2. COUNTER SUITE REPORT CLUSTERS (Grouped by target mapping block)
        { name: 'PR',           get label() { return t('fields.pr_report'); },         type: 'toggle',   header: 'center', searchable: false },
        { name: 'pr_insts',     get label() { return t('fields.pr_inst_ids'); },       type: 'array',    header: null },
        { name: 'pr_groups',    get label() { return t('fields.pr_group_ids'); },      type: 'array',    header: null },

        { name: 'DR',           get label() { return t('fields.dr_report'); },         type: 'toggle',   header: 'center', searchable: false },
        { name: 'dr_insts',     get label() { return t('fields.dr_inst_ids'); },       type: 'array',    header: null },
        { name: 'dr_groups',    get label() { return t('fields.dr_group_ids'); },      type: 'array',    header: null },

        { name: 'TR',           get label() { return t('fields.tr_report'); },         type: 'toggle',   header: 'center', searchable: false },
        { name: 'tr_insts',     get label() { return t('fields.tr_inst_ids'); },       type: 'array',    header: null },
        { name: 'tr_groups',    get label() { return t('fields.tr_group_ids'); },      type: 'array',    header: null },

        { name: 'IR',           get label() { return t('fields.ir_report'); },         type: 'toggle',   header: 'center', searchable: false },
        { name: 'ir_insts',     get label() { return t('fields.ir_inst_ids'); },       type: 'array',    header: null },
        { name: 'ir_groups',    get label() { return t('fields.ir_group_ids'); },      type: 'array',    header: null },

        // 3. DATA HARVEST MONITORING METRICS & CONFIG FILTERS
        { name: 'result',       get label() { return t('fields.last_harvest'); },                                    editable: false },
        { name: 'results',      get label() { return t('fields.last_harvest'); },      type: 'mselect',  options: 'fromURL', optVal: 'result', optTxt: 'result', isFilter: true, filterCol: 'result', header: null, searchable: false },
        { name: 'conso',        get label() { return t('fields.entire_consortium'); }, type: 'toggle',   options: ['Inactive', 'Active'], addValue: 'Inactive', header: null, searchable: false },
        { name: 'institutions', get label() { return t('fields.institutions'); },      type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },
        { name: 'groups',       get label() { return t('fields.groups_multi'); },      type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },
      ],

      required: ['id'],
      static: ['id', 'results', 'institutions', 'groups', 'pr_insts', 'pr_groups', 'dr_insts', 'dr_groups',
               'tr_insts', 'tr_groups', 'ir_insts', 'ir_groups'],
      bulkOptions: [],
      exportFields: ['id', 'platform', 'pr_insts', 'pr_groups', 'dr_insts', 'dr_groups',
                     'tr_insts', 'tr_groups', 'ir_insts', 'ir_groups'],
      sortby: 'platform',
    },

    credentials: {
      urlRoot: '/api/credentials',
      get title() { return t('datasets.credentials.title'); },
      fields: [
        // 1. PRIMARY CREATIVE RECORDS & IDENTITY TRACKERS
        { name: 'status',       get label() { return t('fields.status'); },        type: 'toggle',   options: '', addValue: 'Enabled', header: 'center', searchable: false },
        { name: 'statuses',     get label() { return t('fields.status'); },        type: 'mselect',  options: 'fromURL', isFilter: true, filterCol: 'status', header: null, searchable: false },

        // 2. INSTITUTIONAL LINK PAIRINGS
        { name: 'institution',  get label() { return t('fields.institution'); },                                 editable: false },
        { name: 'institutions', get label() { return t('fields.institution'); },   type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'inst_id',      get label() { return t('fields.institution_id'); },type: 'number',   header: null, editable: false, searchable: false },
        { name: 'local_id',     get label() { return t('fields.local_id'); },                                    header: null },

        // 3. PLATFORM & GROUP LINK PAIRINGS
        { name: 'platform',     get label() { return t('fields.platform'); },                                    editable: false },
        { name: 'platforms',    get label() { return t('fields.platform'); },      type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'prov_id', header: null, searchable: false },
        { name: 'prov_id',      get label() { return t('fields.platform_id'); },   type: 'number',   header: null, editable: false, searchable: false },
        { name: 'groups',       get label() { return t('fields.groups_multi'); },  type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },

        // 4. SUSHI CREDENTIALS STRINGS
        { name: 'customer_id',  get label() { return t('fields.customer_id'); } },
        { name: 'requestor_id', get label() { return t('fields.requestor_id'); } },
        { name: 'api_key',      get label() { return t('fields.api_key'); } },

        // 5. INDIVIDUAL SUSHI HARVESTER REPORT TOGGLES
        { name: 'PR',           get label() { return t('fields.pr_report'); },     type: 'toggle',   header: 'center', searchable: false },
        { name: 'DR',           get label() { return t('fields.dr_report'); },     type: 'toggle',   header: 'center', searchable: false },
        { name: 'TR',           get label() { return t('fields.tr_report'); },     type: 'toggle',   header: 'center', searchable: false },
        { name: 'IR',           get label() { return t('fields.ir_report'); },     type: 'toggle',   header: 'center', searchable: false },

        // 6. HARVEST COMPLIANCE LOGS & FILTERS
        { name: 'result',       get label() { return t('fields.last_harvest'); },  type: 'date',                          editable: false },
        { name: 'results',      get label() { return t('fields.last_harvest'); },  type: 'mselect',  options: 'fromURL', optVal: 'result', optTxt: 'result', isFilter: true, filterCol: 'result', header: null, searchable: false },
      ],

      required: ['institutions', 'platforms', 'customer_id', 'requestor_id', 'api_key'],
      static: ['groups', 'statuses', 'results', 'inst_id', 'prov_id', 'local_id'],
      bulkOptions: ['Enable', 'Disable', 'Delete'],
      exportFields: ['inst_id', 'local_id', 'institution', 'prov_id', 'platform', 'customer_id', 'requestor_id', 'api_key'],
      sortby: 'platform',
    },

    audit: {
      urlRoot: '/api/audit',
      title: '',
      fields: [
        // 1. PRIMARY PLATFORM DEFINITION & RECONCILIATION MATRICES
        { name: 'plat_name',    get label() { return t('fields.platform'); },                  editable: false },
        { name: 'plat_id',      get label() { return t('fields.platform_id'); },               type: 'number',   header: null, editable: false, searchable: false },
        { name: 'plat_valid',   get label() { return t('fields.platform_valid'); },            type: 'toggle',   header: 'center', searchable: false },
        { name: 'platforms',    get label() { return t('fields.platforms'); },                 type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'plat_id', header: null, searchable: false },
        { name: 'json_item',    get label() { return t('fields.json_platform_item'); },        editable: false },
        { name: 'json_host',    get label() { return t('fields.data_host'); },                 editable: false },

        // 2. INSTITUTIONAL DEFINITION & RECONCILIATION MATRICES
        { name: 'inst_name',    get label() { return t('fields.institution'); },               editable: false },
        { name: 'inst_id',      get label() { return t('fields.institution_id'); },            type: 'number',   header: null, editable: false, searchable: false },
        { name: 'inst_valid',   get label() { return t('fields.institution_valid'); },         type: 'toggle',   header: 'center', searchable: false },
        { name: 'institutions', get label() { return t('fields.institutions'); },              type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'json_inst',    get label() { return t('fields.json_institution'); },          editable: false },

        // 3. GROUP ASSOCIATIONS
        { name: 'group_ids',    get label() { return t('fields.group_ids'); },                 type: 'array',    header: null, editable: false, searchable: false },
        { name: 'groups',       get label() { return t('fields.groups'); },                    type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'group_ids', header: null, searchable: false },

        // 4. COMPLEX DATA HARVEST VIEW FILTERS & METRICS
        { name: 'valid_types',  get label() { return t('fields.validation'); },                type: 'select',   options: 'fromURL', isFilter: true, filterCol: 'valid_state', header: null, searchable: false },
        { name: 'conso_only',   get label() { return t('fields.consortium_only'); },           type: 'toggle',   options: ['Inactive', 'Active'], isFilter: true, filterCol: 'is_conso', header: null, searchable: false },
        { name: 'no_inst',      get label() { return t('fields.hide_no_json_inst'); },         type: 'toggle',   options: ['Inactive', 'Active'], isFilter: true, filterCol: 'inst_set', header: null, searchable: false },
        { name: 'no_plat',      get label() { return t('fields.hide_no_json_plat'); },         type: 'toggle',   options: ['Inactive', 'Active'], isFilter: true, filterCol: 'plat_set', header: null, searchable: false },
        { name: 'no_host',      get label() { return t('fields.hide_no_json_host'); },         type: 'toggle',   options: ['Inactive', 'Active'], isFilter: true, filterCol: 'host_set', header: null, searchable: false },
      ],

      required: [],
      static: ['inst_id', 'plat_id', 'statuses', 'valid_types', 'group_ids'],
      bulkOptions: ['Set Institution Validated', 'Set Platform Validated', 'Clear Validation'],
      exportFields: ['plat_name', 'inst_name', 'json_host', 'json_item', 'json_inst'],
      sortby: 'platform',
    },

    jobs: {
      urlRoot: '/api/jobs',
      title: '',
      fields: [
        // 1. PRIMARY TIMESTAMPS & RECORD CODES
        { name: 'created',      get label() { return t('fields.created'); },                   type: 'date',     editable: false },
        { name: 'harvest_id',   get label() { return t('fields.harvest_id'); },                editable: false },
        { name: 'd_status',     get label() { return t('fields.status'); },                    editable: false },

        // 2. DESCRIPTIVE CORE LOG COLUMNS
        { name: 'prov_name',    get label() { return t('fields.platform'); },                  options: 'fromURL', editable: false },
        { name: 'inst_name',    get label() { return t('fields.institution'); },               options: 'fromURL', editable: false },
        { name: 'report_name',  get label() { return t('fields.report'); },                    options: 'fromURL', editable: false },
        { name: 'yearmon',      get label() { return t('fields.usage_date'); },                options: 'fromURL', editable: false },

        // 3. BACKGROUND METADATA STRINGS
        { name: 'rawfile',      get label() { return t('fields.raw_datafile'); },              header: null, editable: false },
        { name: 'retryUrl',     get label() { return t('fields.retry_url'); },                 header: null, editable: false },

        // 4. COMPLEX HARVEST GRAPH DATA FILTERS
        { name: 'platforms',    get label() { return t('fields.platforms'); },                 type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'prov_id', header: null, searchable: false },
        { name: 'institutions', get label() { return t('fields.institutions'); },              type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'reports',      get label() { return t('fields.reports'); },                   type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'report_id', header: null, searchable: false },
        { name: 'codes',        get label() { return t('fields.error_codes'); },               type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'error_id', header: null, searchable: false },
        { name: 'statuses',     get label() { return t('fields.statuses'); },                  type: 'mselect',  options: 'fromURL', optVal: 'value', optTxt: 'text', isFilter: true, filterCol: 'status', header: null, searchable: false },
      ],

      required: [],
      static: ['id', 'rawfile', 'retryUrl'],
      bulkOptions: ['Pause', 'Restart', 'Kill'],
      exportFields: [],
      sortby: 'created',
    },

    harvests: {
      urlRoot: '/api/harvests',
      title: '',
      fields: [
        // 1. PRIMARY TIMESTAMPS & DESCRIPTIVE CORE LOG COLUMNS
        { name: 'updated',     get label() { return t('fields.result_date'); },          editable: false },
        { name: 'prov_name',   get label() { return t('fields.platform'); },             editable: false },
        { name: 'release',     get label() { return t('fields.release'); },              header: 'center', editable: false },
        { name: 'inst_name',   get label() { return t('fields.institution'); },          editable: false },
        { name: 'report_name', get label() { return t('fields.report'); },               header: 'center', editable: false },
        { name: 'yearmon',     get label() { return t('fields.usage_date'); },           header: 'end', editable: false },
        { name: 'harvest_id',  get label() { return t('fields.harvest_id'); },           header: 'end', editable: false },
        { name: 'error',       get label() { return t('fields.result'); },               editable: false },
        { name: 'statusDot',   get label() { return t('fields.status'); },               header: 'center', editable: false, searchable: false },

        // 2. BACKGROUND METADATA STRINGS
        { name: 'rawfile',     get label() { return t('fields.raw_datafile'); },         header: null, editable: false },
        { name: 'retryUrl',    get label() { return t('fields.retry_url'); },            header: null, editable: false },

        // 3. COMPLEX HARVEST FILTER VIEWS
        { name: 'platforms',    get label() { return t('fields.platforms'); },           type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'prov_id', header: null, searchable: false },
        { name: 'institutions', get label() { return t('fields.institutions'); },        type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'groups',       get label() { return t('fields.institution_groups'); },  type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'reports',      get label() { return t('fields.reports'); },             type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'report_id', header: null, searchable: false },
        { name: 'updates',      get label() { return t('fields.updated'); },             type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'timeframes', header: null, searchable: false },
        { name: 'yymms',        get label() { return t('fields.usage_dates'); },         type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'yearmon', header: null, searchable: false },
        { name: 'codes',        get label() { return t('fields.error_codes'); },         type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'error_id', header: null, searchable: false },
        { name: 'statuses',     get label() { return t('fields.statuses'); },            type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'd_status', header: null, searchable: false },
      ],

      required: [],
      static: ['id', 'rawfile', 'retryUrl'],
      bulkOptions: ['Restart', 'Restart as r5', 'Restart as r5.1', 'Delete'],
      exportFields: [],
      sortby: 'updated',
    },

    savedreports: {
      urlRoot: '/api/savedreports',
      get title() { return t('datasets.savedreports.title'); },
      fields: [
        // 1. PRIMARY REPORT REGISTRY CLONES & LABELS
        { name: 'id',            get label() { return t('fields.id'); },            type: 'number', header: null, editable: false, searchable: false },
        { name: 'title',         get label() { return t('fields.report_title'); } },
        { name: 'report_name',   get label() { return t('fields.report'); },                      options: 'fromURL', editable: false },
        { name: 'last_harvest',  get label() { return t('fields.last_run_date'); }, type: 'date',   editable: false },

        // 2. CONFIGURATION SELECTION PARAMETERS
        {
          name: 'date_range',
          get label() { return t('fields.date_range'); },
          type: 'select',
          get options() {
            return [
              { title: t('options.date_range.fYTD'), value: 'fYTD' },
              { title: t('options.date_range.priorFy'), value: 'priorFy' },
              { title: t('options.date_range.cYTD'), value: 'cYTD' },
              { title: t('options.date_range.Custom'), value: 'Custom' },
            ];
          },
          isFilter: true,
        },
        { name: 'format',        get label() { return t('fields.format'); },        type: 'select', options: ['Compact', 'COUNTER'], isFilter: true },
        { name: 'exclude_zeros', get label() { return t('fields.include_zeros'); }, type: 'select', options: ['true', 'false'] },
      ],

      required: [],
      static: ['id'],
      bulkOptions: [],
      exportFields: [],
      sortby: 'title',
    },

};

// ─────────────────────────────────────────────────────────────────────────
// STEP 4: THE AUTOMATED LOOPER
// ─────────────────────────────────────────────────────────────────────────
export const tableSetup = Object.keys(rawTableSetup).reduce((acc, datasetKey) => {
  const dataset = rawTableSetup[datasetKey];

  const mergedFields = dataset.fields.map((field) => {
    const merged = { ...FIELD_DEFAULTS };
    Object.defineProperties(merged, Object.getOwnPropertyDescriptors(field));
    return merged;
  });

  // Auto-derive `static` if omitted so DatasetViewer.vue never crashes
  const derivedStatic = dataset.static ?? mergedFields
    .filter((f) => f.editable === false || f.visible === false)
    .map((f) => f.name);

  const mergedDataset = {};
  Object.defineProperties(mergedDataset, Object.getOwnPropertyDescriptors(dataset));
  mergedDataset.fields = mergedFields;
  mergedDataset.static = derivedStatic;

  acc[datasetKey] = mergedDataset;

  return acc;
}, {});
