<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
// Note - this script is only using ccplus_global classes; connections
//        to consortium-specific tables use the DB query builder
use App\Report;
use App\Sushi;
use App\SushiQueueJob;
use App\CcplusError;
use App\Severity;
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
        $jobs = SushiQueueJob::with('consortium')->orderBy('id', 'ASC')->get();

        // Pull all the global providers so we have them inside the jobs loop
        $all_providers = GlobalProvider::with('registries')->where('is_active',1)->get();

        // Setup conso-specific tables for joining later
        $creds = array();
        $insts = array();
        $harvests = array();
        $failedharvests = array();
        $job_consos = $jobs->pluck(['consortium'])->unique()->all();
        foreach ($job_consos as $con) {
            $creds[$con->id] = 'ccplus_'.$con->ccp_key.'.sushisettings';
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

            // Skip any harvest(s) when credentials are not (or no longer) Active (the settings may have been changed
            // since the harvest was defined). If found, set harvest status to Fail.
            $setting = null;
            if ($keepJob) {

                // Get credentials and join institution data
                $result = DB::table($creds[$cid].' as CR')->where('CR.id',$harvest->sushisettings_id)
                            ->join($insts[$cid].' as II','II.id','=','CR.inst_id')
                            ->select('CR.*','II.name as inst_name','II.is_active as inst_active')
                            ->get();

                // No such credentials? delete the job and move on
                if ( count($result) == 0 ) {
                    $this->line($ts . " QueueHarvester: Unknown Credentials ID: " . $harvest->sushisettings_id .
                                        " , queue entry removed and harvest deleted.");
                    $harvest->delete();
                    $keepJob = false;
                } else {
                    $setting = $result[0];

                    if ($setting->status != 'Enabled') {
                        $error = CcplusError::where('id',9050)->first();
                        if ($error) {
                            $result = DB::table($failedharvests[$cid])
                                        ->insert(['harvest_id'=>$harvest->id, 'process_step'=>'Initiation', 'error_id'=>9050,
                                                  'created_at'=>$ts, 'detail'=>$error->explanation . ', ' . $error->suggestion]);
                        }
                        DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9050]);
                        $keepJob = false;
                    }
                    
                    // Attach global provider to the setting for use in the Sushi class (buildUri)
                    $setting->provider = $all_providers->where('id',$setting->prov_id)->first();
                    if (!$setting->provider) {
                        $this->line($ts . " QueueHarvester: Global Provider ID in credentials: " . $setting->prov_id .
                                        " , queue entry removed.");
                        $keepJob = false;
                    } else {
                        $setting->prov_name = $setting->provider->name;
                        $setting->prov_active = $setting->provider->is_active;
                    }
                }
            }

            // If something above set keepJob false, remove job and get next one
            if (!$keepJob || !$setting) {
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

            // Setup begin and end dates for sushi request
            $yearmon = $harvest->yearmon;
            $ts = date("Y-m-d H:i:s");
            $begin = $yearmon . '-01';
            $end = $yearmon . '-' . date('t', strtotime($begin));

            // If (global) provider or institution is inactive, toss the job and move on
            if (!$setting->prov_active) {
                $error = CcplusError::where('id',9060)->first();
                if ($error) {
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'Initiation',
                                          'error_id' => 9060, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                          'created_at' => $ts]);
                } else {
                    $this->line($ts . " QueueHarvester: Provider: " . $setting->prov_name .
                                        " is INACTIVE , queue entry removed and harvest status set to Fail.");
                }
                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9060]);
                $job->delete();
                continue;
            }
            if (!$setting->inst_active) {
                $error = CcplusError::where('id',9070)->first();
                if ($error) {
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'Initiation',
                                            'error_id' => 9070, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                            'created_at' => $ts]);
                } else {
                    $this->line($ts . " QueueHarvester: Institution: " . $setting->inst_name .
                                        " is INACTIVE , queue entry removed and harvest status set to Fail.");
                }
                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9070]);
                $job->delete();
                continue;
            }


            // Create a new Sushi object
            $sushi = new Sushi($begin, $end);

            // Set output filename for raw data. Create the folder path, if necessary
            $_name = $harvest->id . '_' . $report->name . '_' . $begin . '_' . $end . '.json';
            $sushi->raw_datafile = $unprocessed_path . $_name;

            // Construct URI for the request
            $request_uri = $sushi->buildUri($setting, 'reports', $report, $harvest->release);

            // Make the request
            $request_status = $sushi->request($request_uri);

            // Examine the response
            $error = null;
            $valid_report = false;
            $new_code = $sushi->error_code;
            $new_attempts = $harvest->attempts;

            // If request failed, set a FailedHarvest record and update the harvest record
            if ($request_status == "Fail") {
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
                $result = DB::table($failedharvests[$cid])
                            ->insert(['harvest_id' => $harvest->id, 'process_step' => $sushi->step, 'error_id' => $error->id,
                                      'detail' => $sushi->detail, 'help_url' => $sushi->help_url, 'created_at' => $ts]);
                if ($sushi->error_code != 9010) {
                    $sushi->detail .= " (URL: " . $request_uri . ")";
                }
                $this->line($ts . " QueueHarvester: COUNTER API Exception (" . $sushi->error_code . ") : " .
                                    " (Harvest: " . $harvest->id . ") " . $sushi->message . ", " . $sushi->detail);

                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => $error->id]);
                $job->delete();
                continue;
            }

            // Sushi said "Success"?
            if ($request_status == "Success") {
                $new_status = 'Success';
                // Skip validation for 3030 (no data)
                if ($new_code != 3030) {
                    // Print out any non-fatal message from sushi request
                    if ($sushi->message != "") {
                        $this->line($ts . " QueueHarvester: Non-Fatal COUNTER API Exception (" . $harvest->id . "): (" .
                                            $new_code . ") : " . $sushi->message . ', ' . $sushi->detail);
                        $error = CcplusError::where('id',$new_code)->first();
                    }
                    // Validate the report
                    try {
                        $valid_report = $sushi->validateJson();
                    } catch (\Exception $e) {
                        // if no Report Items, set $sushi with 9030
                        if ($e->getCode() == 9030) {
                            $new_code = 9030;
                            $sushi->message = "No Data For Reported for Requested Dates";
                        // Any other error, set and record it
                        } else {
                            if ($error) {
                                $result = DB::table($failedharvests[$cid])
                                            ->insert(['harvest_id' => $harvest->id, 'process_step' => 'API',
                                                      'error_id' => $new_code, 'detail' => $sushi->message.', '.$sushi->detail,
                                                      'help_url' => $sushi->help_url, 'created_at' => $ts]);
                            // Otherwise, signal 9100 - failed COUNTER validation
                            } else {
                                $result = DB::table($failedharvests[$cid])
                                            ->insert(['harvest_id' => $harvest->id, 'process_step' => 'COUNTER',
                                                      'error_id' => 9100, 'detail' => 'Validation error: ' . $e->getMessage(),
                                                      'help_url' => $sushi->help_url, 'created_at' => $ts]);
                                $this->line($ts . " QueueHarvester: Report failed COUNTER validation :: ".$harvest->id.
                                                    " :: " . $e->getMessage());
                                $new_code = 9100;
                                $error = CcplusError::where('id',9100)->first();
                                // Toss the raw data file
                                try { unlink($sushi->raw_datafile); } catch (\Exception $e2) { }
                            }
                        }
                    }
                }

                // If no data (3030) record a single failedHarvest record, and continue
                if ($new_code == 3030 || $new_code == 9030) {
                    // Get error data from sushi_errors table
                    $this->line($ts . " QueueHarvester: No data in Report Items for harvest ID: " . $harvest->id);
                    $error = CcplusError::where('id',$new_code)->first();

                    // Clear all existing failed records
                    $deleted = DB::table($failedharvests[$cid])->where('harvest_id', $harvest->id)->delete();

                    // Add a single failed record to record the "no records received" exception
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'API',
                                          'error_id' => $new_code, 'detail' => $sushi->message . ', ' . $sushi->detail,
                                          'help_url' => $sushi->help_url, 'created_at' => $ts]);

                    // Update attempts, record error_id and set Success
                    $new_attempts++;
                }
                // Track last successful (last_harvest_id) and most-current harvest (last_harvest) for this sushisetting
                $c_args = array('last_harvest_id' => $harvest->id);
                if ($yearmon > $setting->last_harvest) {
                    $c_args['last_harvest'] = $yearmon;
                }
                DB::table($creds[$cid])->where('id', $harvest->sushisettings_id)->update($c_args);

            // If request is pending (in a provider queue, not a CC+ queue), just set harvest status
            // the record updates when we fall out of the remaining if-else blocks
            } else if ($request_status == "Pending") {
                // $valid_report remains false....
                $new_status = "Pending";
            }

            // If we have a validated report, mark the harvestlog
            if ($valid_report) {
                $this->line($ts . " QueueHarvester: " . $setting->prov_name . " : " . $yearmon . " : " .
                                    $report->name . " saved for " . $setting->inst_name);
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

                // If we're out of retries, the harvest fails and we set an Alert
                if ($new_attempts >= $max_retries) {
                    $new_status = 'NoRetries';
                    // Alert::insert(['yearmon' => $yearmon, 'prov_id' => $setting->prov_id,
                    //                'harvest_id' => $job->harvest->id, 'status' => 'Active', 'created_at' => $ts]);
                } else {
                    $new_status = 'ReQueued'; // ReQueue by default
                }
            }

            // Try to move the JSON to the processed folder when an error is set.
            // Processor script will move the valid+successful JSON data once it is parsed and stored
            if ($new_code > 0) {
                $savePath = $report_path . '/' . $setting->inst_id . '/' . $setting->prov_id;
                if ($setting->inst_id>0 && $setting->prov_id>0 && !is_dir($savePath)) {
                    mkdir($savePath, 0755, true);
                }
                if (is_dir($savePath)) {
                    $newName = $savePath . '/' . $_name;
                    try {
                        rename($sushi->raw_datafile, $newName);
                    } catch (\Exception $e) { // rename failed. Try to cleanup the unprocessed folder (silently)
                        try {
                            unlink($sushi->raw_datafile);
                        } catch (\Exception $e2) { }
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
            unset($sushi);

            // Update the harvest 
            DB::table($harvests[$cid])->where('id', $harvest->id)
              ->update(['status'=>$new_status, 'error_id'=>$new_code, 'attempts'=>$new_attempts, 'rawfile'=>null]);

            // All done, remove the job record unless the harvest is Pending
            if ($new_status != "Pending") {
                $job->delete();
            }

        }   // foreach job
        return 1;
    }
}