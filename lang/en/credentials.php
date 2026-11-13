<?php

return [
    // Authorization & General
    'forbidden_request'      => 'Request failed (403) - Forbidden',
    'delete_forbidden'       => 'Delete failed (403) - Forbidden',
    'update_forbidden'       => 'Not Authorized to update credential',
    'inst_forbidden'         => 'You can only assign credentials for your institution',
    'missing_args'           => 'Request failed - Missing argument(s)',
    'bulk_unrecognized'      => 'Unrecognized bulk action requested',

    // Create / Update / Delete
    'platform_required'      => 'Platform assignment is required',
    'platform_unknown'       => 'Platform unknown or undefined',
    'already_exists'         => 'A credential already exists for this provider and institution',
    'created'                => 'Credentials successfully created',
    'updated'                => 'Credential updated successfully',
    'deleted'                => 'Credentials successfully deleted',
    'reference_error'        => 'Credential reference error for platform or institution',
    'platform_unavailable'   => 'Global platform not found or report unavailable',

    // Testing & Audit
    'decode_error'           => 'Error decoding input!',
    'type_required'          => 'Type is a required argument!',
    'invalid_html_response'  => 'Invalid response - JSON expected but request returned HTML',
    'invalid_json_response'  => 'Invalid response - JSON expected but not received - check URL',
    'test_success'           => 'Request response successfully received',
    'status_request_failed'  => 'Request for service status failed!',
    'audit_invalid_type'     => 'Invalid request type for auditItems',
    'audit_instance_error'   => 'Error getting current instance data',
    'audit_path_undefined'   => 'Global Setting for reports_path is undefined - Stopping!',
    'audit_no_records'       => 'No matching connected credentials to audit.',
    'audit_invalid_column'   => 'Invalid request - no such column',

    // Import
    'import_updated'         => ':count added or updated',
    'import_incomplete'      => ' (:count were incomplete)',
    'import_skipped'         => ':count skipped',
    'import_completed'       => 'COUNTER credentials import completed : :detail',
];
