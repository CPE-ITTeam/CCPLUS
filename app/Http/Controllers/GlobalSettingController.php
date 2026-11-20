<?php

namespace App\Http\Controllers;

use App\Models\Consortium;
use App\Models\GlobalSetting;
use App\Models\HarvestLog;
use Illuminate\Http\Request;
use \Illuminate\Contracts\Cache\Factory;

class GlobalSettingController extends BaseController
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:ServerAdmin']);
    }

    /**
     * Settings method for GlobalAdmin Controller
     * @param  String $type    // 'mail', 'config', or 'all'
     * @return JSON
     */
    public function index($type)
    {
        // Get global settings, minus the server admin credentials
        $skip_vars = array('server_admin', 'server_admin_pass', 'max_name_length');
        $all_settings = GlobalSetting::whereNotIn('name', $skip_vars)->get();
        $records = array();
        foreach ($all_settings as $setting) {
            if ($setting->type == $type || $type == 'all') {
                $records[$setting->name] = $setting->value;
            }
        }
        return response()->json(['records' => $records], 200);
    }

    /**
     * Store / Replace global setting variable values
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function store(Request $request, Factory $cache)
    {
        // Validate form inputs
        $this->validate($request, ['settings' => 'required']);
        $input = $request->all();

        // Get all current settings
        $settings = GlobalSetting::get();
        if (!$settings) {
            return response()->json(['result' => false, 'msg' => 'Error pulling current settings!!']);
        }

        // Save all input values that have a (by-name) matching setting in the table
        foreach ($input['settings'] as $key => $input) {
            $setting = $settings->where('name', $key)->first();
            if ($setting) {
                $setting->value = $input;
                $setting->save();
            }
        }

        // Clear the 'ccplus' section of the cached configuration
        $cache->forget('ccplus');
        return response()->json(['result' => true, 'msg' => 'Settings successfully updated!']);
    }

    /**
     * Report active background worker processes and queue backlog counts
     */
    public function workerStatus(Request $request)
    {
        $harvesterProcs = [];
        $processorConsoKeys = [];
        if (function_exists('shell_exec')) {
            $out = shell_exec("ps -eo pid,args -ww | grep -E 'ccplus:(harvester|reportprocessor)' | grep -v grep");
            foreach (explode("\n", trim((string) $out)) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, 'ccplus:harvester')) {
                    $harvesterProcs[] = $line;
                } elseif (preg_match('/ccplus:reportprocessor\s+([^\s]+)/', $line, $m)) {
                    $processorConsoKeys[$m[1]] = ($processorConsoKeys[$m[1]] ?? 0) + 1;
                }

            }
        }

        $consortia = Consortium::orderBy('name')->get();
        $consoBreakdown = [];
        $totalHarvestQueue = 0;
        $totalProcessQueue = 0;

        foreach ($consortia as $conso) {
            config(['database.connections.consodb.database' => 'ccplus_' . $conso->ccp_key]);
            \DB::purge('consodb');

            $harvestCount = HarvestLog::whereIn('status', ['New', 'Queued', 'Harvesting', 'Pending', 'ReQueued', 'Paused'])->count();
            $processCount = HarvestLog::whereIn('status', ['Waiting', 'Processing'])->count();
            $procRunning = $processorConsoKeys[$conso->ccp_key] ?? 0;

            $totalHarvestQueue += $harvestCount;
            $totalProcessQueue += $processCount;

            $consoBreakdown[] = [
                'id'                  => $conso->id,
                'ccp_key'             => $conso->ccp_key,
                'name'                => $conso->name,
                'harvest_queue_count' => $harvestCount,
                'process_queue_count' => $processCount,
                'processor_running'   => $procRunning,
            ];
        }

        return response()->json([
            'result'              => true,
            'harvester_running'   => count($harvesterProcs),
            'processor_running'   => array_sum($processorConsoKeys),
            'harvest_queue_count' => $totalHarvestQueue,
            'process_queue_count' => $totalProcessQueue,
            'consortia'           => $consoBreakdown,
        ]);
    }



    /**
     * Launch a background ccplus:harvester (max 1 per server) or ccplus:reportprocessor worker
     */
    public function runWorker(Request $request)
    {
        $worker = $request->input('worker');
        $consoKey = $request->input('consortium') ?: session('ccp_con_key');
        $artisan = base_path('artisan');
        $php = PHP_BINARY ?: 'php';

        if ($worker === 'harvester') {
            $running = trim((string) shell_exec('ps -eo args -ww | grep ' . escapeshellarg('ccplus:harvester') . ' | grep -v grep'));
            if ($running !== '') {
                return response()->json(['result' => false, 'msg' => 'Harvester worker is already running.']);
            }
            $cmd = sprintf('nohup %s %s ccplus:harvester > /dev/null 2>&1 &', escapeshellarg($php), escapeshellarg($artisan));
            shell_exec($cmd);
            return response()->json(['result' => true, 'msg' => 'Harvester worker started in background.']);
        }

        if ($worker === 'reportprocessor') {
            if (!$consoKey) {
                return response()->json(['result' => false, 'msg' => 'No active consortium selected.'], 422);
            }
            $cmd = sprintf(
                'nohup %s %s ccplus:reportprocessor %s > /dev/null 2>&1 &',
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($consoKey)
            );
            shell_exec($cmd);
            return response()->json(['result' => true, 'msg' => "Report processor started for {$consoKey}."]);
        }

        return response()->json(['result' => false, 'msg' => 'Invalid worker type.'], 422);
    }
}
