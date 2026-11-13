<?php

return [
    // Shared / General
    'forbidden_request'        => 'Request failed (403) - Forbidden',
    'operation_forbidden'      => 'Operation failed (403) - Forbidden',
    'update_forbidden'         => 'Update failed (403) - Forbidden',
    'delete_forbidden'         => 'Delete failed (403) - Forbidden',
    'self_delete_forbidden'    => 'Self-deletion forbidden (403); have an Admin assist you.',

    // Users
    'email_exists'             => 'Email address is already assigned to another user',
    'role_db_error'            => 'Error adding role to database: :error',
    'created'                  => 'User successfully created',
    'updated'                  => 'User settings successfully updated',
    'import_success'           => 'Import successful, Users',

    // Roles
    'role_invalid_refs'        => 'Operation failed - invalid references',
    'role_not_authorized'      => 'Operation failed - not authorized',
    'role_already_assigned'    => 'User already has the requested role.',
    'role_save_error'          => 'Error saving to database: :error',
    'role_saved'               => 'Role successfully saved',

    // Consortia / Instances
    'conso_admin_undefined'    => 'Server Admin credential is not properly defined!',
    'conso_db_create_failed'   => 'Failed to create database space: :error',
    'conso_clone_failed'       => 'Database cloning failed! Cannot build clone commands for: :table',
    'conso_schema_failed'      => 'Schema replication failed for table ":table": :error',
    'conso_seed_failed'        => 'Database seeding failed with status code: :code',
    'conso_grant_failed'       => 'Database user mapping permissions allocation failed: :error',
    'conso_created'            => 'New consortium : :name Successfully Created.',
    'conso_missing_args'       => 'Missing arguments! - no update applied.',
    'conso_updated'            => 'Consortium : :name Successfully Updated.',
];
