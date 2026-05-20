<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\Consortium;
use App\Models\Report;
use App\Models\GlobalQueueJob;
use App\Models\FailedHarvest;
use App\Models\HarvestLog;
use App\Models\CcplusError;
use App\Models\Severity;
use App\Models\CounterApi;
use App\Models\GlobalProvider;
use App\Models\ConnectionField;
use \ubfr\c5tools\CounterApiRequest;
use \ubfr\c5tools\JsonReport;
use \ubfr\c5tools\exceptions\CounterApiException;
use \ubfr\c5tools\exceptions\CounterApiRequestException;
use \ubfr\c5tools\exceptions\InvalidCounterApiResponseException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;
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
                        $this->line($ts . " QueueHarvester: Undefined Provider ID in credentials: " . $credential->prov_id .
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

            // Set output filename for raw data. Create the folder path, if necessary
            $rawfile = $harvest->id . '_' . $report->name . '_' . $harvest->yearmon . '.json';
            $raw_datafile = $unprocessed_path . $rawfile;

            // Construct URI for the request
            $message = "";
            $detail = "";
            $help_url = "";
            $severity = "";
            $error_code = 0;
            $request_status = "Success";
            $request_uri = CounterApi::buildUri($begin, $end, $credential, $report, 'reports', $harvest->release);
            if (is_null($request_uri)) {
                $error = CcplusError::where('id',9200)->first();
                if ($error) {
                    $result = DB::table($failedharvests[$cid])
                                ->insert(['harvest_id' => $harvest->id, 'process_step' => 'Initiation',
                                        'error_id' => 9200, 'detail' => $error->explanation . ', ' . $error->suggestion,
                                        'created_at' => $ts]);
                } else {
                    $this->line($ts . " QueueHarvester: Platform: " . $credential->prov_name .
                                        " has invalid or missing service_url - check registries and settings.");
                }
                DB::table($harvests[$cid])->where('id', $harvest->id)->update(['status' => 'Fail', 'error_id' => 9200]);
                $job->delete();
                continue;
            }

            // Make the request
            $response = '';
            try {
                $request = CounterApiRequest::fromUrl($request_uri);
                $response = $request->doRequest();
                if ($response->isException() || $response->isReportWithException()) {
                    $request_status = "Fail";
                    throw new CounterApiException($response, $request);
                } elseif ($response->isReport()) {
                    $response = new JsonReport($response, $request);
                    if (method_exists($response,'asJson')) {
                        $_json = $response->asJson();
                        // Set error 9030 if Report_Items missing or empty
                        if ((isset($_json->Report_Items))) {
                            if (count($_json->Report_Items) == 0) {
                                $error_code = 9030;
                            }
                        } else {
                            $error_code = 9030;
                        }
                        unset($_json);
                    }
                } else {
                    $request_status = "Fail";
                    throw new InvalidCounterApiResponseException(
                        'response is unusuable',
                        $request->getRequestUrl(),
                        $response->getHttpResponse()
                    );
                }
            } catch (CounterApiRequestException $e) {
                $error_code = 9200;
                $severity = "ERROR";
                $message = $e->getMessage();
                $detail = (property_exists($e, 'Data')) ? $e->Data : "";
                $help_url = (property_exists($e, 'Help_URL')) ? $e->Help_URL : "";
                $request_status = "Fail";
            } catch (InvalidCounterApiResponseException $e) {
                $error_code = 9300;
                $severity = "ERROR";
                $message = $e->getMessage();
                $detail = (property_exists($e, 'Data')) ? $e->Data : "";
                $help_url = (property_exists($e, 'Help_URL')) ? $e->Help_URL : "";
                $request_status = "Fail";
            } catch (CounterApiException $e) {
                $error_code = $e->getCode();
                $severity = "ERROR";
                $message = $e->getMessage();
                $detail = (property_exists($e, 'Data')) ? $e->Data : "";
                $help_url = (property_exists($e, 'Help_URL')) ? $e->Help_URL : "";
                $request_status = "Fail";
            }

            // All other error codes will set the harvestlog and exit
            // IF success, data will be in $response
            $new_status = $request_status;
            $new_attempts = $harvest->attempts;

            // Check for "queued" state response
            if ($error_code == 1011 || $error_code == 1020) {
                $new_status = 'Pending';
                $this->line($ts . " QueueHarvester: harvest ID: " . $harvest->id . ' set Pending, will be retried');
            // If no data (3030) record a single failedHarvest record, and continue
            } else if ($error_code == 3030 || $error_code == 9030) {
                // Get error data from CC+ errors table
                $this->line($ts . " QueueHarvester: No data in Report Items for harvest ID: " . $harvest->id);
                $error = CcplusError::where('id',$error_code)->first();

                // Clear all existing failed records
                $deleted = DB::table($failedharvests[$cid])->where('harvest_id', $harvest->id)->delete();

                // Add a single failed record to record the "no records received" exception
                $result = DB::table($failedharvests[$cid])
                            ->insert(['harvest_id' => $harvest->id, 'process_step' => 'API',
                                        'error_id' => $error_code, 'detail' => $message . ', ' . $detail,
                                        'help_url' => $help_url, 'created_at' => $ts]);

                // Update attempts, record error_id and set Success
                $new_attempts++;
                $new_status = "Success";
            }

            // Save raw data
            if (method_exists($response,'getJsonString') && $new_status!='Pending' &&
                !in_array($error_code, [3030,9030,9200,9300,9400])) {
                $data = (method_exists($response,'getJsonString')) ? $response->getJsonString() : $response->getHttpResponse();
                if (File::put($raw_datafile, Crypt::encrypt(bzcompress($data, 9), false)) === false) {
                    echo "Failed to save raw data in: " . $raw_datafile;
                    $raw_datafile = "";
                }
            }

            // If request failed, set a FailedHarvest record and update the harvest record
            if ($new_status == "Fail") {
                $error_msg = '';
                // Turn severity string into an ID
                $severity_id = $all_severities->where('name', 'LIKE', $severity . '%')->pluck('id');
                if ($severity_id === null) {  // if not found, set to 'Error' and prepend it to the message
                    $severity_id = $all_severities->where('name', 'Error')->pluck('id');
                    $error_msg .= $severity . " : ";
                }

                // Clean up the message in case this is a new code for the errors table
                $error_msg .= substr(preg_replace('/(.*)(https?:\/\/.*)$/', '$1', $message), 0, 60);

                // Get/Create entry from the CC+ errors table
                if ($error_code == 0) {  // 0 is reserved for "No Error", reset to "unknown" code:9400
                    $error_code = 9400;
                }
                $error = CcplusError::firstOrCreate(
                        ['id' => $error_code],
                        ['id' => $error_code, 'message' => $error_msg, 'severity' => $severity_id, 'new_status' => 'Fail']
                );
                $detail .= " (URL: " . $request_uri . ")";
                $result = DB::table($failedharvests[$cid])
                            ->insert(['harvest_id' => $harvest->id, 'process_step' => 'Request', 'error_id' => $error->id,
                                      'detail' => $detail, 'help_url' => $help_url, 'created_at' => $ts]);

                // Set new status for the harvest update
                $new_status = 'ReQueued'; // ReQueue by default
                $keep_statuses = array('NoRetries','Waiting','ReQueued','Pending');
                if (!in_array($error->new_status, $keep_statuses)) {
                    $new_status = $error->new_status;
                }

                // Increment harvest attempts; if we're out of retries keep error code and set status only
                $new_attempts++;
                $max_retries = intval(config('ccplus.max_harvest_retries'));
                if ($new_attempts >= $max_retries) {
                    $new_status = 'NoRetries';
                }
                // Alert::insert(['yearmon' => $yearmon, 'prov_id' => $credential->prov_id,
                //                'harvest_id' => $job->harvest->id, 'status' => 'Active', 'created_at' => $ts]);

                // If there's an error code, clean up raw data file and or database pointer to it.
                // 9200, 9300 and 9400 errors clear the rawfile field for the harvest. Nothing was saved/kept
                if (in_array($error_code,[9200,9300,9400])) $rawfile = null;

                // Set target path; create folder if not there
                $savePath = $report_path . '/' . $credential->inst_id . '/' . $credential->prov_id;
                if ($credential->inst_id>0 && $credential->prov_id>0 && !is_dir($savePath)) {
                    mkdir($savePath, 0755, true);
                }
                if (is_dir($savePath)) {
                    // If the harvest has a rawfile value set and this attempt returned invalid/no JSON,
                    // clear out the saved data file, if possible.
                    if (in_array($error->id,[9200,9300,9400]) && !is_null($harvest->rawfile)) {
                        $oldFile = $savePath . '/' . $harvest->rawfile;
                        try {
                            unlink($oldFile);
                        } catch (\Exception $e2) { }
                    }
                    // If a rawfile exists from this attempt, try to move JSON to the processed folder.
                    if ($raw_datafile != "") {
                        $newName = $savePath . '/' . $rawfile;
                        try {
                            rename($raw_datafile, $newName);
                        } catch (\Exception $e) { // rename failed. Try to cleanup the unprocessed folder (silently)
                            try {
                                unlink($raw_datafile);
                            } catch (\Exception $e2) { }
                            $rawfile = null;
                        }
                    }
                }
                DB::table($harvests[$cid])->where('id', $harvest->id)
                  ->update(['status'=>$new_status, 'error_id'=>$error->id, 'rawfile'=>$rawfile]);
                $job->delete();

                $this->line($ts . " QueueHarvester: COUNTER API Exception (" . $error->id . ") : " .
                                    " (Harvest: " . $harvest->id . ") " . $message . ", " . $detail);
            }

            // Request returned Success - No Exceptions (could include no-records)
            if ($new_status == "Success" || $new_status == "Pending") {
                // Print out any non-fatal message from request
                if ($new_status == "Pending") {
                    $rawfile = null;
                } else if ($message != "") {
                    $this->line($ts . " QueueHarvester: Non-Fatal COUNTER API Exception (" . $harvest->id . "): (" .
                                        $error_code . ") : " . $message . ', ' . $detail);
                    $error = CcplusError::where('id',$error_code)->first();
                }

                // Skip if error_code holds Pending or too-many requests (will be re-tried)
                if (!in_array($error_code,[1011,1020])) {
                    if ( !in_array($error_code,[3030,9030]) ) $error_code = 0;
                    // Track last successful (last_harvest_id) and most-current harvest (last_harvest) for this credential
                    $c_args = array('last_harvest_id' => $harvest->id);
                    if ($yearmon > $credential->last_harvest) {
                        $c_args['last_harvest'] = $yearmon;
                    }
                    DB::table($creds[$cid])->where('id', $harvest->credentials_id)->update($c_args);

                    $new_attempts++;
                    if ( in_array($error_code,[3030,9030]) ) {
                        $rawfile = null;
                    } else {
                        $this->line($ts . " QueueHarvester: " . $credential->prov_name . " : " . $yearmon . " : " .
                                            $report->name . " saved for " . $credential->inst_name);
                        $new_status = "Waiting";
                    }

                    // Successfully harvested - clear out any existing "failed" records
                    $deleted = DB::table($failedharvests[$cid])->where('harvest_id', $harvest->id)->delete();

                }
                // Update the harvest 
                DB::table($harvests[$cid])->where('id', $harvest->id)
                  ->update(['status' => $new_status, 'error_id' => $error_code, 'attempts' => $new_attempts,
                            'rawfile' => $rawfile]);

                // Remove the job record unless the harvest is Pending
                if ($new_status != "Pending") {
                    $job->delete();
                }
            }

            // Sleep 2 seconds *before* saving the harvest record (keeping it technically "Active"),
            // to avoid having the provider block too-rapid requesting.
            sleep(2);

        }   // foreach job
        return 1;
    }
}