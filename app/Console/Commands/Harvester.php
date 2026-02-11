<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\Consortium;
use App\Models\Report;
use App\Models\CounterApi;
use App\Models\GlobalQueueJob;
use App\Models\FailedHarvest;
use App\Models\HarvestLog;
use App\Models\CcplusError;
use App\Models\Severity;
// use App\Alert;
use App\Models\GlobalProvider;
use App\Models\ConnectionField;
 //
 // CC Plus Queue Harvesting Script
 // Examines the global Jobs queue and processes everything.
 // Retrieved JSON report data is saved in a holding folder, per-consortium,
 // to be processed by the counter processing command script (reportProcessor)
 //
class Harvester extends Command
{
    /**
     * The name and signature for the Harvester console command.
     * @var string
     */
    protected $signature = 'ccplus:harvester';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the CC-Plus Global Harvesting Queue';
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

        // Get jobs for this consortium (FIFO)
        $jobs = GlobalQueueJob::with('consortium')->orderBy('id', 'ASC')->get();

        // Pull all the global providers so we have them inside the jobs loop
        $all_providers = GlobalProvider::with('registries')->where('is_active',1)->get();

        // Setup conso-specific tables for joining later
        $creds = array();
        $insts = array();
        $harvests = array();
        $failedharvests = array();
        $job_consos = $jobs->pluck(['consortium'])->unique()->all();
        foreach ($job_consos as $con) {
            $creds[$con->id] = 'ccplus_'.$con->ccp_key.'.credentials';
            $insts[$con->id] = 'ccplus_'.$con->ccp_key.'.institutions';
            $harvests[$con->id] = 'ccplus_'.$con->ccp_key.'.harvestlogs';
            $failedharvests[$con->id] = 'ccplus_'.$con->ccp_key.'.failedharvests';
        }

        // Loop through all jobs
        foreach ($jobs as $job) {

            // Get the harvest and join the details (using query builder to hit tables explictly)
            $cid = $job->consortium_id;
            $result = DB::table($harvests[$cid])->where('id',$job->harvest_id)->get();

            // No such harvest? delete the job and move on
            if ( count($result) == 0 ) {
                $this->line($ts . " QueueHarvester: Unknown Harvest ID: " . $job->harvest_id . " for ConsoID: " . $cid .
                                    ", job queue entry removed.");
                $job->delete();
                continue;
            }
            $harvest = $result[0];
            $keepJob = true;

            // If the harvest has a wrong status (could have changed since creation) we'll skip it
            if (!in_array($harvest->status, array("New", "Queued", "ReQueued", "Pending"))) {
                $keepJob = false;
            // Skip any "ReQueued" harvest that's already been updated today, loader will add it next go-round
            } else if ($harvest->status=='ReQueued' && (substr($harvest->updated_at, 0, 10)==date("Y-m-d"))) {
                $keepJob = false;
            }

            // Skip "Paused" and any "Pending" harvest updated within the last 10 minutes
            if ($keepJob) {
                if ($harvest->status == 'Paused' ||
                    ($harvest->status == 'Pending' && strtotime($harvest->updated_at) > $ten_ago) ) {
                continue;
                }
            }

            // Get report
            if ($keepJob) {
                $report = Report::find($harvest->report_id);
                if (is_null($report)) {     // report gone? toss entry
                    $this->line($ts . " QueueHarvester: Unknown Report ID: " . $harvest->report_id .
                                ' , queue entry removed and harvest status set to Fail.');
                    DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail']);
                    $keepJob = false;
                }
            }

            // Skip any harvest(s) when credentials are not (or no longer) Active (credentials may have been changed
            // since the harvest was defined). If found, set harvest status to Fail.
            $credential = null;
            if ($keepJob) {

                // Get credentials and join institution data
                $result = DB::table($creds[$cid].' as CR')->where('CR.id',$harvest->credentials_id)
                            ->join($insts[$cid].' as II','II.id','=','CR.inst_id')
                            ->select('CR.*','II.name as inst_name','II.is_active as inst_active')
                            ->get();

                // No such credentials? delete the job and move on
                if ( count($result) == 0 ) {
                    $this->line($ts . " QueueHarvester: Unknown Credentials ID: " . $harvest->credentials_id .
                                        " , queue entry removed and harvest deleted.");
                    $harvest->delete();
                    $keepJob = false;
                } else {
                    $credential = $result[0];

                    if ($credential->status != 'Enabled') {
                        $error = CcplusError::where('id',9100)->first();
                        if ($error) {
                            $result = DB::table($failedharvests[$cid])
                                        ->insert(['harvest_id'=>$harvest->id, 'process_step'=>'Initiation', 'error_id'=>9100,
                                                  'created_at'=>$ts, 'detail'=>$error->explanation . ', ' . $error->suggestion]);
                        }
                        DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9100]);
                        $keepJob = false;
                    }
                    
                    // Attach global provider to the credential for use in the CounterApi class (buildUri)
                    $credential->provider = $all_providers->where('id',$credential->prov_id)->first();
                    if (!$credential->provider) {
                        $this->line($ts . " QueueHarvester: Global Provider ID in credentials: " . $credential->prov_id .
                                        " , queue entry removed.");
                        $keepJob = false;
                    } else {
                        $credential->prov_name = $credential->provider->name;
                        $credential->prov_active = $credential->provider->is_active;
                    }
                }
            }

            // If something above set keepJob false, remove job and get next one
            if (!$keepJob || !$credential) {
                $job->delete();
                continue;
            }

            // Set the output paths and create the folder if it isn't there
            $report_path = config('ccplus.reports_path') . $cid;
            $unprocessed_path = $report_path . '/0_unprocessed/';
            if (!is_dir($unprocessed_path)) {
                mkdir($unprocessed_path, 0755, true);
            }

            // Mark the harvest status as Active while we run the request
            DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Harvesting']);

            // Setup begin and end dates for COUNTER request
            $yearmon = $harvest->yearmon;
            $ts = date("Y-m-d H:i:s");
            $begin = $yearmon . '-01';
            $end = $yearmon . '-' . date('t', strtotime($begin));

            // If (global) provider or institution is inactive, toss the job and move on
            if (!$credential->prov_active) {
                $error = CcplusError::where('id',9100)->first();
                if ($error) {
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'Initiation',
                                          'error_id' => 9100, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                          'created_at' => $ts]);
                } else {
                    $this->line($ts . " QueueHarvester: Provider: " . $credential->prov_name .
                                        " is INACTIVE , queue entry removed and harvest status set to Fail.");
                }
                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9100]);
                $job->delete();
                continue;
            }
            if (!$credential->inst_active) {
                $error = CcplusError::where('id',9100)->first();
                if ($error) {
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'Initiation',
                                            'error_id' => 9100, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                            'created_at' => $ts]);
                } else {
                    $this->line($ts . " QueueHarvester: Institution: " . $credential->inst_name .
                                        " is INACTIVE , queue entry removed and harvest status set to Fail.");
                }
                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9100]);
                $job->delete();
                continue;
            }


            // Create a new CounterApi object
            $capi = new CounterApi($begin, $end);

            // Set output filename for raw data. Create the folder path, if necessary
            $rawfile = $harvest->id . '_' . $report->name . '_' . $harvest->yearmon . '.json';
            $capi->raw_datafile = $unprocessed_path . $rawfile;

            // Construct URI for the request
            $request_uri = $capi->buildUri($credential, 'reports', $report, $harvest->release);

            // Make the request
            $request_status = $capi->request($request_uri);

            // Examine the response
            $error = null;
            $valid_report = false;
            $new_code = $capi->error_code;
            $new_attempts = $harvest->attempts;

            // If request failed, set a FailedHarvest record and update the harvest record
            if ($request_status == "Fail") {
                $error_msg = '';
                // Turn severity string into an ID
                $severity_id = $all_severities->where('name', 'LIKE', $capi->severity . '%')->pluck('id');
                if ($severity_id === null) {  // if not found, set to 'Error' and prepend it to the message
                    $severity_id = $all_severities->where('name', 'Error')->pluck('id');
                    $error_msg .= $capi->severity . " : ";
                }

                // Clean up the message in case this is a new code for the errors table
                $error_msg .= substr(preg_replace('/(.*)(https?:\/\/.*)$/', '$1', $capi->message), 0, 60);

                // Get/Create entry from the CC+ errors table
                if ($capi->error_code == 0) {  // 0 is reserved for "No Error", reset to "unknown" code:9400
                    $capi->error_code = 9400;
                    $new_code = 9400;
                }
                $error = CcplusError::firstOrCreate(
                        ['id' => $capi->error_code],
                        ['id' => $capi->error_code, 'message' => $error_msg, 'severity' => $severity_id]
                );
                $result = DB::table($failedharvests[$cid])
                            ->insert(['harvest_id' => $harvest->id, 'process_step' => $capi->step, 'error_id' => $error->id,
                                      'detail' => $capi->detail, 'help_url' => $capi->help_url, 'created_at' => $ts]);
                if ($capi->error_code != 9200) {
                    $capi->detail .= " (URL: " . $request_uri . ")";
                }
                $this->line($ts . " QueueHarvester: COUNTER API Exception (" . $capi->error_code . ") : " .
                                    " (Harvest: " . $harvest->id . ") " . $capi->message . ", " . $capi->detail);

                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => $error->id]);
                $job->delete();
            }

            // CounterApi said "Success"?
            if ($request_status == "Success") {
                $new_status = 'Success';
                // Skip validation for 3030 (no data)
                if ($new_code != 3030) {
                    // Print out any non-fatal message from request
                    if ($capi->message != "") {
                        $this->line($ts . " QueueHarvester: Non-Fatal COUNTER API Exception (" . $harvest->id . "): (" .
                                            $new_code . ") : " . $capi->message . ', ' . $capi->detail);
                        $error = CcplusError::where('id',$new_code)->first();
                    }
                    // Validate the report
                    try {
                        $valid_report = $capi->validateJson();
                    } catch (\Exception $e) {
                        // if no Report Items, set $capi with 9030
                        if ($e->getCode() == 9030) {
                            $new_code = 9030;
                            $capi->message = "No Data For Reported for Requested Dates";
                        // Any other error, set and record it
                        } else {
                            if ($error) {
                                $result = DB::table($failedharvests[$cid])
                                            ->insert(['harvest_id' => $harvest->id, 'process_step' => 'API',
                                                      'error_id' => $new_code, 'detail' => $capi->message.', '.$capi->detail,
                                                      'help_url' => $capi->help_url, 'created_at' => $ts]);
                            // Otherwise, signal 9400) - failed COUNTER validation
                            } else {
                                $result = DB::table($failedharvests[$cid])
                                            ->insert(['harvest_id' => $harvest->id, 'process_step' => 'COUNTER',
                                                      'error_id' => 9400, 'detail' => 'Validation error: ' . $e->getMessage(),
                                                      'help_url' => $capi->help_url, 'created_at' => $ts]);
                                $this->line($ts . " QueueHarvester: Report failed COUNTER validation :: ".$harvest->id.
                                                    " :: " . $e->getMessage());
                                $new_code = 9400;
                                $error = CcplusError::where('id',9400)->first();
                            }
                        }
                    }
                }

                // If no data (3030) record a single failedHarvest record, and continue
                if ($new_code == 3030 || $new_code == 9030) {
                    // Get error data from CC+ errors table
                    $this->line($ts . " QueueHarvester: No data in Report Items for harvest ID: " . $harvest->id);
                    $error = CcplusError::where('id',$new_code)->first();

                    // Clear all existing failed records
                    $deleted = DB::table($failedharvests[$cid])->where('harvest_id', $harvest->id)->delete();

                    // Add a single failed record to record the "no records received" exception
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'API',
                                          'error_id' => $new_code, 'detail' => $capi->message . ', ' . $capi->detail,
                                          'help_url' => $capi->help_url, 'created_at' => $ts]);

                    // Update attempts, record error_id and set Success
                    $new_attempts++;
                }
                // Track last successful (last_harvest_id) and most-current harvest (last_harvest) for this credential
                $c_args = array('last_harvest_id' => $harvest->id);
                if ($yearmon > $credential->last_harvest) {
                    $c_args['last_harvest'] = $yearmon;
                }
                DB::table($creds[$cid])->where('id', $harvest->credentials_id)->update($c_args);

            // If request is pending (in a provider queue, not a CC+ queue), just set harvest status
            // the record updates when we fall out of the remaining if-else blocks
            } else if ($request_status == "Pending") {
                // $valid_report remains false....
                $new_status = "Pending";
            }

            // If we have a validated report, mark the harvestlog
            if ($valid_report) {
                $this->line($ts . " QueueHarvester: " . $credential->prov_name . " : " . $yearmon . " : " .
                                    $report->name . " saved for " . $credential->inst_name);
                $new_code = 0;
                $new_attempts++;
                $new_status = "Waiting";

                // Successfully processed the report - clear out any existing "failed" records
                $deleted = DB::table($failedharvests[$cid])->where('harvest_id', $harvest->id)->delete();

            // No valid report data saved. If we failed, update harvest record
            // (ignore Pending, 3030, and 9030)
            } else if ($request_status != "Pending" && $new_code != 3030 && $new_code != 9030) {
                // Increment harvest attempts
                $new_attempts++;
                $max_retries = intval(config('ccplus.max_harvest_retries'));

                // If we're out of retries, the harvest failed already - leave code alone and set status only
                if ($new_attempts >= $max_retries) {
                    $new_status = 'NoRetries';
                    // Alert::insert(['yearmon' => $yearmon, 'prov_id' => $credential->prov_id,
                    //                'harvest_id' => $job->harvest->id, 'status' => 'Active', 'created_at' => $ts]);
                } else {
                    $new_status = 'ReQueued'; // ReQueue by default
                }
            }

            // If there's an error code, clean up raw data file and or database pointer to it. The
            // processor script will move the valid+successful JSON data once it is parsed and stored
            if ($new_code > 0) {

                // 9100, 9200, and 9300 should all clear the rawfile field for the harvest. Nothing was saved/kept
                if (in_array($new_code,[9100,9200,9300])) $rawfile = null;

                // Set target path
                $savePath = $report_path . '/' . $credential->inst_id . '/' . $credential->prov_id;
                if ($credential->inst_id>0 && $credential->prov_id>0 && !is_dir($savePath)) {
                    mkdir($savePath, 0755, true);
                }
                if (is_dir($savePath)) {
                    // If the harvest has a rawfile value set and this attempt returned invalid/no JSON,
                    // clear out the saved data file, if possible.
                    if (in_array($new_code,[9100,9200,9300]) && !is_null($harvest->rawfile)) {
                        $oldFile = $savePath . '/' . $harvest->rawfile;
                        try {
                            unlink($oldFile);
                        } catch (\Exception $e2) { }
                    }
                    // If a rawfile exists from this attempt, try to move JSON to the processed folder.
                    if ($capi->raw_datafile != "") {
                        $newName = $savePath . '/' . $rawfile;
                        try {
                            rename($capi->raw_datafile, $newName);
                        } catch (\Exception $e) { // rename failed. Try to cleanup the unprocessed folder (silently)
                            try {
                                unlink($capi->raw_datafile);
                            } catch (\Exception $e2) { }
                            $rawfile = null;
                        }
                    }
                }
            }

            // Force harvest status to the value from any Error, but leave some as-is (set above already)
            if ($error) {
                $keep_statuses = array('NoRetries','Waiting','ReQueued','Pending');
                if (!in_array($error->new_status, $keep_statuses)) {
                    $new_status = $error->new_status;
                }
            }

            // Sleep 2 seconds *before* saving the harvest record (keeping it technically "Active"),
            // to avoid having the provider block too-rapid requesting.
            sleep(2);

            // Update the harvest 
            DB::table($harvests[$cid])->where('id', $harvest->id)
              ->update(['status' => $new_status, 'error_id' => $new_code, 'attempts' => $new_attempts,
                        'rawfile' => $rawfile]);

            // All done, remove the job record unless the harvest is Pending
            unset($capi);
            if ($new_status != "Pending") {
                $job->delete();
            }

        }   // foreach job
        return 1;
    }
}