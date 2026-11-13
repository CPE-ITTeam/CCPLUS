<?php

return [
    // Shared / General
    'not_authorized'           => 'Not Authorized',
    'no_authorization'         => 'No Authorization',
    'update_forbidden'         => 'Update failed (403) - Forbidden',
    'create_forbidden'         => 'Create failed (403) - Forbidden',
    'missing_input'            => 'Missing input values',
    'unrecognized_action'      => 'Unrecognized action',
    'server_error'             => 'Server Error: :error',

    // Institutions
    'name_required'            => 'Name is required',
    'local_id_exists'          => 'Local ID already assigned to another institution.',
    'status_required'          => 'Status argument is required for update',
    'settings_updated'         => 'Settings successfully updated',
    'created'                  => 'Institution successfully created',
    'deleted'                  => 'Institution successfully deleted',
    'not_found_active'         => 'Institution record not found in the active environment.',
    'delete_server_error'      => 'Server Deletion Error: :error',
    'import_success'           => 'Import successful',

    // Institution Bulk Actions
    'bulk_status_updated'      => 'Status Updated',
    'bulk_group_exists'        => 'Group exists',
    'bulk_group_not_found'     => 'Group not found',
    'bulk_group_assigned'      => 'Group Assigned',
    'bulk_type_assigned'       => 'Type Assignment Successful',
    'bulk_deleted'             => 'Institutions deleted successfully',

    // Institution Types
    'type_duplicate'           => 'Another type with that name already exists',
    'type_created'             => 'Institution type successfully created',
    'type_updated'             => 'Institution type successfully updated',
    'type_default_protected'   => 'Default institution type cannot be deleted.',
    'type_in_use'              => 'Cannot delete type while institutions are assigned to it.',
    'type_import_success'      => 'Institution Types imported successfully',
    'type_import_skipped'      => ' (:count existing names skipped)',

    // Institution Groups
    'group_duplicate'          => 'Another group with that name already exists',
    'group_created'            => 'Institution group successfully created',
    'group_updated'            => 'Institution group successfully updated',
    'group_updated_type_skip'  => 'Group updated, but :count institutions skipped or removed due to type restriction',
    'group_import_success'     => 'Institution Groups imported successfully',
    'group_import_skipped'     => ' (:count existing names/ids skipped)',
];
