<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\GlobalProvider;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ConnectionController extends BaseController
{
    /**
     * Return provider connection data as JSON response.
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();

        $groupIds = [];
        if (!$thisUser->isAdmin()) {
            return $this->respondError(__('connections.forbidden_request'));
        }
        if (!$consoAdmin) {
            $groupIds = $thisUser->adminGroups();
        }

        $globals = GlobalProvider::with('connections', 'connections.reports')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get();

        $nh_count = 0;
        $seen_codes = array(0);
        $connections = [];

        foreach ($globals as $global) {
            $rec = [
                'id'         => $global->id,
                'platform'   => $global->name,
                'can_edit'   => false,
                'can_delete' => false,
                'reports'    => $global->master_reports(),
                'result'     => null,
            ];

            $lastHarvest = $global->lastHarvest();
            if ($lastHarvest) {
                $rec['result'] = ($lastHarvest->status === 'Success' || $lastHarvest->error_id == 0)
                    ? 'Success'
                    : $lastHarvest->error_id;
                if ($lastHarvest->error_id > 0 && !in_array($lastHarvest->error_id, $seen_codes)) {
                    $seen_codes[] = $lastHarvest->error_id;
                }
            } else {
                $nh_count++;
                $rec['result'] = 'No Harvests';
            }

            if ($global->connections->count() > 0) {
                if ($consoAdmin) {
                    $rec['can_delete'] = true;
                } else {
                    foreach ($global->connections as $cnx) {
                        if ($cnx->canManage()) {
                            $rec['can_delete'] = true;
                            break;
                        }
                    }
                }
            }

            $enabledReports = $global->enabledReports();
            foreach ($enabledReports as $name => $rpt) {
                $prefix = strtolower($name);
                if ($rpt['conso'] || in_array(1, $rpt['insts'])) {
                    $rec[$prefix . '_insts'] = array(1);
                    $rec[$prefix . '_groups'] = [];
                } else {
                    $rec[$prefix . '_insts'] = $rpt['insts'];
                    $rec[$prefix . '_groups'] = $rpt['groups'];
                }

                $rec[$name] = $rpt;
                $cnxCount = count($rpt['insts']) + count($rpt['groups']);
                $rec[$name]['requested'] = ($cnxCount > 0);

                if (!$rec[$name]['available']) {
                    $rec[$name]['sortval'] = 4;
                } elseif ($rec[$name]['conso']) {
                    $rec[$name]['sortval'] = 1;
                } else {
                    $rec[$name]['sortval'] = $rec[$name]['requested'] ? 3 : 2;
                }
            }

            $connections[] = $rec;
        }

        $options = ['results' => []];
        sort($seen_codes);
        foreach ($seen_codes as $code) {
            $options['results'][] = ['result' => ($code > 0) ? $code : 'Success'];
        }
        if ($nh_count > 0) {
            $options['results'][] = ['result' => 'No Harvests'];
        }

        if ($consoAdmin) {
            $options['institutions'] = Institution::where('is_active', 1)->get(['id', 'name']);
            $options['groups'] = InstitutionGroup::get(['id', 'name']);
        } else {
            $inst_ids = $thisUser->adminInsts();
            $options['institutions'] = Institution::where('is_active', 1)->whereIn('id', $inst_ids)->get(['id', 'name']);
            $options['groups'] = InstitutionGroup::whereIn('id', $groupIds)->get(['id', 'name']);
        }
        $options['reports'] = Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name']);

        return response()->json(['records' => $connections, 'options' => $options, 'result' => true], 200);
    }

    /**
     * Set/update report access and availability.
     */
    public function access(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();

        $this->validate($request, ['id' => 'required', 'rept' => 'required', 'flags' => 'required']);
        $input = $request->all();

        $flags = $input['flags'];
        if (!isset($flags['conso']) || !isset($flags['available'])) {
            return $this->respondError(__('connections.invalid_access_values'));
        }

        $report = Report::where('name', $input['rept'])->first(['id', 'name']);
        if (!$report) {
            return $this->respondError(__('connections.unknown_report'));
        }

        $gp = GlobalProvider::with('connections', 'connections.reports')->where('id', $input['id'])->first();
        if (!$gp) {
            return $this->respondError(__('connections.platform_ref_error'));
        }

        $newCnx = ['global_id' => $gp->id, 'is_active' => $gp->is_active, 'inst_id' => null, 'group_id' => null];
        $consoCnx = $gp->connections->where('inst_id', 1)->first();
        $conso_ids = $consoCnx ? $consoCnx->reports->pluck('id')->toArray() : [];
        $adminInsts = $thisUser->adminInsts();
        $adminGroups = $thisUser->adminGroups();

        if ($consoAdmin && $flags['conso']) {
            if (!$consoCnx) {
                $newCnx['inst_id'] = 1;
                $consoCnx = Connection::create($newCnx);
            }
            if (!in_array($report->id, $conso_ids)) {
                $consoCnx->reports()->attach($report->id);
                $conso_ids = $consoCnx->reports->pluck('id')->toArray();
            }
            $gp->updateReports($conso_ids, 'detach');

            $flags['insts'] = array(1);
            $flags['groups'] = [];
        } else {
            foreach ($flags['insts'] as $inst) {
                if ($consoAdmin || in_array($inst, $adminInsts)) {
                    $cnx = $gp->connections->where('inst_id', $inst)->first();
                    if (!$cnx) {
                        $newCnx['inst_id'] = $inst;
                        $cnx = Connection::create($newCnx);
                    }
                    if (!in_array($report->id, $cnx->reports->pluck('id')->toArray())) {
                        $cnx->reports()->attach($report->id);
                    }
                }
            }

            $newCnx['inst_id'] = null;
            foreach ($flags['groups'] as $group) {
                if ($consoAdmin || in_array($group, $adminGroups)) {
                    $cnx = $gp->connections->where('group_id', $group)->first();
                    if (!$cnx) {
                        $newCnx['group_id'] = $group;
                        $cnx = Connection::create($newCnx);
                    }
                    if (!in_array($report->id, $cnx->reports->pluck('id')->toArray())) {
                        $cnx->reports()->attach($report->id);
                    }
                }
            }
        }

        $flags['requested'] = (!$flags['conso'] && (count($flags['insts']) + count($flags['groups'])) > 0);

        $current_insts = $gp->connectedInstitutions();
        $current_groups = $gp->connectedGroups();
        $del_insts = $consoAdmin ? array_diff($current_insts, $flags['insts']) : array_diff($adminInsts, $flags['insts']);
        $del_groups = $consoAdmin ? array_diff($current_groups, $flags['groups']) : array_diff($adminGroups, $flags['groups']);

        foreach ($gp->connections as $cnx) {
            if (!$cnx->canManage()) {
                continue;
            }
            if (!is_null($cnx->inst_id)) {
                if (in_array($cnx->inst_id, $del_insts)) {
                    if ($cnx->inst_id == 1) {
                        $gp->updateReports([$report->id], 'attach');
                    }
                    $cnx->reports()->detach($report->id);
                }
            } elseif (!is_null($cnx->group_id)) {
                if (in_array($cnx->group_id, $del_groups)) {
                    $cnx->reports()->detach($report->id);
                }
            }
            if ($cnx->reports()->count() === 0) {
                $cnx->delete();
            }
        }

        return $this->respondSuccess(__('connections.access_updated'), $flags);
    }

    /**
     * Remove connections to a given platform.
     */
    public function destroy($id)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        $platform = GlobalProvider::with('connections')->findOrFail($id);

        $num_deleted = 0;
        foreach ($platform->connections as $cnx) {
            if ($cnx->canManage()) {
                $cnx->delete();
                $num_deleted++;
            }
        }

        if ($num_deleted === 0) {
            return $this->respondError(__('connections.none_deleted'));
        }

        $msg = ($num_deleted === 1)
            ? __('connections.deleted_single')
            : __('connections.deleted_multiple', ['count' => $num_deleted]);

        return $this->respondSuccess($msg);
    }

    /**
     * Import connections from a CSV to the database.
     */
    public function import(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        $parsed = $this->parseCsvUpload($request);
        if (isset($parsed['error'])) {
            return $this->respondError($parsed['error']);
        }

        $rows = $parsed['rows'];
        $consoAdmin = $thisUser->isConsoAdmin();
        $adminInsts = $thisUser->adminInsts();
        $adminGroups = $thisUser->adminGroups();
        $all_platforms = GlobalProvider::get(['id', 'name', 'master_reports']);
        $rpt_columns = [3 => 2, 2 => 4, 1 => 6, 4 => 8];
        $cnx_skipped = 0;
        $cnx_updated = 0;
        $cnx_created = 0;
        $cnx_deleted = 0;
        foreach ($rows as $row) {
            $_id = trim(Arr::get($row, 0, ''));
            if ($_id === '' || !is_numeric($_id)) {
                $cnx_skipped++;
                continue;
            }
            $platId = intval($_id);
            $platform = $all_platforms->where('id', $platId)->first();
            if (!$platform) {
                $cnx_skipped++;
                continue;
            }
            $connections = Connection::with('reports')
                ->where('global_id', $platId)
                ->when(!$consoAdmin, function ($qry) use ($adminInsts, $adminGroups) {
                    $qry->whereIn('inst_id', $adminInsts)
                        ->orWhereIn('group_id', $adminGroups);
                })
                ->get();
            $keep_insts = [];
            $keep_groups = [];
            $_created = false;
            $_updated = false;
            foreach ($rpt_columns as $master_id => $col) {
                $rpt_connections = $connections->map(function ($conn) use ($master_id) {
                    if (in_array($master_id, $conn->reports->pluck('id')->toArray())) {
                        return ['id' => $conn->id, 'inst_id' => $conn->inst_id, 'group_id' => $conn->group_id];
                    }
                    return null;
                })->filter()->toArray();
                if (!in_array($master_id, $platform->master_reports())) {
                    continue;
                }
                $rawInsts = Arr::get($row, $col, '');
                $insts = preg_split('/,/', preg_replace('/, /', ',', $rawInsts));
                if (count($insts) > 0) {
                    $conso = false;
                    if (in_array(1, $insts) && $consoAdmin) {
                        $insts = array(1);
                    }
                    $keep_insts = array_unique(array_merge($keep_insts, $insts));
                    foreach ($insts as $_inst) {
                        if ($_inst === '') {
                            continue;
                        }
                        $inst_id = intval($_inst);
                        if ($consoAdmin || in_array($inst_id, $adminInsts)) {
                            $cnx = $connections->where('inst_id', $inst_id)->first();
                            if (!$cnx) {
                                $cnx = Connection::create(['global_id' => $platId, 'is_active' => 1, 'inst_id' => $inst_id]);
                                $cnx->reports()->attach($master_id);
                                $_created = true;
                                continue;
                            }
                            if (!in_array($master_id, $cnx->reports->pluck('id')->toArray())) {
                                $cnx->reports()->attach($master_id);
                                $_updated = true;
                            }
                            if ($inst_id === 1) {
                                $conso = true;
                            }
                        }
                    }
                    if ($conso) {
                        continue;
                    }
                }
                $rawGroups = Arr::get($row, $col + 1, '');
                $groups = preg_split('/,/', preg_replace('/, /', ',', $rawGroups));
                if (count($groups) === 0) {
                    continue;
                }
                $keep_groups = array_unique(array_merge($keep_groups, $groups));
                foreach ($groups as $_group) {
                    if ($_group === '') {
                        continue;
                    }
                    $group_id = intval($_group);
                    if ($consoAdmin || in_array($group_id, $adminGroups)) {
                        $cnx = $connections->where('group_id', $group_id)->first();
                        if (!$cnx) {
                            $cnx = Connection::create(['global_id' => $platId, 'is_active' => 1, 'group_id' => $group_id]);
                            $cnx->reports()->attach($master_id);
                            $_created = true;
                            continue;
                        }
                        if (!in_array($master_id, $cnx->reports->pluck('id')->toArray())) {
                            $cnx->reports()->attach($master_id);
                            $_updated = true;
                        }
                    }
                }
                foreach ($rpt_connections as $rcnx) {
                    $cnx = $connections->where('id', $rcnx['id'])->first();
                    if ($cnx && !in_array($cnx['inst_id'], $insts) && !in_array($cnx['group_id'], $groups)) {
                        $cnx->reports()->detach($master_id);
                    }
                }
            }
            $cnx_created += $_created ? 1 : 0;
            $cnx_updated += ($_updated && !$_created) ? 1 : 0;
            foreach ($connections as $cnx) {
                if (
                    $cnx->reports->count() === 0 ||
                    (!is_null($cnx->inst_id) && !in_array($cnx->inst_id, $keep_insts)) ||
                    (!is_null($cnx->group_id) && !in_array($cnx->group_id, $keep_groups))
                ) {
                    $cnx->delete();
                    $cnx_deleted++;
                }
            }
        }
        $msg = $this->formatImportSummary(
            __('connections.import_success'),
            $cnx_updated,
            $cnx_created,
            $cnx_deleted,
            $cnx_skipped
        );
        return $this->respondSuccess($msg);
    }
}
