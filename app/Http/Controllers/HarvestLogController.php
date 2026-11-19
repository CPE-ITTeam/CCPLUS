<?php

namespace App\Http\Controllers;

use App\Models\CcplusError;
use App\Models\ConnectionField;
use App\Models\Consortium;
use App\Models\CounterApi;
use App\Models\Credential;
use App\Models\GlobalProvider;
use App\Models\GlobalQueueJob;
use App\Models\HarvestLog;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Connection;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;


class HarvestLogController extends BaseController
{
    private array $colors = [];
    private array $xlStatus = [];
    private array $all_error_codes = [];

    public function __construct()
    {
        $this->middleware(['auth']);
        $this->colors = config('ccplus.colors', []);
        $this->xlStatus = __('harvests.statuses');
        $this->all_error_codes = CcplusError::pluck('id')->toArray();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();

        $json = $request->boolean('json') || $request->wantsJson() || $request->is('api/*');
        $filters = json_decode($request->input('filters', '{}'), true) ?: [];
        $groupInsts = null;
        if (!empty($filters['group'])) {
            $groupInsts = InstitutionGroup::with('institutions:id')
                ->find($filters['group'])
                ?->institutions->pluck('id')->toArray() ?? [];
        }

        $hasCredFilter = count($limit_insts) > 0
            || !empty($filters['inst'])
            || !empty($filters['prov'])
            || !is_null($groupInsts);

        $now = new \DateTime();
        $harvests = HarvestLog::with($this->harvestRelations())
            ->when($hasCredFilter, function ($qry) use ($limit_insts, $filters, $groupInsts) {
                $credIds = Credential::query()
                    ->when(count($limit_insts) > 0, fn($q) => $q->whereIn('inst_id', $limit_insts))
                    ->when(!empty($filters['inst']), fn($q) => $q->whereIn('inst_id', $filters['inst']))
                    ->when(!empty($filters['prov']), fn($q) => $q->whereIn('prov_id', $filters['prov']))
                    ->when(!is_null($groupInsts), fn($q) => $q->whereIn('inst_id', $groupInsts))
                    ->pluck('id');

                return $qry->whereIn('credentials_id', $credIds);
            })
            ->when(!empty($filters['rept']), fn($qry) => $qry->whereIn('report_id', $filters['rept']))
            ->when(!empty($filters['stat']), fn($qry) => $qry->whereIn('status', $filters['stat']))
            ->when(!empty($filters['codes']), fn($qry) => $qry->whereIn('error_id', $filters['codes']))
            ->when(!empty($filters['fromYM']), fn($qry) => $qry->where('yearmon', '>=', $filters['fromYM']))
            ->when(!empty($filters['toYM']), fn($qry) => $qry->where('yearmon', '<=', $filters['toYM']))
            ->when(!empty($filters['updated']), function ($qry) use ($filters) {
                $cutoff = match ($filters['updated']) {
                    'Last Hour'     => now()->subHour(),
                    'Last 24 Hours' => now()->subDay(),
                    'Last Week'     => now()->subWeek(),
                    default         => null,
                };
                return $cutoff ? $qry->where('updated_at', '>=', $cutoff) : $qry;
            })
            ->orderBy('updated_at', 'DESC')
            ->limit(2500)
            ->get()
            ->map(fn($harvest) => $this->formatRecord($harvest, $now))
            ->values()
            ->all();

        if ($request->boolean('json')) {
            return response()->json(['harvests' => $harvests], 200);
        }

        // Initial page / DatasetViewer load setup
        $institutions = Institution::when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
            return $qry->where('id', 1)->orWhereIn('id', $limit_insts);
        })->orderBy('name', 'ASC')->get(['id', 'name'])->toArray();

        $inst_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['id', 'name'])->toArray();
        $providers = GlobalProvider::orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->toArray();

        $reports = Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name'])->toArray();
        $bounds = $this->harvestBounds();
        $statuses = [];
        foreach ($this->xlStatus as $key => $text) {
            $statuses[] = ['value' => $key, 'text' => $text];
        }

        $codes = [['id' => 0, 'name' => __('harvests.filters.no_error')]];
        $err_ids = HarvestLog::where('error_id', '>', 0)->distinct()->orderBy('error_id', 'ASC')->pluck('error_id');
        foreach ($err_ids as $code) {
            $codes[] = ['id' => $code, 'name' => $code];
        }

        return response()->json([
            'result'       => true,
            'records'      => $harvests,
            'harvests'     => $harvests,
            'institutions' => $institutions,
            'inst_groups'  => $inst_groups,
            'providers'    => $providers,
            'reports'      => $reports,
            'bounds'       => $bounds,
            'statuses'     => $statuses,
            'codes'        => $codes,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();

        $institutions = Institution::where('id', '>', 1)
            ->where('is_active', true)
            ->when(count($limit_insts) > 0, fn($q) => $q->whereIn('id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->toArray();

        $allGroups = InstitutionGroup::with('institutions:id')
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        $inst_groups = $consoAdmin ? $allGroups->toArray() : [];
        $groupInstMap = $allGroups->mapWithKeys(
            fn($g) => [$g->id => $g->institutions->pluck('id')->map(fn($id) => (int) $id)->all()]
        );

        $master_reports = Report::where('parent_id', 0)
            ->orderBy('dorder', 'ASC')
            ->get(['id', 'name', 'legend']);

        $connections = Connection::with('reports:id,name')
            ->where('is_active', true)
            ->get();

        $cnxByGlobal = $connections->groupBy('global_id');

        $platforms = GlobalProvider::where('is_active', true)
            ->whereIn('id', $cnxByGlobal->keys())
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->map(function ($gp) use ($cnxByGlobal, $master_reports, $groupInstMap) {
                $gpCnxs = $cnxByGlobal->get($gp->id, collect());
                $instSet = [];
                $reportMap = [];

                foreach ($master_reports as $rpt) {
                    $reportMap[$rpt->name] = ['conso' => false, 'insts' => []];
                }

                foreach ($gpCnxs as $cnx) {
                    $cnxInsts = [];
                    if ($cnx->inst_id == 1) {
                        $instSet[1] = true;
                    } elseif (!is_null($cnx->inst_id)) {
                        $instSet[$cnx->inst_id] = true;
                        $cnxInsts[] = (int) $cnx->inst_id;
                    } elseif (!is_null($cnx->group_id)) {
                        foreach ($groupInstMap->get($cnx->group_id, []) as $gInstId) {
                            $instSet[$gInstId] = true;
                            $cnxInsts[] = $gInstId;
                        }
                    }

                    foreach ($cnx->reports as $rpt) {
                        if (!isset($reportMap[$rpt->name])) {
                            continue;
                        }
                        if ($cnx->inst_id == 1) {
                            $reportMap[$rpt->name]['conso'] = true;
                        } else {
                            $reportMap[$rpt->name]['insts'] = array_values(
                                array_unique(array_merge($reportMap[$rpt->name]['insts'], $cnxInsts))
                            );
                        }
                    }
                }

                return [
                    'id'           => $gp->id,
                    'name'         => $gp->name,
                    'institutions' => array_map('intval', array_keys($instSet)),
                    'reports'      => $reportMap,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->toArray();

        $fyName = $thisUser->fiscalYr
            ?: Consortium::where('ccp_key', session('ccp_key'))->value('fiscalYr');
        $fyMo = $fyName ? (int) date('n', strtotime($fyName . ' 1')) : null;

        return response()->json([
            'result'  => true,
            'records' => [
                'institutions'   => $institutions,
                'groups'         => $inst_groups,
                'platforms'      => $platforms,
                'master_reports' => $master_reports->toArray(),
                'fields'         => [],
                'fyMo'           => $fyMo,
            ],
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);

        $input = $request->all();
        if (empty($input['fromYM']) || empty($input['toYM'])) {
            return $this->respondError(__('harvests.errors.invalid_date_range'));
        }

        $ccpKey = $request->header('X-Tenant') ?: session('ccp_key');
        $con = Consortium::where('ccp_key', $ccpKey)->first();
        if (!$con) {
            return $this->respondError(__('harvests.errors.missing_consortium'));
        }

        $provIds = $input['plat'] ?? $input['prov'] ?? [];
        $instIds = $input['inst'] ?? [];
        $groupId = $input['inst_group_id'] ?? $input['inst_group'] ?? 0;
        if (!empty($groupId)) {
            $group = InstitutionGroup::with('institutions:id')->find($groupId);
            if ($group) {
                $instIds = $group->institutions->pluck('id')->toArray();
            }
        }

        if (!$thisUser->isConsoAdmin()) {
            $allowedInsts = $thisUser->adminInsts();
            $instIds = count($instIds) > 0
                ? array_values(array_intersect($instIds, $allowedInsts))
                : $allowedInsts;
        }

        $credentials = Credential::with([
                'institution',
                'provider',
                'provider.registries',
                'provider.connections',
                'provider.connections.reports',
            ])
            ->whereIn('prov_id', $provIds)
            ->when(count($instIds) > 0, fn($q) => $q->whereIn('inst_id', $instIds))
            ->where('status', 'Enabled')
            ->get();

        if ($credentials->isEmpty()) {
            return $this->respondError(__('harvests.errors.missing_credentials'));
        }

        $reportInputs = $input['reports'] ?? [];
        $reports = Report::where('parent_id', 0)
            ->where(function ($q) use ($reportInputs) {
                $q->whereIn('id', $reportInputs)->orWhereIn('name', $reportInputs);
            })
            ->get()
            ->keyBy('id');

        if ($reports->isEmpty()) {
            return $this->respondError(__('harvests.errors.missing_reports'));
        }

        $ymRange = $this->createYMarray($input['fromYM'], $input['toYM']);
        $when = $input['when'] ?? 'later';
        $release = $input['release'] ?? null;
        $skipHarvested = !empty($input['skip_harvested']);
        $createdCount = 0;
        $skippedIncomplete = 0;
        $messages = [];

        foreach ($credentials as $cred) {
            if (!$cred->institution?->is_active || !$cred->provider?->is_active) {
                continue;
            }

            if (!$cred->isComplete()) {
                $skippedIncomplete++;
                continue;
            }

            $enabledReports = $cred->provider->enabledReports($cred->inst_id);
            $useRelease = $release ?: ($cred->provider->default_registry()?->release ?? '5.1');

            foreach ($reports as $repId => $report) {
                $rptFlags = $enabledReports[$report->name] ?? null;
                if (!$rptFlags || empty($rptFlags['available']) || (empty($rptFlags['conso']) && empty($rptFlags['requested']))) {
                    continue;
                }

                foreach ($ymRange as $ym) {
                    if ($skipHarvested) {
                        $exists = HarvestLog::where('credentials_id', $cred->id)
                            ->where('report_id', $repId)
                            ->where('yearmon', $ym)
                            ->where('status', 'Success')
                            ->exists();
                        if ($exists) {
                            continue;
                        }
                    }

                    try {
                        $harvest = HarvestLog::create([
                            'status'         => ($when === 'now') ? 'Queued' : 'New',
                            'credentials_id' => $cred->id,
                            'report_id'      => $repId,
                            'release'        => $useRelease,
                            'yearmon'        => $ym,
                            'source'         => 'M',
                            'attempts'       => 0,
                        ]);

                        if ($when === 'now') {
                            GlobalQueueJob::create([
                                'consortium_id' => $con->id,
                                'harvest_id'    => $harvest->id,
                                'replace_data'  => 1,
                            ]);
                        }
                        $createdCount++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        $code = Arr::get($e->errorInfo, 1, '');
                        if ($code == 1062) {
                            $messages[] = __('harvests.errors.duplicate_skipped', [
                                'report'  => $report->name,
                                'yearmon' => $ym,
                            ]);
                        } else {
                            return $this->respondError(__('harvests.errors.create_record_failed', ['error' => $e->getMessage()]));
                        }
                    }
                }
            }
        }

        if ($createdCount === 0) {
            if ($skippedIncomplete > 0) {
                return $this->respondError(__('harvests.errors.incomplete_credentials'));
            }
            return $this->respondError(__('harvests.errors.no_records_created'));
        }

        return response()->json([
            'result'   => true,
            'msg'      => __('harvests.messages.created_count', ['count' => $createdCount]),
            'messages' => $messages,
        ], 200);
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $harvest = HarvestLog::with($this->harvestRelations())->findOrFail($id);
        abort_unless($harvest->canManage(), 403);
        return response()->json(['harvest' => $this->formatRecord($harvest)], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $harvest = HarvestLog::with($this->harvestRelations())->findOrFail($id);
        abort_unless($harvest->canManage(), 403);
        $record = $this->formatRecord($harvest);
        $statuses = [];
        foreach ($this->xlStatus as $key => $text) {
            $statuses[] = ['value' => $key, 'text' => $text];
        }
        return view('harvests.edit', compact('record', 'statuses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $harvest = HarvestLog::with($this->harvestRelations())->findOrFail($id);
        if (!$harvest->canManage()) {
            return $this->respondError(__('harvests.errors.not_authorized'));
        }

        $input = $request->all();
        if (isset($input['status']) && array_key_exists($input['status'], $this->xlStatus)) {
            $harvest->status = $input['status'];
        }
        if (isset($input['release'])) {
            $harvest->release = $input['release'];
        }
        if (isset($input['attempts'])) {
            $harvest->attempts = (int) $input['attempts'];
        }

        try {
            $harvest->save();
        } catch (\Exception $e) {
            return $this->respondError(__('harvests.errors.update_failed', ['error' => $e->getMessage()]));
        }

        return response()->json([
            'result'  => true,
            'msg'     => __('harvests.messages.updated'),
            'harvest' => $this->formatRecord($harvest->fresh($this->harvestRelations())),
        ], 200);
    }

    /**
     * Perform bulk status updates, restarts, or deletions on selected harvests.
     */
    public function bulkAction(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isAdmin(), 403);

        $input = $request->all();
        $harvestIds = $input['harvests'] ?? [];
        if (empty($input['action']) || !is_array($harvestIds) || count($harvestIds) === 0) {
            return $this->respondError(__('harvests.errors.invalid_bulk_inputs'));
        }

        $con = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
        if (!$con) {
            return $this->respondError(__('harvests.errors.consortium_undefined'));
        }

        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
        $harvests = HarvestLog::with($this->harvestRelations())
            ->whereIn('id', $harvestIds)
            ->when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                return $qry->whereHas('credential', fn($q) => $q->whereIn('inst_id', $limit_insts));
            })
            ->get();

        $harvestIds = $harvests->pluck('id')->toArray();

        if ($input['action'] === 'Status') {
            $newStatus = $input['status'] ?? '';
            if (!array_key_exists($newStatus, $this->xlStatus)) {
                return $this->respondError(__('harvests.errors.invalid_bulk_status'));
            }
            $affectedItems = [];
            foreach ($harvests as $harvest) {
                $harvest->status = $newStatus;
                $harvest->updated_at = now();
                $harvest->save();
                $affectedItems[] = $this->formatRecord($harvest);
            }
            return response()->json([
                'result'        => true,
                'msg'           => __('harvests.messages.updated'),
                'affectedItems' => $affectedItems,
            ], 200);
        }

        if ($input['action'] === 'Restart') {
            $forceRelease = $input['release'] ?? null;
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
                        return $this->respondError(__('harvests.errors.global_job_create_fail'));
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
                $msg  = __('harvests.messages.restarted_count', ['count' => $changed]);
                $msg .= !is_null($forceRelease) ? __('harvests.messages.as_release', ['release' => $forceRelease]) : '';
                if (count($skipped) > 0) {
                    $msg .= __('harvests.messages.skipped_count', ['count' => count($skipped)]);
                    $msg .= !is_null($forceRelease) ? __('harvests.messages.release_unavailable', ['release' => $forceRelease]) : '.';
                }
                return response()->json(['result' => true, 'msg' => $msg, 'affectedItems' => $affectedItems], 200);
            }

            $msg = __('harvests.messages.no_harvests_changed');
            $msg .= !is_null($forceRelease) ? __('harvests.messages.no_harvests_release', ['release' => $forceRelease]) : '';
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

        return $this->respondError(__('harvests.errors.unknown_bulk_action'));
    }

    /**
     * Download raw data for a harvest.
     */
    public function downloadRaw(Request $request, $id)
    {
        $harvest = HarvestLog::findOrFail($id);
        if (!$harvest->canManage()) {
            return $this->respondError(__('harvests.errors.not_authorized_hyphen'));
        }

        $con = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
        if (!$con) {
            return $this->respondError(__('harvests.errors.consortium_undefined'));
        }

        if (is_null(config('ccplus.reports_path'))) {
            return $this->respondError(__('harvests.errors.reports_path_unset'));
        }

        $filename = config('ccplus.reports_path') . $con->id . '/';
        if ($harvest->status === 'Waiting') {
            $matches = glob("{$filename}0_unprocessed/{$harvest->id}_*");
            $filename = (is_array($matches) && count($matches) > 0) ? reset($matches) : '/xyzzy/not-found';
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

        return $this->respondError(__('harvests.errors.raw_file_inaccessible'));
    }

    /**
     * Remove the specified harvest record and its stored report data.
     */
    public function destroy($id)
    {
        $record = HarvestLog::findOrFail($id);
        if (!$record->canManage()) {
            return $this->respondError(__('harvests.errors.not_authorized'));
        }

        GlobalQueueJob::where('harvest_id', $id)->delete();
        $related_credential_ids = $this->deleteStoredData([$id]);
        if (count($related_credential_ids) > 0) {
            $this->resetLastHarvest($related_credential_ids);
        }
        $record->delete();

        return $this->respondSuccess(__('harvests.messages.deleted'));
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

        $now = new \DateTime();
        $data = HarvestLog::with($this->harvestRelations())
            ->when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                return $qry->whereHas('credential', fn($q) => $q->whereIn('inst_id', $limit_insts));
            })
            ->whereIn('status', $limit_status)
            ->orderBy('updated_at', 'DESC')
            ->limit(501)
            ->get();

        $truncated = $data->count() > 500;
        $harvests = $data->take(500)->map(fn($harvest) => $this->formatRecord($harvest, $now))->all();

        $filter_options = [
            'codes' => [['id' => 0, 'name' => __('harvests.filters.no_error')]],
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
            'credential.provider.registries',
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

    private function formatRecord($harvest, ?\DateTime $now = null): array
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
            ? 'https://countermetrics.org'
            : 'https://projectcounter.org';

        $beg = $harvest->yearmon . '-01';
        $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
        $rec['retryUrl'] = CounterApi::buildUri($beg, $end, $harvest->credential, $harvest->report, 'reports', $harvest->release);

        $rec['timeframes'] = [];
        $current_dt = $now ?? new \DateTime();
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
