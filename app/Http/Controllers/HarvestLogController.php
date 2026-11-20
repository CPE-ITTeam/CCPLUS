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
    private $xlStatus = [];
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

        $queueStatuses = ['New', 'Queued', 'Harvesting', 'Pending', 'Paused', 'ReQueued', 'Waiting', 'Processing'];
        $badCredCodes = [1030, 2000, 2010, 2020, 9100];
        $baseQuery = HarvestLog::query()
            ->whereNotIn('status', $queueStatuses)
            ->when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                $allowedCreds = Credential::whereIn('inst_id', $limit_insts)->pluck('id');
                return $qry->whereIn('credentials_id', $allowedCreds);
            });

        $successCount = (clone $baseQuery)->where('status', 'Success')->count();
        $badCredCount = (clone $baseQuery)
            ->where('status', '!=', 'Success')
            ->where(fn($q) => $q->where('status', 'BadCreds')->orWhereIn('error_id', $badCredCodes))
            ->count();
        $noRetriesCount = (clone $baseQuery)->where('status', 'NoRetries')->count();
        $otherCount = (clone $baseQuery)
            ->whereNotIn('status', ['Success', 'BadCreds', 'NoRetries'])
            ->where(fn($q) => $q->whereNull('error_id')->orWhereNotIn('error_id', $badCredCodes))
            ->count();

        $statusCounts = array_values(array_filter([
            $successCount > 0   ? ['status' => 'Success',   'label' => 'Success',         'color' => 'success',   'count' => $successCount]   : null,
            $badCredCount > 0   ? ['status' => 'BadCreds',  'label' => 'Bad Credentials', 'color' => 'warning',   'count' => $badCredCount]   : null,
            $noRetriesCount > 0 ? ['status' => 'NoRetries', 'label' => 'Out of Retries',  'color' => 'error',     'count' => $noRetriesCount] : null,
            $otherCount > 0     ? ['status' => 'Other',     'label' => 'Other',           'color' => 'secondary', 'count' => $otherCount]     : null,
        ]));
        $logTotal = $successCount + $badCredCount + $noRetriesCount + $otherCount;

        if ($request->boolean('summary_only')) {
            return response()->json([
                'result'        => true,
                'status_counts' => $statusCounts,
                'queue_total'   => $logTotal,
            ], 200);
        }

        $rawFilters = $request->input('filters', []);
        $filters = is_string($rawFilters) ? (json_decode($rawFilters, true) ?: []) : (array) $rawFilters;

        $groupInsts = null;
        if (!empty($filters['group']) || !empty($filters['groups'])) {
            $groupFilter = (array) ($filters['group'] ?? $filters['groups']);
            $groupInsts = InstitutionGroup::with('institutions:id')
                ->whereIn('id', $groupFilter)
                ->get()
                ->flatMap(fn($g) => $g->institutions->pluck('id'))
                ->unique()
                ->values()
                ->toArray();
        }

        $instFilter    = (array) ($filters['inst'] ?? $filters['institutions'] ?? []);
        $provFilter    = (array) ($filters['prov'] ?? $filters['platforms'] ?? []);
        $reptFilter    = (array) ($filters['rept'] ?? $filters['reports'] ?? []);
        $codeFilter    = array_map('intval', (array) ($filters['codes'] ?? []));
        $yymmFilter    = (array) ($filters['yymms'] ?? []);
        $rawUpd = $filters['updated'] ?? $filters['updates'] ?? [];
        if (is_array($rawUpd) && isset($rawUpd['id'])) {
            $updFilter = [$rawUpd['id']];
        } else {
            $updFilter = (array) $rawUpd;
        }
        $rawStatFilter = (array) ($filters['stat'] ?? $filters['statuses'] ?? []);

        $isConsoFilter = $filters['is_conso'] ?? 'All';
        $hasCredFilter = count($instFilter) > 0 || count($provFilter) > 0 || !is_null($groupInsts)
            || in_array($isConsoFilter, ['Active', 'NonConso'], true);

        $now = new \DateTime();
        $data = (clone $baseQuery)
            ->with($this->harvestRelations())
            ->when($hasCredFilter, function ($qry) use ($limit_insts, $instFilter, $provFilter, $groupInsts, $isConsoFilter) {
                $credIds = Credential::query()
                    ->when(count($limit_insts) > 0, fn($q) => $q->whereIn('inst_id', $limit_insts))
                    ->when(count($instFilter) > 0, fn($q) => $q->whereIn('inst_id', $instFilter))
                    ->when(count($provFilter) > 0, fn($q) => $q->whereIn('prov_id', $provFilter))
                    ->when(!is_null($groupInsts), fn($q) => $q->whereIn('inst_id', $groupInsts))
                    ->when(in_array($isConsoFilter, ['Active', 'NonConso'], true), function ($q) use ($isConsoFilter) {
                        $consoProvIds = Connection::where('inst_id', 1)->pluck('global_id');
                        return $isConsoFilter === 'Active'
                            ? $q->whereIn('prov_id', $consoProvIds)
                            : $q->whereNotIn('prov_id', $consoProvIds);
                    })
                    ->pluck('id');

                return $qry->whereIn('credentials_id', $credIds);
            })

            ->when(count($reptFilter) > 0, fn($qry) => $qry->whereIn('report_id', $reptFilter))
            ->when(count($rawStatFilter) > 0, function ($qry) use ($rawStatFilter, $badCredCodes) {
                return $qry->where(function ($sub) use ($rawStatFilter, $badCredCodes) {
                    foreach ($rawStatFilter as $label) {
                        if ($label === 'Success') {
                            $sub->orWhere('status', 'Success');
                        } elseif ($label === 'Bad Credentials') {
                            $sub->orWhere(function ($bc) use ($badCredCodes) {
                                $bc->where('status', '!=', 'Success')
                                    ->where(fn($q) => $q->where('status', 'BadCreds')->orWhereIn('error_id', $badCredCodes));
                            });
                        } elseif ($label === 'Out of Retries') {
                            $sub->orWhere('status', 'NoRetries');
                        } elseif ($label === 'Other') {
                            $sub->orWhere(function ($other) use ($badCredCodes) {
                                $other->whereNotIn('status', ['Success', 'BadCreds', 'NoRetries'])
                                    ->where(fn($q) => $q->whereNull('error_id')->orWhereNotIn('error_id', $badCredCodes));
                            });
                        } else {
                            $matchedKey = array_search($label, $this->xlStatus, true);
                            $sub->orWhere('status', $matchedKey !== false ? $matchedKey : $label);
                        }
                    }
                });
            })
            ->when(count($codeFilter) > 0, function ($qry) use ($codeFilter) {
                if (in_array(0, $codeFilter, true)) {
                    $nonZero = array_filter($codeFilter, fn($c) => $c !== 0);
                    return $qry->where(function ($q) use ($nonZero) {
                        $q->whereNull('error_id')->orWhere('error_id', 0);
                        if (count($nonZero) > 0) {
                            $q->orWhereIn('error_id', $nonZero);
                        }
                    });
                }
                return $qry->whereIn('error_id', $codeFilter);
            })
            ->when(count($yymmFilter) > 0, fn($qry) => $qry->whereIn('yearmon', $yymmFilter))
            ->when(!empty($filters['fromYM']), fn($qry) => $qry->where('yearmon', '>=', $filters['fromYM']))
            ->when(!empty($filters['toYM']), fn($qry) => $qry->where('yearmon', '<=', $filters['toYM']))
            ->when(count($updFilter) > 0, function ($qry) use ($updFilter) {
                $cutoff = null;
                foreach ($updFilter as $u) {
                    $label = is_array($u) ? ($u['id'] ?? $u['name'] ?? '') : $u;
                    $c = match ($label) {
                        'Last Hour'     => now()->subHour(),
                        'Last 6 Hours'  => now()->subHours(6),
                        'Today'         => now()->startOfDay(),
                        'Last 24 Hours' => now()->subDay(),
                        'Last Week'     => now()->subWeek(),
                        'Last 30 Days'  => now()->subDays(30),
                        'Last 90 Days'  => now()->subDays(90),
                        default         => null,
                    };
                    if ($c && (!$cutoff || $c->lt($cutoff))) {
                        $cutoff = $c;
                    }
                }
                return $cutoff ? $qry->where('updated_at', '>=', $cutoff) : $qry;
            })

            ->orderBy('updated_at', 'DESC')
            ->limit(2501)
            ->get();


        $truncated = $data->count() > 2500;
        $harvests = $data->take(2500)
            ->map(fn($harvest) => $this->formatRecord($harvest, $now))
            ->values()
            ->all();

        if ($request->boolean('json')) {
            return response()->json(['harvests' => $harvests], 200);
        }

        $logCredIds = (clone $baseQuery)->select('credentials_id')->distinct()->pluck('credentials_id');
        $logInstIds = Credential::whereIn('id', $logCredIds)->select('inst_id')->distinct()->pluck('inst_id');
        $logProvIds = Credential::whereIn('id', $logCredIds)->select('prov_id')->distinct()->pluck('prov_id');
        $logReptIds = (clone $baseQuery)->select('report_id')->distinct()->pluck('report_id');

        $institutions = Institution::where('id', '>', 1)
            ->whereIn('id', $logInstIds)
            ->when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->toArray();

        $groups = $consoAdmin
            ? InstitutionGroup::whereHas('institutions', fn($q) => $q->whereIn('institutions.id', $logInstIds))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->toArray()
            : [];

        $platforms = GlobalProvider::whereIn('id', $logProvIds)
            ->get(['id', 'name'])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->toArray();

        $reports = Report::where('parent_id', 0)
            ->whereIn('id', $logReptIds)
            ->orderBy('dorder', 'ASC')
            ->get(['id', 'name'])
            ->toArray();

        $updates = [
            ['id' => 'Last Hour',     'name' => 'Last Hour'],
            ['id' => 'Last 6 Hours',  'name' => 'Last 6 Hours'],
            ['id' => 'Today',         'name' => 'Today'],
            ['id' => 'Last 24 Hours', 'name' => 'Last 24 Hours'],
            ['id' => 'Last Week',     'name' => 'Last Week'],
            ['id' => 'Last 30 Days',  'name' => 'Last 30 Days'],
            ['id' => 'Last 90 Days',  'name' => 'Last 90 Days'],
        ];

        $yymms = (clone $baseQuery)
            ->select('yearmon')
            ->distinct()
            ->pluck('yearmon')
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn($ym) => ['id' => $ym, 'name' => $ym])
            ->all();

        $statuses = array_map(
            fn($sc) => ['id' => $sc['label'], 'name' => $sc['label']],
            $statusCounts
        );

        $hasNoError = (clone $baseQuery)
            ->where(fn($q) => $q->whereNull('error_id')->orWhere('error_id', 0))
            ->exists();
        $codes = $hasNoError ? [['id' => 0, 'name' => __('harvests.filters.no_error')]] : [];
        $err_ids = (clone $baseQuery)
            ->where('error_id', '>', 0)
            ->select('error_id')
            ->distinct()
            ->pluck('error_id')
            ->map(fn($c) => (int) $c)
            ->unique()
            ->sort()
            ->values();
        foreach ($err_ids as $code) {
            $codes[] = ['id' => $code, 'name' => (string) $code];
        }

        $bounds = $this->harvestBounds();

        return response()->json([
            'result'        => true,
            'records'       => $harvests,
            'truncated'     => $truncated,
            'bounds'        => $bounds,
            'status_counts' => $statusCounts,
            'queue_total'   => $logTotal,
            'options'       => [
                'platforms'    => $platforms,
                'institutions' => $institutions,
                'groups'       => $groups,
                'reports'      => $reports,
                'updates'      => $updates,
                'yymms'        => $yymms,
                'codes'        => $codes,
                'statuses'     => $statuses,
            ],
        ], 200);
    }

    public function getItems(Request $request)
    {
        return $this->index($request);
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

        if ($when === 'now' && $createdCount > 0 && function_exists('shell_exec')) {
            $running = trim((string) shell_exec('ps -eo args -ww | grep ' . escapeshellarg('ccplus:harvester') . ' | grep -v grep'));
            if ($running === '') {
                $artisan = base_path('artisan');
                $php = trim((string) shell_exec('which php')) ?: '/usr/bin/php';
                $setsid = is_executable('/usr/bin/setsid') ? '/usr/bin/setsid ' : '';
                $log = storage_path('logs/harvester.log');
                shell_exec(sprintf('%s%s %s ccplus:harvester >> %s 2>&1 < /dev/null &', $setsid, escapeshellarg($php), escapeshellarg($artisan), escapeshellarg($log)));
            }
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
        $rawIds = $input['ids'] ?? $input['harvests'] ?? [];
        $harvestIds = collect(is_array($rawIds) ? $rawIds : [])
            ->map(fn($item) => is_array($item) ? ($item['id'] ?? null) : $item)
            ->filter()
            ->values()
            ->all();
        $rawAction = $input['action'] ?? '';
        if (empty($rawAction) || count($harvestIds) === 0) {
            return $this->respondError(__('harvests.errors.invalid_bulk_inputs'));
        }

        $ccpKey = $request->header('X-Tenant') ?: session('ccp_key');
        $con = Consortium::where('ccp_key', $ccpKey)->first();
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

        $action = $rawAction;
        $forceRelease = filled($input['release'] ?? null) ? trim((string) $input['release']) : null;
        if ($rawAction === 'Restart as r5') {
            $action = 'Restart';
            $forceRelease = '5';
        } elseif ($rawAction === 'Restart as r5.1') {
            $action = 'Restart';
            $forceRelease = '5.1';
        }

        if ($action === 'Status') {
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

        if ($action === 'Restart') {
            $changed = 0;
            $skipped = [];
            $affectedItems = [];

            foreach ($harvests as $harvest) {
                $cred = $harvest->credential;
                if (
                    !$cred ||
                    $cred->status !== 'Enabled' ||
                    !$cred->institution?->is_active ||
                    !$cred->provider?->is_active
                ) {
                    $skipped[] = $harvest->id;
                    continue;
                }

                if (!is_null($forceRelease)) {
                    $registry = $cred->provider->registries
                        ->first(fn($r) => trim((string) $r->release) === $forceRelease);
                    if (!$registry) {
                        $skipped[] = $harvest->id;
                        continue;
                    }
                    if ($forceRelease !== trim((string) $harvest->release)) {
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
                $harvest->error_id = 0;
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

        if ($action === 'Delete' || $action === 'Kill') {
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
        $limit_status = ['New', 'Queued', 'Harvesting', 'Pending', 'ReQueued', 'Paused', 'Waiting', 'Processing'];
        $queueColors  = [
            'New'        => 'secondary',
            'Queued'     => 'secondary',
            'Harvesting' => 'info',
            'Pending'    => 'secondary',
            'ReQueued'   => 'warning',
            'Paused'     => 'error',
            'Waiting'    => 'secondary',
            'Processing' => 'info',
        ];
        $processStages = ['Waiting', 'Processing'];

        $baseQuery = HarvestLog::query()
            ->when(count($limit_insts) > 0, function ($qry) use ($limit_insts) {
                return $qry->whereHas('credential', fn($q) => $q->whereIn('inst_id', $limit_insts));
            })
            ->whereIn('status', $limit_status);

        $rawCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusCounts = [];
        foreach ($limit_status as $st) {
            $cnt = (int) ($rawCounts[$st] ?? 0);
            if ($cnt > 0 || in_array($st, ['Waiting', 'Processing'], true)) {
                $statusCounts[] = [
                    'status' => $st,
                    'stage'  => in_array($st, $processStages, true) ? 'process' : 'harvest',
                    'label'  => $this->xlStatus[$st] ?? $st,
                    'color'  => $queueColors[$st] ?? ($this->colors[$st] ?? 'secondary'),
                    'count'  => $cnt,
                ];
            }
        }


        $queueTotal = array_sum(array_column($statusCounts, 'count'));

        if ($request->boolean('summary_only')) {
            return response()->json([
                'result'        => true,
                'status_counts' => $statusCounts,
                'queue_total'   => $queueTotal,
            ], 200);
        }

        $filters = $request->input('filters', []);
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?: [];
        }

        $selectedLabels = (array) ($filters['statuses'] ?? []);
        $selectedStatuses = [];
        if (count($selectedLabels) > 0) {
            foreach ($limit_status as $dbKey) {
                $label = $this->xlStatus[$dbKey] ?? $dbKey;
                if (in_array($label, $selectedLabels, true) || in_array($dbKey, $selectedLabels, true)) {
                    $selectedStatuses[] = $dbKey;
                }
            }
        }

        $provFilter = (array) ($filters['platforms'] ?? $filters['prov'] ?? []);
        $instFilter = (array) ($filters['institutions'] ?? $filters['inst'] ?? []);
        $reptFilter = (array) ($filters['reports'] ?? $filters['rept'] ?? []);
        $yymmFilter = (array) ($filters['yymms'] ?? []);
        $codeFilter = array_map('intval', (array) ($filters['codes'] ?? []));

        $groupFilter = (array) ($filters['groups'] ?? $filters['group'] ?? []);
        $groupInsts = null;
        if (count($groupFilter) > 0) {
            $groupInsts = InstitutionGroup::with('institutions:id')
                ->whereIn('id', $groupFilter)
                ->get()
                ->flatMap(fn($g) => $g->institutions->pluck('id'))
                ->unique()
                ->values()
                ->toArray();
        }

        $isConsoFilter = $filters['is_conso'] ?? 'All';
        $hasCredFilter = count($provFilter) > 0 || count($instFilter) > 0 || !is_null($groupInsts)
            || in_array($isConsoFilter, ['Active', 'NonConso'], true);

        $now = new \DateTime();
        $data = (clone $baseQuery)
            ->when(count($selectedStatuses) > 0, fn($q) => $q->whereIn('status', $selectedStatuses))
            ->when($hasCredFilter, function ($qry) use ($provFilter, $instFilter, $groupInsts, $isConsoFilter) {
                $credIds = Credential::query()
                    ->when(count($provFilter) > 0, fn($q) => $q->whereIn('prov_id', $provFilter))
                    ->when(count($instFilter) > 0, fn($q) => $q->whereIn('inst_id', $instFilter))
                    ->when(!is_null($groupInsts), fn($q) => $q->whereIn('inst_id', $groupInsts))
                    ->when(in_array($isConsoFilter, ['Active', 'NonConso'], true), function ($q) use ($isConsoFilter) {
                        $consoProvIds = Connection::where('inst_id', 1)->pluck('global_id');
                        return $isConsoFilter === 'Active'
                            ? $q->whereIn('prov_id', $consoProvIds)
                            : $q->whereNotIn('prov_id', $consoProvIds);
                    })
                    ->pluck('id');
                return $qry->whereIn('credentials_id', $credIds);
            })

            ->when(count($reptFilter) > 0, fn($q) => $q->whereIn('report_id', $reptFilter))
            ->when(count($yymmFilter) > 0, fn($q) => $q->whereIn('yearmon', $yymmFilter))
            ->when(!empty($filters['fromYM']), fn($q) => $q->where('yearmon', '>=', $filters['fromYM']))
            ->when(!empty($filters['toYM']), fn($q) => $q->where('yearmon', '<=', $filters['toYM']))
            ->when(count($codeFilter) > 0, function ($qry) use ($codeFilter) {

                if (in_array(0, $codeFilter, true)) {
                    $nonZero = array_filter($codeFilter, fn($c) => $c !== 0);
                    return $qry->where(function ($q) use ($nonZero) {
                        $q->whereNull('error_id')->orWhere('error_id', 0);
                        if (count($nonZero) > 0) {
                            $q->orWhereIn('error_id', $nonZero);
                        }
                    });
                }
                return $qry->whereIn('error_id', $codeFilter);
            })
            ->with($this->harvestRelations())
            ->orderByRaw("FIELD(status, 'New', 'Queued', 'Waiting', 'Harvesting', 'Processing', 'Pending', 'ReQueued', 'Paused')")->orderBy('updated_at', 'DESC')
            ->limit(501)
            ->get();

        $truncated = $data->count() > 500;
        $harvests = $data->take(500)->map(fn($harvest) => $this->formatRecord($harvest, $now))->all();

        // Build filter options from the FULL queue (distinct values only)
        $fullCodes = (clone $baseQuery)
            ->where('error_id', '>', 0)
            ->select('error_id')
            ->distinct()
            ->pluck('error_id')
            ->map(fn($c) => (int) $c)
            ->unique()
            ->sort()
            ->values();

        $hasNoError = (clone $baseQuery)
            ->where(fn($q) => $q->whereNull('error_id')->orWhere('error_id', 0))
            ->exists();

        $filter_options = [
            'codes' => $hasNoError ? [['id' => 0, 'name' => __('harvests.filters.no_error')]] : [],
        ];
        foreach ($fullCodes as $code) {
            $filter_options['codes'][] = ['id' => $code, 'name' => (string) $code];
        }

        $queueCredIds = (clone $baseQuery)->select('credentials_id')->distinct()->pluck('credentials_id');
        $queueInstIds = Credential::whereIn('id', $queueCredIds)->select('inst_id')->distinct()->pluck('inst_id');
        $queueProvIds = Credential::whereIn('id', $queueCredIds)->select('prov_id')->distinct()->pluck('prov_id');
        $queueReptIds = (clone $baseQuery)->select('report_id')->distinct()->pluck('report_id');
        $queueYymms   = (clone $baseQuery)->select('yearmon')->distinct()->pluck('yearmon')->unique()->sortDesc()->values();

        $filter_options['institutions'] = Institution::whereIn('id', $queueInstIds)
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->toArray();

        $filter_options['groups'] = $consoAdmin
            ? InstitutionGroup::whereHas('institutions', fn($q) => $q->whereIn('institutions.id', $queueInstIds))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->toArray()
            : [];

        $filter_options['platforms'] = GlobalProvider::whereIn('id', $queueProvIds)
            ->get(['id', 'name'])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->toArray();

        $filter_options['reports'] = Report::whereIn('id', $queueReptIds)
            ->orderBy('dorder', 'ASC')
            ->get(['id', 'name'])
            ->toArray();

        $filter_options['yymms'] = $queueYymms
            ->map(fn($ym) => ['id' => $ym, 'name' => $ym])
            ->values()
            ->toArray();

        $filter_options['statuses'] = array_map(
            fn($sc) => ['id' => $sc['label'], 'name' => $sc['label']],
            $statusCounts
        );

        return response()->json([
            'result'        => true,
            'records'       => $harvests,
            'options'       => $filter_options,
            'truncated'     => $truncated,
            'status_counts' => $statusCounts,
            'queue_total'   => $queueTotal,
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

    private function resolveErrorThemeColor(int $errorId, ?array $errorData, string $fallbackColor): string
    {
        if ($errorId === 0) {
            return $fallbackColor;
        }

        if (in_array($errorId, [3030, 3050, 3060, 3061, 3062, 3063, 9030], true)) {
            return 'success';
        }
        if (in_array($errorId, [1030, 2000, 2010, 2020, 9100], true)) {
            return 'warning';
        }
        if (in_array($errorId, [1000, 1010, 1011, 1020, 3031], true)) {
            return 'info';
        }
        if (in_array($errorId, [9200, 9300], true)) {
            return 'secondary';
        }

        $newStatus = $errorData['new_status'] ?? '';
        return match ($newStatus) {
            'Success'             => 'success',
            'BadCreds'            => 'warning',
            'ReQueued', 'Pending' => 'info',
            'Fail'                => 'error',
            default               => match (strtoupper((string) ($errorData['color'] ?? ''))) {
                '#00DD00' => 'success',
                '#FF9900' => 'warning',
                '#39AAE0' => 'info',
                '#999999' => 'secondary',
                '#DD0000' => 'error',
                default   => $fallbackColor,
            },
        };
    }

    private function formatRecord($harvest, ?\DateTime $now = null): array
    {
        $dStatus = $this->xlStatus[$harvest->status] ?? $harvest->status;
        $statusColor = $this->colors[$harvest->status] ?? 'secondary';

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
            'd_status'    => $dStatus,
            'rawfile'     => $harvest->rawfile,
            'error_id'    => (int) ($harvest->error_id ?? 0),
            'error'       => [
                'id'      => (int) ($harvest->error_id ?? 0),
                'message' => '',
            ],
            'failed'      => [],
        ];

        $rec['updated'] = $harvest->updated_at ? $harvest->updated_at->toIso8601String() : '';
        $rec['created'] = $harvest->created_at ? $harvest->created_at->toIso8601String() : '';


        $lastFailed = $harvest->failedHarvests ? $harvest->failedHarvests->sortByDesc('created_at')->first() : null;
        if ($harvest->lastError) {
            $rec['error_id'] = (int) $harvest->error_id;
            $rec['error'] = $harvest->lastError->toArray();
            $rec['error']['detail'] = '';
            $rec['error']['help_url'] = '';
            $rec['error']['process_step'] = '';
        } elseif ($lastFailed && $harvest->error_id > 0) {
            $rec['error_id'] = (int) $lastFailed->error_id;
            if ($lastFailed->ccplusError) {
                $rec['error'] = $lastFailed->ccplusError->toArray();
            }
            $rec['error']['detail'] = $lastFailed->detail ?? '';
            $rec['error']['help_url'] = $lastFailed->help_url ?? '';
            $rec['error']['process_step'] = $lastFailed->process_step ?? '';
        }

        $resultColor = $this->resolveErrorThemeColor((int) $rec['error_id'], $rec['error'], $statusColor);

        $rec['error']['known_error'] = in_array($rec['error_id'], $this->all_error_codes);
        $rec['error']['noretries'] = ($harvest->status === 'NoRetries');
        $rec['error']['color'] = $resultColor;
        $rec['error']['counter_url'] = ($harvest->release === '5.1')
            ? 'https://countermetrics.org'
            : 'https://projectcounter.org';

        $beg = $harvest->yearmon . '-01';
        $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
        $rec['retryUrl'] = CounterApi::buildUri($beg, $end, $harvest->credential, $harvest->report, 'reports', $harvest->release);

        $rec['timeframes'] = [];
        if ($harvest->updated_at) {
            $currentDt   = $now ?? new \DateTime();
            $updatedDt   = new \DateTime($harvest->updated_at);
            $diffSeconds = max(0, $currentDt->getTimestamp() - $updatedDt->getTimestamp());

            $windows = [
                'Last Hour'     => 3600,
                'Last 6 Hours'  => 6 * 3600,
                'Last 24 Hours' => 86400,
                'Last Week'     => 7 * 86400,
                'Last 30 Days'  => 30 * 86400,
                'Last 90 Days'  => 90 * 86400,
            ];

            foreach ($windows as $label => $maxSeconds) {
                if ($diffSeconds <= $maxSeconds) {
                    $rec['timeframes'][] = $label;
                }
            }

            if ($updatedDt->format('Y-m-d') === $currentDt->format('Y-m-d')) {
                $rec['timeframes'][] = 'Today';
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
