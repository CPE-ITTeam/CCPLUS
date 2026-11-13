<?php

namespace App\Http\Controllers;

use App\Models\ConnectionField;
use App\Models\Consortium;
use App\Models\CounterRegistry;
use App\Models\GlobalProvider;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GlobalProviderController extends BaseController
{
    private $masterReports;
    private $allConnectors;
    private array $resultMap;

    public function __construct()
    {
        $this->middleware(['auth', 'role:ServerAdmin']);
        $this->resultMap = [
            'success'    => ['refresh_text' => 'Open Registry Details', 'refresh_icon' => 'mdi-web-check', 'refresh_color' => 'blue'],
            'new'        => ['refresh_text' => 'New Platform Entry', 'refresh_icon' => 'mdi-web-plus', 'refresh_color' => 'green'],
            'orphan'     => ['refresh_text' => 'Deprecated', 'refresh_icon' => 'mdi-web-remove', 'refresh_color' => 'red'],
            'partial'    => ['refresh_text' => 'Incomplete Result', 'refresh_icon' => 'mdi-web-remove', 'refresh_color' => 'orange'],
            'failed'     => ['refresh_text' => 'Last Refresh Failed', 'refresh_icon' => 'mdi-web-remove', 'refresh_color' => 'red'],
            'norefresh'  => ['refresh_text' => 'Registry Refresh Disabled', 'refresh_icon' => 'mdi-web-cancel', 'refresh_color' => 'black'],
            'noregistry' => ['refresh_text' => 'Registry Refresh Disabled', 'refresh_icon' => 'mdi-web-cancel', 'refresh_color' => 'black'],
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $thisUser = $request->user();
        abort_unless($thisUser->isServerAdmin(), 403);

        $gp_data = GlobalProvider::with('registries', 'registries.dataHost')->orderBy('name', 'ASC')->get();

        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $this->allConnectors->toArray();

        $instances = Consortium::get();
        $all_releases = [];
        $providers = [];

        foreach ($gp_data as $gp) {
            $provider = [
                'id'            => $gp->id,
                'name'          => $gp->name,
                'abbrev'        => $gp->abbrev,
                'day_of_month'  => $gp->day_of_month,
                'platform_parm' => $gp->platform_parm,
                'status'        => $gp->is_active ? 'Active' : 'Inactive',
                'refreshable'   => $gp->refreshable ? 'Active' : 'Inactive',
                'registry_id'   => (is_null($gp->registry_id) || $gp->registry_id === '') ? null : $gp->registry_id,
            ];

            $resultKey = '';
            if (is_null($provider['registry_id'])) {
                $resultKey = 'noregistry';
            }
            if (in_array($gp->refresh_result, array_keys($this->resultMap), true)) {
                if ($resultKey === '') {
                    $resultKey = $gp->refresh_result;
                }
                $provider['refresh_result'] = $resultKey;
            } else {
                $resultKey = 'norefresh';
                $provider['refresh_result'] = $gp->refreshable ? null : 'norefresh';
            }

            $provider['refresh_text']  = $this->resultMap[$resultKey]['refresh_text'];
            $provider['refresh_icon']  = $this->resultMap[$resultKey]['refresh_icon'];
            $provider['refresh_color'] = $this->resultMap[$resultKey]['refresh_color'];

            $provider['cur_release']     = $gp->default_release();
            $provider['service_url']     = $gp->service_url();
            $provider['registries']      = [];
            $provider['reg_releases']    = [];
            $provider['is_selected']     = 'Inactive';
            $provider['connector_state'] = [];
            $provider['report_state']    = $this->reportState([]);

            foreach ($gp->registries->sortBy('release') as $registry) {
                if (!in_array($registry->release, $all_releases)) {
                    $all_releases[] = $registry->release;
                }
                $reg = $registry->toArray();
                $reg['connector_state'] = $this->connectorState($registry->connectors);
                $reg['report_state'] = $this->reportState($registry->master_reports);
                $reg['is_selected'] = ($registry->release == $provider['cur_release']) ? 'Active' : 'Inactive';
                $reg['data_host'] = $registry->dataHost ? $registry->dataHost->name : '-missing-';

                if ($reg['is_selected'] === 'Active') {
                    $provider['is_selected'] = 'Active';
                    $provider['connector_state'] = $reg['connector_state'];
                    $provider['report_state'] = $reg['report_state'];
                    $provider['data_host'] = $reg['data_host'];
                    foreach ($provider['connector_state'] as $con => $val) {
                        $provider[$con] = $val ? 'Y' : 'N';
                    }
                }
                $provider['registries'][] = $reg;
                $provider['reg_releases'][] = trim($registry->release);
            }

            $provider['can_delete'] = true;
            $provider['instance_count'] = 0;
            $connections = [];

            foreach ($instances as $instance) {
                $details = $this->instanceDetails($instance->ccp_key, $gp);
                if ($details['harvest_count'] > 0) {
                    $provider['can_delete'] = false;
                }
                if ($details['cnxcount'] > 0) {
                    $connections[] = [
                        'key'          => $instance->ccp_key,
                        'name'         => $instance->name,
                        'num'          => $details['cnxcount'],
                        'last_harvest' => $details['last_harvest'],
                    ];
                    $provider['instance_count']++;
                }
            }

            $provider['can_edit'] = true;
            $provider['connections'] = $connections;
            $provider['updated'] = is_null($gp->updated_at) ? '' : date('Y-m-d H:i', strtotime($gp->updated_at));
            $providers[] = $provider;
        }

        $filter_options = [
            'releases'       => $all_releases,
            'all_connectors' => $all_connectors,
            'master_reports' => $this->masterReports,
            'statuses'       => ['Active', 'Inactive'],
            'results'        => [
                ['id' => 'success', 'value' => 'Success'],
                ['id' => 'new', 'value' => 'New'],
                ['id' => 'orphan', 'value' => 'Deprecated'],
                ['id' => 'partial', 'value' => 'Incomplete'],
                ['id' => 'failed', 'value' => 'Failed'],
                ['id' => 'norefresh', 'value' => 'Refresh Disabled'],
                ['id' => 'noregistry', 'value' => 'No Registry ID'],
            ],
        ];

        return response()->json(['records' => $providers, 'options' => $filter_options], 200);
    }

    /**
     * Display a listing of the resource (for the API).
     */
    public function apiIndex(Request $request)
    {
        $data = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();

        $providers = [];
        foreach ($data as $gp) {
            $providers[] = [
                'id'               => $gp->id,
                'name'             => $gp->name,
                'abbrev'           => $gp->abbrev,
                'day_of_month'     => $gp->day_of_month,
                'platform_parm'    => $gp->platform_parm,
                'content_provider' => $gp->content_provider,
                'status'           => $gp->is_active ? 'Active' : 'Inactive',
                'refreshable'      => $gp->refreshable ? 'Yes' : 'No',
                'registry_id'      => (is_null($gp->registry_id) || $gp->registry_id === '') ? null : $gp->registry_id,
                'cur_release'      => $gp->default_release(),
                'service_url'      => $gp->service_url(),
            ];
        }

        return response()->json(['records' => $providers], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name'        => 'required',
            'status'      => 'required',
            'service_url' => 'required',
            'cur_release' => 'required',
            'refreshable' => 'required',
        ]);
        $input = $request->all();

        $provider = new GlobalProvider();
        $provider->name = $input['name'];
        $provider->is_active = ($input['status'] === 'Active') ? 1 : 0;
        $provider->refreshable = ($input['refreshable'] === 'Active') ? 1 : 0;
        $provider->refresh_result = null;
        $provider->day_of_month = isset($input['day_of_month']) ? min(max((int) $input['day_of_month'], 1), 28) : 15;
        $provider->platform_parm = $input['platform_parm'] ?? null;
        $provider->save();

        $registry = new CounterRegistry();
        $registry->global_id = $provider->id;
        $registry->service_url = $input['service_url'];
        $input_release = isset($input['cur_release']) ? trim($input['cur_release']) : '';
        $registry->release = strlen($input_release) > 0 ? $input_release : '0';

        $connectors = [];
        $this->getConnectionFields();
        foreach ($this->allConnectors as $cnx) {
            if (!empty($input['connector_state'][$cnx->name])) {
                $connectors[] = $cnx->id;
            }
        }
        $registry->connectors = $connectors;

        $master_reports = [];
        if (isset($input['report_state'])) {
            $this->getMasterReports();
            foreach ($this->masterReports as $rpt) {
                if (!empty($input['report_state'][$rpt->name])) {
                    $master_reports[] = $rpt->id;
                }
            }
        }
        $registry->master_reports = $master_reports;
        $registry->save();
        $provider->load('registries');
        $record = [
            'id'              => $provider->id,
            'name'            => $provider->name,
            'abbrev'          => $provider->abbrev,
            'day_of_month'    => $provider->day_of_month,
            'platform_parm'   => $provider->platform_parm,
            'can_edit'        => true,
            'can_delete'      => true,
            'cnxcount'        => 0,
            'status'          => $provider->is_active ? 'Active' : 'Inactive',
            'refreshable'     => $provider->refreshable ? 'Active' : 'Inactive',
            'connector_state' => $input['connector_state'] ?? [],
            'report_state'    => $input['report_state'] ?? [],
            'data_host'       => $provider->name,
            'registries'      => $provider->registries->toArray(),
            'cur_release'     => $provider->default_release(),
            'reg_releases'    => [$input_release],
            'is_selected'     => 'Active',
            'service_url'     => $provider->service_url(),
            'connections'     => [],
            'instance_count'  => 0,
            'updated'         => is_null($provider->updated_at) ? '' : date('Y-m-d H:i', strtotime($provider->updated_at)),
        ];
        return $this->respondSuccess(__('connections.platform_created'), $record);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $provider = GlobalProvider::with('registries', 'registries.dataHost')->findOrFail($id);
        $orig_name = $provider->name;
        $orig_refreshable = $provider->refreshable;
        $input = $request->all();
        if (!isset($input['status']) && isset($input['is_active'])) {
            $value = ($input['is_active'] == 0) ? 'Inactive' : 'Active';
            $input = ['status' => $value];
        }
        $isActive = ($input['status'] === 'Active') ? 1 : 0;
        $provider->is_active = $isActive;
        if (!$isActive) {
            $provider->refresh_result = null;
        }
        $provider->refreshable = (array_key_exists('refreshable', $input) && $input['refreshable'] === 'Active') ? 1 : 0;
        if ($provider->refreshable != $orig_refreshable) {
            $provider->refresh_result = ($provider->refreshable == 1) ? 'success' : null;
        }
        $this->getConnectionFields();
        $this->getMasterReports();
        if (isset($input['name'])) {
            $provider->name = $input['name'];
        } else {
            $provider->name = $orig_name;
        }
        $input_release = $input['cur_release'] ?? '';
        $release = strlen(trim($input_release)) > 0 ? $input_release : '0';
        if ($provider->registries->count() === 1) {
            $registry = $provider->registries->first();
            $registry->release = $release;
        } else {
            $registry = $provider->registries->where('release', $release)->first();
        }
        if (!$registry) {
            $registry = new CounterRegistry();
            $registry->global_id = $provider->id;
            $registry->service_url = $input['service_url'] ?? null;
            $registry->release = $release;
        }
        if (isset($input['service_url']) && !$provider->refreshable) {
            $registry->service_url = $input['service_url'];
        }
        if (array_key_exists('connector_state', $input)) {
            $new_connectors = [];
            foreach ($this->allConnectors as $cnx) {
                if (!empty($input['connector_state'][$cnx->name])) {
                    $new_connectors[] = $cnx->id;
                }
            }
            $registry->connectors = $new_connectors;
        }
        $master_reports = [];
        if (array_key_exists('report_state', $input)) {
            foreach ($this->masterReports as $rpt) {
                if (!empty($input['report_state'][$rpt->name])) {
                    $master_reports[] = $rpt->id;
                }
            }
        }
        $registry->master_reports = $master_reports;
        $registry->save();
        $isSelected = $input['is_selected'] ?? null;
        if ($isSelected === 'Active') {
            $provider->selected_release = trim($registry->release);
        }
        $provider->day_of_month = isset($input['day_of_month']) ? min(max((int) $input['day_of_month'], 1), 28) : 15;
        foreach (['platform_parm', 'content_provider', 'registry_id'] as $key) {
            if (array_key_exists($key, $input)) {
                $provider->{$key} = $input[$key] ? trim($input[$key]) : null;
            }
        }
        $provider->updated_at = now();
        $provider->save();
        $provider->load('registries', 'registries.dataHost');
        $reg_releases = [];
        foreach ($provider->registries as $regItem) {
            $regItem->connector_state = $this->connectorState($regItem->connectors);
            $regItem->report_state = $this->reportState($regItem->master_reports);
            $regItem->is_selected = ($regItem->release == $provider->selected_release);
            $reg_releases[] = $regItem->release;
        }
        $provider->applyToInstances();
        $defaultRegistry = $provider->default_registry();
        $record = [
            'id'            => $provider->id,
            'name'          => $provider->name,
            'abbrev'        => $provider->abbrev,
            'day_of_month'  => $provider->day_of_month,
            'platform_parm' => $provider->platform_parm,
            'registry_id'   => $provider->registry_id,
            'status'        => $provider->is_active ? 'Active' : 'Inactive',
            'refreshable'   => $provider->refreshable ? 'Active' : 'Inactive',
        ];
        $resultKey = '';
        if (is_null($provider['registry_id'])) {
            $resultKey = 'noregistry';
        }
        if (in_array($provider->refresh_result, array_keys($this->resultMap), true)) {
            if ($resultKey === '') {
                $resultKey = $provider->refresh_result;
            }
            $record['refresh_result'] = $resultKey;
        } else {
            $resultKey = 'norefresh';
            $record['refresh_result'] = $provider->refreshable ? null : 'norefresh';
        }
        $record['refresh_text']    = $this->resultMap[$resultKey]['refresh_text'];
        $record['refresh_icon']    = $this->resultMap[$resultKey]['refresh_icon'];
        $record['refresh_color']   = $this->resultMap[$resultKey]['refresh_color'];
        $record['registries']      = $provider->registries->toArray();
        $record['cur_release']     = $provider->default_release();
        $record['is_selected']     = ($record['cur_release'] == $release) ? 'Active' : 'Inactive';
        $record['reg_releases']    = $reg_releases;
        $record['service_url']     = $defaultRegistry ? $defaultRegistry->service_url : $provider->service_url();
        $record['data_host']       = ($defaultRegistry && $defaultRegistry->dataHost) ? $defaultRegistry->dataHost->name : '-missing-';
        $record['report_state']    = $input['report_state'] ?? [];
        $record['connector_state'] = $input['connector_state'] ?? [];
        $record['can_edit'] = true;
        $record['can_delete'] = true;
        $record['instance_count'] = 0;
        $connections = [];
        $instances = Consortium::get();
        foreach ($instances as $instance) {
            $details = $this->instanceDetails($instance->ccp_key, $provider);
            if ($details['harvest_count'] > 0) {
                $record['can_delete'] = false;
            }
            if ($details['cnxcount'] > 0) {
                $connections[] = [
                    'key'          => $instance->ccp_key,
                    'name'         => $instance->name,
                    'num'          => $details['cnxcount'],
                    'last_harvest' => $details['last_harvest'],
                ];
                $record['instance_count']++;
            }
        }
        $record['connections'] = $connections;
        $record['updated'] = is_null($provider->updated_at) ? '' : date('Y-m-d H:i', strtotime($provider->updated_at));
        return $this->respondSuccess(__('connections.platform_updated'), $record);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $globalProvider = GlobalProvider::findOrFail($id);
        $instances = Consortium::get();
        $keepDB = config('database.connections.consodb.database');
        try {
            foreach ($instances as $instance) {
                config(['database.connections.consodb.database' => 'ccplus_' . $instance->ccp_key]);
                try {
                    DB::reconnect('consodb');
                } catch (\Exception $e) {
                    return $this->respondError(__('connections.instance_db_error', ['name' => $instance->name]));
                }
                try {
                    DB::connection('consodb')->table('connections')->where('global_id', $id)->delete();
                } catch (\Exception $ex) {
                    return $this->respondError($ex->getMessage());
                }
            }
        } finally {
            config(['database.connections.consodb.database' => $keepDB]);
            DB::reconnect('consodb');
        }
        try {
            $globalProvider->delete();
        } catch (\Exception $ex) {
            return $this->respondError($ex->getMessage());
        }
        return $this->respondSuccess(__('connections.platform_deleted'));
    }
    /**
     * Bulk operations from the UI.
     */
    public function bulk(Request $request)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isServerAdmin()) {
            return $this->respondError(__('connections.forbidden_request'));
        }
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();
        if (!in_array($input['action'], ['Set Active', 'Set Inactive', 'Delete'], true)) {
            return $this->respondError(__('connections.bulk_unrecognized'));
        }
        $gp_data = GlobalProvider::whereIn('id', $input['ids'])->get();
        $successIds = [];
        $skippedIds = [];
        $failureIds = [];
        $new_value = ($input['action'] === 'Set Active') ? 1 : 0;
        foreach ($gp_data as $global) {
            if ($input['action'] === 'Set Active' || $input['action'] === 'Set Inactive') {
                if ($global->is_active == $new_value) {
                    $skippedIds[] = $global->id;
                } else {
                    $global->update(['is_active' => $new_value]);
                    $successIds[] = $global->id;
                }
            } elseif ($input['action'] === 'Delete') {
                try {
                    $global->delete();
                    $successIds[] = $global->id;
                } catch (\Exception $ex) {
                    $failureIds[] = $global->id;
                }
            }
            $global->applyToInstances();
        }
        $msg = '';
        if (count($skippedIds) > 0 || count($failureIds) > 0) {
            $msg = __('connections.bulk_updated', ['count' => count($successIds)]);
            if (count($skippedIds) > 0) {
                $msg .= __('connections.bulk_skipped', ['count' => count($skippedIds)]);
            }
            if (count($failureIds) > 0) {
                $msg .= __('connections.bulk_failed', ['count' => count($failureIds)]);
            }
        }
        return response()->json(['result' => true, 'msg' => $msg, 'affectedIds' => $successIds], 200);
    }
    /**
     * GET route to pull platform data for exporting.
     */
    public function export(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isServerAdmin(), 403);
        $gp_data = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();
        $this->getMasterReports();
        $this->getConnectionFields();
        $providers = [];
        foreach ($gp_data as $gp) {
            $provider = [
                'registry_id'   => (is_null($gp->registry_id) || $gp->registry_id === '') ? null : $gp->registry_id,
                'name'          => $gp->name,
                'day_of_month'  => $gp->day_of_month,
                'platform_parm' => $gp->platform_parm,
                'status'        => $gp->is_active ? 'Active' : 'Inactive',
                'refreshable'   => $gp->refreshable ? 'Active' : 'Inactive',
            ];
            foreach ($gp->registries->sortBy('release') as $registry) {
                $provider['release'] = trim($registry->release);
                $provider['service_url'] = $registry->service_url;
                foreach ($this->connectorState($registry->connectors) as $con => $val) {
                    $provider[$con] = $val ? 'Y' : 'N';
                }
                foreach ($this->reportState($registry->master_reports) as $rpt => $val) {
                    $provider[$rpt] = $val ? 'Y' : 'N';
                }
                $providers[] = $provider;
            }
        }
        return response()->json(['records' => $providers], 200);
    }
    /**
     * Import providers from a CSV file to the database.
     */
    public function import(Request $request)
    {
        if (!auth()->user()->isServerAdmin()) {
            return $this->respondError(__('connections.forbidden_request'));
        }
        $parsed = $this->parseCsvUpload($request);
        if (isset($parsed['error'])) {
            return $this->respondError($parsed['error']);
        }
        $rows = $parsed['rows'];
        $global_providers = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();
        $this->getMasterReports();
        $this->getConnectionFields();
        $col_defaults = [
            3  => 'Active',
            4  => '',
            5  => 'Y',
            6  => 15,
            7  => 'Y',
            8  => 'N',
            9  => 'N',
            10 => 'N',
            11 => 'Y',
            12 => 'N',
            13 => 'N',
            14 => null,
        ];
        $rpt_columns = [3 => 7, 2 => 8, 1 => 9, 4 => 10];
        $cnx_columns = [1 => 11, 2 => 12, 3 => 13, 4 => 15];
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        foreach ($rows as $row) {
            if (count($row) < 5) {
                continue;
            }
            $_regid       = trim((string) Arr::get($row, 0, ''));
            $_name        = trim((string) Arr::get($row, 1, ''));
            $_release     = trim((string) Arr::get($row, 2, ''));
            $_service_url = trim((string) Arr::get($row, 4, ''));
            if ($_regid === 'Registry ID' || $_name === 'Platform Name') {
                continue;
            }
            if (($_regid === '' && $_name === '') || $_release === '' || $_service_url === '') {
                $prov_skipped++;
                continue;
            }
            $platform = null;
            if (strlen($_regid) > 0) {
                $platform = $global_providers->where('registry_id', $_regid)->first();
            }
            if ($platform) {
                if (strlen($_name) < 1 || $global_providers->where('name', $_name)->first()) {
                    $_name = trim($platform->name);
                }
            } else {
                $platform = $global_providers->where('name', $_name)->first();
                if ($platform) {
                    $_name = trim($platform->name);
                }
            }
            if (strlen($_name) < 1 || (strlen($_regid) === 0 && strlen($_service_url) === 0)) {
                $prov_skipped++;
                continue;
            }
            foreach ($col_defaults as $_col => $_val) {
                if (!isset($row[$_col]) || strlen(trim((string) $row[$_col])) < 1) {
                    $row[$_col] = $_val;
                }
            }
            $_active = (trim((string) Arr::get($row, 3, 'Active')) === 'Active') ? 1 : 0;
            $rawDay  = trim((string) Arr::get($row, 6, '15'));
            $_day    = ($rawDay === '') ? 15 : intval($rawDay);
            if ($_day < 0 || $_day > 28) {
                $_day = 15;
            }
            $_prov = [
                'name'         => $_name,
                'is_active'    => $_active,
                'day_of_month' => $_day,
            ];
            $rawParm = Arr::get($row, 14);
            if (!is_null($rawParm)) {
                $_prov['platform_parm'] = trim((string) $rawParm);
            }
            if ($platform) {
                $_prov['id'] = $platform->id;
                $platform->update($_prov);
                $prov_updated++;
            } else {
                if (strlen($_regid) === 0) {
                    $_prov['refreshable'] = 0;
                }
                $platform = GlobalProvider::create($_prov);
                $global_providers->push($platform);
                $prov_created++;
            }
            $reports = [];
            foreach ($rpt_columns as $id => $col) {
                if (Arr::get($row, $col) === 'Y') {
                    $reports[] = $id;
                }
            }
            $connectors = [];
            foreach ($cnx_columns as $id => $col) {
                if (Arr::get($row, $col) === 'Y') {
                    $connectors[] = $id;
                }
            }
            $registry = $platform->registries->where('release', $_release)->first();
            if ($registry) {
                $registry->service_url = $_service_url;
                $registry->master_reports = $reports;
                $registry->connectors = $connectors;
                $registry->save();
            } else {
                CounterRegistry::create([
                    'global_id'      => $platform->id,
                    'release'        => $_release,
                    'service_url'    => $_service_url,
                    'master_reports' => $reports,
                    'connectors'     => $connectors,
                ]);
            }
        }
        $msg = $this->formatImportSummary(
            __('connections.platform_import_success'),
            $prov_updated,
            $prov_created,
            0,
            $prov_skipped
        );
        return $this->respondSuccess($msg);
    }
    /**
     * Pull and re-order master reports.
     */
    private function getMasterReports(): void
    {
        $this->masterReports = Report::where('revision', 5)
            ->where('parent_id', 0)
            ->orderBy('dorder', 'ASC')
            ->get(['id', 'name']);
    }
    /**
     * Pull all connection fields.
     */
    private function getConnectionFields(): void
    {
        $this->allConnectors = ConnectionField::get();
    }
    /**
     * Return an array of booleans for report-state from provider reports columns.
     */
    private function reportState($reports): array
    {
        $rpt_state = [];
        foreach ($this->masterReports as $rpt) {
            $rpt_state[$rpt->name] = in_array($rpt->id, (array) $reports);
        }
        return $rpt_state;
    }
    /**
     * Return an array of booleans for connector-state from provider connectors columns.
     */
    private function connectorState($connectors): array
    {
        $cnxs = is_string($connectors)
            ? (json_decode($connectors, true) ?? [])
            : (is_array($connectors) ? $connectors : []);
        $cnx_state = [];
        foreach ($this->allConnectors as $fld) {
            $cnx_state[$fld->name] = in_array($fld->id, $cnxs);
        }
        return $cnx_state;
    }
    /**
     * Return harvest_count, connection count, and last_harvest for a global provider in a given instance.
     */ private function instanceDetails(string $instanceKey, GlobalProvider $gp): array
    {
        $qry  = 'SELECT count(*) as num, max(last_harvest) as last FROM ccplus_' . $instanceKey . '.credentials ';
        $qry .= 'WHERE prov_id = ' . $gp->id;
        $result = DB::select($qry);
        $firstRow = reset($result);
        $cnxcount = $firstRow ? $firstRow->num : 0;
        $last = $firstRow ? $firstRow->last : null;

        $qry .= ' AND last_harvest IS NOT NULL';
        $result = DB::select($qry);
        $secondRow = reset($result);
        $count = $secondRow ? $secondRow->num : 0;

        return ['harvest_count' => $count, 'cnxcount' => $cnxcount, 'last_harvest' => $last];
    }
}
