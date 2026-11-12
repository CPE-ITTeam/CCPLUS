<?php

namespace App\Http\Controllers;

use App\Models\CcplusError;
use App\Models\Connection;
use App\Models\Consortium;
use App\Models\CounterApi;
use App\Models\Credential;
use App\Models\GlobalProvider;
use App\Models\GlobalQueueJob;
use App\Models\HarvestLog;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Report;
use App\Models\ReportField;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HarvestLogController extends BaseController
{
    private array $all_error_codes = [];
    private array $xlStatus = [
        'Queued'     => 'Harvest Queue',
        'Harvesting' => '*Harvesting',
        'Pending'    => 'Queued by Vendor',
        'BadCreds'   => 'Bad Credentials',
        'Paused'     => 'Paused',
        'ReQueued'   => 'ReQueued',
        'Waiting'    => 'Process Queue',
        'Processing' => '*Processing',
        'NoRetries'  => 'Out of Retries',
    ];
    private array $colors = [
        'Success'   => 'success',
        'Fail'      => 'error',
        'NoRetries' => 'secondary',
        'BadCreds'  => 'warning',
    ];


    public function __construct()
    {
        try {
            $this->all_error_codes = CcplusError::pluck('id')->toArray();
        } catch (\Exception $e) {
            $this->all_error_codes = [];
        }
    }

    /**
     * Returns filter_options for the UI. Item records returned with getItems().
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
        $limit_groups = $consoAdmin ? [] : $thisUser->adminGroups();

        $filter_options = [
            'institutions' => Institution::when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('id', $limit_insts))
                ->get(['id', 'name'])
                ->toArray(),
            'groups' => InstitutionGroup::when(count($limit_groups) > 0, fn($qry) => $qry->whereIn('id', $limit_groups))
                ->get(['id', 'name'])
                ->toArray(),
            'platforms' => GlobalProvider::get(['id', 'name'])->toArray(),
            'updates'   => ['Last Hour', 'Last 24 Hours', 'Last Week'],
            'yymms'     => HarvestLog::select('yearmon')->distinct()->pluck('yearmon')->toArray(),
            'statuses'  => ['Success', 'Fail', 'Bad Credentials'],
            'reports'   => Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name'])->toArray(),
            'codes'     => [['id' => 0, 'name' => 'No Error']],
        ];

        $codes = HarvestLog::where('error_id', '>', 0)
            ->orderBy('error_id')
            ->select('error_id')
            ->distinct()
            ->pluck('error_id');

        foreach ($codes as $code) {
            $filter_options['codes'][] = ['id' => $code, 'name' => $code];
        }

        return response()->json(['records' => [], 'options' => $filter_options], 200);
    }

    /**
     * Returns (possibly filtered) items for the UI.
     */
    public function getItems(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        $input = $request->all();
        $type = $input['type'] ?? 'harvests';
        if ($type !== 'harvests' && $type !== 'jobs') {
            return $this->respondError('Invalid request type for getItems');
        }

        $filters = [
            'institutions' => [],
            'groups'       => [],
            'platforms'    => [],
            'yymms'        => [],
            'reports'      => [],
            'codes'        => [],
            'statuses'     => [],
            'updates'      => [],
        ];

        if (!empty($input['filters'])) {
            foreach ($input['filters'] as $key => $val) {
                $filters[$key] = is_null($val) ? [] : $val;
            }
        }

        $harvest_statuses = ['Success', 'Fail', 'BadCreds'];
        $jobs_statuses = ['Queued', 'Harvesting', 'Pending', 'Paused', 'ReQueued', 'Waiting', 'Processing'];
        if (count($filters['statuses']) === 0) {
            $filters['statuses'] = ($type === 'harvests') ? $harvest_statuses : $jobs_statuses;
        }

        $idx = array_search('Bad Credentials', $filters['statuses']);
        if ($idx !== false) {
            $filters['statuses'][$idx] = 'BadCreds';
        }

        // Enforce limits on institutions and groups
        $limit_insts = [];
        $group_insts = [];
        if (count($filters['groups']) > 0 || !$consoAdmin) {
            $limit_groups = !$consoAdmin
                ? array_intersect($thisUser->adminGroups(), $filters['groups'])
                : $filters['groups'];

            $data = InstitutionGroup::whereIn('id', $limit_groups)->with('institutions:id,name')->get();
            foreach ($data as $group) {
                $group_insts = array_unique(array_merge($group_insts, $group->institutions->pluck('id')->toArray()));
            }
            $limit_insts = $group_insts;
        }

        if (count($filters['institutions']) > 0 || !$consoAdmin) {
            $limit_insts = !$consoAdmin
                ? array_intersect($thisUser->adminInsts(), $filters['institutions'])
                : $filters['institutions'];
        }
        if (count($filters['institutions']) > 0 && count($filters['groups']) > 0) {
            $limit_insts = array_intersect($limit_insts, $group_insts);
        }

        $credential_ids = Credential::when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_insts))
            ->when(count($filters['platforms']) > 0, fn($qry) => $qry->whereIn('prov_id', $filters['platforms']))
            ->pluck('id')
            ->toArray();

        if (count($filters['codes']) > 0 && in_array('No Error', $filters['codes'])) {
            $idx = array_search('No Error', $filters['codes']);
            if ($idx !== false) {
                $filters['codes'][$idx] = 0;
            }
        }

        $harvest_data = HarvestLog::with($this->harvestRelations())
            ->whereIn('credentials_id', $credential_ids)
            ->orderBy('updated_at', 'DESC')
            ->when(count($filters['reports']) > 0, fn($qry) => $qry->whereIn('report_id', $filters['reports']))
            ->when(count($filters['codes']) > 0, fn($qry) => $qry->whereIn('error_id', $filters['codes']))
            ->when(count($filters['statuses']) > 0, fn($qry) => $qry->whereIn('status', $filters['statuses']))
            ->when(count($filters['yymms']) > 0, fn($qry) => $qry->whereIn('yearmon', $filters['yymms']))
            ->when($filters['updates'], function ($qry) use ($filters) {
                if ($filters['updates'] === 'Last 24 Hours') {
                    return $qry->whereRaw('updated_at >= (now() - INTERVAL 24 HOUR)');
                } elseif ($filters['updates'] === 'Last Hour') {
                    return $qry->whereRaw('updated_at >= (now() - INTERVAL 1 HOUR)');
                } elseif ($filters['updates'] === 'Last Week') {
                    return $qry->whereRaw('updated_at >= (now() - INTERVAL 168 HOUR)');
                }
                return $qry->where('updated_at', 'like', '%' . $filters['updates'] . '%');
            })
            ->limit(501)
            ->get();

        $truncated = ($harvest_data->count() > 500);
        if ($truncated) {
            $harvest_data->pop();
        }

        $harvests = $harvest_data->map(fn($harvest) => $this->formatRecord($harvest))->all();

        return response()->json(['result' => true, 'records' => $harvests, 'truncated' => $truncated], 200);
    }

    /**
     * Return options for Manual Harvesting.
     */
    public function create(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_by_inst = $consoAdmin ? [] : $thisUser->adminInsts();

        $global_ids = Connection::when(count($limit_by_inst) > 0, function ($qry) use ($limit_by_inst) {
            return $qry->where('inst_id', 1)->orWhereIn('inst_id', $limit_by_inst);
        })->select('global_id')->distinct()->pluck('global_id')->toArray();

        $institutions = Institution::when(count($limit_by_inst) > 0, fn($qry) => $qry->whereIn('id', $limit_by_inst))
            ->get(['id', 'name'])
            ->toArray();

        $groups = [];
        $group_ids = $thisUser->adminGroups();
        if (count($group_ids) > 0 || $consoAdmin) {
            $data = InstitutionGroup::with('institutions:id,name')
                ->when($consoAdmin, fn($qry) => $qry->whereNull('user_id'))
                ->when(!$consoAdmin, fn($qry) => $qry->whereIn('id', $group_ids))
                ->orderBy('name', 'ASC')
                ->get();

            foreach ($data as $group) {
                if ($group->institutions->count() > 0) {
                    $groups[] = [
                        'id'           => $group->id,
                        'name'         => $group->name,
                        'institutions' => $group->institutions,
                    ];
                }
            }
        }

        $globals = GlobalProvider::with('connections', 'connections.reports')
            ->whereIn('id', $global_ids)
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        $platforms = [];
        foreach ($globals as $global) {
            $global_inst_ids = $global->connectedInstitutions();
            if (count($limit_by_inst) === 0 || count(array_intersect($global_inst_ids, $limit_by_inst)) > 0) {
                $global->reports = $global->enabledReports();
                $global->institutions = $global_inst_ids;
                $platforms[] = $global;
            }
        }
        $all_reports = Report::with('children')->orderBy('id', 'asc')->get();
        $master_reports = $all_reports->where('parent_id', 0)->select(['id', 'legend', 'name'])->toArray();
        $field_data = ReportField::orderBy('id', 'asc')->with('reportFilter')->get();
        $fields = $field_data->map(fn($rec) => [
            'id'        => $rec->id,
            'qry'       => $rec->qry_as,
            'report_id' => $rec->report_id,
            'column'    => $rec->reportFilter ? $rec->reportFilter->report_column : null,
        ])->all();
        $fy_month = 1;
        $userFY = $thisUser->getFY();
        if (!is_null($userFY)) {
            $date = date_parse($userFY);
            $fy_month = $date['month'];
        }
        return response()->json([
            'records' => [
                'institutions'   => $institutions,
                'groups'         => $groups,
                'platforms'      => $platforms,
                'all_reports'    => $all_reports,
                'master_reports' => $master_reports,
                'fields'         => $fields,
                'fyMo'           => $fy_month,
            ],
        ], 200);
    }
    /**
     * Store newly created manual harvest records in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $consoAdmin = $thisUser->isConsoAdmin();
        $this->validate($request, [
            'plat'    => 'required',
            'reports' => 'required',
            'fromYM'  => 'required',
            'toYM'    => 'required',
            'when'    => 'required',
        ]);
        $input = $request->all();
        if (!isset($input['inst']) || !isset($input['inst_group_id'])) {
            return $this->respondError('Error: Missing input arguments!');
        }
        if (count($input['inst']) === 0 && $input['inst_group_id'] <= 0) {
            return $this->respondError('Error: Institution/Group invalid in request');
        }
        $input_release = $input['release'] ?? '';
        $firstYM = config('ccplus.first_yearmon_51');
        $skip_harvested = (bool) ($input['skip_harvested'] ?? false);
        if (count($input['inst']) > 0) {
            $limit_insts = !$consoAdmin ? array_intersect($thisUser->adminInsts(), $input['inst']) : $input['inst'];
        } else {
            $group = InstitutionGroup::find($input['inst_group_id']);
            if (!$group) {
                return $this->respondError('Error: InstitutionGroup is invalid or corrupt');
            }
            $limit_insts = $group->institutions->pluck('id')->toArray();
        }
        $master_reports = Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name']);
        $cnx_insts = $limit_insts;
        if (!in_array(1, $cnx_insts)) {
            $cnx_insts[] = 1;
        }
        $global_ids = Connection::where('is_active', true)
            ->whereIn('global_id', $input['plat'])
            ->whereIn('inst_id', $cnx_insts)
            ->select('global_id')
            ->distinct()
            ->pluck('global_id')
            ->toArray();
        $global_platforms = GlobalProvider::with(
            'credentials',
            'credentials.institution:id,is_active',
            'connections',
            'connections.reports',
            'registries'
        )->where('is_active', true)->whereIn('id', $global_ids)->get();
        $state = 'New';
        $con = null;
        if ($input['when'] === 'now') {
            $state = 'Queued';
            $con = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
            if (!$con) {
                return $this->respondError('Cannot create jobs with current consortium setting.');
            }
        }
        $this_month = date('Y-m');
        $to = ($input['toYM'] > $this_month) ? $this_month : $input['toYM'];
        $from = ($input['fromYM'] > $to) ? $to : $input['fromYM'];
        $year_mons = $this->createYMarray($from, $to);
        $num_queued = 0;
        $created_ids = [];
        $updated_ids = [];
        foreach ($year_mons as $yearmon) {
            foreach ($global_platforms as $global_platform) {
                $release = $global_platform->default_release();
                if ($input_release === 'System Default' && !is_null($firstYM)) {
                    $available_releases = $global_platform->registries->sortByDesc('release')->pluck('release')->toArray();
                    if (count($available_releases) > 1 && in_array('5.1', $available_releases)) {
                        $idx51 = array_search('5.1', $available_releases);
                        if ($yearmon < $firstYM) {
                            if (isset($available_releases[$idx51 + 1])) {
                                $release = $available_releases[$idx51 + 1];
                            }
                        } else {
                            $release = '5.1';
                        }
                    }
                }
                $consoCnx = $global_platform->connections->where('inst_id', 1)->first();
                $conso_reports = $consoCnx ? $consoCnx->reports->pluck('id')->toArray() : [];
                foreach ($global_platform->credentials->whereIn('inst_id', $limit_insts) as $cred) {
                    if ($cred->status !== 'Enabled' || !$cred->institution->is_active) {
                        continue;
                    }
                    $report_ids = [];
                    foreach ($global_platform->connections as $cnx) {
                        if ($cnx->inst_id != 1 && $cnx->inst_id != $cred->inst_id) {
                            continue;
                        }
                        $report_ids = array_unique(array_merge($report_ids, $cnx->reports->pluck('id')->toArray()));
                    }
                    $reports = $master_reports->whereIn('id', $report_ids)->map(function ($rec) use ($conso_reports) {
                        $rec->source = in_array($rec->id, $conso_reports) ? 'C' : 'I';
                        return $rec;
                    });
                    foreach ($reports as $report) {
                        if (!in_array($report->name, $input['reports'])) {
                            continue;
                        }
                        $harvest = HarvestLog::where([
                            ['credentials_id', '=', $cred->id],
                            ['report_id', '=', $report->id],
                            ['yearmon', '=', $yearmon],
                        ])->first();
                        if ($harvest) {
                            if ($skip_harvested) {
                                continue;
                            }
                            $harvest->release = $release;
                            $harvest->attempts = 0;
                            $harvest->status = $state;
                            $harvest->save();
                            $updated_ids[] = $harvest->id;
                        } else {
                            $harvest = HarvestLog::create([
                                'status'         => $state,
                                'credentials_id' => $cred->id,
                                'release'        => $release,
                                'report_id'      => $report->id,
                                'yearmon'        => $yearmon,
                                'source'         => $report->source,
                                'attempts'       => 0,
                            ]);
                            $created_ids[] = $harvest->id;
                        }
                        if ($input['when'] === 'now') {
                            try {
                                GlobalQueueJob::create([
                                    'consortium_id' => $con->id,
                                    'harvest_id'    => $harvest->id,
                                    'replace_data'  => 1,
                                ]);
                                $num_queued++;
                            } catch (QueryException $e) {
                                $code = $e->errorInfo;
                                if ($code == '1062') {
                                    continue;
                                }
                                return $this->respondError("Failure adding Harvest ID: {$harvest->id} to Queue! Error {$code}");
                            }
                        }
                    }
                }
            }
        }
        $new = count($created_ids) > 0
            ? HarvestLog::whereIn('id', $created_ids)->with($this->harvestRelations())->get()->map(fn($rec) => $this->formatRecord($rec))->all()
            : [];
        $bounds = count($created_ids) > 0 ? $this->harvestBounds() : [];
        $updated = count($updated_ids) > 0
            ? HarvestLog::whereIn('id', $updated_ids)->with($this->harvestRelations())->get()->map(fn($rec) => $this->formatRecord($rec))->all()
            : [];
        $msg  = 'Success : ' . count($created_ids) . ' new harvests added, ' . count($updated_ids) . ' harvests updated';
        $msg .= ($num_queued > 0) ? ", and {$num_queued} queue jobs created." : '.';
        return response()->json([
            'result'       => true,
            'msg'          => $msg,
            'new_harvests' => $new,
            'upd_harvests' => $updated,
            'bounds'       => $bounds,
        ]);
    }
    /**
     * Bulk operations from the UI.
     */
    public function bulk(Request $request)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return $this->respondError('Request failed (403) - Forbidden');
        }
        $consoAdmin = $thisUser->isConsoAdmin();
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
        $credential_ids = Credential::when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_insts))
            ->pluck('id')
            ->toArray();
        $harvests = HarvestLog::with('report:id,name', 'credential', 'credential.institution', 'credential.provider', 'credential.provider.registries')
            ->whereIn('id', $input['ids'])
            ->whereIn('credentials_id', $credential_ids)
            ->orderBy('updated_at', 'DESC')
            ->get();
        $harvestIds = $harvests->pluck('id')->toArray();
        $con = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
        if (!$con) {
            return $this->respondError('Error: Corrupt session or consortium settings');
        }
        if ($input['action'] === 'Pause') {
            $affectedItems = [];
            foreach ($harvests as $harvest) {
                $harvest->status = 'Paused';
                $harvest->updated_at = now();
                $harvest->save();
                $affectedItems[] = $this->formatRecord($harvest);
            }
            return response()->json(['result' => true, 'msg' => '', 'affectedItems' => $affectedItems], 200);
        }
        if (in_array($input['action'], ['Restart', 'Restart as r5', 'Restart as r5.1'], true)) {
            $forceRelease = str_starts_with($input['action'], 'Restart as ') ? substr($input['action'], 11) : null;
            $changed = 0;
            $skipped = [];
            $affectedItems = [];
            foreach ($harvests as $harvest) {
                if (
                    $harvest->credential->status !== 'Enabled' ||
                    !$harvest->credential->institution->is_active ||
                    !$harvest->credential->provider->is_active
                ) {
                    $skipped[] = $harvest->id;
                    continue;
                }
                if (!is_null($forceRelease)) {
                    $registry = $harvest->credential->provider->registries->where('release', $forceRelease)->first();
                    if (!$registry) {
                        $skipped[] = $harvest->id;
                        continue;
                    }
                    if ($forceRelease !== trim($harvest->release)) {
                        $harvest->release = $forceRelease;
                    }
                }
                $existsInQueue = GlobalQueueJob::where('consortium_id', $con->id)
                    ->where('harvest_id', $harvest->id)
                    ->exists();
                if (!$existsInQueue) {
                    try {
                        GlobalQueueJob::create([
                            'consortium_id' => $con->id,
                            'harvest_id'    => $harvest->id,
                            'replace_data'  => 1,
                        ]);
                    } catch (\Exception $e) {
                        return $this->respondError('Error creating job entry in global table!');
                    }
                }
                $harvest->attempts = 0;
                $harvest->status = 'Queued';
                $harvest->updated_at = now();
                $harvest->save();
                $affectedItems[] = $this->formatRecord($harvest);
                $changed++;
            }
            if ($changed > 0) {
                $msg  = "Successfully restarted {$changed} harvests";
                $msg .= !is_null($forceRelease) ? " as release {$forceRelease}" : '';
                if (count($skipped) > 0) {
                    $msg .= ', and skipped ' . count($skipped) . ' harvests';
                    $msg .= !is_null($forceRelease) ? " (release {$forceRelease} may not be available)." : '.';
                }
                return response()->json(['result' => true, 'msg' => $msg, 'affectedItems' => $affectedItems], 200);
            }
            $msg = 'No selected harvests modified';
            $msg .= !is_null($forceRelease) ? ", release {$forceRelease} may not be available" : '';
            return response()->json(['result' => true, 'msg' => $msg, 'affectedItems' => []], 200);
        }
        if ($input['action'] === 'Delete' || $input['action'] === 'Kill') {
            GlobalQueueJob::where('consortium_id', $con->id)->whereIn('harvest_id', $harvestIds)->delete();
            $related_credential_ids = $this->deleteStoredData($harvestIds);
            HarvestLog::whereIn('id', $harvestIds)->delete();
            if (count($related_credential_ids) > 0) {
                $this->resetLastHarvest($related_credential_ids);
            }
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $harvestIds], 200);
        }
        return $this->respondError('Unrecognized bulk action requested');
    }
    /**
     * Download raw data for a harvest.
     */
    public function downloadRaw(Request $request, $id)
    {
        $harvest = HarvestLog::findOrFail($id);
        if (!$harvest->canManage()) {
            return $this->respondError('Error - Not authorized');
        }
        $con = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
        if (!$con) {
            return $this->respondError('Error: Current consortium is undefined.');
        }
        if (is_null(config('ccplus.reports_path'))) {
            return $this->respondError('System not configured to save raw data, check config value of CCP_REPORTS.');
        }
        $filename = config('ccplus.reports_path') . $con->id . '/';
        if ($harvest->status === 'Waiting') {
            $matches = glob("{$filename}0_unprocessed/{$harvest->id}_*");
            $filename = count($matches) > 0 ? $matches : '/xyzzy/not-found';
            $return_name = substr($filename, strrpos($filename, '/', 0) + 1);
        } else {
            $filename .= "{$harvest->credential->inst_id}/{$harvest->credential->prov_id}/" . ($harvest->rawfile ?? 'not-found');
            $return_name = $harvest->rawfile;
        }
        if (is_readable($filename)) {
            return response()->streamDownload(function () use ($filename) {
                echo bzdecompress(Crypt::decrypt(File::get($filename), false));
            }, $return_name, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $return_name . '"',
            ]);
        }
        return $this->respondError('Raw datafile is not accessible.');
    }
    /**
     * Remove the specified harvest record and its stored report data.
     */
    public function destroy($id)
    {
        $record = HarvestLog::findOrFail($id);
        if (!$record->canManage()) {
            return $this->respondError('Not authorized!');
        }
        GlobalQueueJob::where('harvest_id', $id)->delete();
        $related_credential_ids = $this->deleteStoredData([$id]);
        if (count($related_credential_ids) > 0) {
            $this->resetLastHarvest($related_credential_ids);
        }
        $record->delete();
        return $this->respondSuccess('Log record deleted successfully');
    }
    /**
     * Return Harvest Queue records for the current consortium.
     */
    public function harvestQueue(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
        $limit_status = ['Queued', 'Harvesting', 'Pending', 'Paused', 'ReQueued', 'Waiting', 'Processing'];
        $data = HarvestLog::with($this->harvestRelations())
            ->when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                return $qry->whereHas('credential', fn($q) => $q->whereIn('inst_id', $limit_insts));
            })
            ->whereIn('status', $limit_status)
            ->orderBy('updated_at', 'DESC')
            ->get();
        $truncated = $data->count() > 500;
        $harvests = $data->take(500)->map(fn($harvest) => $this->formatRecord($harvest))->all();
        $filter_options = [
            'codes' => [['id' => 0, 'name' => 'No Error']],
        ];
        $codes = $data->where('error_id', '>', 0)->unique('error_id')->sortBy('error_id')->pluck('error_id');
        foreach ($codes as $code) {
            $filter_options['codes'][] = ['id' => $code, 'name' => $code];
        }
        $filter_options['institutions'] = Institution::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
            return $qry->where('id', 1)->orWhereIn('id', $limit_insts);
        })->get(['id', 'name'])->toArray();
        $filter_options['platforms'] = GlobalProvider::get(['id', 'name'])->toArray();
        $filter_options['reports'] = Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name'])->toArray();
        $filter_options['statuses'] = [];
        foreach ($this->xlStatus as $key => $text) {
            if (in_array($key, $limit_status)) {
                $filter_options['statuses'][] = ['value' => $key, 'text' => $text];
            }
        }
        return response()->json([
            'records'   => $harvests,
            'options'   => $filter_options,
            'truncated' => $truncated,
        ], 200);
    }
    /**
     * Common eager-loading relationships for HarvestLog records.
     */
    private function harvestRelations(): array
    {
        return [
            'report:id,name',
            'credential',
            'credential.institution:id,name',
            'credential.provider',
            'lastError',
            'failedHarvests',
            'failedHarvests.ccplusError',
        ];
    }
    private function createYMarray(string $from, string $to): array
    {
        $range = [];
        $start = strtotime($from);
        $end = strtotime($to);
        if ($start > $end) {
            return $range;
        }
        while ($start <= $end) {
            $range[] = date('Y-m', $start);
            $start = strtotime('+1 month', $start);
        }
        return $range;
    }
    private function harvestBounds(): array
    {
        $result = HarvestLog::selectRaw('min(yearmon) as YM_min, max(yearmon) as YM_max')->get()->toArray();
        return [$result];
    }
    private function formatRecord($harvest): array
    {
        $rec = [
            'id'          => $harvest->id,
            'harvest_id'  => $harvest->id,
            'inst_id'     => $harvest->credential->inst_id,
            'prov_id'     => $harvest->credential->prov_id,
            'release'     => is_null($harvest->release) ? '' : $harvest->release,
            'yearmon'     => $harvest->yearmon,
            'attempts'    => $harvest->attempts,
            'inst_name'   => $harvest->credential->institution->name,
            'prov_name'   => $harvest->credential->provider->name,
            'report_id'   => $harvest->report->id,
            'report_name' => $harvest->report->name,
            'status'      => $harvest->status,
            'rawfile'     => $harvest->rawfile,
            'error_id'    => 0,
            'error'       => ['id' => $harvest->error_id, 'message' => ''],
            'failed'      => [],
        ];
        $rec['updated'] = $harvest->updated_at ? date('Y-m-d H:i', strtotime($harvest->updated_at)) : ' ';
        $rec['created'] = ($rec['updated'] !== ' ') ? $rec['updated'] : ' ';
        $rec['d_status'] = $this->xlStatus[$rec['status']] ?? $rec['status'];
        $lastFailed = $harvest->failedHarvests ? $harvest->failedHarvests->sortByDesc('created_at')->first() : null;
        if ($harvest->lastError) {
            $rec['error_id'] = $harvest->error_id;
            $rec['error'] = $harvest->lastError->toArray();
            $rec['error']['detail'] = '';
            $rec['error']['help_url'] = '';
            $rec['error']['process_step'] = '';
        } elseif ($lastFailed && $harvest->error_id > 0) {
            $rec['error_id'] = $lastFailed->error_id;
            if ($lastFailed->ccplusError) {
                $rec['error'] = $lastFailed->ccplusError->toArray();
            }
            $rec['error']['detail'] = $lastFailed->detail ?? '';
            $rec['error']['help_url'] = $lastFailed->help_url ?? '';
            $rec['error']['process_step'] = $lastFailed->process_step ?? '';
        }
        $rec['error']['known_error'] = in_array($rec['error_id'], $this->all_error_codes);
        $rec['error']['noretries'] = ($harvest->status === 'NoRetries');
        $rec['error']['color'] = $this->colors[$rec['status']] ?? 'secondary';
        $rec['error']['counter_url'] = ($harvest->release === '5.1')
            ? 'https://cop5.countermetrics.org/en/5.1/appendices/d-handling-errors-and-exceptions.html'
            : 'https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html';
        $beg = $harvest->yearmon . '-01';
        $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
        $rec['retryUrl'] = CounterApi::buildUri($beg, $end, $harvest->credential, $harvest->report, 'reports', $harvest->release);
        $rec['timeframes'] = [];
        $current_dt = new \DateTime();
        $updated_dt = new \DateTime($harvest->updated_at);
        $interval = $updated_dt->diff($current_dt);
        if ($interval->days < 7) {
            $rec['timeframes'][] = 'Last Week';
        }
        if ($interval->days < 1) {
            $rec['timeframes'][] = 'Last 24 Hours';
            if ($interval->h < 1) {
                $rec['timeframes'][] = 'Last Hour';
            }
        }
        return $rec;
    }
    private function deleteStoredData(array $harvest_ids): array
    {
        $reset_ids = [];
        $conso_db = config('database.connections.consodb.database');
        $harvests = HarvestLog::with('credential', 'report')->whereIn('id', $harvest_ids)->get();
        foreach ($harvests as $harvest) {
            if (!$harvest->report || !$harvest->credential) {
                continue;
            }
            $table = $conso_db . '.' . strtolower($harvest->report->name) . '_report_data';
            DB::table($table)
                ->where('inst_id', $harvest->credential->inst_id)
                ->where('prov_id', $harvest->credential->prov_id)
                ->where('yearmon', $harvest->yearmon)
                ->delete();
            if ($harvest->credential->last_harvest == $harvest->yearmon && !in_array($harvest->credentials_id, $reset_ids)) {
                $reset_ids[] = $harvest->credentials_id;
            }
        }
        return $reset_ids;
    }
    private function resetLastHarvest(array $cred_ids): void
    {
        $credentials = Credential::with('harvestLogs')->whereIn('id', $cred_ids)->get();
        foreach ($credentials as $cred) {
            $cred->last_harvest = $cred->harvestLogs->where('status', 'Success')->max('yearmon');
            $cred->save();
        }
    }
}
