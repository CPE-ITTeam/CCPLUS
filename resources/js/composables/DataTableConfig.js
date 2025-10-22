  // Dataset config map
export const tableSetup = {
    consortia: {
      url: '',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Consortium Name', key: 'name' },
        { title: 'DB Key', key: 'ccp_key' },
        { title: 'Name', key: 'admin_name' },
        { title: 'Email', key: 'admin_email' },
      ],
      searchFields: [ 'name', 'displayName', 'databaseKey', 'admin', 'email' ]
    },
    institutions: {
      url: '/api/getInsts/admin',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Institution Name', key: 'name' },
        { title: 'Group(s)', key: 'group_string' },
        { title: 'Type', key: 'type' },
        { title: 'Consortium', key: 'ccp_key' },
        { title: 'Role', key: 'role' },
      ],
      searchFields: ['name', 'groups', 'type', 'ccp_key', 'role']
    },
    institutionGroups: {
      url: '/api/getInstGroups',
      headers: [
        { title: 'Group', key: 'name' },
        { title: 'Member Count', key: 'count' },
      ],
      searchFields: ['name']
    },
    institutionTypes: {
      url: '/api/getInstTypes',
      headers: [
        { title: 'Type Value', key: 'id' },
        { title: 'Name', key: 'name' },
      ],
      searchFields: ['id', 'name']
    },
    connections: {
      url: '/api/getCreds',
      headers: [
        { title: 'Platform', key: 'platform.name' },
        { title: 'Institution', key: 'institution.name' },
        { title: 'Customer ID', key: 'customerId' },
        { title: 'Requestor ID', key: 'requestorId' },
        { title: 'API Key', key: 'apiKey' },
        { title: 'PR', key: 'PR', class: 'narrow-col' },
        { title: 'DR', key: 'DR', class: 'narrow-col' },
        { title: 'TR', key: 'TR', class: 'narrow-col' },
        { title: 'IR', key: 'IR', class: 'narrow-col' },
      ],
      searchFields: ['value', 'platform', 'institution', 'customerId', 'requestorId', 'apiKey']
    },
    platforms: {
      url: '/api/getPlatforms/admin',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Abbr', key: 'abbrev' },
        { title: 'Rel', key: 'release' },
        { title: 'Platform Name', key: 'name' },
        { title: 'Data Host', key: 'dataHost' },
        { title: 'Harvest On', key: 'harvestDate' },
        { title: 'Conx', key: 'connectionCount' },
        { title: 'Updated', key: 'datetime' },
      ],
      searchFields: ['name', 'abbrev', 'dataHost', 'registryId']
    },
    users: {
      url: '/api/getUsers',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Email', key: 'email' },
        { title: 'Institution', key: 'institution.name' },
        { title: 'Consortium Key', key: 'ccp_key' },
        { title: 'Last Login', key: 'last_login' },
      ],
      searchFields: [ 'email', 'institution.name', 'ccp_key' ]
    },
    roles: {
      url: '/api/getRoles',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Name', key: 'name' },
        { title: 'Email', key: 'email' },
        { title: 'Institution', key: 'institution.name' },
        { title: 'Consortium Key', key: 'ccp_key' },
        { title: 'Role', key: 'role' },
      ],
      searchFields: [ 'name', 'email', 'institution.name', 'ccp_key', 'role' ]
    },
    harvests: {
      url: '/api/getHarvests',
      headers: [
        { title: 'Result Date', key: 'updated' },
        { title: 'Platform', key: 'prov_name' },
        { title: 'Institution', key: 'inst_name' },
        { title: 'Report', key: 'report_name', align: 'center' },
        { title: 'Usage Date', key: 'yearmon' },
        { title: 'Harvest ID', key: 'id', align: 'center', width: '100px'},
        { title: 'Result', key: 'error.id' },
        { title: 'Status', key: 'status', align: 'center'},
      ],
      searchFields: [ 'prov_name', 'inst_name', 'report_name', 'yearmon', 'status' ]
    },
    jobs: {
      url: '/api/getJobs',
      headers: [
        { title: 'Created', key: 'created' },
        { title: 'Platform', key: 'prov_name' },
        { title: 'Institution', key: 'inst_name' },
        { title: 'Report', key: 'report_name', align: 'center' },
        { title: 'Usage Date', key: 'yearmon' },
        { title: 'Harvest ID', key: 'id', align: 'center'},
        { title: 'Status', key: 'dStatus' },
      ],
      searchFields: [ 'prov_name', 'inst_name', 'report_name', 'yearmon', 'dStatus' ]
    },
    savedreports: {
      url: '/api/getSavedReports',
      headers: [
        { title: 'Report Title', key: 'title' },
        { title: 'Report', key: 'master_name' },
        { title: 'Date Range', key: 'date_range' },
        { title: 'Last Run Date', key: 'last_harvest' },
        { title: 'Format', key: 'format' },
        { title: 'Include Zeros', key: 'exclude_zeros', align: 'center' },
        { title: 'Actions', key: 'action', align: 'end', sortable: false },
      ],
      searchFields: [ 'title', 'master_name', 'date_range', 'last_harvest', 'format' ]
    }
};
