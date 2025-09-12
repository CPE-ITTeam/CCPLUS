  // Dataset config map
export const tableSetup = {
    consortia: {
      url: '/consoList',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Consortium Name', key: 'name' },
        { title: 'Display Name', key: 'displayName' },
        { title: 'DB Key', key: 'databaseKey' },
        { title: 'Admin', key: 'admin' },
        { title: 'Email', key: 'email' },
      ],
      searchFields: [ 'name', 'displayName', 'databaseKey', 'admin', 'email' ]
    },
    credentials: {
      url: '/api/getCreds',
      headers: [
        { title: 'Connected', key: 'connected' },
        { title: 'Name', key: 'name' },
        { title: 'PR', key: 'PR', class: 'narrow-col' },
        { title: 'DR', key: 'DR', class: 'narrow-col' },
        { title: 'TR', key: 'TR', class: 'narrow-col' },
        { title: 'IR', key: 'IR', class: 'narrow-col' },
      ],
      searchFields: ['name', 'abbrev', 'dataHost']
    },
    institutions: {
      url: '/api/getInsts',
      headers: [
        { title: 'Status', key: 'status' },
        { title: 'Institution Name', key: 'name' },
        { title: 'Type', key: 'type' },
        { title: 'Consortium', key: 'consortiumKey' },
      ],
      searchFields: ['name', 'type', 'groups', 'consortiumKey']
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
    platforms: {
      url: '/api/getPlatforms',
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
        { title: 'Consortium Key', key: 'consortiumKey' },
        { title: 'Role', key: 'role_string' },
        { title: 'Last Login', key: 'last_login' },
      ],
      searchFields: [ 'username', 'email', 'institution', 'consortiumKey', 'role' ]
    }
};
