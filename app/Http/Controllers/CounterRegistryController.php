<?php

namespace App\Http\Controllers;

use App\Models\ConnectionField;
use App\Models\CounterRegistry;
use App\Models\DataHost;
use App\Models\GlobalProvider;
use App\Models\Report;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class CounterRegistryController extends BaseController
{
    private $masterReports;
    private $allConnectors;
    private $client;
    private $options;

    /**
     * Returns contents of the global counter_registries table as JSON (for the API).
     */
    public function index(Request $request)
    {
        $jsonData = [];

        $masterReports = Report::where('revision', 5)
            ->where('parent_id', 0)
            ->orderBy('dorder', 'ASC')
            ->get(['id', 'name']);
        $allConnectors = ConnectionField::get();

        $records = CounterRegistry::with('globalProv', 'dataHost')->get();
        foreach ($records as $rec) {
            $row = [
                'id'                => $rec->id,
                'global_id'         => $rec->global_id,
                'release'           => $rec->release,
                'datahost_id'       => $rec->datahost_id,
                'service_url'       => $rec->service_url,
                'notifications_url' => $rec->notifications_url,
                'global_platform'   => $rec->globalProv ? $rec->globalProv->name : '',
                'data_host'         => $rec->dataHost ? $rec->dataHost->name : '',
                'connection_fields' => [],
                'master_reports'    => [],
            ];

            if (is_array($rec->connectors) || is_object($rec->connectors)) {
                foreach ($rec->connectors as $id) {
                    $_field = $allConnectors->where('id', $id)->first();
                    if ($_field) {
                        $row['connection_fields'][] = $_field->name;
                    }
                }
            }

            if (is_array($rec->master_reports) || is_object($rec->master_reports)) {
                foreach ($rec->master_reports as $id) {
                    $_rpt = $masterReports->where('id', $id)->first();
                    if ($_rpt) {
                        $row['master_reports'][] = $_rpt->name;
                    }
                }
            }

            $jsonData[] = $row;
        }

        return response()->json(['result' => true, 'records' => $jsonData]);
    }

    /**
     * Return current registry refresh progress.
     */
    public function progress()
    {
        return response()->json(Cache::get('registry_refresh_progress', [
            'current' => 0,
            'total'   => 0,
            'status'  => 'idle',
        ]));
    }

    /**
     * Cancel an in-progress registry refresh.
     */
    public function cancel()
    {
        Cache::put('registry_refresh_cancel', true, 300);
        Cache::forget('registry_refresh_progress');
        return response()->json(['cancelled' => true]);
    }

    /**
     * Pull and return a fresh copy of the registry data for one or all providers.
     */
    public function refresh(Request $request)
    {
        $this->client = new Client();
        $this->options = [
            'headers' => ['User-Agent' => 'Mozilla/5.0 (CC-Plus custom) Firefox/80.0'],
        ];

        $this->validate($request, ['ids' => 'required']);
        Cache::forget('registry_refresh_cancel');
        Cache::put('registry_refresh_progress', [
            'current' => 0,
            'total'   => 0,
            'name'    => __('connections.registry_fetching'),
            'status'  => 'running',
        ], 600);

        $input = $request->all();
        $full_refresh = false;
        $no_refresh = [];

        $all_globals = GlobalProvider::whereNotNull('registry_id')->get();

        if ($input['ids'] === 'ALL') {
            $global_providers = $all_globals->where('refreshable', 1);
            $no_refresh = $all_globals->where('refreshable', 0)->pluck('name')->toArray();
            $is_dialog = false;
            $full_refresh = true;
        } else {
            $is_dialog = $input['dialog'] ?? false;
            if (!is_array($input['ids'])) {
                return $this->respondError(__('connections.registry_invalid_input'));
            }
            $global_providers = $all_globals->where('refreshable', 1)->whereIn('id', $input['ids']);

            if ($global_providers->count() === 0) {
                return $this->respondError(__('connections.registry_not_refreshable'));
            }
            $no_refresh = $all_globals->where('refreshable', 0)->whereIn('id', $input['ids'])->pluck('name')->toArray();
        }

        $gpCount = count($global_providers);
        $ids_to_update = $global_providers->pluck('registry_id')->toArray();

        if ($gpCount === 1) {
            $_gp = $global_providers->first();
            $_url = 'https://countermetrics.org' . $_gp->registry_id . '/?format=json';
        } else {
            $_url = 'https://countermetrics.org?format=json';
        }

        $json = $this->requestURI($_url);
        if ($json === 'Request Failed') {
            return $this->respondError(__('connections.registry_request_failed'));
        }
        if ($json === 'JSON Failed') {
            return $this->respondError(__('connections.registry_json_failed'));
        }

        if (is_array($json)) {
            $platform_records = $json;
            if (count($platform_records) === 0) {
                return $this->respondError(__('connections.registry_empty_data'));
            }
        } elseif (is_object($json)) {
            $platform_records = [$json];
        } else {
            return $this->respondError(__('connections.registry_invalid_datatype'));
        }

        $this->getMasterReports();
        $this->getConnectionFields();
        $datahosts = DataHost::get();

        $api_connectors = [
            'customer_id_info'      => ['field' => 'customer_id', 'id' => null],
            'requestor_id_required' => ['field' => 'requestor_id', 'id' => null],
            'api_key_required'      => ['field' => 'api_key', 'id' => null],
        ];
        foreach ($api_connectors as $key => $cnx) {
            $fld = $this->allConnectors->where('name', $cnx['field'])->first();
            if ($fld) {
                $api_connectors[$key]['id'] = $fld->id;
            }
        }

        $errorData = [
            ['result' => 'success', 'msg' => __('connections.registry_err_success')],
            ['result' => 'failed', 'msg' => __('connections.registry_err_sushi')],
            ['result' => 'failed', 'msg' => __('connections.registry_err_connection')],
            ['result' => 'failed', 'msg' => __('connections.registry_err_no_match')],
        ];

        $success_count = 0;
        $return_data = [];
        $updated_ids = [];
        $no_registryID = [];
        $no_url = [];
        $new_platforms = [];

        Cache::forget('registry_refresh_cancel');
        $total_platforms = $full_refresh ? count($platform_records) : $gpCount;
        $processed_count = 0;
        Cache::put('registry_refresh_progress', [
            'current' => 0,
            'total'   => $total_platforms,
            'name'    => 'Starting...',
            'status'  => 'running',
        ], 600);

        foreach ($platform_records as $platform) {
            if (Cache::get('registry_refresh_cancel')) {
                Cache::put('registry_refresh_progress', [
                    'current' => $processed_count,
                    'total'   => $total_platforms,
                    'name'    => 'Cancelled',
                    'status'  => 'cancelled',
                ], 60);
                break;
            }

            if (is_null($platform->id) || (!$full_refresh && !in_array($platform->id, $ids_to_update))) {
                continue;
            }

            $processed_count++;
            Cache::put('registry_refresh_progress', [
                'current' => $processed_count,
                'total'   => $total_platforms,
                'name'    => $platform->name ?? '',
                'status'  => 'running',
            ], 600);

            $newProvider = false;
            $global_provider = $all_globals->where('registry_id', $platform->id)->first();

            if ($global_provider && $full_refresh) {
                if ($global_provider->refreshable == 0) {
                    continue;
                }
            } elseif (!$global_provider && !$full_refresh) {
                continue;
            }

            $orig_name = $global_provider ? $global_provider->name : '';
            $orig_isActive = $global_provider ? $global_provider->is_active : 0;

            $hasUrl = false;
            foreach ($platform->sushi_services as $service) {
                $svc = isset($service->id) ? $service : $this->requestURI($service->url);
                if (is_object($svc) && strlen(trim($svc->url ?? '')) > 0) {
                    $hasUrl = true;
                    break;
                }
            }

            if (!$hasUrl) {
                if ($global_provider) {
                    CounterRegistry::where('global_id', $global_provider->id)->update(['service_url' => null]);
                } else {
                    if ($gpCount === 1) {
                        return $this->respondError(Arr::get($errorData, '1.msg'));
                    }
                    $return_data[] = ['error' => 1, 'id' => null, 'name' => $platform->name];
                    continue;
                }
            } else {
                if (!$global_provider) {
                    $global_provider = $all_globals->where('name', $platform->name)->first();
                    if ($global_provider) {
                        if ($global_provider->refreshable == 0) {
                            continue;
                        }
                    } else {
                        if ($full_refresh) {
                            $global_provider = new GlobalProvider();
                            $global_provider->refresh_result = 'new';
                            $new_platforms[] = $platform->name;
                            $newProvider = true;
                        } elseif ($gpCount > 1) {
                            continue;
                        } else {
                            return $this->respondError(__('connections.registry_match_error'));
                        }
                    }
                }
                $return_rec = ['error' => 0, 'id' => $global_provider->id, 'name' => $platform->name];
                $releases = [];
                $service_details = [];
                $serviceFailed = false;
                foreach ($platform->sushi_services as $service) {
                    $svc = isset($service->id) ? $service : $this->requestURI($service->url);
                    if (is_object($svc)) {
                        $cr = $svc->counter_release;
                        $releases[] = $cr;
                        $service_details[$cr] = $svc;
                    } elseif (!$is_dialog) {
                        if ($global_provider->refresh_result !== 'new') {
                            $global_provider->refresh_result = 'failed';
                            $global_provider->updated_at = now();
                            $global_provider->save();
                        }
                        if ($gpCount === 1) {
                            return $this->respondError(Arr::get($errorData, '2.msg'));
                        }
                        $return_rec['error'] = 2;
                        $return_data[] = $return_rec;
                        $serviceFailed = true;
                        break;
                    }
                }
                if ($serviceFailed) {
                    continue;
                }
                if (!$is_dialog && count($service_details) === 0) {
                    if ($global_provider->refresh_result !== 'new') {
                        $global_provider->refresh_result = 'failed';
                        $global_provider->is_active = 0;
                        $global_provider->updated_at = now();
                        $global_provider->save();
                        if ($orig_isActive) {
                            $global_provider->applyToInstances();
                        }
                    }
                    if ($gpCount === 1) {
                        return $this->respondError(Arr::get($errorData, '1.msg'));
                    }
                    $return_rec['error'] = 1;
                    $return_data[] = $return_rec;
                    continue;
                }
                $global_provider->load('registries', 'registries.dataHost');
                foreach ($global_provider->registries as $reg) {
                    $len = isset($reg->service_url) ? strlen(trim($reg->service_url)) : 0;
                    if ($global_provider->registries->count() > 1 && (!in_array($reg->release, $releases) || $len === 0)) {
                        $reg->delete();
                    }
                }
                $default_url = $global_provider->service_url();
                if (strlen($default_url) > 0) {
                    $hasUrl = true;
                }
            }
            $connectors_changed = false;
            if (!$hasUrl) {
                $no_url[] = $global_provider->name;
                $global_provider->is_active = 0;
                $global_provider->refresh_result = 'partial';
                $global_provider->updated_at = now();
                if (!$is_dialog) {
                    $global_provider->save();
                }
            } else {
                $platformReports = $this->masterReports->map(function ($rec) use ($platform) {
                    $releases = [];
                    foreach ($platform->reports as $pRep) {
                        if ($rec->name == $pRep->report_id) {
                            $releases[] = $pRep->counter_release;
                        }
                    }
                    return [
                        'id'       => $rec->id,
                        'name'     => $rec->name,
                        'releases' => $releases,
                    ];
                })->all();
                $global_provider->registry_id = $platform->id;
                $global_provider->name = $platform->name;
                $global_provider->content_provider = $platform->content_provider_name;
                $global_provider->abbrev = $platform->abbrev;
                $global_provider->refresh_result = $newProvider ? 'new' : 'success';
                $global_provider->updated_at = now();
                if (!$is_dialog) {
                    $global_provider->save();
                }
                $old_connectors = $global_provider->connectors();
                foreach ($service_details as $release => $details) {
                    if (strlen(trim($details->url)) > 0) {
                        $registry = $global_provider->registries->where('release', $release)->first();
                        if (!$registry) {
                            $registry = new CounterRegistry();
                            $registry->global_id = $global_provider->id;
                            $registry->release = $release;
                            $zero_reg = $global_provider->registries->where('release', '0')->first();
                            if ($zero_reg) {
                                $zero_reg->delete();
                            }
                        }
                        $_reports = [];
                        foreach ($platformReports as $pRep) {
                            if (in_array($release, $pRep['releases'])) {
                                $_reports[] = $pRep['id'];
                            }
                        }
                        $registry->master_reports = $_reports;
                        $registry->datahost_id = null;
                        if (isset($details->data_host)) {
                            $datahost_url = $details->data_host;
                            $parts = explode('/', $datahost_url);
                            $host_id = trim((string) Arr::get($parts, count($parts) - 2, ''));
                            $dataHost = $datahosts->where('id', $host_id)->first();
                            if ($dataHost) {
                                $registry->datahost_id = $host_id;
                            } else {
                                $host = $this->requestURI($datahost_url);
                                if (is_object($host)) {
                                    try {
                                        $dataHost = DataHost::create([
                                            'datahost_key' => $host->id,
                                            'name'         => $host->name,
                                        ]);
                                        $registry->datahost_id = $dataHost->id;
                                        $datahosts->push($dataHost);
                                    } catch (\Exception $e) {
                                        $registry->datahost_id = null;
                                    }
                                }
                            }
                        }
                        $registry->service_url = trim($details->url);
                        $registry->notifications_url = trim((string) ($details->notifications_url ?? ''));
                        $connectors = [];
                        foreach ($api_connectors as $key => $cnx) {
                            if ($key === 'customer_id_info' || !empty($details->{$key})) {
                                $connectors[] = $cnx['id'];
                            }
                        }
                        foreach ($global_provider->connectionFields() as $cf) {
                            if ($cf['name'] === 'extra_args' && $cf['required']) {
                                $connectors[] = $cf['id'];
                                break;
                            }
                        }
                        $registry->connectors = $connectors;
                        $registry->save();
                    }
                }
                $global_provider->load('registries');
                $cur_connectors = $global_provider->connectors();
                $connectors_changed = ($cur_connectors != $old_connectors);
                $success_count++;
            }
            if (!$is_dialog) {
                $_release = $global_provider->default_release();
                $global_provider->update(['selected_release' => $_release]);
            }
            if ($global_provider->name != $orig_name || $global_provider->is_active != $orig_isActive || $connectors_changed) {
                $global_provider->applyToInstances();
            }
            $return_rec = $global_provider->toArray();
            $return_rec['registries'] = [];
            $return_rec['release'] = $global_provider->default_release();
            foreach ($global_provider->registries->sortBy('release') as $reg) {
                $data = $reg->toArray();
                $data['connector_state'] = $this->connectorState($reg->connectors);
                $data['report_state'] = $this->reportState($reg->master_reports);
                if ($reg->release == $return_rec['release']) {
                    $return_rec['data_host'] = $reg->dataHost ? $reg->dataHost->name : '-missing-';
                }
                $return_rec['registries'][] = $data;
            }
            $return_rec['release'] = $global_provider->default_release();
            $return_rec['service_url'] = $global_provider->service_url();
            $return_rec['status'] = $global_provider->is_active ? 'Active' : 'Inactive';
            $return_rec['refreshable'] = $global_provider->refreshable ? 'Active' : 'Inactive';
            $return_rec['updated'] = date('Y-m-d H:i', strtotime($global_provider->updated_at));
            $updated_ids[] = $global_provider->id;
            $return_rec['error'] = 0;
            $return_data[] = $return_rec;
        }
        $was_cancelled = (bool) Cache::get('registry_refresh_cancel');
        if (!$was_cancelled) {
            Cache::put('registry_refresh_progress', [
                'current' => $processed_count,
                'total'   => $total_platforms,
                'name'    => 'Complete',
                'status'  => 'done',
            ], 60);
        }
        if (count($updated_ids) === 0) {
            $_msg = $was_cancelled
                ? __('connections.registry_cancelled')
                : ($gpCount > 1 ? __('connections.registry_no_updates') : __('connections.registry_refresh_failed'));
            return $this->respondError($_msg);
        }
        if (!$is_dialog && !$was_cancelled && count($updated_ids) > 0) {
            $orphans = $global_providers->where('is_active', 1)->whereNotIn('id', $updated_ids)->all();
            foreach ($orphans as $gp) {
                $gp->refresh_result = 'orphan';
                $gp->is_active = 0;
                if (is_null($gp->registry_id) || $gp->registry_id === '') {
                    $no_registryID[] = $gp->name;
                } else {
                    $return_data[] = ['error' => 3, 'id' => $gp->id, 'name' => $gp->name];
                }
                $gp->save();
            }
        }
        $summary_html = '';
        if ($gpCount > 1) {
            $summary_html = ($success_count > 0) ? __('connections.registry_sum_refreshed', ['count' => $success_count]) : '';
            if (count($no_refresh) > 0) {
                $summary_html .= ($summary_html === '') ? '' : '
';
                $summary_html .= '' . __('connections.registry_sum_disabled', ['count' => count($no_refresh)]) . '';
            }
            if (count($new_platforms) > 0) {
                $summary_html .= ($summary_html === '') ? '' : '
';
                $summary_html .= '' . __('connections.registry_sum_new') . '
';
                foreach ($new_platforms as $name) {
                    $summary_html .= $name . '
';
                }
            }
            if (count($no_registryID) > 0) {
                $summary_html .= ($summary_html === '') ? '' : '
';
                $summary_html .= '' . __('connections.registry_sum_no_id') . '
';
                foreach ($no_registryID as $name) {
                    $summary_html .= $name . '
';
                }
            }
            if (count($no_url) > 0) {
                $summary_html .= ($summary_html === '') ? '' : '
';
                $summary_html .= '' . __('connections.registry_sum_no_url') . '
';
                foreach ($no_url as $name) {
                    $summary_html .= $name . '
';
                }
            }
            for ($eid = 1; $eid < count($errorData); $eid++) {
                $error = $errorData[$eid];
                $matches = array_filter($return_data, fn($rec) => $rec['error'] == $eid);
                if (count($matches) > 0) {
                    $summary_html .= '
' . $error['msg'] . ':
';
                    foreach ($matches as $rec) {
                        $summary_html .= $rec['name'] . '
';
                    }
                }
            }
        }
        return response()->json(['result' => true, 'affectedItems' => $return_data, 'summary' => $summary_html]);
    }
    /**
     * Fetch and decode a JSON endpoint from the COUNTER registry.
     */
    private function requestURI(string $uri)
    {
        try {
            $result = $this->client->request('GET', $uri, $this->options);
        } catch (\Exception $e) {
            return 'Request Failed';
        }
        $json = json_decode((string) $result->getBody());
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'JSON Failed';
        }
        return $json;
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
        $cnx_state = [];
        foreach ($this->allConnectors as $fld) {
            $cnx_state[$fld->name] = in_array($fld->id, (array) $connectors);
        }
        return $cnx_state;
    }
}
