<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\ConnectionField;
use App\Models\Consortium;
use App\Models\Credential;
use App\Models\GlobalProvider;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Report;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class CredentialController extends BaseController
{
    private $masterReports;

    /**
     * Return a listing of the resource.
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);

        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_to_insts = array_map('intval', (array) $thisUser->adminInsts());
        if ($limit_to_insts === [1]) {
            $limit_to_insts = [];
        }

        $data = Credential::with('institution:id,name,is_active,local_id', 'institution.institutionGroups:id', 'lastHarvest')
            ->when(count($limit_to_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_to_insts))
            ->get();

        $globals = GlobalProvider::with('registries', 'connections', 'connections.reports')
            ->orderBy('name', 'ASC')
            ->get();

        $global_connectors = ConnectionField::get();
        $masterReports = Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name']);

        $globalsById = [];
        $platformMeta = [];
        $platformIsConso = [];
        $platformsFilterList = [];
        $platformBaseReports = [];
        $platformInstReports = [];

        foreach ($globals as $plat) {
            $globalsById[$plat->id] = $plat;

            $_connectors = [];
            $required = $plat->connectors();
            foreach ($global_connectors as $gc) {
                $_connectors[$gc->name] = in_array($gc->id, $required);
            }

            $platformMeta[$plat->id] = [
                'service_url' => $plat->service_url(),
                'connectors'  => $this->buildConnectorList($global_connectors, $required),
            ];

            $available = $plat->master_reports() ?? [];
            $consoWide = $plat->connections->firstWhere('inst_id', 1);
            $isConsoPlat = (bool) $consoWide;
            $platformIsConso[$plat->id] = $isConsoPlat;
            $consoReportIds = $consoWide ? $consoWide->reports->pluck('id')->all() : [];

            $platformsFilterList[] = [
                'id'         => $plat->id,
                'name'       => $plat->name,
                'is_active'  => $plat->is_active,
                'is_conso'   => $isConsoPlat,
                'connectors' => $_connectors,
            ];

            $base = [];
            foreach ($masterReports as $mr) {
                $isAvail = in_array($mr->id, $available);
                $isConso = $isAvail && in_array($mr->id, $consoReportIds);
                $base[$mr->name] = [
                    'conso'     => $isConso,
                    'insts'     => [],
                    'groups'    => [],
                    'requested' => false,
                    'available' => $isAvail,
                    'sortval'   => !$isAvail ? 4 : ($isConso ? 1 : 2),
                ];
            }
            $platformBaseReports[$plat->id] = $base;

            $instMap = [];
            foreach ($plat->connections as $cnx) {
                if (!$cnx->inst_id || $cnx->inst_id == 1) {
                    continue;
                }
                if (!$consoAdmin && !in_array($cnx->inst_id, $limit_to_insts)) {
                    continue;
                }
                foreach ($cnx->reports as $rpt) {
                    $instMap[$cnx->inst_id][$rpt->name] = true;
                }
            }
            $platformInstReports[$plat->id] = $instMap;
        }

        $filter_options = [
            'results'   => [],
            'statuses'  => $data->unique('status')->pluck('status')->values()->toArray(),
            'platforms' => $platformsFilterList,
        ];

        if ($consoAdmin) {
            $filter_options['groups'] = InstitutionGroup::whereNull('user_id')->orderBy('name', 'ASC')->get(['id', 'name']);
            $filter_options['institutions'] = Institution::where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->get(['id', 'name', 'is_active']);
        } else {
            $inst_ids = $thisUser->adminInsts();
            $filter_options['groups'] = [];
            $filter_options['institutions'] = Institution::where('is_active', 1)
                ->whereIn('id', $inst_ids)
                ->orderBy('name', 'ASC')
                ->get(['id', 'name', 'is_active']);
        }

        $nh_count = 0;
        $seen_codes = [0];
        $credentials = [];

        foreach ($data as $cred) {
            $global = $globalsById[$cred->prov_id] ?? null;
            if (!$global) {
                continue;
            }

            $meta = $platformMeta[$cred->prov_id];
            $rec = [
                'id'             => $cred->id,
                'status'         => $cred->status,
                'is_conso'       => $platformIsConso[$cred->prov_id] ?? false,
                'inst_id'        => $cred->inst_id,
                'group_ids'      => $cred->institution
                    ? $cred->institution->institutionGroups->pluck('id')->map(fn($id) => (int) $id)->values()->toArray()
                    : [],
                'prov_id'        => $cred->prov_id,
                'legacy_prov_id' => $global->legacy_id,
                'customer_id'    => $cred->customer_id,
                'requestor_id'   => $cred->requestor_id,
                'api_key'        => $cred->api_key,
                'extra_args'     => $cred->extra_args,
                'platform'       => $global->name,
                'can_edit'       => true,
                'can_delete'     => true,
                'inst_active'    => $cred->institution ? $cred->institution->is_active : 0,
                'plat_active'    => $global->is_active,
                'service_url'    => $meta['service_url'],
                'connectors'     => $meta['connectors'],
                'institution'    => $cred->institution ? $cred->institution->name : '',
                'local_id'       => $cred->institution ? $cred->institution->local_id : '',
                'connected'      => ($cred->status === 'Enabled'),
            ];

            $lastHarvest = $cred->lastHarvest;
            if ($lastHarvest) {
                if ($lastHarvest->error_id > 0 && !in_array($lastHarvest->error_id, $seen_codes)) {
                    $seen_codes[] = $lastHarvest->error_id;
                }
                $rec['result'] = ($lastHarvest->status === 'Success' || $lastHarvest->error_id == 0)
                    ? 'Success'
                    : $lastHarvest->error_id;
            } else {
                $nh_count++;
                $rec['result'] = 'No Harvests';
            }

            $recReports = $platformBaseReports[$cred->prov_id];
            $instEnabled = $platformInstReports[$cred->prov_id][$cred->inst_id] ?? [];
            foreach ($recReports as $rName => $rData) {
                if ($rData['available'] && !$rData['conso'] && isset($instEnabled[$rName])) {
                    $rData['insts'] = [$cred->inst_id];
                    $rData['requested'] = true;
                    $rData['sortval'] = 3;
                }
                $rec[$rName] = $rData;
            }

            $credentials[] = $rec;
        }

        sort($seen_codes);
        foreach ($seen_codes as $code) {
            $filter_options['results'][] = ['result' => ($code > 0) ? $code : 'Success'];
        }
        if ($nh_count > 0) {
            $filter_options['results'][] = ['result' => 'No Harvests'];
        }
        $filter_options['all_connectors'] = $global_connectors;

        return response()->json(['records' => $credentials, 'options' => $filter_options, 'result' => true], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        $input = $request->all();

        if (empty($input['inst_id']) || !$thisUser->canManage($input['inst_id'])) {
            return $this->respondError(__('credentials.inst_forbidden'));
        }

        if (empty($input['prov_id'])) {
            return $this->respondError(__('credentials.platform_required'));
        }

        $gp = GlobalProvider::find($input['prov_id']);
        if (!$gp) {
            return $this->respondError(__('credentials.platform_unknown'));
        }

        $exists = Credential::where('inst_id', $input['inst_id'])
            ->where('prov_id', $input['prov_id'])
            ->exists();

        if ($exists) {
            return $this->respondError(__('credentials.already_exists'));
        }

        $connected_inst_ids = $gp->connectedInstitutions();
        if (!in_array(1, $connected_inst_ids) && !in_array($input['inst_id'], $connected_inst_ids)) {
            $new_conn = Connection::create([
                'global_id' => $gp->id,
                'is_active' => $gp->is_active,
                'inst_id'   => $input['inst_id'],
            ]);

            $this->getMasterReports();
            $availIds = $gp->master_reports() ?? [];
            $masters = $this->masterReports->whereIn('id', $availIds);
            foreach ($masters as $rpt) {
                $new_conn->reports()->attach($rpt->id);
            }
        }

        $global_connectors = ConnectionField::get();
        $requiredIds = $gp->connectors();
        $attrs = [
            'inst_id'      => (int) $input['inst_id'],
            'prov_id'      => (int) $input['prov_id'],
            'customer_id'  => trim((string) ($input['customer_id'] ?? '')) ?: null,
            'requestor_id' => trim((string) ($input['requestor_id'] ?? '')) ?: null,
            'api_key'      => trim((string) ($input['api_key'] ?? '')) ?: null,
            'extra_args'   => trim((string) ($input['extra_args'] ?? '')) ?: null,
            'status'       => 'Enabled',
        ];

        foreach ($global_connectors as $gc) {
            if (in_array($gc->id, $requiredIds) && empty($attrs[$gc->name])) {
                $attrs[$gc->name] = '-required-';
                $attrs['status'] = 'Incomplete';
            }
        }

        $cred = Credential::create($attrs);
        $cred->load('institution:id,name,is_active,local_id');

        $rec = [
            'id'             => $cred->id,
            'status'         => $cred->status,
            'connected'      => ($cred->status === 'Enabled'),
            'result'         => 'No Harvests',
            'inst_id'        => $cred->inst_id,
            'prov_id'        => $cred->prov_id,
            'legacy_prov_id' => $gp->legacy_id,
            'customer_id'    => $cred->customer_id,
            'requestor_id'   => $cred->requestor_id,
            'api_key'        => $cred->api_key,
            'extra_args'     => $cred->extra_args,
            'platform'       => $gp->name,
            'can_edit'       => true,
            'can_delete'     => true,
            'inst_active'    => $cred->institution ? $cred->institution->is_active : 0,
            'plat_active'    => $gp->is_active,
            'service_url'    => $gp->service_url(),
            'connectors'     => $this->buildConnectorList($global_connectors, $requiredIds),
            'institution'    => $cred->institution ? $cred->institution->name : '',
            'local_id'       => $cred->institution ? $cred->institution->local_id : '',
        ];

        $this->attachReportFlags($rec, $gp->enabledReports($cred->inst_id));

        return $this->respondSuccess(__('credentials.created'), $rec);
    }

    /**
     * Set/update credential values and report access/availability.
     */
    public function update(Request $request)
    {
        $this->validate($request, ['id' => 'required']);
        $input = $request->all();

        $cred = Credential::with('institution', 'provider', 'lastHarvest')->findOrFail($input['id']);
        $global = GlobalProvider::findOrFail($cred->prov_id);
        $institution = Institution::findOrFail($cred->inst_id);

        if (!$institution->canManage()) {
            return $this->respondError(__('credentials.update_forbidden'));
        }

        if (!$cred->institution || !$cred->provider) {
            return $this->respondError(__('credentials.reference_error'));
        }

        $requiredConnectors = $global->connectors();
        $all_connectors = ConnectionField::get();

        if (!$request->boolean('report_only')) {
            $fields = [
                'status'       => in_array($input['status'] ?? '', ['Enabled', 'Disabled'], true) ? $input['status'] : $cred->status,
                'customer_id'  => trim((string) ($input['customer_id'] ?? '')) ?: null,
                'requestor_id' => trim((string) ($input['requestor_id'] ?? '')) ?: null,
                'api_key'      => trim((string) ($input['api_key'] ?? '')) ?: null,
                'extra_args'   => trim((string) ($input['extra_args'] ?? '')) ?: null,
            ];

            foreach ($fields as $fld => $val) {
                $cred->$fld = $val;
            }

            foreach ($all_connectors as $cnx) {
                if (
                    in_array($cnx->id, $requiredConnectors) &&
                    (is_null($cred->{$cnx->name}) || $cred->{$cnx->name} === '')
                ) {
                    $cred->{$cnx->name} = '-required-';
                }
            }

            $cred->plat_valid = null;
            $cred->inst_valid = null;

            if ($cred->status === 'Disabled') {
                $cred->save();
            } else {
                $cred->resetStatus(true);
            }
        }

        $this->getMasterReports();
        foreach ($this->masterReports as $rpt) {
            if (!isset($input[$rpt->name]) || !empty($input[$rpt->name]['conso'])) {
                continue;
            }
            $result = $this->updateInstReport($cred->inst_id, $cred->provider, $rpt->id, $input[$rpt->name]);
            if (!$result['success']) {
                return $this->respondError($result['msg']);
            }
        }

        $global->load('connections', 'connections.reports');
        $lastHarvest = $cred->lastHarvest;
        $lastResult = 'No Harvests';
        if ($lastHarvest) {
            $lastResult = ($lastHarvest->status === 'Success' || $lastHarvest->error_id == 0)
                ? 'Success'
                : $lastHarvest->error_id;
        }

        $record = [
            'id'             => $cred->id,
            'status'         => $cred->status,
            'inst_id'        => $cred->inst_id,
            'prov_id'        => $cred->prov_id,
            'legacy_prov_id' => $global->legacy_id,
            'customer_id'    => $cred->customer_id,
            'requestor_id'   => $cred->requestor_id,
            'api_key'        => $cred->api_key,
            'extra_args'     => $cred->extra_args,
            'platform'       => $global->name,
            'can_edit'       => true,
            'can_delete'     => true,
            'inst_active'    => $cred->institution->is_active,
            'plat_active'    => $global->is_active,
            'service_url'    => $global->service_url(),
            'connectors'     => $this->buildConnectorList($all_connectors, $requiredConnectors),
            'institution'    => $cred->institution->name,
            'local_id'       => $cred->institution->local_id,
            'connected'      => ($cred->status === 'Enabled'),
            'result'         => $lastResult,
        ];
        $this->attachReportFlags($record, $global->enabledReports($cred->inst_id));

        return $this->respondSuccess(__('credentials.updated'), $record);
    }


    /**
     * Update report definition for a connection.
     */
    private function updateInstReport(int $inst_id, ?GlobalProvider $global, int $report_id, array $flags): array
    {
        if (!$global) {
            return ['success' => false, 'msg' => __('credentials.platform_unavailable')];
        }
        $requested = (bool) ($flags['requested'] ?? false);
        $attached = false;
        $cnx = Connection::where('inst_id', $inst_id)
            ->where('global_id', $global->id)
            ->with('reports')
            ->first();
        if ($cnx) {
            $attached = !is_null($cnx->reports->where('id', $report_id)->first());
        } elseif ($requested) {
            $cnx = Connection::create([
                'name'      => $global->name,
                'global_id' => $global->id,
                'is_active' => $global->is_active,
                'inst_id'   => $inst_id,
            ]);
        }
        try {
            if ($requested && !$attached && $cnx) {
                $cnx->reports()->attach($report_id);
            }
            if ($attached && !$requested && $cnx) {
                $cnx->reports()->detach($report_id);
                if ($cnx->reports()->count() === 0) {
                    $cnx->delete();
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
        return ['success' => true, 'msg' => ''];
    }
    /**
     * Test the credentials for a given provider-institution.
     */


    public function test(Request $request)
    {

        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return $this->respondError(__('credentials.decode_error'));
        }
        if (!isset($input['type'])) {
            return $this->respondError(__('credentials.type_required'));
        }
        if (!empty($input['credentials_id'])) {
            $ccpKey = $request->header('X-Tenant') ?: session('ccp_key');
            if ($ccpKey) {
                $tenantDb = 'ccplus_' . $ccpKey;
                if (config('database.connections.consodb.database') !== $tenantDb) {
                    config(['database.connections.consodb.database' => $tenantDb]);
                    \Illuminate\Support\Facades\DB::purge('consodb');
                }
            }

            $cred = Credential::findOrFail($input['credentials_id']);
            $thisUser = auth()->user();
            abort_unless($thisUser && ($thisUser->isConsoAdmin() || $thisUser->isAdmin($cred->inst_id)), 403);

            $input['prov_id'] = $cred->prov_id;
            foreach (['customer_id', 'requestor_id', 'api_key', 'extra_args'] as $field) {
                if ((!isset($input[$field]) || $input[$field] === '') && !empty($cred->$field)) {
                    $input[$field] = $cred->$field;
                }
            }
        }


        $provider = GlobalProvider::with('registries')->findOrFail($input['prov_id']);
        $options = [
            'headers' => ['User-Agent' => 'Mozilla/5.0 (CC-Plus custom) Firefox/80.0'],
            'http_errors' => false,
            'timeout' => 30,
        ];


        $registry = $provider->default_registry() ?? $provider->registries->first();
        $release = $registry?->release ?? '5';
        $baseUrl = $registry?->service_url ?: $provider->service_url();

        $_url = rtrim((string) $baseUrl);
        $_url = preg_replace('#/(reports|status|members)/?$#i', '', $_url);
        $_uri = rtrim($_url, '/');
        if ($release === '5.1' && !preg_match('#/r51$#i', $_uri)) {
            $_uri .= '/r51';
        }

        if (isset($input['extra_args'])) {
            $input['extra_args'] = ltrim(trim($input['extra_args']), '&?');
        }
        $request_uri = '';
        if ($input['type'] === 'test') {
            $_uri .= '/reports/pr?';
            $uri_auth = !is_null($provider->platform_parm) ? 'platform=' . urlencode($provider->platform_parm) : '';
            foreach (['customer_id', 'requestor_id', 'api_key', 'extra_args'] as $fld) {
                if (isset($input[$fld]) && $input[$fld] !== '') {
                    $uri_auth .= ($uri_auth === '') ? '' : '&';
                    if ($fld === 'extra_args') {
                        parse_str($input['extra_args'], $extraParams);
                        $uri_auth .= !empty($extraParams)
                            ? http_build_query($extraParams)
                            : urlencode($input['extra_args']);
                    } else {
                        $uri_auth .= $fld . '=' . urlencode($input[$fld]);
                    }
                }
            }
            $dates = '&begin_date=' . date('Y-m-d', strtotime('first day of previous month'))
                . '&end_date=' . date('Y-m-d', strtotime('last day of previous month'));
            $request_uri = $_uri . $uri_auth . $dates;
        }
        $rows = [];
        $client = new Client();
        try {
            if ($input['type'] === 'status') {
                $response = $client->request('GET', $_uri . '/status', $options);
                $json = (string) $response->getBody();
                $rows[] = 'Service Status (' . $_uri . '/status):';
            } else {
                $response = $client->request('GET', $request_uri, $options);
                $json = (string) $response->getBody();
                $rows[] = 'Request URL:';
                $rows[] = $request_uri;
                $rows[] = 'JSON Response:';
            }
            $begin_txt = substr(trim($json), 0, 80);
            $decoded = json_decode($json, true);
            if (stripos($begin_txt, 'doctype html') !== false || stripos($begin_txt, '<html') !== false) {
                return response()->json([
                    'result' => false,
                    'msg'    => __('credentials.invalid_html_response'),
                    'rows'   => $rows,
                ]);
            } elseif (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json([
                    'result' => false,
                    'msg'    => __('credentials.invalid_json_response'),
                    'rows'   => $rows,
                ]);
            }

            // Detect COUNTER JSON Exception payloads (both R5 and R5.1, single object or list)
            $exception = $decoded['Exception']
                ?? $decoded['Exceptions']
                ?? $decoded['Report_Header']['Exceptions']
                ?? (isset($decoded['Code']) ? $decoded : null);
            if (is_array($exception) && array_is_list($exception)) {
                $exception = $exception[0] ?? null;
            }
            $code = is_array($exception) && isset($exception['Code']) ? (int) $exception['Code'] : 0;
            $isErrorCode = $code > 0 && !in_array($code, [3030, 3031, 3032, 3040], true);

            $rows[] = json_encode($decoded, JSON_PRETTY_PRINT);

            if ($isErrorCode || $response->getStatusCode() >= 400) {
                $msg = is_array($exception) && isset($exception['Message'])
                    ? "COUNTER Exception ({$code}): {$exception['Message']}"
                    : __('credentials.status_request_failed');
                return response()->json([
                    'result' => false,
                    'msg'    => $msg,
                    'rows'   => $rows,
                ]);
            }

            $result = __('credentials.test_success');
        } catch (\Exception $e) {
            $rows[] = $e->getMessage();
            return response()->json([
                'result' => false,
                'msg'    => __('credentials.status_request_failed'),
                'rows'   => $rows,
            ]);
        }
        return response()->json(['result' => true, 'rows' => $rows, 'msg' => $result]);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $credential = Credential::with('provider', 'institution')->findOrFail($id);
        if (!$credential->institution || !$credential->institution->canManage()) {
            return $this->respondError(__('credentials.delete_forbidden'));
        }
        $cnx = Connection::where('inst_id', $credential->inst_id)
            ->where('global_id', $credential->prov_id)
            ->first();
        if ($cnx) {
            $cnx->reports()->detach();
            $cnx->delete();
        }
        $credential->delete();
        return $this->respondSuccess(__('credentials.deleted'));
    }
    /**
     * Bulk operations from the UI.
     */
    public function bulk(Request $request)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return $this->respondError(__('credentials.forbidden_request'));
        }
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();
        $limit_to_insts = array_map('intval', (array) $thisUser->adminInsts());
        if ($limit_to_insts === array(1)) {
            $limit_to_insts = [];
        }
        $credentials = Credential::with('institution:id,name,is_active', 'provider', 'lastHarvest')
            ->whereIn('id', $input['ids'])
            ->when(count($limit_to_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_to_insts))
            ->get();
        if ($input['action'] === 'Enable') {
            $affectedItems = [];
            foreach ($credentials as $cred) {
                $status_before = $cred->status;
                $cred->status = 'Enabled';
                $cred->resetStatus();
                if ($cred->status !== $status_before) {
                    $affectedItems[] = ['id' => $cred->id, 'status' => $cred->status];
                }
            }
            return response()->json(['result' => true, 'msg' => '', 'affectedItems' => $affectedItems], 200);
        }
        if ($input['action'] === 'Disable') {
            $credIds = $credentials->where('status', '<>', 'Disabled')->pluck('id')->toArray();
            Credential::whereIn('id', $credIds)->update(['status' => 'Disabled']);
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $credIds], 200);
        }
        if ($input['action'] === 'Delete') {
            $credIds = $credentials->pluck('id')->toArray();
            Credential::whereIn('id', $credIds)->delete();
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $credIds], 200);
        }
        if (str_contains($input['action'], ' Validat')) {
            $credIds = $credentials->pluck('id')->toArray();
            $_ts = now();
            $args = [];
            if ($input['action'] === 'Set Institution Validated') {
                $args = ['inst_valid' => $_ts];
            } elseif ($input['action'] === 'Set Platform Validated') {
                $args = ['plat_valid' => $_ts];
            } elseif ($input['action'] === 'Clear Validation') {
                $args = ['inst_valid' => null, 'plat_valid' => null];
            }
            if (count($args) > 0) {
                Credential::whereIn('id', $credIds)->update($args);
                return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $credIds, 'data' => $args], 200);
            }
        }
        return $this->respondError(__('credentials.bulk_unrecognized'));
    }
    /**
     * Return an array of IDs for not-set credentials.
     */
    public function unset(Request $request)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return $this->respondError(__('credentials.forbidden_request'));
        }
        $input = $request->all();
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
        if (isset($input['inst_id'])) {
            if (!$consoAdmin && !in_array($input['inst_id'], $limit_insts)) {
                return $this->respondError(__('credentials.forbidden_request'));
            }
            $allPlatIds = GlobalProvider::where('is_active', 1)->pluck('id')->toArray();
            $setPlatIds = Credential::where('inst_id', $input['inst_id'])->pluck('prov_id')->toArray();
            $unset_ids = array_diff($allPlatIds, $setPlatIds);
        } elseif (isset($input['plat_id'])) {
            $allInstIds = Institution::where('is_active', 1)->pluck('id')->toArray();
            $setInstIds = Credential::where('prov_id', $input['plat_id'])
                ->when(!$consoAdmin, fn($qry) => $qry->whereIn('inst_id', $limit_insts))
                ->pluck('inst_id')
                ->toArray();
            $unset_ids = array_diff($allInstIds, $setInstIds);
        } else {
            return $this->respondError(__('credentials.missing_args'));
        }
        return response()->json(['result' => true, 'msg' => '', 'unset_ids' => array_values($unset_ids)]);
    }
    /**
     * Import credentials from a CSV file to the database.
     */
    public function import(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);
        $parsed = $this->parseMappedCsvUpload($request, [
            'inst_id'        => ['Institution ID', 'Institution ID (CC+ System ID)', 'CC+ System ID', 'System ID', 'Inst ID', 'fields.ccplus_system_id'],
            'local_id'       => ['Local ID', 'Local Institution Identifier', 'fields.local_id'],
            'institution'    => ['Institution', 'Institution Name', 'fields.institution'],
            'platform_id'    => ['Platform ID', 'Global ID', 'Prov ID', 'fields.platform_id'],
            'legacy_prov_id' => ['Legacy Platform ID', 'Legacy ID', 'Platform ID (CC+ System ID)', 'fields.legacy_platform_id'],
            'platform'       => ['Platform', 'Platform Name', 'fields.platform'],
            'customer_id'    => ['Customer ID', 'fields.customer_id'],
            'requestor_id'   => ['Requestor ID', 'fields.requestor_id'],
            'api_key'        => ['API Key', 'fields.api_key'],
            'extra_args'     => ['Extra Args', 'Extra Arguments', 'fields.extra_args'],
        ]);

        if (isset($parsed['error'])) {
            return $this->respondError($parsed['error']);
        }

        $consoAdmin = $thisUser->isConsoAdmin();
        $adminInsts = $thisUser->adminInsts();
        $institutions = Institution::when(!$consoAdmin, fn($qry) => $qry->whereIn('id', $adminInsts))
            ->orderBy('name', 'ASC')
            ->get();
        $all_platforms = GlobalProvider::with('connections')->get(['id', 'legacy_id', 'name', 'is_active']);

        $updated = 0;
        $skipped = 0;
        $incomplete = 0;

        foreach ($parsed['rows'] as $row) {
            $rawInstId   = $row['inst_id'] ?? '';
            $rawLocalId  = $row['local_id'] ?? '';
            $rawInstName = $row['institution'] ?? '';

            if ($rawInstId === '' && $rawLocalId === '' && $rawInstName === '') {
                continue;
            }

            $institution = null;
            if ($rawLocalId !== '') {
                $institution = $institutions->where('local_id', $rawLocalId)->first();
            }
            if (!$institution && $rawInstId !== '' && is_numeric($rawInstId)) {
                $institution = $institutions->where('id', intval($rawInstId))->first();
            }
            if (!$institution && $rawInstName !== '') {
                $institution = $institutions->first(fn($i) => strcasecmp($i->name, $rawInstName) === 0);
            }

            if (!$institution) {
                $skipped++;
                continue;
            }

            $rawLegacyPlatId = $row['legacy_prov_id'] ?? '';
            $rawPlatId       = $row['platform_id'] ?? '';
            $rawPlatName     = $row['platform'] ?? '';

            $platform = null;
            if ($rawLegacyPlatId !== '' && is_numeric($rawLegacyPlatId)) {
                $platform = $all_platforms->where('legacy_id', intval($rawLegacyPlatId))->first();
            }
            if (!$platform && $rawPlatId !== '' && is_numeric($rawPlatId)) {
                $platform = $all_platforms->where('id', intval($rawPlatId))->first();
            }
            if (!$platform && $rawPlatName !== '') {
                $platform = $all_platforms->first(fn($p) => strcasecmp($p->name, $rawPlatName) === 0);
            }

            if (!$platform) {
                $skipped++;
                continue;
            }

            $connectedInstIds = $platform->connectedInstitutions();
            if (!in_array($institution->id, $connectedInstIds) && !in_array(1, $connectedInstIds)) {
                Connection::create([
                    'global_id' => $platform->id,
                    'is_active' => 1,
                    'inst_id'   => $institution->id,
                ]);
            }

            $exists = Credential::where('inst_id', $institution->id)
                ->where('prov_id', $platform->id)
                ->first();

            $custId = array_key_exists('customer_id', $row) ? $row['customer_id'] : ($exists?->customer_id ?? '');
            $reqId  = array_key_exists('requestor_id', $row) ? $row['requestor_id'] : ($exists?->requestor_id ?? '');
            $apiKey = array_key_exists('api_key', $row) ? $row['api_key'] : ($exists?->api_key ?? '');
            $extra  = array_key_exists('extra_args', $row) ? $row['extra_args'] : ($exists?->extra_args ?? '');

            $_args = [
                'status'       => 'Enabled',
                'customer_id'  => $custId !== '' ? $custId : null,
                'requestor_id' => $reqId !== '' ? $reqId : null,
                'api_key'      => $apiKey !== '' ? $apiKey : null,
                'extra_args'   => $extra !== '' ? $extra : null,
            ];

            $missing_count = 0;
            foreach ($platform->connectionFields() as $c) {
                if (!$c['required']) {
                    continue;
                }
                if (is_null($_args[$c['name']]) || $_args[$c['name']] === '') {
                    $_args[$c['name']] = '-required-';
                    $missing_count++;
                }
            }

            if ($institution->is_active && $platform->is_active) {
                if ($missing_count === 0) {
                    $_args['status'] = 'Enabled';
                } else {
                    $_args['status'] = 'Incomplete';
                    $incomplete++;
                }
            } else {
                $_args['status'] = 'Suspended';
            }

            if ($exists) {
                if (
                    $exists->customer_id != $_args['customer_id'] ||
                    $exists->api_key != $_args['api_key'] ||
                    $exists->requestor_id != $_args['requestor_id'] ||
                    $exists->extra_args != $_args['extra_args']
                ) {
                    $_args['plat_valid'] = null;
                    $_args['inst_valid'] = null;
                }
            }

            Credential::updateOrCreate(
                ['inst_id' => $institution->id, 'prov_id' => $platform->id],
                $_args
            );
            $updated++;
        }

        $detail = '';
        $detail .= ($updated > 0) ? __('credentials.import_updated', ['count' => $updated]) : '';
        if ($incomplete > 0) {
            $detail .= __('credentials.import_incomplete', ['count' => $incomplete]);
        }
        if ($skipped > 0) {
            $skippedStr = __('credentials.import_skipped', ['count' => $skipped]);
            $detail .= ($detail !== '') ? ", {$skippedStr}" : $skippedStr;
        }

        return $this->respondSuccess(__('credentials.import_completed', ['detail' => $detail]));
    }


    /**
     * Returns filter_options for the UI. Item records returned with auditItems().
     */
    public function audit(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
        $connections = Connection::whereNotNull('global_id')->get();
        $limit_plats = $connections->pluck('global_id')->toArray();
        $all_institutions = Institution::with('institutionGroups:id,name')
            ->where('is_active', 1)
            ->when(!$consoAdmin, fn($query) => $query->whereIn('id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get();
        $filter_options = [
            'valid_types' => [
                'Fully Validated',
                'Not Fully Validated',
                'Institution Not Validated',
                'Platform Not Validated',
            ],
        ];
        $globals = GlobalProvider::with('connections')
            ->whereIn('id', $limit_plats)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);
        $filter_options['platforms'] = $globals->map(function ($plat) {
            return [
                'id'       => $plat->id,
                'name'     => $plat->name,
                'is_conso' => $plat->connections()->where('inst_id', 1)->exists(),
            ];
        });
        $adminGroups = $consoAdmin ? [] : $thisUser->adminGroups();
        $filter_options['groups'] = InstitutionGroup::when(!$consoAdmin, fn($query) => $query->whereIn('id', $adminGroups))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);
        $filter_options['institutions'] = $all_institutions->map(fn($rec) => [
            'id'   => $rec['id'],
            'name' => $rec['name'],
        ])->toArray();
        return response()->json(['records' => [], 'options' => $filter_options], 200);
    }

    /**
     * Returns (possibly filtered) items for the Credential Audit UI.
     */
    public function auditItems(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $consoAdmin = $thisUser->isConsoAdmin();
        $input = $request->all();
        $type = $input['type'] ?? 'harvests';
        if ($type !== 'audit') {
            return $this->respondError(__('credentials.audit_invalid_type'));
        }
        $filters = [
            'valid_types'  => [],
            'platforms'    => [],
            'institutions' => [],
            'groups'       => [],
        ];
        $conso = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
        if (!$conso) {
            return $this->respondError(__('credentials.audit_instance_error'));
        }
        if (is_null(config('ccplus.reports_path'))) {
            return $this->respondError(__('credentials.audit_path_undefined'));
        }
        $report_path = config('ccplus.reports_path') . $conso->id . '/';
        if (!empty($input['filters'])) {
            foreach ($input['filters'] as $key => $val) {
                $filters[$key] = is_null($val) ? [] : $val;
            }
        }
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
        $connections = Connection::whereNotNull('global_id')->get();
        $conso_plats = $connections->where('inst_id', 1)->pluck('global_id')->toArray();
        $limit_plats = $connections->pluck('global_id')->toArray();
        if (count($filters['platforms']) > 0) {
            $limit_plats = array_intersect($limit_plats, $filters['platforms']);
        }
        $credentials = Credential::with([
            'provider',
            'institution',
            'harvestLogs' => function ($qry) {
                $qry->where('status', 'Success')->whereNotNull('rawfile')->orderBy('yearmon', 'DESC');
            },
        ])
            ->when(count($limit_insts) > 0, fn($query) => $query->whereIn('inst_id', $limit_insts))
            ->when(count($limit_plats) > 0, fn($query) => $query->whereIn('prov_id', $limit_plats))
            ->get();
        if ($credentials->isEmpty()) {
            return $this->respondError(__('credentials.audit_no_records'));
        }
        $all_institutions = Institution::with('institutionGroups:id,name')
            ->when(!$consoAdmin, fn($query) => $query->whereIn('id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get();
        $records = [];
        foreach ($credentials as $credential) {
            if (is_null($credential->provider) || is_null($credential->institution)) {
                continue;
            }

            $record = [
                'id'          => $credential->id,
                'plat_id'     => $credential->prov_id,
                'plat_name'   => $credential->provider->name,
                'inst_id'     => $credential->inst_id,
                'inst_name'   => $credential->institution->name,
                'inst_valid'  => is_null($credential->inst_valid) ? 'Inactive' : 'Active',
                'plat_valid'  => is_null($credential->plat_valid) ? 'Inactive' : 'Active',
                'is_conso'    => in_array($credential->prov_id, $conso_plats),
                'valid_state' => [],
            ];
            if (!is_null($credential->inst_valid) && !is_null($credential->plat_valid)) {
                $record['valid_state'][] = 'Fully Validated';
            } else {
                if (is_null($credential->inst_valid)) {
                    $record['valid_state'][] = 'Institution Not Validated';
                }
                if (is_null($credential->plat_valid)) {
                    $record['valid_state'][] = 'Platform Not Validated';
                }
                $record['valid_state'][] = 'Not Fully Validated';
            }
            $json_host = 'No data host';
            $json_inst = 'No institution';
            $json_item = 'No platform';
            if ($credential->harvestLogs) {
                foreach ($credential->harvestLogs as $harv) {
                    $jsonFile = "{$report_path}{$credential->inst_id}/{$credential->prov_id}/{$harv->rawfile}";
                    if (file_exists($jsonFile)) {
                        $raw = Crypt::decrypt(File::get($jsonFile), false);
                        if (function_exists('bzdecompress') && str_starts_with($raw, 'BZh')) {
                            $raw = bzdecompress($raw);
                        } elseif (function_exists('gzdecode') && str_starts_with($raw, "\x1f\x8b")) {
                            $raw = gzdecode($raw);
                        }
                        $json = json_decode($raw);
                        if (isset($json->Report_Header)) {
                            $header = $json->Report_Header;
                            $json_host = $header->Created_By ?? 'no-Created_By';
                            if (!is_null($credential->provider->platform_parm)) {
                                $json_host .= "({$credential->provider->platform_parm})";
                            }
                            $json_inst = $header->Institution_Name ?? 'no-Institution_Name';
                            $firstItem = is_array($json->Report_Items ?? null) ? reset($json->Report_Items) : null;
                            if ($firstItem && isset($firstItem->Platform)) {
                                $json_item = $firstItem->Platform;
                            }
                        } else {
                            $json_host = 'no-Report_Header';
                            $json_inst = 'no-Report_Header';
                        }
                    }
                    if (!str_starts_with($json_host, 'no-') && !str_starts_with($json_inst, 'no-')) {
                        break;
                    }
                }
            }

            $record['json_host'] = $json_host;
            $record['json_item'] = $json_item;
            $record['json_inst'] = $json_inst;
            $record['inst_set']  = ($json_inst !== 'No institution' && !str_starts_with($json_inst, 'no-'));
            $record['plat_set']  = ($json_item !== 'No platform' && !str_starts_with($json_item, 'no-'));
            $record['host_set']  = ($json_host !== 'No data host' && !str_starts_with($json_host, 'no-'));
            $_inst = $all_institutions->where('id', $credential->inst_id)->first();
            $record['group_ids'] = $_inst ? $_inst->institutionGroups()->pluck('institution_group_id')->all() : [];
            $records[] = $record;
        }
        return response()->json([
            'result'  => true,
            'records' => $records,
        ], 200);
    }

    /**
     * Update credential as audit-validated.
     */
    public function setvalidated(Request $request, Credential $credential)
    {
        $thisUser = auth()->user();
        $input = $request->all();
        if (!isset($input['inst_valid']) && !isset($input['plat_valid'])) {
            return response()->json(['msg' => __('credentials.audit_invalid_column'), 'result' => false], 200);
        }
        $key = isset($input['inst_valid']) ? 'inst_valid' : 'plat_valid';
        $limit_to_insts = $thisUser->adminInsts();
        if (!$thisUser->isConsoAdmin() && !in_array($credential->inst_id, $limit_to_insts)) {
            return response()->json(['result' => false], 200);
        }
        $credential->{$key} = ($input[$key] === 'Active') ? now() : null;
        $credential->save();
        return response()->json(['result' => true], 200);
    }
    /**
     * Load master reports ordered by display order.
     */
    private function getMasterReports(): void
    {
        $this->masterReports = Report::where('parent_id', 0)->orderBy('dorder', 'ASC')->get(['id', 'name']);
    }
    /**
     * Build the connector definition array for a credential/provider.
     */
    private function buildConnectorList($allConnectors, array $requiredIds): array
    {
        return $allConnectors->map(function ($gc) use ($requiredIds) {
            $cnx = $gc->toArray();
            $cnx['required'] = in_array($gc->id, $requiredIds);
            return $cnx;
        })->all();
    }
    /**
     * Attach per-report availability and sortval flags onto a credential payload array.
     */
    private function attachReportFlags(array &$record, array $enabledReports): void
    {
        foreach ($enabledReports as $name => $rpt) {
            $record[$name] = $rpt;
            if (!$record[$name]['available']) {
                $record[$name]['sortval'] = 4;
            } elseif ($record[$name]['conso']) {
                $record[$name]['sortval'] = 1;
            } else {
                $record[$name]['sortval'] = $rpt['requested'] ? 3 : 2;
            }
        }
    }
}
