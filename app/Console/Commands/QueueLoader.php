<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;
use App\Models\Consortium;
use App\Models\Connection;
use App\Models\Credential;
use App\Models\HarvestLog;
use App\Models\Report;
use App\Models\GlobalQueueJob;

class QueueLoader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:queueloader {consortium : Consortium ID or key-string}
                                               {--M|month= : YYYY-MM to override day_of_month [lastmonth]}
                                               {--P|provider= : Global Provider ID to process [ALL]}
                                               {--I|institution= : Institution ID to process [ALL]}
                                               {--R|report= : Master report NAME to harvest [ALL]}
                                               {--K|keep : Preserve, and ADD TO, existing data [FALSE]}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nightly CC-Plus Global Queue Loader';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * ----------------------------
     *   ccplus:queueloader is intended to be run primarily as a nightly job.
     *      The optional arguments exist to allow the script to be run from the artisan command-line
     *      to add harvests and jobs in a more customized way.
     *   Processing phase-1:
     *      The day-of-month harvest setting for all (active) providers of the given consortium are checked,
     *      and if today is the day, all harvests defined by the credentials are added to the HarvestLogs table.
     *      Providers or institutions set is_active=false are ignored.
     *   Processing phase-2:
     *      Any harvests just added in phase-1 are added to the globaldb:jobs queue along with any harvests
     *      that are in a "Retry" state.
     *
     * @return mixed
     */
    public function handle()
    {
       // Try to get the consortium as ID or Key
        $conarg = $this->argument('consortium');
        $consortium = Consortium::find($conarg);
        if (is_null($consortium)) {
            $consortium = Consortium::where('ccp_key', '=', $conarg)->first();
        }
        if (is_null($consortium)) {
            $this->line('Cannot locate Consortium: ' . $conarg);
            return 0;
        }

        // Bail out - SILENTLY - if consortium is not active 
        if ( $consortium->is_active != 1 ) return 0;

       // Aim the consodb connection at specified consortium's database and initialize the
       // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();

       // Handle input options
        $month  = is_null($this->option('month')) ? 'lastmonth' : $this->option('month');
        $prov_id = is_null($this->option('provider')) ? 0 : $this->option('provider');
        $inst_id = is_null($this->option('institution')) ? 0 : $this->option('institution');
        $rept = is_null($this->option('report')) ? 'ALL' : $this->option('report');
        $replace = ($this->option('keep')) ? false : true;

       // Set yearmon to last month (default) or input value
        if (strtolower($month) == 'lastmonth') {
            $override_dom = false;
            $yearmon = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
        } else {
            $override_dom = true;   // day_of_month for providers is ignored if --month given
            $yearmon = date("Y-m", strtotime($month));
        }

        // Get setting for default release_5.1 availability
        $first_yearmon_51 = config('ccplus.first_yearmon_51');

       // Get detail on (master) reports requested
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
        if (strtoupper($rept) == 'ALL') {
            $requested_reports = $master_reports->pluck('name')->toArray();
        } else {
            $requested_reports = $master_reports->where('name',$rept)->pluck('name')->toArray();
        }
        if (count($requested_reports) == 0) {
            $this->error("No matching reports found; only master reports allowed.");
            return 0;
        }

       // Get active provider data
        if ($prov_id == 0) {
            $global_connections = Connection::with('globalProv','globalProv.registries','reports')
                                            ->where('is_active',true)->get();
        } else {
            $global_connections = Connection::with('globalProv','globalProv.registries','reports')
                                            ->where('is_active',true)->where('global_id',$prov_id)->get();
        }

       // Get credentials for the consortium providers using their global_id 
        $global_ids = $global_connections->unique('global_id')->pluck('global_id')->toArray();
        $credentials = Credential::with('institution', 'provider')
                                 ->when($inst_id > 0, function ($qry) use ($inst_id) {
                                     return $qry->where('inst_id', $inst_id);
                                 })
                                 ->whereIn('prov_id',$global_ids)
                                 ->where('status', 'Enabled')
                                 ->get();

       // Part I : Load any new harvests (based on today's date) into the HarvestLog table
       // ------------------------------------------------------------------------------
        foreach ($credentials as $credential) {
           // Skip this credential if we're just processing a single inst and the IDs don't match
            if (!$credential->institution->is_active) {
                continue;
            }
           // Limit connections to the (global) prov_id in the credential
            $connections = $global_connections->where('global_id',$credential->prov_id);
            $conso_connection = $connections->where('inst_id',1)->first();
            $conso_reports = ($conso_connection) ? $conso_connection->reports->pluck('id')->toArray() : [];
            $doneInsts = array();
           // Loop through connections for the global provider
            foreach ($connections as $cnx) {

                // Inst may be in more than one connection.. if processed already, skip this connection
                if (in_array($credential->inst_id, $doneInsts)) {
                    continue;
                }
                // If not overriding day-of-month, and today is not the day, skip the connection
                if (!$override_dom && $cnx->globalProv->day_of_month != date('j')) {
                    continue;
                }
               // if credential inst_id not related to this connection, skip it
                if (!in_array($credential->inst_id,$cnx->institutionIds())) {
                    continue;
                }

               // De-dupe connection reports against $conso_reports and skip this connection if no unique reports
                $cnx_report_ids = $cnx->reports->pluck('id')->toArray();
                if ($cnx->inst_id>1 && $conso_connection) {
                    $cnx_report_ids = array_intersect( $cnx_report_ids, array_diff($cnx_report_ids, $conso_reports) );
                }
                if (count($cnx_report_ids) == 0) continue;
                $reports = $master_reports->whereIn('id',$cnx_report_ids)->whereIn('name',$requested_reports);
                $source = ($cnx->inst_id == 1) ? "C" : "I";

                // Figure out which COUNTER release to pull
                $available_releases = $cnx->globalProv->registries->sortByDesc('release')->pluck('release')->toArray();

                // To override default release, there need to be: multiple releases, "5.1" needs to be
                // one of the choices, and the GlobalSetting (first_yearmon_51) needs to be non-null
                $or_release = null;
                $idx51 = array_search("5.1", $available_releases);
                if (!is_null($first_yearmon_51) && count($available_releases) > 1 && $idx51) {
                    // requested yearmon before 5.1 default begin date
                    if ($yearmon < $first_yearmon_51) {
                        $or_release = (isset($available_releases[$idx51+1])) ? $available_releases[$idx51+1] : null;
                    // requested yearmon on/after 5.1 default begin date
                    } else {
                        $or_release = "5.1";
                    }
                }

                // Use connection's default release override if or_release still not set
                $release = (!is_null($or_release)) ? $or_release : $cnx->default_release();

                // Loop through all the reports
                foreach ($reports as $report) {
                    $ts = date("Y-m-d H:i:s");
                   // Create new HarvestLog record; catch and prevent duplicates
                    try {
                        HarvestLog::insert(['status' => 'New', 'credentials_id' => $credential->id, 'release' => $release,
                                           'report_id' => $report->id, 'yearmon' => $yearmon, 'source' => $source,
                                           'attempts' => 0, 'created_at' => $ts]);
                    } catch (QueryException $e) {
                        $errorCode = $e->errorInfo[1];
                        if ($errorCode == '1062') {
                            $harvest = HarvestLog::where([['credentials_id', $credential->id],
                                                          ['report_id', $report->id],
                                                          ['yearmon', $yearmon]
                                                         ])->first();
                            if ($harvest->status == 'New') { // if existing harvest is "New", don't modify status
                                continue;                    // since Part II will requeue it anyway
                            }
                            $this->line('Harvest ' . '(ID:' . $harvest->id . ') already defined. Updating to retry (' .
                                        'credential: ' . $credential->id . ', ' . $report->name . ':' . $yearmon . ').');
                            $harvest->status = 'ReQueued';
                            $harvest->save();
                        } else {
                            $this->line('Failed adding to HarvestLog! Error code:' . $errorCode);
                            return 0;
                        }
                    }
                } // for each report
                // Add inst_id to the doneInsts array
                if (!in_array($credential->inst_id)) $doneInsts[] = $credential->inst_id;
            } // for each connection
        } // for each credential

       // Part II : Create queue jobs based on HarvestLogs
       // -----------------------------------------------
        $harvests = HarvestLog::where('status','New')->orWhere('status','ReQueued')->get();
        foreach ($harvests as $harvest) {
            $ts = date("Y-m-d H:i:s");
            try {
                GlobalQueueJob::insert(['consortium_id' => $consortium->id,
                                       'harvest_id' => $harvest->id,
                                       'replace_data' => $replace,
                                       'created_at' => $ts]);
            } catch (QueryException $e) {
                $errorCode = $e->errorInfo[1];
                if ($errorCode == '1062') {
                    $this->line('Harvest ID: ' . $harvest->id . ' for consortium ID: ' . $consortium->id .
                                ' already exists in the queue; not adding.');
                    continue;
                } else {
                    $this->line('Failed adding harvestID: ' . $harvest->id . ' to Queue! Error code:' . $errorCode);
                    continue;
                }
            }
            $harvest->status = 'Queued';
            $harvest->save();
        }
        return 1;
    }
}
