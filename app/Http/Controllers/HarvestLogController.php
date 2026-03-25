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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class HarvestLogController extends Controller
{
    private $all_error_codes;
    private $connection_fields;
    private $xlStatus;
    private $colors;
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
        $this->xlStatus = array('Queued' => 'Harvest Queue', 'Harvesting' => '*Harvesting', 'Pending' => 'Queued by Vendor',
                               'BadCreds' => 'Bad Credentials', 'Paused' => 'Paused', 'ReQueued' => 'ReQueued',
                                'Waiting' => 'Process Queue', 'Processing' => '*Processing', 'NoRetries' => 'Out of Retries');
        $this->colors = array('Success'=>'#00dd00', 'Fail'=>'#dd0000', 'NoRetries'=>'#999999', 'BadCreds'=>'#ff9900');

    }

   /**
    * Returns filter_options for the U/I. Item records returned with getItems() below
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        $filter_options = array();

        // Setup limit arrays for the institutions and groups
        $limit_insts = ($consoAdmin) ? array() : $thisUser->adminInsts();
        $filter_options['institutions'] = Institution::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                                                         return $qry->whereIn('id', $limit_insts);
                                                     })->get(['id','name'])->toArray();
        $limit_groups = ($consoAdmin) ? array() : $thisUser->adminGroups();
        $filter_options['groups'] = InstitutionGroup::when(count($limit_groups) > 0, function ($qry) use ($limit_groups) {
                                                        return $qry->where('id', $limit_groups);
                                                    })->get(['id','name'])->toArray();

        // Get all global IDs and names
        $filter_options['platforms'] = GlobalProvider::get(['id','name'])->toArray();

        // Setup the rest of the filtering options
        $filter_options['yymms'] = HarvestLog::select('yearmon')->distinct()->pluck('yearmon')->toArray();
        $filter_options['statuses'] = array('Success','Fail','Bad Credentials');
        $filter_options['reports'] = Report::where('parent_id',0)->orderBy('dorder','ASC')
                                           ->get(['id','name'])->toArray();
        $filter_options['codes'] = CcplusError::where('new_status','<>','ReQueued')->pluck('id')->toArray();
        array_unshift($filter_options['codes'], 'No Error');

        return response()->json(['records' => [], 'options' => $filter_options, ], 200);
    }

   /**
    * Returns (possibly filtered) items for the U/I
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
    public function getItems(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        $input = $request->all();
        $type = (isset($input['type'])) ? $input['type'] : 'harvests';
        if ($type!='harvests' && $type!='jobs') {
            return response()->json(['result' => false, 'msg' => 'Invalid request type for getItems']);
        }

        // Setup for known filters
        $filters = array('institutions' => [], 'groups' => [], 'platforms' => [], 'yymms' => [], 'reports' => [],
                          'codes' => [], 'statuses' => []);
        $harvest_statuses = array('Success','Fail','BadCreds');
        $jobs_statuses = array('Queued', 'Harvesting', 'Pending', 'Paused', 'ReQueued', 'Waiting','Processing');

        // Validate/handle input filters
        if ($input['filters']) {
            foreach ($input['filters'] as $key => $val) {
                $filters[$key] = (is_null($val)) ? [] : $val;
            }
        }

        // Enforce limits on institutions and groups
        $limit_insts = array();
        $group_insts = array();
        // Turn groups into a set of institutions (harvests happen to insts, not groups)
        if (count($filters['groups']) > 0 || !$consoAdmin) {
            $limit_groups = (!$consoAdmin) ? array_intersect($thisUser->adminGroups(), $filters['groups'])
                                           : $filters['groups'];
            $data = InstitutionGroup::whereIn('id',$limit_groups)->with('institutions:id,name')->get();
            foreach ($data as $group) {
                $_inst_ids = $group->institutions->pluck('id')->toArray();
                $group_insts = array_unique(array_merge($group_insts, $_inst_ids));
            }
        }
        if (!$consoAdmin || count($filters['institutions']) > 0) {
            $limit_insts = (!$consoAdmin) ? array_intersect($thisUser->adminInsts(), $filters['institutions'])
                                          : $filters['institutions'];
        }
        if (count($filters['institutions']) > 0 && count($filters['groups']) > 0) {
            $limit_insts = array_intersect($limit_insts,$group_insts);
        }

        // Get credentials limited by-inst and/or platform (to limit harvests pulled)
        $credential_ids = Credential::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                                        return $qry->whereIn('inst_id', $limit_insts);
                                    })->when(count($filters['platforms']) > 0, function ($qry) use ($filters) {
                                        return $qry->whereIn('prov_id', $filters['platforms']);
                                    })->pluck('id')->toArray();

        // Replace 'No Error' in codes with 0, if present
        if (count($filters["codes"]) > 0 && in_array('No Error', $filters['codes'])) {
            $idx = array_search('No Error', $filters['codes']);
            if ($idx !== false) $filters["codes"][$idx] = 0;
        }

        // Touch up status filter for BadCreds
        if (count($filters['statuses']) == 0) {     // Limit status if not set
            $filters["statuses"] = ($type=='harvests') ? $harvest_statuses : $jobs_statuses;
        } else {
            $idx = array_search('Bad Credentials', $filters['statuses']);
            if ($idx !== false) $filters["statuses"][$idx] = 'BadCreds';
        }

        // Get the harvest rows based on credentials
        $harvest_data = HarvestLog::
            with('report:id,name','credential','credential.institution:id,name','credential.provider',
                 'lastError','failedHarvests','failedHarvests.ccplusError')
            ->whereIn('credentials_id', $credential_ids)
            ->orderBy('updated_at', 'DESC')
            ->when(count($filters['reports']) > 0, function ($qry) use ($filters) {
                return $qry->whereIn('report_id', $filters['reports']);
            })
            ->when(count($filters['codes']) > 0, function ($qry) use ($filters) {
                return $qry->whereIn('error_id', $filters['codes']);
            })
            ->when(count($filters['statuses']) > 0, function ($qry) use ($filters) {
                return $qry->whereIn('status', $filters['statuses']);
            })
            ->when(count($filters['yymms']) > 0, function ($qry) use ($filters) {
                return $qry->whereIn('yearmon', $filters['yymms']);
            })
            ->limit(501)->get();

        // Format records for display , limit to 500 output records
        $truncated = ($harvest_data->count()>500);
        if ($truncated) $harvest_data->pop();
        $harvests = array();
        foreach ($harvest_data as $key => $harvest) {
            $harvests[] = $this->formatRecord($harvest);
        }

        return response()->json(['result' => true, 'records' => $harvests, 'truncated' => $truncated ], 200);
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
        $limit_by_inst = ($consoAdmin) ? array() : $thisUser->adminInsts();

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
        if (count($group_ids) > 0 || $consoAdmin) {
            $data = InstitutionGroup::with('institutions:id,name')
                                    ->when($consoAdmin, function ($qry) {
                                        return $qry->whereNull('user_id');
                                    })
                                    ->when(!$consoAdmin, function ($qry) use ($group_ids) {
                                        return $qry->whereIn('id',$group_ids);
                                    })
                                    ->orderBy('name', 'ASC')->get();
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
        if (count($input["inst"]) == 0 && $input["inst_group_id"] <= 0) {
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

        // Setup an array of inst IDs to be processed
        $limit_insts = array();
        if (count($input["inst"]) > 0) {
            $limit_insts = (!$consoAdmin) ? array_intersect($thisUser->adminInsts(), $input["inst"]) : $input["inst"];
        } else {    // group_id should be set (we checked above)
            $group = InstitutionGroup::where('id',$input["inst_group_id"])->first();
            if (!$group) {
                return response()->json(['result' => false, 'msg' => 'Error: InstitutionGroup is invalid or corrupt']);
            }
            $limit_insts = $group->institutions->pluck('id')->toArray();
        }
        // Make sure limit_insts include id:1 (so that we pick up conso-platforms from the connections below)
        if (!in_array(1,$limit_insts)) $limit_insts[] = 1;

        // Get detail on (master) reports requested
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Get Global Platforms
        $plat_ids = $input["plat"];
        $global_ids = Connection::where('is_active',true)->whereIn('global_id',$plat_ids)->whereIn('inst_id',$limit_insts)
                                ->select('global_id')->distinct()->pluck('global_id')->toArray();
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
                    // If institution is inactive or this inst_id is not in the $limit_insts array, skip it
                    if ($cred->status != "Enabled" || !$cred->institution->is_active ||
                       (!$consoAdmin && !in_array($cred->inst_id,$limit_insts))) {
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
        $limit_insts = ($consoAdmin) ? array() : $thisUser->adminInsts();
        $filter_options['institutions'] = Institution::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                                            return $qry->where('inst_id',1)->orWhereIn('inst_id', $limit_insts);
                                        })->get(['id','name'])->toArray();
        $filter_options['platforms'] = GlobalProvider::get(['id','name'])->toArray();

        // Get credentials limited by-inst; non-conso-admins are limited to the records they can affect
        $credential_ids = Credential::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                                        return $qry->whereIn('inst_id', $limit_insts);
                                    })->pluck('id')->toArray();

        // Get the harvests we'll be updating, limited by credential IDs
        $harvests = HarvestLog::with('report:id,name','credential','credential.institution','credential.provider',
                                     'credential.provider.registries')
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

                // Disallow Restart if credentials are not Enabled, or provider/institution are inactive
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
                $msg  = "Successfully restarted " . $changed . " harvests";
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
                   }, $return_name,
                    [ 'Content-Type' => 'application/json',
                      'Content-Disposition' => 'attachment; filename="' . $return_name . '"']
               );
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

       // Setup limits on the query for the log records
       $limit_insts = ($consoAdmin) ? array() : $thisUser->adminInsts();
       $limit_status = array('Queued', 'Harvesting', 'Pending', 'Paused', 'ReQueued', 'Waiting','Processing');

       // Get all harvests joined with credentials that match the statuses
       $data = HarvestLog::with('credential','credential.provider','credential.institution:id,name','report',
                                'lastError','failedHarvests','failedHarvests.ccplusError')
                         ->when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                             return $qry->whereIn('credential.inst_id', $limit_insts);
                         })
                         ->whereIn('status',$limit_status)
                         ->orderBy("updated_at", "DESC")->limit(501)->get();

        // Format records for display , limit to 500 output records
        $truncated = ($data->count()>500);
        if ($truncated) $data->pop();
        $harvests = array();
        foreach ($data as $key => $harvest) {
            $harvests[] = $this->formatRecord($harvest);
        }

       // Setup options for the U/I - it will limit options further based on what's there
       $filter_options = array();
       // Setup limit arrays for the instID's and provIDs we'll pull credentials for
       $filter_options['institutions'] = Institution::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                                           return $qry->where('inst_id',1)->orWhereIn('inst_id', $limit_insts);
                                       })->get(['id','name'])->toArray();

       // Get all global IDs and names
       $filter_options['platforms'] = GlobalProvider::get(['id','name'])->toArray();
       $filter_options['reports'] = Report::where('parent_id',0)->orderBy('dorder','ASC')
                                          ->get(['id','name'])->toArray();
       $filter_options['codes'] = $data->where('error_id','>',0)->unique('error_id')->sortBy('error_id')
                                       ->pluck('error_id')->toArray();
       array_unshift($filter_options['codes'], 'No Error');
       $filter_options['statuses'] = array_intersect_key($this->xlStatus, array_flip($limit_status));

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
       $rec = array('harvest_id' => $harvest->id, 'inst_id' => $harvest->credential->inst_id,
                    'prov_id' => $harvest->credential->prov_id, 'release' => $harvest->release, 'yearmon' => $harvest->yearmon,
                    'attempts' => $harvest->attempts, 'inst_name' => $harvest->credential->institution->name,
                    'prov_name' => $harvest->credential->provider->name, 'report_id' => $harvest->report->id,
                    'report_name' => $harvest->report->name, 'status' => $harvest->status, 'rawfile' => $harvest->rawfile,
                    'error_id' => 0, 'error' => []
                   );
       $rec['updated'] = ($harvest->updated_at) ? date("Y-m-d H:i", strtotime($harvest->updated_at)) : " ";
       $rec['created'] = ($rec['updated'] != " ") ? date("Y-m-d H:i", strtotime($harvest->updated_at)) : " ";
       $rec['release'] = (is_null($harvest->release)) ? "" : $harvest->release;

       // Setup error details array (starting with default values)
       $rec['error'] = array('id' => $harvest->error_id, 'message' => '');
       $rec['d_status'] = (isset($this->xlStatus[$rec['status']])) ? $this->xlStatus[$rec['status']] : $rec['status'];
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
       $rec['error']['color'] = (isset($this->colors[$rec['status']])) ?  $this->colors[$rec['status']] : '#999999';
       $rec['failed'] = [];
       $rec['error']['counter_url'] = ($harvest->release == '5.1')
           ? "https://cop5.countermetrics.org/en/5.1/appendices/d-handling-errors-and-exceptions.html"
           : "https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html";

       // Build a URL to test+confirm the error(s); let CounterApi class do the work
       $beg = $harvest->yearmon . '-01';
       $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
       $rec['retryUrl'] = CounterApi::buildUri($beg,$end,$harvest->credential, $harvest->report, 'reports', $harvest->release);
 
       // Set required connectors
       $prov_connectors = $harvest->credential->provider->connectors();
       $connectors = $this->connection_fields->whereIn('id',$prov_connectors)->pluck('name')->toArray();
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
