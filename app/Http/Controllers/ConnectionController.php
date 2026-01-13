<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\GlobalProvider;
use App\Models\Report;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    /**
     * Return provider connection data as JSON request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isConsoAdmin(), 403);

        // Get all active Global Providers
        $globals = GlobalProvider::with("consoProviders","consoProviders.reports")
                                 ->where('is_active',1)->get();

        // Get Consortium providers, with report-settings that currently defined as conso-wide        
        $consos = Provider::with('reports')->where('inst_id',1)->get();

        // Get master report definitions
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Keep track of the last error values for the filter options
        $nh_count = 0;
        $seen_codes = array(0); // pretend we've seen success

        // Map in report connections
        $connections = array();
        foreach ($globals as $global) {
            $rec = array('id' => $global->id, 'platform' => $global->name, 'can_edit' => false, 'can_delete' => false,
                         'result' => null);

            // Get last_harvest data if one exists
            $lastHarvest = $global->lastHarvest();
            if ($lastHarvest) {
                $rec['result'] = ($lastHarvest->status == 'Success' || $lastHarvest->error_id==0)
                                 ? 'Success' : $lastHarvest->error_id;
                if ($lastHarvest->error_id>0 && !in_array($lastHarvest->error_id,$seen_codes)) {
                    $seen_codes[] = $lastHarvest->error_id;
                }
            } else {
                $nh_count++;
                $rec['result'] = 'No Harvests';
            }

            // Determine if this is a consortium-provider and what, if any, reports are enabled
            $conso_prov = $consos->where('global_id',$global->id)->first();
            $conso_ids = ($conso_prov) ? $conso_prov->reports->pluck('id')->toArray() : array();

            // Set boolean flags for available and conso and add sortval
            foreach ($master_reports as $mr) { 
                $available = (in_array($mr->id, $global->master_reports));
                $conso = (in_array($mr->id, $conso_ids));
                // Set sortval ( 1=conso, 2=available, 3=not-available )
                $sortval = ($conso) ? 1 : 2;
                if (!$available) $sortval = 3;
                $rec[$mr->name] = array( 'available' => $available, 'conso' => $conso, 'sortval' => $sortval);
            }
            $connections[] = $rec;
        }

        // Setup results for filter options
        $options = array('results' => array());
        sort($seen_codes);
        foreach ($seen_codes as $code) {
            $_val = ($code>0) ? $code : 'Success';
            $options['results'][] = array('result' => $_val);
        }
        if ($nh_count > 0) {
            $options['results'][] = array('result' => 'No Harvests');
        }

        // Return the data array
        return response()->json(['records' => $connections, 'options' => $options, 'result' => true], 200);
    }

    /**
     * Set/update report access/availability
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function access(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->isConsoAdmin(), 403);

        $this->validate($request, ['id' => 'required', 'rept' => 'required', 'flags' => 'required']);
        $input = $request->all();

        // Validate flag and report values
        $flags = $input['flags'];
        if (!isset($flags['conso']) || !isset($flags['available'])) {
            return response()->json(['result' => false, 'msg' => 'Invalid input access values']);
        }
        $report = Report::where('name',$input['rept'])->first(['id','name']);
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Unknown report requested']);
        }

        // Get global provider; throw error if not found, or not available
        $gp = GlobalProvider::where('id',$input['id'])->first();
        if (!$gp || !$flags['available']) {
            return response()->json(['result' => false, 'msg' => 'Global Platform reference error']);
        }

        // Check for or create a related consortium provider entry
        $provider = Provider::with('reports')->where('inst_id',1)->where('global_id',$gp->id)->first();
        if (!$provider) {
            // Enable a report for a platform not-yet defined as conso-available
            if ($flags['conso']) {
                $_data = array('name' => $gp->name, 'global_id' => $gp->id, 'is_active' => $gp->is_active, 'inst_id' => 1);
                $provider = Provider::create($_data);

            // Trying to turn off something that cannot be found...?
            } else {
                return response()->json(['result' => false, 'msg' => 'Consortium Platform reference error']);
            }
        }

        // Attach/add report to the provider
        if ($input['flags']['conso']) {
            $provider->reports()->attach($report->id);

            // Detach any matching institution-specific reports definitions
            $conso_ids = $provider->reports->pluck('id')->toArray();
            $res = $gp->updateReports($conso_ids, "detach");

        // Detach/remove report for the (conso) provider
        // Retain the pre-removal report IDs re-attach the report to any existing
        // (institution-specific) provider(s) after the conso-value is cleared
        } else {
            $conso_ids = $provider->reports->pluck('id')->toArray();
            $provider->reports()->detach($report->id);
            $res = $gp->updateReports($conso_ids, "attach");

            // If the (conso) provider has no remaining reports attached, delete it
            if ($provider->reports()->count() == 0) {
                $provider->delete();
            }
        }
        return response()->json(['result' => true, 'msg' => 'Acess updated successfully']);
    }

}
