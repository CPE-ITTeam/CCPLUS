<?php

return [
    // Authorization & General Errors
    'not_authorized'        => 'Not Authorized',
    'forbidden_request'     => 'Request failed (403) - Forbidden',
    'model_not_configured'  => 'Model class not configured on controller.',
    'db_error'              => 'Database Error',

    // Single Record CRUD
    'not_found'             => ':item not found.',
    'deleted'               => ':item successfully deleted.',
    'delete_forbidden'      => 'Delete failed (403) - Forbidden',
    'delete_dependent'      => 'Delete failed (403) - Forbidden or :item has dependent records.',
    'delete_failed'         => 'Unable to delete :item: :error',

    // Bulk Operations
    'bulk_unauthorized'     => 'Unauthorized bulk action.',
    'bulk_unrecognized'     => 'Unrecognized bulk action requested.',
    'bulk_enabled'          => ':items enabled successfully.',
    'bulk_disabled'         => ':items disabled successfully.',
    'bulk_deleted'          => ':items deleted successfully.',
    'bulk_none_selected'    => 'No :items selected.',
    'bulk_activated'        => 'Selected :items successfully activated.',
    'bulk_deactivated'      => 'Selected :items successfully deactivated.',


    // CSV Imports
    'import_file_error'     => 'Error accessing CSV import file',
    'import_type_invalid'   => 'Error - unrecognized import type.',
    'import_empty'          => 'Import file is empty, no changes applied.',
    'import_removed'        => ':count removed',
    'import_updated'        => ':count updated',
    'import_added'          => ':count added',
    'import_skipped'        => ':count skipped',
    'import_no_changes'     => '0 changes',
];
