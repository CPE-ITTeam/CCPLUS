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

        $limit_to_insts = $thisUser->adminInsts();
        if ($limit_to_insts ===) {
            $limit_to_insts = [];
        }

        $data = Credential::with('institution:id,name,is_active,local_id', 'provider', 'lastHarvest')
            ->when(count($limit_to_insts) > 0, fn ($qry) => $qry->whereIn('inst_id', $limit_to_insts))
            ->get();

        $globals = GlobalProvider::with('registries', 'connections', 'connections.reports')
            ->orderBy('name', 'ASC')
            ->get();

        $global_connectors = ConnectionField::get();
        $filter_options = [
            'results'   => [],
            'statuses'  => $data->unique('status')->pluck('status')->toArray(),
            'platforms' => $globals->map(function ($plat) use ($global_connectors) {
                $_connectors = [];
                $required = $plat->connectors();
                foreach ($global_connectors as $gc) {
                    $_connectors[$gc->name] = in_array($gc->id, $required);
                }
                return [
                    'id'         => $plat->id,
                    'name'       => $plat->name,
                    'is_active'  => $plat->is_active,
                    'connectors' => $_connectors,
                ];
            }),
        ];

        if ($thisUser->isConsoAdmin()) {
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
        $seen_codes =;
        $this->getMasterReports();

        $credentials = [];
        foreach ($data as $cred) {
            if (!$cred->provider) {
                continue;
            }

            $rec = [
                'id'           => $cred->id,
                'status'       => $cred->status,
                'inst_id'      => $cred->inst_id,
                'prov_id'      => $cred->prov_id,
                'customer_id'  => $cred->customer_id,
                'requestor_id' => $cred->requestor_id,
                'api_key'      => $cred->api_key,
                'platform'     => $cred->provider->name,
                'can_edit'     => true,
                'can_delete'   => true,
                'inst_active'  => $cred->institution->is_active,
                'plat_active'  => $cred->provider->is_active,
                'service_url'  => $cred->provider->service_url(),
                'connectors'   => $this->buildConnectorList($global_connectors, $cred->provider->connectors()),
                'institution'  => $cred->institution ? $cred->institution->name : '',
                'local_id'     => $cred->institution ? $cred->institution->local_id : '',
                'connected'    => ($cred->status === 'Enabled'),
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

            $global = $globals->where('id', $cred->prov_id)->first();
            if ($global) {
                $this->attachReportFlags($rec, $global->enabledReports($cred->inst_id));
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

        if (!$thisUser->canManage($input['inst_id'])) {
            return $this->respondError('You can only assign credentials for your institution');
        }

        if (!isset($input['prov_id'])) {
            return $this->respondError('Platform assignment is required');
        }

        $gp = GlobalProvider::find($input['prov_id']);
        if (!$gp) {
            return $this->respondError('Platform unknown or undefined');
        }

        $exists = Credential::where('inst_id', $input['inst_id'])
            ->where('prov_id', $input['prov_id'])
            ->exists();

        if ($exists) {
            return $this->respondError('A credential already exists for this provider and institution');
        }

        $connected_inst_ids = $gp->connectedInstitutions();
        if (!in_array(1, $connected_inst_ids) && !in_array($input['inst_id'], $connected_inst_ids)) {
            $new_conn = Connection::create([
                'global_id' => $gp->id,
                'is_active' => $gp->is_active,
                'inst_id'   => $input['inst_id'],
            ]);

            $this->getMasterReports();
            $masters = $this->masterReports->whereIn('id', $gp->master_reports());
            foreach ($masters as $rpt) {
                $new_conn->reports()->attach($rpt->id);
            }
        }

        $fields = Arr::except($input, ['institutions', 'platforms']);
        $cred = Credential::firstOrCreate($fields);
        $cred->load('institution', 'provider');

        $global_connectors = ConnectionField::get();
        $rec = [
            'id'           => $cred->id,
            'status'       => 'Enabled',
            'connected'    => true,
            'result'       => 'No Harvests',
            'inst_id'      => $cred->inst_id,
            'prov_id'      => $cred->prov_id,
            'customer_id'  => $cred->customer_id,
            'requestor_id' => $cred->requestor_id,
            'api_key'      => $cred->api_key,
            'platform'     => $cred->provider->name,
            'can_edit'     => true,
            'can_delete'   => true,
            'service_url'  => $cred->provider->service_url(),
            'connectors'   => $this->buildConnectorList($global_connectors, $cred->provider->connectors()),
            'institution'  => $cred->institution ? $cred->institution->name : '',
        ];

        $this->attachReportFlags($rec, $gp->enabledReports($cred->inst_id));

        return $this->respondSuccess('Credentials successfully created', $rec);
    }

    /**
     * Set/update credential values and report access/availability.
     */
    public function update(Request $request)
    {
        $this->validate($request, ['id' => 'required']);
        $input = $request->all();

        $cred = Credential::with('institution', 'provider')->findOrFail($input['id']);
        $global = GlobalProvider::findOrFail($cred->prov_id);
        $institution = Institution::findOrFail($cred->inst_id);

        if (!$institution->canManage()) {
            return $this->respondError('Not Authorized to update credential');
        }

        if (!$cred->institution || !$cred->provider) {
            return $this->respondError('Credential reference error for platform or institution');
        }

        $fields = [
            'status'       => in_array($input['status'] ?? '', ['Enabled', 'Disabled'], true) ? $input['status'] : $cred->status,
            'customer_id'  => $input['customer_id'] ?? null,
            'requestor_id' => $input['requestor_id'] ?? null,
            'api_key'      => $input['api_key'] ?? null,
            'extra_args'   => $input['extra_args'] ?? null,
        ];

        foreach ($fields as $fld => $val) {
            $cred->$fld = $val;
        }

        // Enforce '-required-' placeholder on blank required connectors
        $registry = $cred->provider->default_registry();
        $all_connectors = ConnectionField::get();
        foreach ($all_connectors as $cnx) {
            if (
                in_array($cnx->id, $registry->connectors) &&
                (is_null($cred->{$cnx->name}) || $cred->{$cnx->name} === '')
            ) {
                $cred->{$cnx->name} = '-required-';
            }
        }

        // Updating credential values resets validation timestamps
        $cred->plat_valid = null;
        $cred->inst_valid = null;

        if ($cred->status === 'Disabled') {
            $cred->save();
        } else {
            $cred->resetStatus(true);
        }

        // Update report settings
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
$record = $input;
$record['status'] = $cred->status;
$record['platform'] = $cred->provider->name;
$record['institution'] = $cred->institution->name;
$record['connectors'] = $this->buildConnectorList($all_connectors, $registry->connectors);
$record['connected'] = ($cred->status === 'Enabled');
$this->attachReportFlags($record, $global->enabledReports($cred->inst_id));
return $this->respondSuccess('Credential updated successfully', $record);
}
/**
* Update report definition for a connection.
*/
private function updateInstReport(int $inst_id, ?GlobalProvider $global, int $report_id, array $flags): array
{
if (!$global) {
return ['success' => false, 'msg' => 'Global platform not found or report unavailable'];
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
return $this->respondError('Error decoding input!');
}
if (!isset($input['type'])) {
return $this->respondError('Type is a required argument!');
}
$provider = GlobalProvider::findOrFail($input['prov_id']);
$options = [
'headers' => ['User-Agent' => 'Mozilla/5.0 (CC-Plus custom) Firefox/80.0'],
];
$_url = rtrim($provider->service_url());
$_url = preg_replace('//(reports|status|members)/?$/i', '', $_url);
$_uri = rtrim($_url, '/');
if (isset($input['extra_args'])) {
$input['extra_args'] = ltrim(trim($input['extra_args']), '&?');
}
$request_uri = '';
if ($input['type'] === 'test') {
$_uri .= '/reports/pr?';
$uri_auth = !is_null($provider->platform_parm) ? 'platform=' . $provider->platform_parm : '';
foreach (['customer_id', 'requestor_id', 'api_key', 'extra_args'] as $fld) {
if (isset($input[$fld]) && $input[$fld] !== '') {
$uri_auth .= ($uri_auth === '') ? '' : '&';
$uri_auth .= ($fld === 'extra_args')
? urlencode($input['extra_args'])
: $fld . '=' . urlencode($input[$fld]);
}
}
$dates = '&begin_date=' . date('Y-m-d', strtotime('first day of previous month'))
. '&end_date=' . date('Y-m-d', strtotime('last day of previous month'));
$request_uri = $_uri . $uri_auth . $dates;
}
$rows = [];
$client = new Client();
$result = '';
try {
if ($input['type'] === 'status') {
$response = $client->request('GET', $_uri . '/status', $options);
$json = (string) $response->getBody();
$rows[] = 'Service Status: ';
} else {
$response = $client->request('GET', $request_uri, $options);
$json = (string) $response->getBody();
$rows[] = 'Request URL: ';
$rows[] = $request_uri;
$rows[] = 'JSON Response:';
}
$begin_txt = substr(trim($json), 0, 80);
$decoded = json_decode($json, true);
if (stripos($begin_txt, 'doctype html') !== false || stripos($begin_txt, '<html') !== false) {
$result = 'Invalid response - JSON expected but request returned HTML';
$rows = '';
} elseif (json_last_error() !== JSON_ERROR_NONE) {
$result = 'Invalid response - JSON expected but not received - check URL';
$rows = '';
} else {
$result = 'Request response successfully received';
$rows[] = json_encode($decoded, JSON_PRETTY_PRINT);
}
} catch (\Exception $e) {
$rows[] = $e->getMessage();
return response()->json([
'result' => false,
'msg'    => 'Request for service status failed!',
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
return $this->respondError('Delete failed (403) - Forbidden');
}
$cnx = Connection::where('inst_id', $credential->inst_id)
->where('global_id', $credential->prov_id)
->first();
if ($cnx) {
$cnx->reports()->detach();
$cnx->delete();
}
$credential->delete();
return $this->respondSuccess('Credentials successfully deleted');
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
$this->validate($request, ['ids' => 'required', 'action' => 'required']);
$input = $request->all();
$limit_to_insts = $thisUser->adminInsts();
if ($limit_to_insts ===) {
$limit_to_insts = [];
}
$credentials = Credential::with('institution:id,name,is_active', 'provider', 'lastHarvest')
->whereIn('id', $input['ids'])
->when(count($limit_to_insts) > 0, fn ($qry) => $qry->whereIn('inst_id', $limit_to_insts))
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
return $this->respondError('Unrecognized bulk action requested');
}
/**
* Return an array of IDs for not-set credentials.
*/
public function unset(Request $request)
{
$thisUser = auth()->user();
if (!$thisUser->isAdmin()) {
return $this->respondError('Request failed (403) - Forbidden');
}
$input = $request->all();
$consoAdmin = $thisUser->isConsoAdmin();
$limit_insts = $consoAdmin ? [] : $thisUser->adminInsts();
if (isset($input['inst_id'])) {
if (!$consoAdmin && !in_array($input['inst_id'], $limit_insts)) {
return $this->respondError('Request failed (403) - Forbidden');
}
$allPlatIds = GlobalProvider::where('is_active', 1)->pluck('id')->toArray();
$setPlatIds = Credential::where('inst_id', $input['inst_id'])->pluck('prov_id')->toArray();
$unset_ids = array_diff($allPlatIds, $setPlatIds);
} elseif (isset($input['plat_id'])) {
$allInstIds = Institution::where('is_active', 1)->pluck('id')->toArray();
$setInstIds = Credential::where('prov_id', $input['plat_id'])
->when(!$consoAdmin, fn ($qry) => $qry->whereIn('inst_id', $limit_insts))
->pluck('inst_id')
->toArray();
$unset_ids = array_diff($allInstIds, $setInstIds);
} else {
return $this->respondError('Request failed - Missing argument(s)');
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
$parsed = $this->parseCsvUpload($request);
if (isset($parsed['error'])) {
return $this->respondError($parsed['error']);
}
$rows = $parsed['rows'];
$consoAdmin = $thisUser->isConsoAdmin();
$adminInsts = $thisUser->adminInsts();
$institutions = Institution::when(!$consoAdmin, fn ($qry) => $qry->whereIn('id', $adminInsts))
->orderBy('name', 'ASC')
->get();
$all_platforms = GlobalProvider::with('connections')->get(['id', 'name']);
$updated = 0;
$skipped = 0;
$incomplete = 0;
foreach ($rows as $rowNum => $row) {
if ($rowNum === 0 || (!isset($row) && !isset($row))) {
continue;
}
$input_inst_id = isset($row) ? strval(trim($row)) : null;
$localID = isset($row) && strlen(trim($row)) > 0 ? trim($row) : null;
if (!$localID && ($input_inst_id === '' || !is_numeric($input_inst_id))) {
$skipped++;
continue;
}
$institution = $localID
? $institutions->where('local_id', $localID)->first()
: (!is_null($input_inst_id) ? $institutions->where('id', $input_inst_id)->first() : null);
if (!$institution) {
$skipped++;
continue;
}
$platform = isset($row) ? $all_platforms->where('id', $row)->first() : null;
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
$_args = [
'status'       => 'Enabled',
'customer_id'  => isset($row) ? trim($row) : null,
'requestor_id' => isset($row) ? trim($row) : null,
'api_key'      => isset($row) ? trim($row) : null,
'extra_args'   => isset($row) ? trim($row) : null,
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
$exists = Credential::where('inst_id', $institution->id)
->where('prov_id', $platform->id)
->first();
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
$msg = '';
$msg .= ($updated > 0) ? "{$updated} added or updated" : '';
if ($incomplete > 0) {
$msg .= " ({$incomplete} were incomplete)";
}
if ($skipped > 0) {
$msg .= ($msg !== '') ? ", {$skipped} skipped" : "{$skipped} skipped";
}
return $this->respondSuccess('COUNTER credentials import completed : ' . $msg);
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
->when(!$consoAdmin, fn ($query) => $query->whereIn('id', $limit_insts))
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
$filter_options['groups'] = InstitutionGroup::when(!$consoAdmin, fn ($query) => $query->whereIn('id', $adminGroups))
->orderBy('name', 'ASC')
->get(['id', 'name']);
$filter_options['institutions'] = $all_institutions->map(fn ($rec) => [
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
return $this->respondError('Invalid request type for auditItems');
}
$filters = [
'valid_types'  => [],
'platforms'    => [],
'institutions' => [],
'groups'       => [],
];
$conso = Consortium::where('ccp_key', $request->header('X-Tenant'))->first();
if (!$conso) {
return $this->respondError('Error getting current instance data');
}
if (is_null(config('ccplus.reports_path'))) {
return $this->respondError('Global Setting for reports_path is undefined - Stopping!');
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
->when(count($limit_insts) > 0, fn ($query) => $query->whereIn('inst_id', $limit_insts))
->when(count($limit_plats) > 0, fn ($query) => $query->whereIn('prov_id', $limit_plats))
->get();
if ($credentials->isEmpty()) {
return $this->respondError('No matching connected credentials to audit.');
}
$all_institutions = Institution::with('institutionGroups:id,name')
->when(!$consoAdmin, fn ($query) => $query->whereIn('id', $limit_insts))
->orderBy('name', 'ASC')
->get();
$selectedValidTypes = (array) $filters['valid_types'];
$records = [];
foreach ($credentials as $credential) {
if (is_null($credential->provider) || is_null($credential->institution)) {
continue;
}
if (count($selectedValidTypes) > 0) {
$matches = false;
if (in_array('Fully Validated', $selectedValidTypes, true) && $credential->inst_valid && $credential->plat_valid) {
$matches = true;
}
if (in_array('Not Fully Validated', $selectedValidTypes, true) && (is_null($credential->inst_valid) || is_null($credential->plat_valid))) {
$matches = true;
}
if (in_array('Institution Not Validated', $selectedValidTypes, true) && is_null($credential->inst_valid)) {
$matches = true;
}
if (in_array('Platform Not Validated', $selectedValidTypes, true) && is_null($credential->plat_valid)) {
$matches = true;
}
if (!$matches) {
continue;
}
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
$json = json_decode(bzdecompress(Crypt::decrypt(File::get($jsonFile), false)));
if (isset($json->Report_Header)) {
$header = $json->Report_Header;
$json_host = $header->Created_By ?? 'no-Created_By';
if (!is_null($credential->provider->platform_parm)) {
$json_host .= "({$credential->provider->platform_parm})";
}
$json_inst = $header->Institution_Name ?? 'no-Institution_Name';
if (isset($json->Report_Items) && is_array($json->Report_Items) && isset($json->Report_Items->Platform)) {
$json_item = $json->Report_Items->Platform;
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
$record['inst_set']  = ($json_inst !== 'No institution');
$record['plat_set']  = ($json_item !== 'No platform');
$record['host_set']  = ($json_host !== 'No data host');
$_inst = $all_institutions->where('id', $credential->inst_id)->first();
$record['group_ids'] = $_inst ? $_inst->institutionGroups()->pluck('institution_group_id')->all() : [];
$records[] = $record;
}
return response()->json(['result' => true, 'records' => $records, 'truncated' => false], 200);
}
/**
* Update credential as audit-validated.
*/
public function setvalidated(Request $request, Credential $credential)
{
$thisUser = auth()->user();
$input = $request->all();
if (!isset($input['inst_valid']) && !isset($input['plat_valid'])) {
return response()->json(['msg' => 'Invalid request - no such column', 'result' => false], 200);
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
