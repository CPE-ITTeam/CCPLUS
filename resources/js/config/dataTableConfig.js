// resources/js/config/DataTableConfig.js

// ─────────────────────────────────────────────────────────────────────────
// STEP 1: THE DEFAULTS DEFINITION (Add this to the absolute top of the file)
// ─────────────────────────────────────────────────────────────────────────
const FIELD_DEFAULTS = {
  type: 'text',
  header: 'start', 
  editable: true,
  searchable: true,
  icon: '',
  helperText: '',
  options: null
};

// ─────────────────────────────────────────────────────────────────────────
// STEP 2: YOUR CURRENT DATASETS INDEX MAP
// Just change this single keyword line at the top to a regular "const" variable.
// Leave the rest of your 100+ table configuration blocks exactly as they are.
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
    institutions: {
      urlRoot: '/api/institutions', title: 'Institution',
      fields: [
        // 1. SYSTEM KEYS (Turn off searchable explicitly here)
        { name: 'id',           label: 'CC+ System ID',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'status',       label: 'Status',                    type: 'toggle',   options: 'fromURL', addValue: 'Active', header: 'center', searchable: false },

        // 2. PRIMARY TEXT DESCRIPTORS (Super clean: implicitly searchable!)
        { name: 'name',         label: 'Institution Name' },
        { name: 'local_id',     label: 'Local ID',                                    header: null },
        { name: 'role',         label: 'Role',                                        editable: false },

        // 3. COMPLEX RELATIONAL PIPELINES
        { name: 'group_string', label: 'Groups',                                      editable: false },
        { name: 'group_ids',    label: 'Group Assigmnent(s)',       type: 'array',    header: null, editable: false, searchable: false },
        { name: 'groups',       label: 'Groups',                    type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'group_ids', header: null, searchable: false },
        
        { name: 'type',         label: 'Institution Type',          type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'type_id' },
        { name: 'type_id',      label: 'Type ID',                   type: 'number',   header: null, editable: false, searchable: false },

        // 4. METRICS, TEXTAREAS & OPT-INS
        { name: 'fte',          label: 'FTE',                       type: 'number',   header: null, searchable: false },
        { name: 'notes',        label: 'Notes',                     type: 'textarea', header: null },
        { name: 'creds',        label: 'Add Credential Placeholders', type: 'toggle',  options: ['Inactive','Active'], addValue: 'Active', header: null, searchable: false },
      ],

      required: ['status', 'name', 'local_id', 'fte', 'type', 'creds'],
      static: ['id','group_string','group_ids','type_id'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Assign Type', 'Create New Group', 'Add to Existing Group'],
      exportFields: ['id','local_id','name','status','fte','group_ids','notes'],
      sortby: 'name',
    },
    types: {
      urlRoot: '/api/types', title: 'Institution Type',
      fields: [
        { name: 'id',   label: 'ID',               type: 'number', header: null, editable: false, searchable: false },
        { name: 'name', label: 'Institution Type' }
      ],

      required: ['name'],
      static: ['id'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'], 
      exportFields: ['id','name'],
      sortby: 'name',
    },
    groups: {
      urlRoot: '/api/groups', title: 'Institution Group',
      fields: [
        { name: 'id',           label: 'ID',                                 type: 'number',   header: null, editable: false, searchable: false },
        { name: 'name',         label: 'Group' },
        
        { name: 'institutions', label: 'Member Institutions',                type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },
        { name: 'member_ids',   label: 'Members',                            type: 'array',    header: null, editable: false, searchable: false },
        { name: 'count',        label: 'Member Count',                                         header: 'center', editable: false },
        
        { name: 'type_string',  label: 'Institution Type Restriction',                         editable: false },
        { name: 'types',        label: 'Institution Type Restriction',       type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'type_id', header: null },
        { name: 'type_id',      label: 'Type ID',                            type: 'number',   addValue: 1, header: null, editable: false, searchable: false },
      ],

      required: ['name','institutions'],
      static: ['id','type_id','type_string','member_ids'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'], 
      exportFields: ['id','name','member_ids'],
      sortby: 'name',
    },
    users: {
      urlRoot: '/api/users', title: 'User',
      fields: [
        // 1. IDENTITY & PRIMARY USER FIELDS
        { name: 'id',               label: 'ID',                  type: 'number',   header: null, editable: false, searchable: false },
        { name: 'status',           label: 'Status',              type: 'toggle',   options: 'fromURL', addValue: 'Active', header: 'center', searchable: false },
        { name: 'name',             label: 'User Name' },
        { name: 'email',            label: 'Email Address' },
        { name: 'phone',            label: 'Phone Number',                                          header: null },

        // 2. INSTITUTIONAL LINK RELATIONSHIPS
        { name: 'institution.name', label: 'Home Institution',                                                      editable: false },
        { name: 'institutions',     label: 'Institution',         type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'inst_id',          label: 'Institution ID',      type: 'number',   header: null, editable: false, searchable: false },

        // 3. SECURE AUTH & OPERATING PARAMETERS
        { name: 'user_role',        label: 'Role',                                                                  editable: false },
        { name: 'uroles',           label: 'Role',                type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'user_role', header: null, searchable: false },
        { name: 'inst_roles',       label: 'Institutional Role(s)',type: 'array',    header: null, editable: false, searchable: false },
        { name: 'group_roles',      label: 'Group Role(s)',       type: 'array',    header: null, editable: false, searchable: false },
        
        // 4. METADATA & RE-AUTHENTICATION STRINGS
        { name: 'password',         label: 'Password',            type: 'password', header: null, searchable: false },
        { name: 'confirm_pass',     label: 'Confirm Password',    type: 'passconf', header: null, searchable: false },
        { name: 'fiscalYr',         label: 'Fiscal Year Start',   type: 'select',   options: '', optVal: 'label', optTxt: 'label', header: null, searchable: false },
        { name: 'last_login',       label: 'Last Login',                                            header: 'center', editable: false, searchable: false },
      ],

      required: ['status', 'name', 'email', 'institutions', 'password', 'confirm_pass'],
      static: ['id', 'institution.name', 'inst_id', 'uroles', 'inst_roles', 'group_roles'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'],
      exportFields: ['id','email','password','name','phone','status','inst_id','inst_roles','group_roles'],
      sortby: 'email',
    },
    roles: {
      urlRoot: '/api/roles', title: 'Permissions',
       fields: [
        // 1. PRIMARY SYSTEM ID CROSS-REFERENCES
        { name: 'role_id',     label: 'role_id',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'user_id',     label: 'user_id',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'inst_id',     label: 'inst_id',             type: 'number',   header: null, editable: false, searchable: false },
        { name: 'group_id',    label: 'group_id',            type: 'number',   header: null, editable: false, searchable: false },

        // 2. READ-ONLY PROFILE MONITORING STRINGS (Implicitly searchable)
        { name: 'name',        label: 'Username',                                            editable: false },
        { name: 'email',       label: 'Email Address',                                       editable: false },
        { name: 'role_string', label: 'Role',                                                editable: false },
        { name: 'scope',       label: 'Scope',                                               editable: false },

        // 3. EDITABLE ASSIGNMENT RELATIONSHIPS & FILTERS
        { name: 'role',        label: 'Role',                type: 'select',   options: 'fromURL', isFilter: true, filterCol: 'role_string', header: null, searchable: false },
        { name: 'user_role',   label: 'Role',                type: 'select',   options: 'fromURL', header: null, searchable: false },
        { name: 'user',        label: 'User',                type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'user_id', header: null, searchable: false },
        { name: 'conso',       label: 'Entire Consortium',   type: 'toggle',   options: ['Inactive','Active'], addValue: 'Inactive', header: null, searchable: false },
        { name: 'institution', label: 'Institution',         type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'group',       label: 'Group',               type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'group_id', header: null, searchable: false },
      ],

      required: ['user_role', 'user', 'conso', 'institution', 'group'],
      static: ['role', 'user_id', 'role_id', 'inst_id', 'group_id'],
      bulkOptions: ['Delete'],
      exportFields: [],
      sortby: 'email',
    },
    connections: {
      urlRoot: '/api/connections', title: 'Connection',
      fields: [
        // 1. PRIMARY PLATFORM IDENTITY KEYS
        { name: 'id',           label: 'ID',                                 type: 'number',   header: null, editable: false, searchable: false },
        { name: 'platform',     label: 'Platform Name',                                                      editable: false },

        // 2. COUNTER SUITE REPORT CLUSTERS (Grouped by target mapping block)
        { name: 'PR',           label: 'PR Report',                          type: 'toggle',   header: 'center', searchable: false },
        { name: 'pr_insts',     label: 'PR Institution ID(s)',               type: 'array',    header: null },
        { name: 'pr_groups',    label: 'PR Group ID(s)',                     type: 'array',    header: null },

        { name: 'DR',           label: 'DR Report',                          type: 'toggle',   header: 'center', searchable: false },
        { name: 'dr_insts',     label: 'DR Institution ID(s)',               type: 'array',    header: null },
        { name: 'dr_groups',    label: 'DR Group ID(s)',                     type: 'array',    header: null },

        { name: 'TR',           label: 'TR Report',                          type: 'toggle',   header: 'center', searchable: false },
        { name: 'tr_insts',     label: 'TR Institution ID(s)',               type: 'array',    header: null },
        { name: 'tr_groups',    label: 'TR Group ID(s)',                     type: 'array',    header: null },

        { name: 'IR',           label: 'IR Report',                          type: 'toggle',   header: 'center', searchable: false },
        { name: 'ir_insts',     label: 'IR Institution ID(s)',               type: 'array',    header: null },
        { name: 'ir_groups',    label: 'IR Group ID(s)',                     type: 'array',    header: null },

        // 3. DATA HARVEST MONITORING METRICS & CONFIG FILTERS
        { name: 'result',       label: 'Last Harvest',                                                       editable: false },
        { name: 'results',      label: 'Last Harvest',                       type: 'mselect',  options: 'fromURL', optVal: 'result', optTxt: 'result', isFilter: true, filterCol: 'result', header: null, searchable: false },
        { name: 'conso',        label: 'Entire Consortium',                  type: 'toggle',   options: ['Inactive','Active'], addValue: 'Inactive', header: null, searchable: false },
        { name: 'institutions', label: 'Institution(s)',                     type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },
        { name: 'groups',       label: 'Group(s)',                           type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },
      ],


      required: ['id'],
      static: ['id', 'results','institutions', 'groups', 'pr_insts', 'pr_groups', 'dr_insts', 'dr_groups',
               'tr_insts', 'tr_groups', 'ir_insts', 'ir_groups'],
      bulkOptions: [],
      exportFields: ['id', 'platform', 'pr_insts', 'pr_groups', 'dr_insts', 'dr_groups',
                     'tr_insts', 'tr_groups', 'ir_insts', 'ir_groups'],
      sortby: 'platform',
    },
    credentials: {
      urlRoot: '/api/credentials', title: 'Credentials',
      // Report columns are type=select to offer options based on user's role
      fields: [
        // 1. PRIMARY CREATIVE RECORDS & IDENTITY TRACKERS
        { name: 'status',           label: 'Status',                    type: 'toggle',   options: '', addValue: 'Enabled', header: 'center', searchable: false },
        { name: 'statuses',         label: 'Status',                    type: 'mselect',  options: 'fromURL', isFilter: true, filterCol: 'status', header: null, searchable: false },

        // 2. INSTITUTIONAL LINK PAIRINGS
        { name: 'institution',      label: 'Institution',                                                 editable: false },
        { name: 'institutions',     label: 'Institution',               type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'inst_id',          label: 'Institution ID',            type: 'number',   header: null, editable: false, searchable: false },
        { name: 'local_id',         label: 'Local ID',                                                    header: null },

        // 3. PLATFORM & GROUP LINK PAIRINGS
        { name: 'platform',         label: 'Platform',                                                    editable: false },
        { name: 'platforms',        label: 'Platform',                  type: 'select',   options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'prov_id', header: null, searchable: false },
        { name: 'prov_id',          label: 'Platform ID',               type: 'number',   header: null, editable: false, searchable: false },
        { name: 'groups',           label: 'Group(s)',                  type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', header: null, searchable: false },

        // 4. SUSHI CREDENTIALS STRINGS (Implicitly text, start, editable, and searchable)
        { name: 'customer_id',      label: 'Customer ID' },
        { name: 'requestor_id',     label: 'Requestor ID' },
        { name: 'api_key',          label: 'API Key' },

        // 5. INDIVIDUAL SUSHI HARVESTER REPORT TOGGLES
        { name: 'PR',               label: 'PR Report',                 type: 'toggle',   header: 'center', searchable: false },
        { name: 'DR',               label: 'DR Report',                 type: 'toggle',   header: 'center', searchable: false },
        { name: 'TR',               label: 'TR Report',                 type: 'toggle',   header: 'center', searchable: false },
        { name: 'IR',               label: 'IR Report',                 type: 'toggle',   header: 'center', searchable: false },

        // 6. HARVEST COMPLIANCE LOGS & FILTERS
        { name: 'result',           label: 'Last Harvest',                                                editable: false },
        { name: 'results',          label: 'Last Harvest',              type: 'mselect',  options: 'fromURL', optVal: 'result', optTxt: 'result', isFilter: true, filterCol: 'result', header: null, searchable: false },
      ],

      required: ['institutions','platforms','customer_id', 'requestor_id', 'api_key'],
      static: ['groups', 'statuses', 'results', 'inst_id', 'prov_id', 'local_id'],
      bulkOptions: ['Enable', 'Disable', 'Delete'],
      exportFields: ['inst_id', 'local_id', 'institution', 'prov_id', 'platform', 'customer_id', 'requestor_id', 'api_key'],
      sortby: 'platform',
    },
    audit: {
      urlRoot: '/api/audit', title: '',
      fields: [
        // 1. PRIMARY PLATFORM DEFINITION & RECONCILIATION MATRICES
        { name: 'plat_name',    label: 'Platform',                                                    editable: false },
        { name: 'plat_id',      label: 'Platform ID',               type: 'number',   header: null, editable: false, searchable: false },
        { name: 'plat_valid',   label: 'Platform Valid',            type: 'toggle',   header: 'center', searchable: false },
        { name: 'platforms',    label: 'Platform(s)',               type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'plat_id', header: null, searchable: false },
        { name: 'json_item',    label: 'JSON Platform Item',                                          editable: false },
        { name: 'json_host',    label: 'Data Host',                                                   editable: false },

        // 2. INSTITUTIONAL DEFINITION & RECONCILIATION MATRICES
        { name: 'inst_name',    label: 'Institution',                                                 editable: false },
        { name: 'inst_id',      label: 'Institution ID',            type: 'number',   header: null, editable: false, searchable: false },
        { name: 'inst_valid',   label: 'Institution Valid',         type: 'toggle',   header: 'center', searchable: false },
        { name: 'institutions', label: 'Institution(s)',            type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'json_inst',    label: 'JSON Institution',                                            editable: false },

        // 3. GROUP ASSOCIATIONS
        { name: 'group_ids',    label: 'Group IDs',                 type: 'array',    header: null, editable: false, searchable: false },
        { name: 'groups',       label: 'Groups',                    type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'group_ids', header: null, searchable: false },

        // 4. COMPLEX DATA HARVEST VIEW FILTERS & METRICS
        { name: 'valid_types',  label: 'Validation',                type: 'select',   options: 'fromURL', isFilter: true, filterCol: 'valid_state', header: null, searchable: false },
        { name: 'conso_only',   label: 'Consortium Only',           type: 'toggle',   options: ['Inactive','Active'], isFilter: true, filterCol: 'is_conso', header: null, searchable: false },
        { name: 'no_inst',      label: 'Hide No JSON Institution',  type: 'toggle',   options: ['Inactive','Active'], isFilter: true, filterCol: 'inst_set', header: null, searchable: false },
        { name: 'no_plat',      label: 'Hide No JSON Platform',     type: 'toggle',   options: ['Inactive','Active'], isFilter: true, filterCol: 'plat_set', header: null, searchable: false },
        { name: 'no_host',      label: 'Hide No JSON DataHost',     type: 'toggle',   options: ['Inactive','Active'], isFilter: true, filterCol: 'host_set', header: null, searchable: false },
      ],

      required: [],
      static: ['inst_id', 'plat_id', 'statuses', 'valid_types', 'group_ids'],
      bulkOptions: ['Set Institution Validated', 'Set Platform Validated','Clear Validation'],
      exportFields: ['plat_name','inst_name','json_host','json_item','json_inst'],
      sortby: 'platform',
    },
    jobs: {
      urlRoot: '/api/jobs', title:'',
      fields: [
        // 1. PRIMARY TIMESTAMPS & RECORD CODES (Implicitly text, start, and searchable)
        { name: 'created',      label: 'Created',                                             editable: false },
        { name: 'harvest_id',   label: 'Harvest ID',                                          editable: false },
        { name: 'd_status',     label: 'Status',                                              editable: false },

        // 2. DESCRIPTIVE CORE LOG COLUMNS
        { name: 'prov_name',    label: 'Platform',                  options: 'fromURL',       editable: false },
        { name: 'inst_name',    label: 'Institution',               options: 'fromURL',       editable: false },
        { name: 'report_name',  label: 'Report',                    options: 'fromURL',       editable: false },
        { name: 'yearmon',      label: 'Usage Date',                options: 'fromURL',       editable: false },

        // 3. BACKGROUND METADATA STRINGS
        { name: 'rawfile',      label: 'Raw Datafile',                                        header: null, editable: false },
        { name: 'retryUrl',     label: 'Retry URL',                                           header: null, editable: false },

        // 4. COMPLEX HARVEST GRAPH DATA FILTERS (Grouped by tracking target mappings)
        { name: 'platforms',    label: 'Platform(s)',               type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'prov_id', header: null, searchable: false },
        { name: 'institutions', label: 'Institution(s)',            type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'reports',      label: 'Report(s)',                 type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'report_id', header: null, searchable: false },
        { name: 'codes',        label: 'Error Code(s)',             type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'error_id', header: null, searchable: false },
        { name: 'statuses',     label: 'Status(es)',                type: 'mselect',  options: 'fromURL', optVal: 'value', optTxt: 'text', isFilter: true, filterCol: 'status', header: null, searchable: false },
      ],

      required: [],
      static: ['id','rawfile','retryUrl'],
      bulkOptions: ['Pause', 'Restart', 'Kill'],
      exportFields: [],
      sortby: 'created',
    },
    harvests: {
      urlRoot: '/api/harvests', title:'',
      fields: [
        // 1. PRIMARY TIMESTAMPS & DESCRIPTIVE CORE LOG COLUMNS
        { name: 'updated',     label: 'Result Date',                                         editable: false },
        { name: 'prov_name',   label: 'Platform',                                            editable: false },
        { name: 'release',     label: 'Release',                                             header: 'center', editable: false },
        { name: 'inst_name',   label: 'Institution',                                         editable: false },
        { name: 'report_name', label: 'Report',                                              header: 'center', editable: false },
        { name: 'yearmon',     label: 'Usage Date',                                          header: 'end', editable: false },
        { name: 'harvest_id',  label: 'Harvest ID',                                          header: 'end', editable: false },
        { name: 'error',       label: 'Result',                                              editable: false },
        { name: 'statusDot',   label: 'Status',                                              header: 'center', editable: false, searchable: false },

        // 2. BACKGROUND METADATA STRINGS
        { name: 'rawfile',     label: 'Raw Datafile',                                        header: null, editable: false },
        { name: 'retryUrl',    label: 'Retry URL',                                           header: null, editable: false },

        // 3. COMPLEX HARVEST FILTER VIEWS (Grouped by tracking target mappings)
        { name: 'platforms',    label: 'Platform(s)',               type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'prov_id', header: null, searchable: false },
        { name: 'institutions', label: 'Institution(s)',            type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'groups',       label: 'Institution Group(s)',       type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'inst_id', header: null, searchable: false },
        { name: 'reports',      label: 'Report(s)',                 type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'report_id', header: null, searchable: false },
        { name: 'updates',      label: 'Updated',                   type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'timeframes', header: null, searchable: false },
        { name: 'yymms',        label: 'Usage Date(s)',             type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'yearmon', header: null, searchable: false },
        { name: 'codes',        label: 'Error Code(s)',             type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'error_id', header: null, searchable: false },
        { name: 'statuses',     label: 'Status(es)',                type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'name', isFilter: true, filterCol: 'd_status', header: null, searchable: false },
      ],

      required: [],
      static: ['id','rawfile','retryUrl'],
      bulkOptions: ['Restart', 'Restart as r5', 'Restart as r5.1', 'Delete'],
      exportFields: [],
      sortby: 'updated',
    },
    savedreports: {
      urlRoot: '/api/savedreports', title: 'Saved Report',
      fields: [
        // 1. PRIMARY REPORT REGISTRY CLONES & LABELS
        { name: 'id',            label: 'ID',                     type: 'number',   header: null, editable: false, searchable: false },
        { name: 'title',         label: 'Report Title' },
        { name: 'report_name',   label: 'Report',                                   options: 'fromURL', editable: false },
        { name: 'last_harvest',  label: 'Last Run Date',                            editable: false },

        // 2. CONFIGURATION SELECTION PARAMETERS (Custom array blocks clustered together)
        { name: 'date_range',    label: 'Date Range',             type: 'select',   options: [ { title: 'Fiscal YTD', value: 'fYTD' }, { title: 'Prior FY', value: 'priorFy' }, { title: 'Calendar YTD', value: 'cYTD' }, { title: 'Custom', value: 'Custom' } ], isFilter: true },
        { name: 'format',        label: 'Format',                 type: 'select',   options: ['Compact', 'COUNTER'], isFilter: true },
        { name: 'exclude_zeros', label: 'Include Zeros',          type: 'select',   options: ['true', 'false'] },
      ],

      required: [],
      static: ['id'],
      bulkOptions: [],
      exportFields: [],
      sortby: 'title',
    },
    consortia: {
      urlRoot: '/api/consortia', 
      title: 'Consortium', 
      dataset: 'consortia',

      // 💡 KEPT STANDARD: Matches 'required' like every other array in your config!
      required: [
        'name', 
        'ccp_key', 
        'email', 
        'admin_user', 
        'admin_pass', 
        'admin_confirm_pass'
      ],

      fields: [
        { name: 'status',      label: 'Status',         options: 'fromURL', header: 'center', searchable: false },
        { name: 'name',        label: 'Consortium Name' },
        { name: 'ccp_key',     label: 'Database Key' },
        { name: 'admin_name',  label: 'Admin Contact' },
        { name: 'email',       label: 'Email Address' }, // Aligned to your controller rule

        // Form-only visibility fields required by your backend controller
        { name: 'admin_user',         label: 'Admin Username/Email', type: 'text',     header: null },
        { name: 'admin_pass',         label: 'Admin Password',       type: 'password', header: null },
        { name: 'admin_confirm_pass', label: 'Confirm Password',     type: 'passconf', header: null },

        { name: 'displayName', label: 'Display Name',   header: null },
        { name: 'firstName',   label: 'First Name',     header: null },
        { name: 'lastName',    label: 'Last Name',      header: null },
      ],
      static: [],
      bulkOptions: ['Set Active', 'Set Inactive', 'Delete'], 
      sortby: 'name',
    },

    platforms: {
      urlRoot: '/api/platforms', title: 'Platform',
      fields: [
        // 1. PRIMARY SYSTEM CODES & TOGGLES
        { name: 'id',              label: 'ID',                         type: 'number',   header: null, editable: false, searchable: false },
        { name: 'status',          label: 'Status',                     type: 'toggle',   options: '', addValue: 'Active', header: 'center', searchable: false },
        { name: 'is_selected',     label: 'Status',                     type: 'toggle',   options: '', header: null, searchable: false },

        // 2. CORE VIEWABLE LOG DESCRIPTORS (Implicitly text and searchable)
        { name: 'name',            label: 'Platform Name' },
        { name: 'abbrev',          label: 'Abbreviation' },
        { name: 'cur_release',     label: 'Release',                                      header: 'center' },
        { name: 'data_host',       label: 'Data Host' },
        { name: 'day_of_month',    label: 'Harvest Day',                type: 'number',   header: 'center' },
        { name: 'instance_count',  label: 'Connected Instances',                          editable: false },
        { name: 'updated',         label: 'Last Updated',                                 editable: false },

        // 3. DIALOG DATA ARRAYS & CONFIGURATION REPOSITORIES
        { name: 'reg_releases',    label: 'COUNTER Releases',           type: 'array',    header: null },
        { name: 'connector_state', label: 'Connection Fields',          type: 'array',    header: null },
        { name: 'report_state',    label: 'Supported Reports',          type: 'array',    header: null },
        { name: 'service_url',     label: 'Service URL',                                  header: null },
        { name: 'platform_parm',   label: 'Platform Parameter',                            header: null },
        { name: 'registry_id',     label: 'Registry ID',                                  header: null, searchable: false },
        { name: 'refreshable',     label: 'Refreshable',                type: 'toggle',   addValue: 'Inactive', header: null, searchable: false },

        // 4. SUSHI CREDENTIAL PATHING OVERLAY STRINGS
        { name: 'customer_id',     label: 'Customer ID',                                  header: null, searchable: false },
        { name: 'requestor_id',    label: 'Requestor ID',                                 header: null, searchable: false },
        { name: 'api_key',         label: 'API Key',                                      header: null, searchable: false },

        // 5. TRANSIENT DIAGNOSTIC LOGS & REFRESH STATUS METADATA
        { name: 'refresh_result',  label: 'Refresh Result',                               header: null, editable: false },
        { name: 'refresh_text',    label: 'Refresh Result Tooltip',                       header: null, editable: false },
        { name: 'refresh_icon',    label: 'Refresh Result Icon',                          header: null, editable: false },
        { name: 'refresh_color',   label: 'Refresh ResultColor',                          header: null, editable: false },

        // 6. SHARED TABLE FILTER MULTI-SELECT MAPPINGS
        { name: 'releases',        label: 'COUNTER Releases',           type: 'mselect',  options: 'fromURL', isFilter: true, filterCol: 'reg_releases', header: null },
        { name: 'results',         label: 'Filter Refresh Result',      type: 'mselect',  options: 'fromURL', optVal: 'id', optTxt: 'value', isFilter: true, filterCol: 'refresh_result', header: null },
        { name: 'statuses',        label: 'Filter Status',              type: 'mselect',  options: 'fromURL', isFilter: true, filterCol: 'status', header: null },
      ],

      required: ['name', 'status', 'abbrev', 'cur_release', 'service_url', 'connector_state', 'report_state', 'platform_parm',
                 'day_of_month', 'refreshable', 'registry_id'],
      static: ['id','instance_count','updated','PR','DR','TR','IR','is_selected','reg_releases','customer_id',
               'requestor_id','api_key','connector_state','report_state','data_host','refresh_result','refresh_text',
               'refresh_icon','refresh_color'],
      bulkOptions: ['Set Active', 'Set Inactive', 'Refresh Registry', 'Delete'],
      // These are set by GlobalProviderController export() method
      exportFields: ['registry_id','name','release','status','service_url','refreshable','day_of_month','PR','DR','TR','IR',
                     'customer_id','requestor_id','api_key','platform_parm'],
      sortby: 'name',
    }
};
// ─────────────────────────────────────────────────────────────────────────
// STEP 3: THE AUTOMATED LOOPER (Paste this at the absolute BOTTOM of the file)
// ─────────────────────────────────────────────────────────────────────────
// This single loop processes all tables at build time and exports the complete map.
// Your other components can still import { tableSetup } with zero syntax changes!
export const tableSetup = Object.keys(rawTableSetup).reduce((acc, datasetKey) => {
  const dataset = rawTableSetup[datasetKey];
  
  acc[datasetKey] = {
    ...dataset,
    fields: dataset.fields.map(field => ({
      ...FIELD_DEFAULTS,
      ...field // Your custom field mappings overwrite the defaults automatically
    }))
  };
  
  return acc;
}, {});