<?php

return [
    // Authorization & General
    'forbidden_request'         => 'Request failed (403) - Forbidden',
    'invalid_access_values'     => 'Invalid input access values',
    'unknown_report'            => 'Unknown report requested',
    'platform_ref_error'        => 'Global Platform reference error',
    'bulk_unrecognized'         => 'Unrecognized bulk action requested',

    // Connections
    'access_updated'            => 'Access updated successfully',
    'none_deleted'              => 'No Connections deleted - confirm your role(s)',
    'deleted_single'            => 'Connection successfully deleted',
    'deleted_multiple'          => ':count connections successfully deleted',
    'import_success'            => 'Import successful, Connections',

    // Global Providers / Platforms
    'platform_created'          => 'Platform successfully created',
    'platform_updated'          => 'Platform settings successfully updated',
    'platform_deleted'          => 'Global Platform successfully deleted',
    'instance_db_error'         => 'Error connecting to database for the :name instance!',
    'platform_import_success'   => 'Import successful, Platforms',
    'bulk_updated'              => ':count records successfully updated',
    'bulk_skipped'              => '; :count were skipped',
    'bulk_failed'               => '; :count failed',

    // COUNTER Registry Refresh
    'registry_fetching'         => 'Fetching registry list...',
    'registry_invalid_input'    => 'Refresh Request Failed - Invalid Input!',
    'registry_not_refreshable'  => 'The requested platform(s) are marked not refreshable',
    'registry_request_failed'   => 'Unable to retrieve COUNTER registry details: ',
    'registry_json_failed'      => 'Error decoding JSON returned by registry!',
    'registry_empty_data'       => 'No Platform data returned from Registry Platform request!',
    'registry_invalid_datatype' => 'Error getting registry details - invalid datatype received!',
    'registry_match_error'      => 'Error matching platform to registry!',
    'registry_cancelled'        => 'Refresh cancelled',
    'registry_no_updates'       => 'No Records updated',
    'registry_refresh_failed'   => 'Refresh failed',
    'registry_err_success'      => 'Platform successfully refreshed',
    'registry_err_sushi'        => 'Registry Error - sushi services improperly defined',
    'registry_err_connection'   => 'Registry Error - COUNTER API connection details invalid',
    'registry_err_no_match'     => 'No match for CC+ Registry_ID in Registry',
    'registry_sum_refreshed'    => ':count Platforms successfully refreshed',
    'registry_sum_disabled'     => ':count Platforms Skipped (Refresh Disabled)',
    'registry_sum_new'          => 'New Platforms Added:',
    'registry_sum_no_id'        => 'Platforms Skipped (No Registry ID):',
    'registry_sum_no_url'       => 'Platforms with no service URL:',
];
