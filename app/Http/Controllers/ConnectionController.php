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

        // Map in report connections
        $connections = array();
        foreach ($globals as $global) {
            $rec = array('id' => $global->id, 'platform' => $global->name, 'can_edit' => false, 'can_delete' => false);

            // Determine if this is a consortium-provider and what, if any, reports are enabled
            $conso_prov = $consos->where('global_id',$global->id)->first();
            $conso_ids = ($conso_prov) ? $conso_prov->reports->pluck('id')->toArray() : array();

            // Set boolean flags for available and conso
            foreach ($master_reports as $mr) { 
                $available = (in_array($mr->id, $global->master_reports));
                $conso = (in_array($mr->id, $conso_ids));
                $rec[$mr->name] = array( 'available' => $available, 'conso' => $conso);
            }
            $connections[] = $rec;
        }

        // Return the data array
        return response()->json(['records' => $connections, 'options' => array(), 'result' => true], 200);
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
            $res = $this->updateReports($gp->id, $conso_ids, "detach");

        // Detach/remove report for the (conso) provider
        // Retain the pre-removal report IDs re-attach the report to any existing
        // (institution-specific) provider(s) after the conso-value is cleared
        } else {
            $conso_ids = $provider->reports->pluck('id')->toArray();
            $provider->reports()->detach($report->id);
            $res = $this->updateReports($gp->id, $conso_ids, "attach");

            // If the (conso) provider has no remaining reports attached, delete it
            if ($provider->reports()->count() == 0) {
                $provider->delete();
            }
        }
        return response()->json(['result' => true, 'msg' => 'Acess updated successfully']);
    }

    /**
     * Updates report-assignments
     *
     * @param  Integer global_id
     * @param  Array   conso_ids  (consortium report ID's to match on)
     * @param  String  type : operation to perform
     * @return Integer deleted : count of providers deleted
     */
    private function updateReports($global_id, $conso_ids, $type) {
        $deleted = 0;
        // Loop through all (non-consortium) providers connected to the global
        $inst_provs = Provider::with('reports')->where('global_id',$global_id)->where('inst_id','<>',1)->get();
        foreach ($inst_provs as $prov) {

            // Get IDs to add/remove
            $current_ids = $prov->reports->pluck('id')->toArray();
            $changed_ids = ($type=="attach") ? array_diff($conso_ids, $current_ids)
                                             : array_intersect($current_ids, $conso_ids);

            // Add/Remove the report connection(s)
            foreach ($changed_ids as $r) {
                if ($type == "attach") {
                    $prov->reports()->attach($r);
                } else {
                    $prov->reports()->detach($r);
                }
            }
            // If there are no remaining reports attached for this provider, delete it
            if ($type == "detach" && $prov->reports()->count() == 0) {
                $prov->delete();
                $deleted++;
            }
        }
        return $deleted;
    }

}
