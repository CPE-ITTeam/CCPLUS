<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use DB;
use App\Models\Report;
use App\Models\Consortium;
use App\Models\GlobalProvider;
use App\Models\Institution;
use App\Models\Counter5Processor;

class C5Test extends Command
{
    /**
     * C5Test runs a 1-shot attempt to validate, process, and SAVE a given input (JSON) report
     *
     * @var string
     */
    protected $signature = 'ccplus:C5test {consortium : The Consortium ID or key-string}
                              {infile : The input file}
                              {--M|month= : YYYY-MM for the file}
                              {--P|provider= : (Global) Provider ID to process}
                              {--I|institution= : Institution ID to process}
                              {--R|report= : Master report NAME to harvest}
                              {--D|debug=0 : Dump validation details}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Counter processing for a given input file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get the consortium as ID or Key
        $conarg = $this->argument('consortium');
        $consortium = Consortium::find($conarg);
        if (is_null($consortium)) {
            $consortium = Consortium::where('ccp_key', '=', $conarg)->first();
        }
        if (is_null($consortium)) {
            $this->line('Cannot Load Consortium: ' . $conarg);
            return 0;
        }

        // The other required arguments
        $month  = is_null($this->option('month')) ? 'lastmonth' : $this->option('month');
        $prov_id = is_null($this->option('provider')) ? 0 : $this->option('provider');
        $inst_id = is_null($this->option('institution')) ? 0 : $this->option('institution');
        $rept = is_null($this->option('report')) ? 'ALL' : $this->option('report');
        $debug = $this->argument('debug');
        $infile = $this->argument('infile');

        // Aim the consodb connection at specified consortium's database and setup
        // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();

        // Get/confirm report
        $master_report = Report::where('name', $rept)->first();
        if (!$master_report) {
            $this->line("Report (".$rept.") not found; check requested report name");
            return 0;
        }
        // Get/confirm global provider record
        $global = GlobalProvider::where('is_active', 1)->where('id', $prov_id)->first();
        if (!$global) {
            $this->line("Provider (".$prov_id.") not found; check requested (global) provider ID");
            return 0;
        }
        $global_ids = $global->master_reports;
        if (!in_array($master_report->id,$global_ids)) {
            $this->line($global->name . " does not provide the requested report (".$rept.")");
            return 0;
        }

        // Get/confirm institution recordThe name and signature of the console command
        $institution = Institution::where('is_active', 1)->where('id', $inst_id)-first();
        if (!$institution) {
            $this->line("Institution (".$inst_id.") not found; check requested institution ID");
            return 0;
        }

        // Get/confirm input file
        $json_text = file_get_contents($infile);
        if ($json_text === false) {
            $this->line("System Error - reading file {$infile} failed");
            return 0;
        }

        // Try to decode the file as JSON
        $json = json_decode($json_text);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->line("Error decoding JSON - " . json_last_error_msg());
            return 0;
        }

        // Make sure $json is a proper object
        if (! is_object($json)) {
            $this->line('JSON must be an object, found ' . (is_array($json) ? 'an array' : 'a scalar'));
            return 0;
        }

        // Create the processor object
        $C5processor = new Counter5Processor($prov_id, $inst_id, $month, $month, "");
        $this->line("Processing " . $master_report->name . " for " . $global->name);

        // Validate report
        try {
            $report = \ubfr\c5tools\Report::fromFile($filename);
            $checkResult = $report->getCheckResult();
        } catch (Exception $e) {
            $checkResult = new \ubfr\c5tools\CheckResult();
            $checkResult->addFatalError($e->getMessage(), $e->getMessage());
            $this->line($checkResult->asText(0));
            return 0;
        }

        // Get validation results
        $details = null;
        $_res = $report->isUsable() ? "usable" : "unusable";
        $this->line('Validation result shows report is ' . $_res);
        if ($debug) $details = $report->debug();

        // Process and store the report if it's valid
        if ($report->isUsable()) {
            $result = $C5processor->{$master_report->name}($report->asJson());
        } else {
            $this->line("COUNTER Validation Failed");
            return 0;
        }

        // Summarize result
        if ($debug && !is_null($details)) {
            dump($details);
        }
        $this->line("Memory Usage : " . memory_get_usage() . " / " . memory_get_usage(true));
        $this->line("Peak Usage: " . memory_get_peak_usage() . " / " . memory_get_peak_usage(true));
        $this->line("Test completed: " . date("Y-m-d H:i:s"));
        return 1;
    }
}
