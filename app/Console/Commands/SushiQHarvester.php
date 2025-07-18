<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use DB;
use App\Consortium;
use App\Report;
use App\Sushi;
use App\SushiQueueJob;
use App\FailedHarvest;
use App\HarvestLog;
use App\CcplusError;
use App\Severity;
// use App\Alert;
use App\GlobalProvider;
use App\ConnectionField;
 //
 // CC Plus Queue Harvesting Script
 // Examines the global Jobs queue and processes everything.
 // Retrieved JSON report data is saved in a holding folder, per-consortium,
 // to be processed by the counter processing command script (reportProcessor)
 //
class SushiQHarvester extends Command
{
    /**
     * The name and signature for the single-report Sushi processing console command.
     * @var string
     */
    protected $signature = 'ccplus:sushiharvester';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the CC-Plus Sushi Harvesting Queue';
    private $all_consortia;
    private $global_providers;
    private $connection_fields;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        try {
          $this->global_providers = GlobalProvider::where('is_active', true)->get();
        } catch (\Exception $e) {
          $this->global_providers = collect();
        }
        try {
          $this->connection_fields = ConnectionField::get();
        } catch (\Exception $e) {
          $this->connection_fields = collect();
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       // Allow input consortium to be an ID or KeyPick
        $ts = date("Y-m-d H:i:s") . " ";
        $ten_ago = strtotime("-10 minutes");

        // If this isn't set, bail with an error
        if (is_null(config('ccplus.reports_path'))) {
            $this->line($ts . "QueueHarvester: Global Setting for reports_path is not defined - Stopping!");
            return 0;
        }

       // Set error-severity so we only have to query for it once
        $all_severities = Severity::get();

//
// If we want to revisit using priority in the Job queue, this is how it USED to work :
//    ->orderBy('priority', 'DESC')->get();

       // Get all the ACTIVE consortia
        $consortia = Consortium::where('is_active',1)->get();
        foreach ($consortia as $con) {

            // Get jobs for this consortium
            $jobs = SushiQueueJob::where('consortium_id',$con->id)->orderBy('jobs.id', 'ASC')
                                 ->get(['jobs.id as id','consortium_id','harvest_id']);

            // Point the consodb connection at consortium's database
            config(['database.connections.consodb.database' => 'ccplus_' . $con->ccp_key]);
            DB::reconnect();

            // Set the output paths and create the folder if it isn't there
            $report_path = config('ccplus.reports_path') . $con->id;
            $unprocessed_path = $report_path . '/0_unprocessed/';
            if (!is_dir($unprocessed_path)) {
                mkdir($unprocessed_path, 0755, true);
            }

            // Add harvest and sushiSetting relations to the jobs collection
            $jobs->load('harvest','harvest.sushiSetting','harvest.sushiSetting.provider',
                        'harvest.sushiSetting.provider.registries');
            foreach ($jobs as $job) {
                // If the job points to a job with a wrong status (could have changed since creation), or the
                // the harvest record is AWOL, skip and delete the job record
                $keepJob = true;
                if (!$job->harvest) {
                    $keepJob = false;
                } else if (!in_array($job->harvest->status, array("New", "Queued", "ReQueued", "Pending"))) {
                    $keepJob = false;
                // Skip any "ReQueued" harvest that's already been updated today, loader will add it next go-round
                } else if ($job->harvest->status=='ReQueued' && (substr($job->harvest->updated_at, 0, 10)==date("Y-m-d"))) {
                    $keepJob = false;
                }

                // Skip "Paused" and any "Pending" harvest updated within the last 10 minutes
                    if ($keepJob) {
                        if ($job->harvest->status == 'Paused' ||
                            ($job->harvest->status == 'Pending' && strtotime($job->harvest->updated_at) > $ten_ago) ) {
                        continue;
                        }
                    }

                // Check sushi settings
                if ($keepJob && is_null($job->harvest->sushiSetting)) {     // settings gone? toss the job
                    $this->line($ts . " QueueHarvester: Unknown Sushi Credentials ID: " . $job->harvest->sushisettings_id .
                                        " , queue entry removed and harvest deleted.");
                    $job->harvest->delete();
                    $keepJob = false;
                }

                // Skip any harvest(s) related to a sushisetting that is not (or no longer) Active (the settings
                // may have been changed since the harvest was defined) - if found, set harvest status to Fail.
                if ($keepJob) {
                    if ($job->harvest->sushiSetting->status != 'Enabled') {
                        $error = CcplusError::where('id',9050)->first();
                        if ($error) {
                            FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => 'Initiation',
                                                    'error_id' => 9050, 'created_at' => $ts,
                                                    'detail' => $error->explanation . ', ' . $error->suggestion]);
                        }
                        $job->harvest->error_id = 9050;
                        $job->harvest->status = 'Fail';
                        $keepJob = false;
                    }
                }

                // Get report
                if ($keepJob) {
                    $report = Report::find($job->harvest->report_id);
                    if (is_null($report)) {     // report gone? toss entry
                        $this->line($ts . " QueueHarvester: Unknown Report ID: " . $job->harvest->report_id .
                                    ' , queue entry removed and harvest status set to Fail.');
                        $job->harvest->status = 'Fail';
                        $job->harvest->save();
                        $keepJob = false;
                    }
                }

                // If something above set keepJob false, remove job and get next one
                if (!$keepJob) {
                    $job->delete();
                    continue;
                }

                // Mark the harvest status as Active while we run the request
                $job->harvest->status = 'Harvesting';
                $job->harvest->save();

                // Setup begin and end dates for sushi request
                $yearmon = $job->harvest->yearmon;
                $ts = date("Y-m-d H:i:s");
                $begin = $yearmon . '-01';
                $end = $yearmon . '-' . date('t', strtotime($begin));
                $setting = $job->harvest->sushiSetting;

                // If (global) provider or institution is inactive, toss the job and move on
                if (!$setting->provider->is_active) {
                    $error = CcplusError::where('id',9060)->first();
                    if ($error) {
                        FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => 'Initiation',
                                                'error_id' => 9060, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                                'created_at' => $ts]);
                    } else {
                        $this->line($ts . " QueueHarvester: Provider: " . $setting->provider->name .
                                            " is INACTIVE , queue entry removed and harvest status set to Fail.");
                    }
                    $job->delete();
                    $job->harvest->error_id = 9060;
                    $job->harvest->status = 'Fail';
                    $job->harvest->save();
                    continue;
                }
                if (!$setting->institution->is_active) {
                    $error = CcplusError::where('id',9070)->first();
                    if ($error) {
                        FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => 'Initiation',
                                                'error_id' => 9070, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                                'created_at' => $ts]);
                    } else {
                        $this->line($ts . " QueueHarvester: Institution: " . $setting->institution->name .
                                            " is INACTIVE , queue entry removed and harvest status set to Fail.");
                    }
                    $job->delete();
                    $job->harvest->error_id = 9070;
                    $job->harvest->status = 'Fail';
                    $job->harvest->save();
                    continue;
                }

                // Create a new Sushi object
                $sushi = new Sushi($begin, $end);

                // Set output filename for raw data. Create the folder path, if necessary
                $_name = $job->harvest_id . '_' . $report->name . '_' . $begin . '_' . $end . '.json';
                $sushi->raw_datafile = $unprocessed_path . $_name;

                // Construct URI for the request
                $request_uri = $sushi->buildUri($setting, 'reports', $report, $job->harvest->release);

                // Make the request
                $request_status = $sushi->request($request_uri);

                // Examine the response
                $error = null;
                $valid_report = false;
                if ($request_status == "Success") {
                    // Skip validation for 3030 (no data)
                    if ($sushi->error_code != 3030) {
                        // Print out any non-fatal message from sushi request
                        if ($sushi->message != "") {
                            $this->line($ts . " QueueHarvester: Non-Fatal COUNTER API Exception (" . $job->harvest->id . "): (" .
                                                $sushi->error_code . ") : " . $sushi->message . ', ' . $sushi->detail);
                            $error = CcplusError::where('id',$sushi->error_code)->first();
                        }
                        // Validate the report
                        try {
                            $valid_report = $sushi->validateJson();
                        } catch (\Exception $e) {
                            // if no Report Items, set $sushi with 9030
                            if ($e->getCode() == 9030) {
                                $sushi->error_code = 9030;
                                $sushi->message = "No Data For Reported for Requested Dates";
                            // Any other error, set and record it
                            } else {
                                if ($error) {
                                    FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => 'API',
                                                            'error_id' => $sushi->error_code,
                                                            'detail' => $sushi->message . ', ' . $sushi->detail,
                                                            'help_url' => $sushi->help_url, 'created_at' => $ts]);
                                    $job->harvest->error_id = $sushi->error_code;
                                // Otherwise, signal 9100 - failed COUNTER validation
                                } else {
                                    FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => 'COUNTER',
                                                            'error_id' => 9100, 'detail' => 'Validation error: ' . $e->getMessage(),
                                                            'help_url' => $sushi->help_url, 'created_at' => $ts]);
                                    $this->line($ts . " QueueHarvester: Report failed COUNTER validation : " . $e->getMessage());
                                    $job->harvest->error_id = 9100;
                                    $error = CcplusError::where('id',9100)->first();
                                }
                            }
                        }
                    }

                    // If no data (3030) record a single failedHarvest record, and continue
                    if ($sushi->error_code == 3030 || $sushi->error_code == 9030) {
                        // Get error data from sushi_errors table
                        $this->line($ts . " QueueHarvester: No data in Report Items for harvest ID: " . $job->harvest->id);
                        $error = CcplusError::where('id',$sushi->error_code)->first();

                        // Clear all existing failed records
                        $deleted = FailedHarvest::where('harvest_id', $job->harvest->id)->delete();
                        // Add a single failed record to record the "no records received" exception
                        FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => 'API',
                                                'error_id' => $sushi->error_code ,
                                                'detail' => $sushi->message . ', ' . $sushi->detail,
                                                'help_url' => $sushi->help_url, 'created_at' => $ts]);

                        // Update attempts, record error_id and set Success
                        $job->harvest->attempts++;
                        $job->harvest->error_id = $sushi->error_code;
                    }
                    // Track last successful (last_harvest_id) and most-current harvest (last_harvest) for this sushisetting
                    $setting->last_harvest_id = $job->harvest->id;
                    if ($yearmon > $setting->last_harvest) {
                        $setting->last_harvest = $yearmon;
                    }
                    $setting->save();

                // If request is pending (in a provider queue, not a CC+ queue), just set harvest status
                // the record updates when we fall out of the remaining if-else blocks
                } else if ($request_status == "Pending") {
                    $job->harvest->status = "Pending";

                // If request failed, update the Logs
                } else {    // Fail
                    $error_msg = '';
                    // Turn severity string into an ID
                    $severity_id = $all_severities->where('name', 'LIKE', $sushi->severity . '%')->pluck('id');
                    if ($severity_id === null) {  // if not found, set to 'Error' and prepend it to the message
                        $severity_id = $all_severities->where('name', 'Error')->pluck('id');
                        $error_msg .= $sushi->severity . " : ";
                    }

                    // Clean up the message in case this is a new code for the errors table
                    $error_msg .= substr(preg_replace('/(.*)(https?:\/\/.*)$/', '$1', $sushi->message), 0, 60);

                    // Get/Create entry from the sushi_errors table
                    if ($sushi->error_code == 0) {  // Reserve 0 for "No Error"
                        $sushi->error_code = 9000;
                    }
                    $error = CcplusError::firstOrCreate(
                            ['id' => $sushi->error_code],
                            ['id' => $sushi->error_code, 'message' => $error_msg, 'severity' => $severity_id]
                    );
                    FailedHarvest::insert(['harvest_id' => $job->harvest->id, 'process_step' => $sushi->step,
                                            'error_id' => $error->id, 'detail' => $sushi->detail,
                                            'help_url' => $sushi->help_url, 'created_at' => $ts]);
                    if ($sushi->error_code != 9010) {
                        $sushi->detail .= " (URL: " . $request_uri . ")";
                    }
                    $this->line($ts . " QueueHarvester: COUNTER API Exception (" . $sushi->error_code . ") : " .
                                        " (Harvest: " . $job->harvest->id . ") " . $sushi->message . ", " . $sushi->detail);
                    $job->harvest->error_id = $error->id;
                }

                // If we have a validated report, mark the harvestlog
                if ($valid_report) {
                    $this->line($ts . " QueueHarvester: " . $setting->provider->name . " : " . $yearmon . " : " .
                                        $report->name . " saved for " . $setting->institution->name);
                    $job->harvest->error_id = 0;
                    $job->harvest->attempts++;
                    $job->harvest->status = "Waiting";

                    // Successfully processed the report - clear out any existing "failed" records
                    $deleted = FailedHarvest::where('harvest_id', $job->harvest->id)->delete();

                // No valid report data saved. If we failed, update harvest record
                // (ignore Pending, 3030, and 9030)
                } else if ($request_status != "Pending" &&
                            $sushi->error_code != 3030 && $sushi->error_code != 9030) {
                    // Increment harvest attempts
                    $job->harvest->attempts++;
                    $max_retries = intval(config('ccplus.max_harvest_retries'));

                    // If we're out of retries, the harvest fails and we set an Alert
                    if ($job->harvest->attempts >= $max_retries) {
                        $job->harvest->status = 'NoRetries';
                        // Alert::insert(['yearmon' => $yearmon, 'prov_id' => $setting->prov_id,
                        //                'harvest_id' => $job->harvest->id, 'status' => 'Active', 'created_at' => $ts]);
                    } else {
                        $job->harvest->status = 'ReQueued'; // ReQueue by default
                    }
                }

                // Try to move the JSON to the processed folder when an error is set
                if ($sushi->error_code > 0) {
                    $savePath = $report_path . '/' . $setting->inst_id . '/' . $setting->prov_id;
                    if ($setting->inst_id>0 && $setting->prov_id>0 && !is_dir($savePath)) {
                        mkdir($savePath, 0755, true);
                    }
                    $job->harvest->rawfile = null;  // default to no file saved
                    if (is_dir($savePath)) {
                        $newName = $savePath . '/' . $_name;
                        try {
                            rename($sushi->raw_datafile, $newName);
                            $job->harvest->rawfile = $_name;
                        } catch (\Exception $e) { // rename failed. Try to cleanup the unprocessed folder
                            try {
                                unlink($sushi->raw_datafile);
                            } catch (\Exception $e2) { }
                        }
                    }
                }

                // Force harvest status to the value from any Error, but leave some as-is (set above already)
                $keep_statuses = array('NoRetries','Waiting','ReQueued','Pending');
                if ($error && !in_array($job->harvest->status, $keep_statuses)) {
                    $job->harvest->status = $error->new_status;
                }

                // Sleep 2 seconds *before* saving the harvest record (keeping it technically "Active"),
                // to avoid having the provider block too-rapid requesting.
                sleep(2);

                // Clean up and update the database;
                // unless the request is "Pending", remove the job from the queue.
                unset($sushi);
                $job->harvest->save();
                if ($request_status != "Pending") {
                    $job->delete();
                }

            }   // foreach job for the current consortium
        }      // foreach active consortium
        return 1;
    }
}
