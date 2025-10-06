<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use App\Models\HarvestLog;
use App\Models\Consortium;
use App\Models\FailedHarvest;
use App\Models\Report;
use App\Models\Provider;
use App\Models\GlobalProvider;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Credential;
use App\Models\Sushi;
use App\Models\SushiQueueJob;
use App\Models\ConnectionField;
use App\Models\CcplusError;
use App\Services\HarvestService;
use App\Models\ReportField;
use Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class HarvestLogController extends Controller
{
   private $connection_fields;
   protected $harvestService;

   public function __construct(HarvestService $harvestService)
   {
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

       // Setup limit arrays for the instID's and provIDs we'll pull credentials for
       $limit_to_insts = ($thisUser->isConsoAdmin()) ? array() : $thisUser->adminInsts();
       $limit_to_provs = GlobalProvider::with('credentials', 'credentials.institution:id,is_active')
                                       ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                           return $qry->where('inst_id',1)->orWhereIn('inst_id', $limit_to_insts);
                                       })
                                       ->pluck('id')->toArray();

        // Get credentials limited by-inst and/or by-prov
        $credential_ids = Credential::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                        return $qry->whereIn('inst_id', $limit_to_insts);
                                    })
                                    ->when(count($limit_to_provs) > 0, function ($qry) use ($limit_to_provs) {
                                        return $qry->whereIn('prov_id', $limit_to_provs);
                                    })
                                    ->pluck('id')->toArray();

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

        return response()->json(['records' => $harvests], 200);
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

        // limit institutions by user rols(s)
        $_insts = $thisUser->viewerInsts(); // returns [1] for conso or serverAdmin
        $limit_by_inst = ($_insts === [1]) ? array() : $_insts;
        // Pull globalProvider IDs based on the consortium providers defined for institutions
        // in $limit_by_inst ; everyone gets consortium-wide providers (where inst_id=1)
        $global_ids = Provider::when(count($limit_by_inst) > 0, function ($qry) use ($limit_by_inst) {
                                return $qry->where('inst_id',1)->orWhereIn('inst_id',$limit_by_inst);
                             })
                             ->select('global_id')->distinct()->pluck('global_id')->toArray();

        // Setup option arrays for the report creator
        $institutions = Institution::when(count($limit_by_inst)>0, function ($qry) use ($limit_by_inst) {
                                       return $qry->whereIn('id', $limit_by_inst);
                                   })->get(['id','name'])->toArray();

        // Admins see groups, but only ones that have members
        $groups = array();
        if ($thisUser->isAdmin()) {
            $data = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();
            foreach ($data as $group) {
                if ( $group->institutions->count() > 0 ) {
                    $groups[] = array('id' => $group->id, 'name' => $group->name, 'institutions' => $group->institutions);
                }
            }
        }

        // Build platforms from globals connected to insts in limit_by_inst and add report assignments
        $globals = GlobalProvider::with('consoProviders','consoProviders.reports')->whereIn('id',$global_ids)
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
       abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
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
       $is_admin =$thisUser->hasRole('Admin');
       $firstYM = config('ccplus.first_yearmon_51');

       // Set flag for "skip previously harvested data"
       $skip_harvested = false;
       if (isset($input["skip_harvested"])) {
           $skip_harvested = $input["skip_harvested"];
       }

       // Admins can harvest multiple insts or a group
       $inst_ids = array();
       if ($is_admin) {
           // Set inst_ids (force to user's inst if not an admin)
           if ($input["inst_group_id"] > 0) {
               $group = InstitutionGroup::with('institutions')->findOrFail($input["inst_group_id"]);
               $inst_ids = $group->institutions->pluck('id')->toArray();
           } else {
               // A value of 0 in inst_ids means we're doing consortium
               if (in_array(0,$input["inst"])) {
                   $inst_ids = Institution::where('is_active',true)->pluck('id')->toArray();
               } else {
                   $inst_ids = $input["inst"];
               }
           }
       // Managers are confined to only their inst
       } else {
           $inst_ids = array($user_inst);
       }
       if (sizeof($inst_ids) == 0) {
           return response()->json(['result' => false, 'msg' => 'Error: no matching institutions to harvest']);
       }

       // Get detail on (master) reports requested
       $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

       // Get platform info
       if (in_array(0,$input["plat"])) {    //  Get all consortium-enabled global platforms?
           $global_ids = Provider::where('is_active',true)->where('inst_id',1)->pluck('global_id')->toArray();
       } else {
           $plat_ids = $input["plat"];
           $global_ids = array();
           // plat_ids with  -1  means ALL, set global_ids based whose asking (admin or not)
           if ($is_admin) {
               if (in_array(-1, $plat_ids) || count($plat_ids) == 0) {
                   $global_ids = Provider::where('is_active',true)->pluck('global_id')->toArray();
               } else {
                   $global_ids = Provider::where('is_active',true)->whereIn('global_id',$plat_ids)->pluck('global_id')->toArray();
               }
           } else {
               if (in_array(-1, $plat_ids) || count($plat_ids) == 0) {
                   $global_ids = Provider::where('is_active',true)->whereIn('inst_id',[1,$user_inst])
                                         ->pluck('global_id')->toArray();
               } else {
                   $global_ids = Provider::where('is_active',true)->whereIn('inst_id',[1,$user_inst])
                                         ->whereIn('global_id',$plat_ids)->pluck('global_id')->toArray();
               }
           }
       }
       $global_platforms = GlobalProvider::with('credentials','credentials.institution:id,is_active','consoProviders',
                                                'consoProviders.reports','registries')
                                         ->where('is_active',true)->whereIn('id',$global_ids)->get();

       // Set the status for the harvests we're creating based on "when"
       $state = "New";
       if ($input["when"] == 'now') {
           $state = "Queued";
           $con = Consortium::where('ccp_key', '=', session('ccp_con_key'))->first();
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
               $consoProv = $global_platform->consoProviders->where('inst_id',1)->first();
               $conso_reports = ($consoProv) ? $consoProv->reports->pluck('id')->toArray() : [];

               // Loop through all credentials
               foreach ($global_platform->credentials as $cred) {
                  // If institution is inactive or this inst_id is not in the $inst_ids array, skip it
                   if ($cred->status != "Enabled" ||
                       !$cred->institution->is_active || !in_array($cred->inst_id,$inst_ids)) {
                       continue;
                   }

                  // Set reports to process based on consortium-wide and, if defined, institution-specific credential_ids
                   $report_ids = array();
                   foreach ($global_platform->consoProviders as $conso_provider) {
                        // if inst-specific provider for an inst different from the current cred, skip it
                        if ($conso_provider->inst_id != 1 && $conso_provider->inst_id != $cred->inst_id) {
                            continue;
                        }
                       $_ids = $conso_provider->reports->pluck('id')->toArray();
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
                               $newjob = SushiQueueJob::create(['consortium_id' => $con->id,
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
    * Return available providers for an array of inst_ids or an inst_group
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
   */
   public function availableProviders(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
       $group_id = json_decode($request->group_id, true);
       $insts = json_decode($request->inst_ids, true);

       // Setup an array of inst_ids for querying against the credentials
       if ($group_id > 0) {
           $group = InstitutionGroup::with('institutions')->findOrFail($group_id);
           $inst_ids = $group->institutions->pluck('id')->toArray();
       } else if (sizeof($insts) > 0) {
           $inst_ids = $insts;
       } else {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // Query the credentials for providers connected to the requested inst IDs
       if (in_array(0,$inst_ids)) {
           $availables = Credential::where('status','Enabled')->pluck('prov_id')->toArray();
       } else {
           $availables = Credential::where('status','Enabled')->whereIn('inst_id',$inst_ids)->pluck('prov_id')->toArray();
       }

       // Use availables (IDs) to get the provider data and return it via JSON
       // ( include inst_id and reports relationship like index() does )
       $providers = array();
       $provider_data = GlobalProvider::with('credentials','consoProviders','consoProviders.reports','registries')
                                      ->whereIn('id', $availables)->orderBy('name', 'ASC')->get(['id','name']);
       foreach ($provider_data as $gp) {
            $rec = array('id' => $gp->id, 'name' => $gp->name);
            $consoCnx = $gp->consoProviders->where('inst_id',1)->first();
            $rec['inst_id'] = ($consoCnx) ? 1 : null;
            $enabled_cred = $gp->credentials->where('status','Enabled')->first();
            $rec['sushi_enabled'] = ($enabled_cred) ? true : false;
            $_reports = $gp->enabledReports();
            $rec['reports'] = $_reports;
            $rec['releases'] = $gp->registries->sortBy('release')->pluck('release');
            if ($rec['releases']->count() > 1) {
                $rec['releases']->prepend('System Default');
            }              
            $providers[] = $rec;
        }

       if (sizeof($providers) == 0) {
           return response()->json(['result' => false, 'msg' => 'No matching, active platforms found']);
       } else {
           return response()->json(['providers' => $providers], 200);
       }
   }

   /**
    * Return available institutions for an array of provider IDs
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
   */
   public function availableInstitutions(Request $request)
   {
       $thisUser = auth()->user();
       abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
       $provs = json_decode($request->prov_ids, true);

       if (sizeof($provs) > 0) {
           $prov_ids = $provs;
       } else {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // Query the credentials for institutions connected to the requested inst IDs
       if (in_array(0,$prov_ids)) {
           $availables = Credential::where('status','Enabled')->pluck('inst_id')->toArray();
       } else {
           $availables = Credential::where('status','Enabled')->whereIn('prov_id',$prov_ids)->pluck('inst_id')->toArray();
       }

       // Use availables (IDs) to get the provider data and return it via JSON
       // ( include inst_id and reports relationship like index() does )
       $institutions = array();
       $inst_data = Institution::with('credentials:id,inst_id,prov_id','institutionGroups','institutionGroups.institutions')
                               ->whereIn('id', $availables)->orderBy('name', 'ASC')->get(['id','name']);

       // Loop through institutions and build output records and list of groups
       $groups = array();
       $group_ids = array();    // keep track of group IDs seen
       foreach ($inst_data as $inst) {
           $rec = array('id' => $inst->id, 'name' => $inst->name);
           $new_groups = $inst->institutionGroups->whereNotIn('id',$group_ids);
           foreach ($new_groups as $group) {
               $groups[] = array('id' => $group->id, 'name' => $group->name, 'institutions' => $group->institutions);
               $group_ids[] = $group->id;
           }
           $institutions[] = $rec;
       }

       if (sizeof($institutions) == 0) {
           return response()->json(['result' => false, 'msg' => 'No matching, active institutions found']);
       } else {
           return response()->json(['institutions' => $institutions, 'groups' => $groups], 200);
       }
   }

   /**
    * Update status for a given harvest
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function updateStatus(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

       // Get and verify input or bail with error in json response
       try {
           $input = json_decode($request->getContent(), true);
       } catch (\Exception $e) {
           return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
       }
       if (!isset($input['ids']) || !isset($input['status'])) {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // Limit new status input to 2 possible values
       $new_status_allowed = array('Pause', 'ReStart', '5', '5.1');
       if (!in_array($input['status'], $new_status_allowed)) {
           return response()->json(['result' => false,
                                    'msg' => 'Invalid request: status cannot be set to requested value.']);
       }
       $status_action = ($input['status'] == 'Pause') ? "Pause" : "ReStart";

       // Get consortium info
       $con = Consortium::where("ccp_key", session("ccp_con_key"))->first();
       if (!$con) {
           return response()->json(['result' => false, 'msg' => 'Error: Corrupt session or consortium settings']);
       }

       // Check status input for a specific COUNTER release for restarting
       $forceRelease = ($input['status']=='5' || $input['status']=='5.1') ? $input['status'] : null;

       // Get and process the harvest(s)
       $changed = 0;
       $skipped = [];
       $harvests = HarvestLog::with('credential','credential.institution','credential.provider',
                                    'credential.provider.registries')
                             ->whereIn('id',$input['ids'])->get();
       foreach ($harvests as $harvest) {
           // keep track of original status
           $original_status = $harvest->status;

           // Disallow ReStart on any harvest where credentials are not Enabled, or provider or institution are
           // are not active
           if ( ($status_action == 'ReStart') && ($harvest->credential->status != 'Enabled' ||
                !$harvest->credential->institution->is_active || !$harvest->credential->provider->is_active) ) {
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

           // Setting Paused just changes status
           if ($status_action == 'Pause') {
               $harvest->status = 'Paused';

           // Setting Queued means attempts get set to zero
           // Restart will reset attempts and create a SushiQueueJob if one does not exist
           } else if ($status_action == 'ReStart') {
               $_job = SushiQueueJob::where('consortium_id',$con->id)->where('harvest_id',$harvest->id)->first();
               if (!$_job) {
                   try {
                       $newjob = SushiQueueJob::create(['consortium_id' => $con->id,
                                                        'harvest_id' => $harvest->id,
                                                        'replace_data' => 1
                                                      ]);
                   } catch (\Exception $e) {
                       return response()->json(['result' => false, 'msg' => 'Error creating job entry in global table!']);
                   }
               }
               $harvest->attempts = 0;
               $harvest->status = 'Queued';
           }

           // Update the harvest record and return
           $harvest->updated_at = now();
           $harvest->save();
           $changed++;
       }

       // Return result
       $msg_result = ($status_action == 'ReStart') ? "restarted" : "paused";
       if ($changed > 0) {
           $msg  = "Successfully  " . $msg_result . " " . $changed . " harvests";
           $msg .= (!is_null($forceRelease)) ? " as release ".$forceRelease : "";
           if (count($skipped) > 0) {
               $msg .= " , and skipped " . count($skipped) . " harvests";
               $msg .= (!is_null($forceRelease)) ? " (release ".$forceRelease." may not be available)." : ".";
            }
       } else {
           $msg = "No selected harvests modified";
           $msg .= (!is_null($forceRelease)) ? ", release ".$forceRelease." may not be available" : "";
       }
       return response()->json(['result' => true, 'msg' => $msg, 'skipped' => $skipped]);
   }

  /**
   * Display a form for editting the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
   public function edit($id)
   {
       $harvest = HarvestLog::with('report:id,name','credential', 'credential.institution:id,name',
                                   'credential.provider:id,name')
                            ->findOrFail($id);

       // Get any failed attempts, pass as an array
       $data = FailedHarvest::with('ccplusError', 'ccplusError.severity')->where('harvest_id', '=', $id)
                            ->orderBy('created_at','DESC')->get();
       $attempts = $data->map(function ($rec) {
           $rec->severity = $rec->ccplusError->severity->name;
           $rec->message = $rec->ccplusError->message;
           $rec->attempted = ($rec->created_at) ? date("Y-m-d H:i", strtotime($rec->created_at)) : " ";
           return $rec;
       })->toArray();

       // If harvest successful, pass it as an array
       if ($harvest->status == 'Success') {
           $rec = array('process_step' => 'SUCCESS', 'error_id' => '', 'severity' => '', 'detail' => '');
           $rec['message'] = "Harvest successfully completed";
           $rec['attempted'] = ($harvest->created_at) ? date("Y-m-d H:i", strtotime($harvest->created_at)) : " ";
           array_unshift($attempts,$rec);
       } else {
           // Harvests could have prior failures, but attampes has been reset to zero to requeue ot
           if (sizeof($attempts) == 0) {
               if ($harvest->attempts == 0) {
                   $attempts[] = array('severity' => "Unknown", 'message' => "Harvest has not yet been attempted",
                                       'attempted' => "Unknown");
               } else {
                   $attempts[] = array('severity' => "Unknown", 'message' => "Failure records are missing!",
                                       'attempted' => "Unknown");
               }
           }
       }

       return view('harvests.edit', compact('harvest', 'attempts'));
   }

   /**
    * Display the resource w/ built-in form (manager/admin) for editting the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function show($id)
   {
       //
   }

   /**
    * Update the specified resource in storage.
    *
    * @param  \Illuminate\Http\Request $request
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function update(Request $request, $id)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

       $harvest = HarvestLog::findOrFail($id);
       $this->validate($request, ['status' => 'required']);

       // A harvest being updated to ReQueued means setting attempts to zero
       if ($request->input('status') == 'ReQueued' && $harvest->status != "ReQueued") {
           $harvest->attempts = 0;
       }
       $harvest->status = $request->input('status');
       $harvest->save();
       $harvest->load('report:id,name','credential','credential.institution:id,name','credential.provider:id,name');
       $harvest->updated = ($harvest->updated_at) ? date("Y-m-d H:i", strtotime($harvest->updated_at)) : " ";

       return response()->json(['result' => true, 'harvest' => $harvest]);
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
       if (!$thisUser->hasRole(['Admin'])) {
           if (!$thisUser->hasRole(['Manager']) || $harvest->credential->inst_id != $thisUser->inst_id) {
               return response()->json(['result' => false, 'msg' => 'Error - Not authorized']);
           }
       }

       // Get consortium_id
       $con = Consortium::where('ccp_key', session('ccp_con_key'))->first();
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
       $jobs = SushiQueueJob::where('harvest_id', $id)->get();
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
    * Delete multiple harvests
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function bulkDestroy(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

       // Get and verify input or bail with error in json response
       try {
           $input = json_decode($request->getContent(), true);
       } catch (\Exception $e) {
           return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
       }
       if (!isset($input['harvests'])) {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // Get the harvests requested
       $harvest_data = HarvestLog::with('credential','report')->whereIn('id', $input['harvests'])->get();
       $skipped = 0;
       $msg = "Result: ";

       // Build a list of IDs that current user allowed to delete
       $deleted = 0;
       $deleteable_ids = [];
       foreach ($harvest_data as $harvest) {
           if (!$harvest->canManage()) {
               $skipped++;
               continue;
           }
           $deleteable_ids[] = $harvest->id;
       }
       if (count($deleteable_ids) == 0) {
           return response()->json(['result' => false, 'msg' => 'Error: Authorization failed for requested inputs']);
       }
       // Get consortium_id
       $con = Consortium::where('ccp_key', session('ccp_con_key'))->first();
       if (!$con) {
           return response()->json(['result'=>false, 'msg'=>'Error: Cannot delete harvests with current consortium settings.']);
       }

       // Delete any related jobs from the global queue before deleting the harvests
       $result = SushiQueueJob::where('consortium_id',$con->id)->whereIn('harvest_id', $deleteable_ids)->delete();

       // Delete stored data for the harvest(s)
       $related_credential_ids = $this->deleteStoredData($deleteable_ids);

       // Delete the harvest record(s)
       $result = HarvestLog::whereIn('id', $deleteable_ids)->delete();

       // Update last_harvest setting for affected credentials based on what's left
       if ( count($related_credential_ids) > 0) {
           $this->resetLastHarvest($related_credential_ids);
       }

       // return result
       $msg .= count($deleteable_ids) . " harvests deleted";
       $msg .= ($skipped>0) ? ", and " . $skipped . "harvests skipped." : " successfully.";
       return response()->json(['result' => true, 'msg' => $msg, 'removed' => $deleteable_ids]);
   }

   /**
    * return #-jobs in the global harvesting queue
    *
    * @param  \Illuminate\Http\Request  $request
    * @return JSON
    */
   public function queueCount(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(["Admin","Manager"]), 403);

       // Get job count for current consortium
       $count = 0;
       $con = Consortium::where("ccp_key", session("ccp_con_key"))->first();
       if ($con) {
          $count = SushiQueueJob::where('consortium_id', $con->id)->count();
       }

       // Get the job-count
       return response()->json(['count' => $count]);
   }

    /**
     * Entry point for returning Harvest Queue records for the current consortium_id
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   public function harvestQueue(Request $request)
   {
       $thisUser = auth()->user();
       abort_unless($thisUser->isAdmin(), 403);

       // Get and verify input or bail with error in json response
       try {
           $input = json_decode($request->getContent(), true);
       } catch (\Exception $e) {
           return response()->json(["result" => false, "msg" => "Error decoding input!"]);
       }

       // Get consortium_id; if not set, return empty results
       $con = Consortium::where("ccp_key", session("ccp_con_key"))->first();
       if (!$con) {
           return response()->json(["result" => true, "harvests" => [], "prov_ids" => [], "inst_ids" => [], "rept_ids" => [],
                                    "statuses" => [], "codes" => []]);
       }

       // Setup "display names" for internal system status values; these also limit what is sent back
       $displayStatus = array('Queued' => 'Harvest Queue', 'Harvesting' => '*Harvesting', 'Pending' => 'Queued by Vendor',
                              'Paused' => 'Paused', 'ReQueued' => 'ReQueued', 'Waiting' => 'Process Queue',
                              'Processing' => '*Processing');

       // Setup limit_to_insts with the instID's we'll pull settings for
       $limit_to_insts = ($thisUser->isConsoAdmin()) ? array() : $thisUser->adminInsts();

       // Get all harvests joined with credentials that match the statuses
       $data = HarvestLog::with('credential','credential.provider','credential.institution:id,name','report',
                                'lastError','failedHarvests','failedHarvests.ccplusError')
                         ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                             return $qry->whereIn('credential.inst_id', $limit_to_insts);
                         })
                         ->whereIn('status',array_keys($displayStatus))
                         ->orderBy("updated_at", "DESC")->get();

       // Build an output array of no more than 500 harvests
       $output_count = 0;
       $truncated = false;
       $harvests = array();
       foreach ($data as $rec) {
          $rec->prov_id = $rec->credential->prov_id;
          $rec->inst_id = $rec->credential->inst_id;
          $rec->prov_name = $rec->credential->provider->name;
          $rec->inst_name = $rec->credential->institution->name;
          $rec->report_name = $rec->report->name;
          $rec->created = date("Y-m-d H:i", strtotime($rec->updated_at));
          $rec->dStatus = $displayStatus[$rec->status];
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
          $_error['counter_url'] = ($rec->release == '5.1')
              ? "https://cop5.countermetrics.org/en/5.1/appendices/d-handling-errors-and-exceptions.html"
              : "https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html";
          $rec->error = $_error;

          // Add a test+confirm URL
          $beg = $rec->yearmon . '-01';
          $end = $rec->yearmon . '-' . date('t', strtotime($beg));
          $sushi = new Sushi($beg, $end);
          // set url for manual retry/confirm icon using buildUri
          $rec->retryUrl = $sushi->buildUri($rec->credential, 'reports', $rec->report, $rec->release);
          // add record to the outbound array
          $harvests[] = $rec->toArray();

          // Limit to 500 rows of output
          $output_count++;
          if ($output_count == 500) {
              $truncated = true;
              break;
          }
       }
       return response()->json(['records' => $harvests], 200);

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

   // user-defined comparison function to sort based on timestamp
   static function sortTimeStamp($time1, $time2)
   {
       if (strtotime($time1) < strtotime($time2)) {
           return 1;
       } else if (strtotime($time1) > strtotime($time2)) {
           return -1;
       } else {
           return 0;
       }
   }

   // Return an array of bounding yearmon strings based on exsiting Harvestlogs
   //   bounds[0] will hold absolute min and max yearmon for all harvests across all reports
   private function harvestBounds() {

       $conso_db = config('database.connections.consodb.database');

       // Query for min and max yearmon values
       $raw_query = "min(yearmon) as YM_min, max(yearmon) as YM_max";
       $result = HarvestLog::selectRaw($raw_query)->get()->toArray();
       $bounds[0] = $result[0];

       return $bounds;
   }

   // Build a consistent output record using an input harvest record
   // input should include Report, Credential with institution+provider and failedHarvests with ccplusError
   private function formatRecord($harvest) {
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
       $rec['release'] = (is_null($harvest->release)) ? "" : $harvest->release;

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
           $rec['error'] = $lastFailed->ccplusError->toArray();
           $rec['error']['detail'] = (is_null($lastFailed->detail)) ? '' : $lastFailed->detail;
           $rec['error']['help_url'] = (is_null($lastFailed->help_url)) ? '' : $lastFailed->help_url;
           $rec['error']['process_step'] = (is_null($lastFailed->process_step)) ? '' : $lastFailed->process_step;
       }
       $rec['failed'] = [];
       $rec['error']['counter_url'] = ($harvest->release == '5.1')
           ? "https://cop5.countermetrics.org/en/5.1/appendices/d-handling-errors-and-exceptions.html"
           : "https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html";

       // Build a URL to test+confirm the error(s); let Sushi class do the work
       $beg = $harvest->yearmon . '-01';
       $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
       $sushi = new Sushi($beg, $end);
 
       // setup required connectors for buildUri
       $prov_connectors = $harvest->credential->provider->connectors();
       $connectors = $this->connection_fields->whereIn('id',$prov_connectors)->pluck('name')->toArray();
       $rec['retryUrl'] = $sushi->buildUri($harvest->credential, 'reports', $harvest->report, $harvest->release);
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

       $credentials = Credemtial::with('harvestLogs')->whereIn('id',$cred_ids)->get();
       foreach ($credentials as $cred) {
           $cred->last_harvest = $cred->harvestLogs->where('status','Success')->max('yearmon');
           $cred->save();
       }
   }

}
