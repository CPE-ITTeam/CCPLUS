<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class CcplusErrorsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Make sure we're talking to the global database
        $_db = \Config::get('database.connections.globaldb.database');
        $table = $_db . ".ccplus_errors";

        // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
           // SUSHI errors : 1XXX - 3XXX
            DB::table($table)->insert([
            ['id'=>1000, 'message' => 'Service Not Available', 'severity_id' => 12,
                'explanation' => 'The SUSHI service for the provider is not responding',
                'suggestion' => 'Contact the provider to report this issue.',
                'new_status' => 'ReQueued', 'color' => '#3686B4'
            ],
            ['id'=>1010, 'message' => 'Service Busy', 'severity_id' => 12,
                'explanation' => 'The request to the SUSHI server was successful, but the service is currently busy' .
                                 ' and cannot connect.',
                'suggestion' => 'Wait for the next retry. If this error occurs multiple times, contact the provider' .
                                ' to report this issue.',
                'new_status' => 'ReQueued', 'color' => '#3686B4'
            ],
            ['id'=>1011, 'message' => 'Report Queued for Processing', 'severity_id' => 11,
                'explanation' => "The SUSHI service accepted the request and put it into a queue for future" .
                                 " processing at the provider's service.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Pending', 'color' => '#00DD00'
            ],
            ['id'=>1020, 'message' => 'Client has made too many requests', 'severity_id' => 12,
                'explanation' => 'The request to the SUSHI server was successful, but the limit of requests per day' .
                                 ' has been reached.',
                'suggestion' => 'Wait for the next retry. If this error occurs multiple times, contact the provider' .
                                ' to report this issue.',
                'new_status' => 'ReQueued', 'color' => '#3686B4'
            ],
            ['id'=>1030, 'message' => 'Insufficient Information to Process Request', 'severity_id' => 12,
                'explanation' => 'One or more credentials is missing.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.',
                'new_status' => 'BadCreds', 'color' => '#FF9900'
            ],
            ['id'=>2000, 'message' => 'Requestor Not Authorized to Access Service', 'severity_id'=>12,
                'explanation' => 'One or more of your credentials is incorrect or has not been authorized, likely' .
                                 ' the requestor_id.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.',
                'new_status' => 'BadCreds', 'color' => '#FF9900'
            ],
            ['id'=>2010, 'message' => 'Requestor is Not Authorized to Access Usage for Institution', 'severity_id'=>12,
                'explanation' => 'The account reflected by your requestor_id does not have permission to access' .
                                 ' credentials for this institution or provider.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.',
                'new_status' => 'BadCreds', 'color' => '#FF9900'
            ],
            ['id'=>2011, 'message' => 'Global Reports Not Supported', 'severity_id'=>12,
                'explanation' => 'The Provider does not support Global Reports.',
                'suggestion' => 'Check details from the raw JSON',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>2020, 'message' => 'APIKey Invalid', 'severity_id'=>12,
                'explanation' => 'The APIKey for the request was not recognized by the report provider.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.',
                'new_status' => 'BadCreds', 'color' => '#FF9900'
            ],
            ['id'=>3000, 'message' => 'Report Not Supported', 'severity_id'=>12,
                'explanation' => 'The provider is not providing this report via this SUSHI endpoint.',
                'suggestion' => 'Remove this report from your settings to avoid future failures.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3010, 'message' => 'Report Version Not Supported', 'severity_id'=>12,
                'explanation' => 'The provider is not providing this report via this SUSHI endpoint.',
                'suggestion' => 'Remove this report from your settings to avoid future failures.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3020, 'message' => 'Invalid Date Arguments', 'severity_id'=>12,
                'explanation' => 'The dates requested are incorrect or contain an error.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3030, 'message' => 'No Usage Available for Requested Dates', 'severity_id'=>0,
                'explanation' => 'No usage data for the requested month exists or has been recorded by the provider.',
                'suggestion' => 'If data should exist, check with the provider.',
                'new_status' => 'Success', 'color' => '#00DD00'
            ],
            ['id'=>3031, 'message' => 'Usage Not Ready for Requested Dates', 'severity_id'=>12,
                'explanation' => 'A report for the dates requested is not yet available, but will be.',
                'suggestion' => 'Wait for the next retry or stop the harvest for now and restart it later. If this' .
                                ' error persists, consider asking the CC-PLUS admin to change the date on which the' .
                                ' monthly report is run.',
                'new_status' => 'ReQueued', 'color' => '#3686B4'
            ],
            ['id'=>3032, 'message' => 'Usage No Longer Available for Requested Dates', 'severity_id'=>12,
                'explanation' => 'Usage data for the requested month is not available.',
                'suggestion' => 'Contact the report provider to learn more details about the missing data.' .
                                ' The dataset could still be available, just not via the SUSHI service.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3040, 'message' => 'Partial Data Returned', 'severity_id' => 11,
                'explanation' => 'The request did not return a complete report.',
                'suggestion' => 'Work with the CC-PLUS admin and report provider to correct this error.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3050, 'message' => 'Parameter Not Recognized in this Context', 'severity_id' => 0,
                'explanation' => "The request asked for something that the server didn't recognize.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Success', 'color' => '#00DD00'
            ],
            ['id'=>3060, 'message' => 'Invalid ReportFilter Value', 'severity_id' => 0,
                'explanation' => "The request asked to filter out some data that the server didn't recognize.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Success', 'color' => '#00DD00'
            ],
            ['id'=>3061, 'message' => 'Incongruous ReportFilter Value', 'severity_id'=>0,
                'explanation' => 'Specified filter values out of scope for the requested report.',
                'suggestion' => 'Contact the provider to report this issue.',
                'new_status' => 'Success', 'color' => '#00DD00'
            ],
            ['id'=>3062, 'message' => 'Invalid ReportAttribute Value', 'severity_id'=>0,
                'explanation' => "The request asked for something that the server didn't recognize.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Success', 'color' => '#00DD00'
            ],
            ['id'=>3063, 'message' => 'Components Not Supported', 'severity_id'=>0,
                'explanation' => "The request asked component details, but reporting on component usage is not supported.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Success', 'color' => '#00DD00'
            ],
            ['id'=>3070, 'message' => 'Required ReportFilter Missing', 'severity_id' => 11,
                'explanation' => 'The request required a filter that was not present.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3071, 'message' => 'Required ReportAttribute Missing', 'severity_id' => 11,
                'explanation' => 'The request required a piece of information that was not present.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.',
                'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ['id'=>3080, 'message' => 'Limit Requested Greater than Maximum Server Limit', 'severity_id' => 11,
                  'explanation' => 'The request to the SUSHI server was successful, but the limit of requests per' .
                                   ' day has been reached.',
                  'suggestion' => 'Wait for the next retry. If this error occurs multiple times, contact the' .
                                  ' provider to report this issue.',
                  'new_status' => 'Fail', 'color' => '#DD0000'
            ],
            ]);
            // CCPLUS errors : 9000 - 9999
            DB::table($table)->insert([
             ['id'=>9030, 'message' => 'No usage reported for requested dates', 'severity_id' => 0,
                 'explanation' => 'No usage data for the requested month was reported by the provider.',
                 'suggestion' => 'If data should exist, check with the provider.',
                 'new_status' => 'Success', 'color' => '#00DD00'
             ],
             ['id'=>9100, 'message' => 'COUNTER / SUSHI credentials not enabled','severity_id' => 99,
                 'explanation' => 'Connection credentials must be enabled in order to harvest. ',
                 'suggestion' => 'Verify that the credentials related to this harvest are enabled.',
                 'new_status' => 'BadCreds', 'color' => '#FF9900'
             ],
             ['id'=>9200, 'message' => 'Unable to reach harvest endpoint','severity_id' => 99,
                 'explanation' => 'The request to the SUSHI server failed to connect.',
                 'suggestion' => 'Confirm that the URL in the platform settings is correct and retry.',
                 'new_status' => 'Fail', 'color' => '#999999'
             ],
             ['id'=>9300, 'message' => 'Harvest endpoint did not return JSON', 'severity_id' => 99,
                 'explanation' => 'The dataset received appears to contain invalid JSON.',
                 'suggestion' => 'Contact the provider to report this issue.',
                 'new_status' => 'Fail', 'color' => '#999999'
             ],
             ['id'=>9400, 'message' => 'Unknown decoding error in harvester', 'severity_id' => 99,
                 'explanation' => 'The downloaded dataset failed to be decoded as JSON.',
                 'suggestion' => 'Contact the provider to report this issue.',
                 'new_status' => 'Fail', 'color' => '#DD0000'
             ],
             ['id'=>9900, 'message' => 'COUNTER processing error', 'severity_id' => 99,
                 'explanation' => 'The system encountered errors parsing and storing report records',
                 'suggestion' => 'Check JSON keys and attributes; contact the provider to report this issue.',
                 'new_status' => 'Fail', 'color' => '#DD0000'
             ],
             ]);
        }
    }
}
