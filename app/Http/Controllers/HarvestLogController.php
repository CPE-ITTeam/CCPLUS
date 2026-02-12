<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use App\Models\HarvestLog;
use App\Models\Consortium;
use App\Models\FailedHarvest;
use App\Models\Report;
use App\Models\Connection;
use App\Models\GlobalProvider;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Credential;
use App\Models\CounterApi;
use App\Models\GlobalQueueJob;
use App\Models\ConnectionField;
use App\Models\CcplusError;
use App\Services\HarvestService;
use App\Models\ReportField;
use Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class HarvestLogController extends Controller
{
   private $all_error_codes;
   private $connection_fields;
   protected $harvestService;

   public function __construct(HarvestService $harvestService)
   {
       // Load all known error codes
       try {
           $this->all_error_codes = CcplusError::pluck('id')->toArray();
       } catch (\Exception $e) {
           $this->all_error_codes = collect();
       }
       // Load all connection fields
       try {
           $this->connection_fields = ConnectionField::get();
       } catch (\Exception $e) {
           $this->connection_fields = collect();
       }
       $this->harvestService = $harvestService;
   }

   /**
    * Display a listing of the resource.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
   public function index(Request $request)
   {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);

        $filter_options = array();

        // Setup limit arrays for the instID's we'll pull credentials for
        $limit_to_insts = ($thisUser->isConsoAdmin()) ? array() : $thisUser->adminInsts();
        $filter_options['institutions'] = Institution::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                            return $qry->where('inst_id',1)->orWhereIn('inst_id', $limit_to_insts);
                                        })->get(['id','name'])->toArray();

        // Get all global IDs and names
        $filter_options['platforms'] = GlobalProvider::get(['id','name'])->toArray();

        // Get credentials limited by-inst
        $credential_ids = Credential::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                        return $qry->whereIn('inst_id', $limit_to_insts);
                                    })->pluck('id')->toArray();

        // Get the harvest rows based on credentials
        $harvest_data = HarvestLog::
            with('report:id,name','credential','credential.institution:id,name','credential.provider',
                 'lastError','failedHarvests','failedHarvests.ccplusError')
            ->whereIn('credentials_id', $credential_ids)
            ->orderBy('updated_at', 'DESC')
            ->get();

        // Make arrays for updating the filter options in the U/I
        // Format records for display , limit to 500 output records
        $count = 0;
        $truncated = false;
        $max_records = 500;
        $harvests = array();
        foreach ($harvest_data as $key => $harvest) {
            $formatted_harvest = $this->formatRecord($harvest);
            // bump counter and add the record to the output array
            $count += 1;
            if ($count > $max_records) {
                $truncated = true;
                break;
            }
            $harvests[] = $formatted_harvest;
        }

        // Query for min and max yearmon values
        $bounds = $this->harvestBounds();

        // Setup the rest of the filtering options
        $filter_options['statuses'] = array('Harvest Queue', 'Harvesting', 'Queued by Vendor', 'Paused', 'ReQueued',
                                            'Process Queue', 'Processing');
        $filter_options['reports'] = Report::where('parent_id',0)->orderBy('dorder','ASC')
                                           ->get(['id','name'])->toArray();
        $filter_options['codes'] = $harvest_data->where('error_id','>',0)->unique('error_id')->sortBy('error_id')
                                                ->pluck('error_id')->toArray();
        array_unshift($filter_options['codes'], 'No Error');

        return response()->json(['records' => $harvests, 'options' => $filter_options, ], 200);
   }

    /**
     * Return options for Manual Harvesting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON (array of options)
     */
    public function create(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();

        // Limit institutions by user role(s)
        $limit_by_inst = ($consoAdmin) ? array() : $thisUser->viewerInsts();

        // Pull globalProvider IDs based on the connections defined for institutions
        // in $limit_by_inst ; everyone gets consortium-wide connections (where inst_id=1)
        $global_ids = Connection::when(count($limit_by_inst) > 0, function ($qry) use ($limit_by_inst) {
                                    return $qry->where('inst_id',1)->orWhereIn('inst_id',$limit_by_inst);
                                })->select('global_id')->distinct()->pluck('global_id')->toArray();

        // Get allowed/visible institutions
        $institutions = Institution::when(count($limit_by_inst)>0, function ($qry) use ($limit_by_inst) {
                                       return $qry->whereIn('id', $limit_by_inst);
                                   })->get(['id','name'])->toArray();

        // Admins see groups they admin - that have members
        $groups = array();
        $group_ids = $thisUser->adminGroups();
        if (count($group_ids) > 0) {
            $data = InstitutionGroup::whereIn('id',$group_ids)->with('institutions:id,name')->orderBy('name', 'ASC')->get();
            foreach ($data as $group) {
                if ( $group->institutions->count() > 0 ) {
                    $groups[] = array('id' => $group->id, 'name' => $group->name, 'institutions' => $group->institutions);
                }
            }
        }

        // Build platform list of globals connected to insts in limit_by_inst 
        $globals = GlobalProvider::with('connections','connections.reports')->whereIn('id',$global_ids)
                                 ->orderBy('name','ASC')->get(['id','name']);
        $platforms = array();
        foreach ($globals as $global) {
            $global_inst_ids = $global->connectedInstitutions();
            if (count($limit_by_inst) == 0 || count(array_intersect($global_inst_ids, $limit_by_inst)) > 0) {
                $global->reports = $global->enabledReports();
                $global->institutions = $global_inst_ids;
                $platforms[] = $global;
            }
        }

        // Report creator component wants reports ordered by ID (not dorder)
        $all_reports = Report::with('children')->orderBy('id', 'asc')->get();
        $master_reports = $all_reports->where('parent_id',0)->select(['id','legend','name'])->toArray();

        // Get report fields and tack on filter column for those that have one
        $field_data = ReportField::orderBy('id', 'asc')->with('reportFilter')->get();
        $fields = array();
        foreach ($field_data as $rec) {
            $column = ($rec->reportFilter) ? $rec->reportFilter->report_column : null;
            $fields[] = ['id' => $rec->id, 'qry' => $rec->qry_as, 'report_id' => $rec->report_id, 'column' => $column];
        }

        // set FiscalYr for the user, default to Jan if missing
        $fy_month = 1;
        $userFY = $thisUser->getFY();
        if ( !is_null($userFY) ) {
            $date = date_parse($userFY);
            $fy_month = $date['month'];
        }

        $data = array('institutions' => $institutions, 'groups' => $groups, 'platforms' => $platforms,
                      'all_reports' => $all_reports, 'master_reports' => $master_reports, 'fields' => $fields,
                      'fyMo' => $fy_month);
        return response()->json(['records' => $data], 200);
    }

   /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        $this->validate($request,
            ['plat' => 'required', 'reports' => 'required', 'fromYM' => 'required', 'toYM' => 'required', 'when' => 'required']
        );
        $input = $request->all();
        if (!isset($input["inst"]) || !isset($input["inst_group_id"])) {
            return response()->json(['result' => false, 'msg' => 'Error: Missing input arguments!']);
        }
        if (sizeof($input["inst"]) == 0 && $input["inst_group_id"] <= 0) {
            return response()->json(['result' => false, 'msg' => 'Error: Institution/Group invalid in request']);
        }
        $input_release = (isset($input["release"])) ? $input["release"] : "";
        $user_inst =$thisUser->inst_id;
        $firstYM = config('ccplus.first_yearmon_51');

        // Set flag for "skip previously harvested data"
        $skip_harvested = false;
        if (isset($input["skip_harvested"])) {
            $skip_harvested = $input["skip_harvested"];
        }

        // Admins can harvest multiple insts
        $inst_ids = $thisUser->adminInsts();

        // Get detail on (master) reports requested
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Get Global Platforms
        if (in_array(0,$input["plat"])) {    // Get all consortium-enabled global platforms?
            $global_ids = Connection::where('is_active',true)->where('inst_id',1)->pluck('global_id')->toArray();
        } else {
            $plat_ids = $input["plat"];
            $global_ids = array();
            // plat_ids with  -1  means ALL, set global_ids based whose asking
            if (in_array(-1, $plat_ids) || count($plat_ids) == 0) {
                $global_ids = Connection::where('is_active',true)
                                        ->when( !$consoAdmin,  function ($qry) use ($inst_ids) {
                                            $qry->where('inst_id',1)->orWhereIn('instid',$inst_ids);
                                        })->select('global_id')->distinct()->pluck('global_id')->toArray();
            } else {
                $global_ids = Connection::where('is_active',true)->whereIn('global_id',$plat_ids)
                                        ->when( !$consoAdmin,  function ($qry) use ($inst_ids) {
                                            $qry->where('inst_id',1)->orWhereIn('instid',$inst_ids);
                                        })->select('global_id')->distinct()->pluck('global_id')->toArray();
            }
        }
        $global_platforms = GlobalProvider::with('credentials','credentials.institution:id,is_active','connections',
                                                 'connections.reports','registries')
                                          ->where('is_active',true)->whereIn('id',$global_ids)->get();

        // Set the status for the harvests we're creating based on "when"
        $state = "New";
        if ($input["when"] == 'now') {
            $state = "Queued";
            $con = Consortium::where('ccp_key', '=', session('ccp_key'))->first();
            if (!$con) {
                return response()->json(['result' => false, 'msg' => 'Cannot create jobs with current consortium setting.']);
            }
        }

        // Check From/To - truncate to current month if in future and ensure from <= to ,
        // then turn them into an array of yearmon strings
        $this_month = date("Y-m", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
        $to = ($input["toYM"] > $this_month) ? $this_month : $input["toYM"];
        $from = ($input["fromYM"] > $to) ? $to : $input["fromYM"];
        $year_mons = self::createYMarray($from, $to);

        // Loop for all months requested
        $num_queued = 0;
        $created_ids = [];
        $updated_ids = [];
        foreach ($year_mons as $yearmon) {
            // Loop for all global providers
            foreach ($global_platforms as $global_platform) {

               
                // Set the COUNTER release to be harvested 
                $release = $global_platform->default_release();
                if ($input_release == 'System Default' && !is_null($firstYM)) {
                    $available_releases = $global_platform->registries->sortByDesc('release')->pluck('release')->toArray();
                    if ($available_releases > 1 && in_array("5.1",$available_releases)) {
                        $idx51 = array_search("5.1", $available_releases);
                        // requested yearmon before 5.1 default begin date
                        if ($yearmon < $firstYM) {
                            if (isset($available_releases[$idx51+1])) {
                                $release = $available_releases[$idx51+1];
                            }
                        // requested yearmon on/after 5.1 default begin date
                        } else {
                            $release = "5.1";
                        }
                    }
                }
                // Set an array with the report_ids enabled consortium-wide
                $consoCnx = $global_platform->connections->where('inst_id',1)->first();
                $conso_reports = ($consoCnx) ? $consoCnx->reports->pluck('id')->toArray() : [];

                // Loop through all credentials
                foreach ($global_platform->credentials as $cred) {
                    // If institution is inactive or this inst_id is not in the $inst_ids array, skip it
                    if ($cred->status != "Enabled" || !$cred->institution->is_active ||
                       (!$consoAdmin && !in_array($cred->inst_id,$inst_ids))) {
                        continue;
                    }

                    // Set reports to process based on consortium-wide and, if defined, institution-specific credential_ids
                    $report_ids = array();
                    foreach ($global_platform->connections as $cnx) {
                         // skip credentials for other insts
                         if ($cnx->inst_id != 1 && $cnx->inst_id != $cred->inst_id) {
                             continue;
                         }
                        $_ids = $cnx->reports->pluck('id')->toArray();
                        $report_ids = array_unique(array_merge($report_ids,$_ids));
                    }

                    // Add a "source" value to $reports
                    $report_data = $master_reports->whereIn('id',$report_ids);
                    $reports = $report_data->map(function ($rec) use ($conso_reports) {
                        $rec->source = (in_array($rec->id,$conso_reports)) ? "C" : "I";
                        return $rec;
                    });

                    // Loop through all reports
                    foreach ($reports as $report) {
                        // if this report isn't in $inputs['reports'], skip it
                        if (!in_array($report->name, $input['reports'])) {
                            continue;
                        }

                        // Get the harvest record, if it exists
                        $harvest = HarvestLog::where([['credentials_id', '=', $cred->id],
                                                      ['report_id', '=', $report->id],
                                                      ['yearmon', '=', $yearmon]
                                                     ])->first();
                        // Harvest exists
                        if ($harvest) {
                            if ($skip_harvested) {
                              continue;
                            }
                            // We're not skipping... reset the harvest
                            $harvest->release = $release;
                            $harvest->attempts = 0;
                            $harvest->status = $state;
                            $harvest->save();
                            $updated_ids[] = $harvest->id;
                        // Insert new HarvestLog record
                        } else {
                            $harvest = HarvestLog::create(['status' => $state, 'credentials_id' => $cred->id,
                                                 'release' => $release, 'report_id' => $report->id, 'yearmon' => $yearmon,
                                                 'source' => $report->source, 'attempts' => 0]);
                            $created_ids[] = $harvest->id;
                        }

                        // If user wants it added now create the queue entry - set replace_data to overwrite
                        if ($input["when"] == 'now') {
                            try {
                                $newjob = GlobalQueueJob::create(['consortium_id' => $con->id,
                                                                 'harvest_id' => $harvest->id,
                                                                 'replace_data' => 1
                                                               ]);
                                $num_queued++;
                            } catch (QueryException $e) {
                                $code = $e->errorInfo[1];
                                if ($code == '1062') {     // If already in queue, continue silently
                                    continue;
                                } else {
                                    $msg = 'Failure adding Harvest ID: ' . $harvest->id . ' to Queue! Error ' . $code;
                                    return response()->json(['result' => false, 'msg' => $msg]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Setup full details for new harvest entries
        $new = array();
        if (count($created_ids) > 0) {
            $new_data = HarvestLog::whereIn('id', $created_ids)->with('report:id,name','credential',
                                'credential.institution:id,name','credential.provider:id,name',
                                'lastError','failedHarvests','failedHarvests.ccplusError')->get();
            foreach ($new_data as $rec) {
                $new[] = $this->formatRecord($rec);
            }
        }
        // New harvests means "bounds" changed
        $bounds = (count($created_ids) > 0) ? $this->harvestBounds() : array();

        // Setup full details for updated harvest entries
        $updated = array();
        if (count($updated_ids) > 0) {
            $upd_data = HarvestLog::whereIn('id', $updated_ids)->with('report:id,name','credential',
                                'credential.institution:id,name','credential.provider:id,name',
                                'lastError','failedHarvests','failedHarvests.ccplusError')->get();
            foreach ($upd_data as $rec) {
                $updated[] = $this->formatRecord($rec);
            }
        }

        // Send back confirmation with counts of what happened
        $msg  = "Success : " . count($created_ids) . " new harvests added, " . count($updated_ids) . " harvests updated";
        $msg .= ($num_queued > 0) ? ", and " . $num_queued . " queue jobs created." : ".";
        return response()->json(['result'=>true, 'msg'=>$msg, 'new_harvests'=>$new, 'upd_harvests'=>$updated,
                                 'bounds'=>$bounds]);
    }

    /**
     * Bulk operations from the U/I.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function bulk(Request $request)
    {
        global $thisUser;
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }
        $consoAdmin = $thisUser->isConsoAdmin();

        // Validate form inputs
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();

        // Setup institution limits and start setting up filter options
        $filter_options = array();
        $limit_to_insts = ($consoAdmin) ? array() : $thisUser->adminInsts();
        $filter_options['institutions'] = Institution::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                            return $qry->where('inst_id',1)->orWhereIn('inst_id', $limit_to_insts);
                                        })->get(['id','name'])->toArray();
        $filter_options['platforms'] = GlobalProvider::get(['id','name'])->toArray();

        // Get credentials limited by-inst; non-conso-admins are limited to the records they can affect
        $credential_ids = Credential::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                        return $qry->whereIn('inst_id', $limit_to_insts);
                                    })->pluck('id')->toArray();

        // Get the harvests we'll be updating, limited by credential IDs
        $harvests = HarvestLog::with('report:id,name','credential','credential.institution:id,name',
                                        'credential.provider','credential.provider.registries')
                              ->whereIn('id', $input['ids'])->whereIn('credentials_id', $credential_ids)            
                              ->orderBy('updated_at', 'DESC')->get();
        $harvestIds = $harvests->pluck('id')->toArray();

        // Get consortium_id for updating the global jobs queue
        $con = Consortium::where('ccp_key', session('ccp_key'))->first();
        if (!$con) {
            return response()->json(['result' => false, 'msg' => 'Error: Corrupt session or consortium settings']);
        }

        // Handle status changes
        if ($input['action']=='Pause') {

            // Pause updates only status and 'updated_at'; return full row to replace item(s)
            $affectedItems = array();
            foreach ($harvests as $harvest) {
                $harvest->status = 'Paused';
                $harvest->updated_at = now();
                $harvest->save();
                $affectedItems[] = $this->formatRecord($harvest);
            }
            return response()->json(['result' => true, 'msg' => '', 'affectedItems' => $affectedItems], 200);

        } else if ($input['action']=='Restart' || $input['action']=='Restart as r5' || $input['action']=='Restart as r5.1') {

            // Check status input for a specific COUNTER release for restarting
            $forceRelease = (substr($input['action'],0,10) == 'Restart as') ? substr($input['action'],12) : null;

            // Get and process the harvest(s)
            $changed = 0;
            $skipped = array();
            $affectedItems = array();
            foreach ($harvests as $harvest) {
                // keep track of original status
                $original_status = $harvest->status;

                // Disallow ReStart if credentials are not Enabled, or provider/institution are inactive
                if ( $harvest->credential->status != 'Enabled' || !$harvest->credential->institution->is_active ||
                     !$harvest->credential->provider->is_active ) {
                    $skipped[] = $harvest->id;
                    continue;
                }

                // Confirm that a "forcedRelease" is available for this harvests' provider
                if (!is_null($forceRelease)) {
                    $registry = $harvest->credential->provider->registries->where('release',$forceRelease)->first();
                    if (!$registry) {
                        $skipped[] = $harvest->id;
                        continue;
                    }
                    // Update the release value in the harvest record now so that the new job processes it right
                    if ($forceRelease != trim($harvest->release)) {
                        $harvest->release = $forceRelease;
                    }
                }

                // Restart sets status to 'Queued, resets attempts and creates a GlobalQueueJob if one does not exist
                $_job = GlobalQueueJob::where('consortium_id',$con->id)->where('harvest_id',$harvest->id)->first();
                if (!$_job) {
                    try {
                        $newjob = GlobalQueueJob::create(['consortium_id' => $con->id,
                                                          'harvest_id' => $harvest->id,
                                                          'replace_data' => 1
                                                        ]);
                    } catch (\Exception $e) {
                        return response()->json(['result' => false, 'msg' => 'Error creating job entry in global table!']);
                    }
                }
                $harvest->attempts = 0;
                $harvest->status = 'Queued';

                // Update the harvest record and return
                $harvest->updated_at = now();
                $harvest->save();
                $affectedItems[] = $this->formatRecord($harvest);
                $changed++;
            }

            // Return result
            if ($changed > 0) {
                $msg  = "Successfully restarted " . $msg_result . " " . $changed . " harvests";
                $msg .= (!is_null($forceRelease)) ? " as release ".$forceRelease : "";
                if (count($skipped) > 0) {
                    $msg .= " , and skipped " . count($skipped) . " harvests";
                    $msg .= (!is_null($forceRelease)) ? " (release ".$forceRelease." may not be available)." : ".";
                }
                return response()->json(['result' => true, 'msg' => $msg, 'affectedItems' => $affectedItems], 200);
            } else {
                $msg = "No selected harvests modified";
                $msg .= (!is_null($forceRelease)) ? ", release ".$forceRelease." may not be available" : "";
                return response()->json(['result' => true, 'msg' => $msg, 'affectedItems' => array()], 200);
            }

        // Handle delete (assumes U/I already confirmed... make it happen)
        } else if ($input['action'] == 'Delete' || $input['action'] == 'Kill') {

            // Delete any related jobs from the global queue before deleting the harvest records
            $result = GlobalQueueJob::where('consortium_id',$con->id)->whereIn('harvest_id', $harvestIds)->delete();

            // Delete stored data for the harvest(s)
            $related_credential_ids = $this->deleteStoredData($harvestIds);

            // Delete the harvest record(s)
            $result = HarvestLog::whereIn('id', $harvestIds)->delete();

            // Update last_harvest setting for affected credentials based on what's left
            if ( count($related_credential_ids) > 0) {
                $this->resetLastHarvest($related_credential_ids);
            }
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $harvestIds], 200);

        // Unrecognized action
        } else {
            return response()->json(['result' => false, 'msg' => 'Unrecognized bulk action requested'], 200);
        }

    }

   /**
    * Download raw data for a harvest
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function downloadRaw($id)
   {
       $thisUser = auth()->user();
       $harvest = HarvestLog::findOrFail($id);
       if (!$harvest->canManage()) {
           return response()->json(['result' => false, 'msg' => 'Error - Not authorized']);
       }

       // Get consortium_id
       $con = Consortium::where('ccp_key', session('ccp_key'))->first();
       if (!$con) {
          return response()->json(['result'=>false, 'msg'=>'Error: Current consortium is undefined.']);
       }

       if (!is_null(config('ccplus.reports_path'))) {
           // Set the path and filename based on config and harvest credentials
           $return_name = "";
           $filename  = config('ccplus.reports_path') . $con->id . '/';
           if ($harvest->status == 'Waiting') {
               $searchPat = $filename . "0_unprocessed/" . $harvest->id . "_*";
               $matches = glob($searchPat);
               $filename = (count($matches) > 0) ? $matches[0] : "/_xyzzy_/not-found";
               $return_name = substr($filename, strrpos($filename,'/',0)+1);
           } else {
               $filename .= $harvest->credential->inst_id . '/' . $harvest->credential->prov_id . '/';
               $filename .= (is_null($harvest->rawfile)) ? 'not-found' : $harvest->rawfile;
               $return_name = $harvest->rawfile;
           }

           // Confirm the file exists and is readable before trying to decrypt and return it
           if (is_readable($filename)) {
               return response()->streamDownload(function () use ($filename) {
                   echo bzdecompress(Crypt::decrypt(File::get($filename), false));
               }, $return_name);
           } else {
               $msg = 'Raw datafile is not accessible.';
           }
       } else {
           $msg = 'System not configured to save raw data, check config value of CCP_REPORTS.';
       }
       return response()->json(['result' => false, 'msg' => $msg]);
   }

   /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function destroy($id)
   {
       $record = HarvestLog::findOrFail($id);
       abort_unless($record->canManage(), 403);
       if (!$record->canManage()) {
           return response()->json(['result' => false, 'msg' => 'Not authorized!']);
       }

       // Delete any related jobs from the global queue
       $jobs = GlobalQueueJob::where('harvest_id', $id)->get();
       foreach ($jobs as $job) {
           $job->delete();
       }

       // Delete stored data for the harvest
       $related_credential_ids = $this->deleteStoredData( [$id] );

       // Update last_harvest setting for affected credentials based on what's left
       if ( count($related_credential_ids) > 0) {
           $this->resetLastHarvest($related_credential_ids);
       }

       // Delete the harvestlog record itself
       $record->delete();
       return response()->json(['result' => true, 'msg' => 'Log record deleted successfully']);
   }

    /**
     * Entry point for returning Harvest Queue records for the current consortium_id
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JSON
     */
   public function harvestQueue(Request $request)
   {
       $thisUser = auth()->user();
       abort_unless($thisUser->isAdmin(), 403);
       $consoAdmin = $thisUser->isConsoAdmin();

       // Translator (for more verbose statuses) and Log filter (to limit results) 
       $xlStatus = array('Queued' => 'Harvest Queue', 'Harvesting' => '*Harvesting', 'Pending' => 'Queued by Vendor',
                         'Paused' => 'Paused', 'ReQueued' => 'ReQueued', 'Waiting' => 'Process Queue',
                         'Processing' => '*Processing');

       // Setup limit_to_insts with the instID's we'll pull settings for
       $limit_to_insts = ($consoAdmin) ? array() : $thisUser->adminInsts();

       // Get all harvests joined with credentials that match the statuses
       $data = HarvestLog::with('credential','credential.provider','credential.institution:id,name','report',
                                'lastError','failedHarvests','failedHarvests.ccplusError')
                         ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                             return $qry->whereIn('credential.inst_id', $limit_to_insts);
                         })
                         ->whereIn('status',array_keys($xlStatus))
                         ->orderBy("updated_at", "DESC")->get();

       // Build an output array of no more than 500 harvests
       $output_count = 0;
       $truncated = false;
       $harvests = array();
       foreach ($data as $rec) {
          if (!$rec->credential || !$rec->report) continue;
          $rec->prov_id = $rec->credential->prov_id;
          $rec->inst_id = $rec->credential->inst_id;
          $rec->prov_name = $rec->credential->provider->name;
          $rec->inst_name = $rec->credential->institution->name;
          $rec->report_name = $rec->report->name;
          $rec->created = date("Y-m-d H:i", strtotime($rec->updated_at));
          $rec->d_status = $xlStatus[$rec->status];

          // Include last error details if they exist
          $_error = [];
          $lastFailed = null;
          if ($rec->failedHarvests) {
              $lastFailed = $rec->failedHarvests->sortByDesc('created_at')->first();
          }
          if ($rec->lastError) {
              $_error = $rec->lastError->toArray();
              $_error['detail'] = '';
              $_error['help_url'] = '';
              $_error['process_step'] = '';
          } else if ($lastFailed && $rec->error_id > 0) {
              $rec->error_id = $lastFailed->error_id;
              $_error = $lastFailed->ccplusError->toArray();
              $_error['detail'] = (is_null($lastFailed->detail)) ? '' : $lastFailed->detail;
              $_error['help_url'] = (is_null($lastFailed->help_url)) ? '' : $lastFailed->help_url;
              $_error['process_step'] = (is_null($lastFailed->process_step)) ? '' : $lastFailed->process_step;
          }
          $_error['known_error'] = in_array($rec['error_id'],$this->all_error_codes);
          $_error['counter_url'] = ($rec->release == '5.1')
              ? "https://cop5.countermetrics.org/en/5.1/appendices/d-handling-errors-and-exceptions.html"
              : "https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html";
          $rec->error = $_error;

          // Add a test+confirm URL
          $beg = $rec->yearmon . '-01';
          $end = $rec->yearmon . '-' . date('t', strtotime($beg));
          $capi = new CounterApi($beg, $end);
          // set url for manual retry/confirm icon using buildUri
          $rec->retryUrl = $capi->buildUri($rec->credential, 'reports', $rec->report, $rec->release);
          // add record to the outbound array
          $harvests[] = $rec->toArray();

          // Limit to 500 rows of output
          $output_count++;
          if ($output_count == 500) {
              $truncated = true;
              break;
          }
       }

       // Setup options for the U/I - it will limit options further based on what's there
       $filter_options = array();
       // Setup limit arrays for the instID's and provIDs we'll pull credentials for
       $filter_options['institutions'] = Institution::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                           return $qry->where('inst_id',1)->orWhereIn('inst_id', $limit_to_insts);
                                       })->get(['id','name'])->toArray();

       // Get all global IDs and names
       $filter_options['platforms'] = GlobalProvider::get(['id','name'])->toArray();
       $filter_options['reports'] = Report::where('parent_id',0)->orderBy('dorder','ASC')
                                          ->get(['id','name'])->toArray();
       $filter_options['codes'] = $data->where('error_id','>',0)->unique('error_id')->sortBy('error_id')
                                       ->pluck('error_id')->toArray();
       array_unshift($filter_options['codes'], 'No Error');
       $filter_options['statuses'] = array_values($xlStatus);

       return response()->json(['records' => $harvests, 'options' => $filter_options], 200);

   }

   // Turn a fromYM/toYM range into an array of yearmon strings
   private function createYMarray($from, $to)
   {
       $range = array();
       $start = strtotime($from);
       $end = strtotime($to);
       if ($start > $end) {
           return $range;
       }
       while ($start <= $end) {
           $range[] = date('Y-m', $start);
           $start = strtotime("+1 month", $start);
       }
       return $range;
   }

   // Return an array of bounding yearmon strings based on exsiting Harvestlogs
   //   bounds[0] will hold absolute min and max yearmon for all harvests across all reports
   private function harvestBounds() {

       // Query for min and max yearmon values
       $raw_query = "min(yearmon) as YM_min, max(yearmon) as YM_max";
       $result = HarvestLog::selectRaw($raw_query)->get()->toArray();
       $bounds[0] = $result[0];

       return $bounds;
   }

   // Build a consistent output record using an input harvest record
   // input should include Report, Credential with institution+provider and failedHarvests with ccplusError
   private function formatRecord($harvest) {
       $xlStatus = array('Queued' => 'Harvest Queue', 'Pending' => 'Queued by Vendor', 'Waiting' => 'Process Queue',
                         'BadCreds' => 'Bad Credentials', 'NoRetries' => 'Out of Retries');
       $rec = array('id' => $harvest->id, 'yearmon' => $harvest->yearmon, 'attempts' => $harvest->attempts,
                    'inst_name' => $harvest->credential->institution->name,
                    'prov_name' => $harvest->credential->provider->name,
                    'prov_inst_id' => $harvest->credential->provider->inst_id,
                    'release' => $harvest->release,
                    'report_name' => $harvest->report->name,
                    'status' => $harvest->status, 'rawfile' => $harvest->rawfile,
                    'error_id' => 0, 'error' => []
                   );
       $rec['updated'] = ($harvest->updated_at) ? date("Y-m-d H:i", strtotime($harvest->updated_at)) : " ";
       $rec['created'] = ($rec['updated'] != " ") ? date("Y-m-d H:i", strtotime($harvest->updated_at)) : " ";
       $rec['release'] = (is_null($harvest->release)) ? "" : $harvest->release;

       // Setup error details array (starting with default values)
       $rec['error'] = array('id' => $harvest->error_id, 'message' => '');
       $rec['error']['color'] = ($rec['status'] == 'Success') ? '#00DD00' : '#999999';
       $rec['d_status'] = (isset($xlStatus[$rec['status']])) ? $xlStatus[$rec['status']] : $rec['status'];
       $lastFailed = null;
       if ($harvest->failedHarvests) {
           $lastFailed = $harvest->failedHarvests->sortByDesc('created_at')->first();
       }
       if ($harvest->lastError) {
           $rec['error_id'] = $harvest->error_id;
           $rec['error'] = $harvest->lastError->toArray();
           $rec['error']['detail'] = '';
           $rec['error']['help_url'] = '';
           $rec['error']['process_step'] = '';
       } else if ($lastFailed && $harvest->error_id > 0) {
           $rec['error_id'] = $lastFailed->error_id;
           if ($lastFailed->ccplusError) {
               $rec['error'] = $lastFailed->ccplusError->toArray();
           }
           $rec['error']['detail'] = (is_null($lastFailed->detail)) ? '' : $lastFailed->detail;
           $rec['error']['help_url'] = (is_null($lastFailed->help_url)) ? '' : $lastFailed->help_url;
           $rec['error']['process_step'] = (is_null($lastFailed->process_step)) ? '' : $lastFailed->process_step;
       }
       $rec['error']['known_error'] = in_array($rec['error_id'],$this->all_error_codes);
       $rec['error']['noretries'] = ($harvest->status == 'NoRetries');
       $rec['failed'] = [];
       $rec['error']['counter_url'] = ($harvest->release == '5.1')
           ? "https://cop5.countermetrics.org/en/5.1/appendices/d-handling-errors-and-exceptions.html"
           : "https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html";

       // Build a URL to test+confirm the error(s); let CounterApi class do the work
       $beg = $harvest->yearmon . '-01';
       $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
       $capi = new CounterApi($beg, $end);
 
       // setup required connectors for buildUri
       $prov_connectors = $harvest->credential->provider->connectors();
       $connectors = $this->connection_fields->whereIn('id',$prov_connectors)->pluck('name')->toArray();
       $rec['retryUrl'] = $capi->buildUri($harvest->credential, 'reports', $harvest->report, $harvest->release);
       return $rec;
   }

   /**
    * delete stored data records for a given array of harvest ids
    *
    * @param  Array  $harvest_ids
    * @return Array  $reset_ids : array of affected credential IDs
    */
   private function deleteStoredData($harvest_ids)
   {
       $reset_ids = array();  // will hold credential Ids needing updating
       $conso_db = config('database.connections.consodb.database');
       $harvests = HarvestLog::with('credential','report')->whereIn('id',$harvest_ids)->get();
       foreach ($harvests as $harvest) {
           if (!$harvest->report || !$harvest->credential) continue;
           $table = $conso_db . "." . strtolower($harvest->report->name) . "_report_data";
           // Delete the data rows
           $result = DB::table($table)
                       ->where('inst_id',$harvest->credential->inst_id)
                       ->where('prov_id',$harvest->credential->prov_id)
                       ->where('yearmon',$harvest->yearmon)
                       ->delete();
           // If we just deleted a harvest that matches the last_harvest for it's related credential,
           // add the credentials_id to $reset_ids (there could be multiple deletions, so we'll track
           // the IDs and update them after deleting all the data records).
           if ($harvest->credential->last_harvest == $harvest->yearmon &&
               !in_array($harvest->credentials_id,$reset_ids)) {
               $reset_ids[] = $harvest->credentials_id;
           }
       }
       return $reset_ids;
   }

   /**
    * Update last_harvest for harvest credential(s)
    *
    * @param  Array  $cred_ids
    */
   private function resetLastHarvest($cred_ids)
   {
       // Update affected credentials

       $credentials = Credential::with('harvestLogs')->whereIn('id',$cred_ids)->get();
       foreach ($credentials as $cred) {
           $cred->last_harvest = $cred->harvestLogs->where('status','Success')->max('yearmon');
           $cred->save();
       }
   }

}
