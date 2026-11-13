<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Harvest Log & Queue Messages
    |--------------------------------------------------------------------------
    */

    'errors' => [
        'not_authorized'         => 'Not authorized!',
        'not_authorized_hyphen'  => 'Error - Not authorized',
        'update_failed'          => 'Update failed (:error)',
        'consortium_undefined'   => 'Error: Current consortium is undefined.',
        'reports_path_unset'     => 'System not configured to save raw data, check config value of CCP_REPORTS.',
        'raw_file_inaccessible'  => 'Raw datafile is not accessible.',
        'invalid_date_range'     => 'From/To dates invalid or missing!',
        'missing_consortium'     => 'Error: missing consortium',
        'missing_credentials'    => 'Error: missing or invalid credentials',
        'missing_reports'        => 'Error: missing reports',
        'no_records_created'     => 'No harvest log records created, check provider and credentials.',
        'invalid_bulk_inputs'    => 'Invalid inputs passed to bulkAction!',
        'invalid_bulk_status'    => 'Invalid status requested for bulkAction!',
        'global_job_create_fail' => 'Error creating job entry in global table!',
        'unknown_bulk_action'    => 'Unrecognized bulk action requested',
        'duplicate_skipped'      => 'Harvest already scheduled for report :report and month :yearmon, skipping...',
        'create_record_failed'   => 'Failed to create harvest record: :error',
    ],

    'messages' => [
        'updated'             => 'HarvestLog successfully updated',
        'deleted'             => 'Log record deleted successfully',
        'created_count'       => ':count harvest log records successfully created',
        'restarted_count'     => 'Successfully restarted :count harvests',
        'as_release'          => ' as release :release',
        'skipped_count'       => ', and skipped :count harvests',
        'release_unavailable' => ' (release :release may not be available).',
        'no_harvests_changed' => 'No selected harvests modified',
        'no_harvests_release' => ', release :release may not be available',
    ],

    'filters' => [
        'no_error' => 'No Error',
    ],

    'statuses' => [
        'Success'    => 'Success',
        'Queued'     => 'Queued',
        'Harvesting' => 'Harvesting',
        'Pending'    => 'Pending',
        'Paused'     => 'Paused',
        'Stopped'    => 'Stopped',
        'NoRetries'  => 'Failed - No Retries',
        'New'        => 'New',
        'ReQueued'   => 'ReQueued',
        'Waiting'    => 'Waiting',
        'Processing' => 'Processing',
    ],

];
