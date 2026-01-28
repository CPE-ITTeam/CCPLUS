<?php

namespace App\Http\Controllers;

use App\Models\GlobalProvider;
use App\Models\Connection;
use App\Models\Report;
use App\Models\Institution;
use App\Models\InstitutionGroup;
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

        // Requesting user needs to be consoAdmin or an admin of a group or multiple insts
        $groupIds = [];
        if (!$thisUser->isConsoAdmin()) {
            $groupIds = $thisUser->adminGroups();
            $instIds = $thisUser->adminInsts();
            if (count($groupIds) == 0 && count($instIds) <= 1) {
                return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
            }
        }

        // Get all active Global Providers
        $globals = GlobalProvider::with("connections","connections.reports")->where('is_active',1)->get();

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

            // Set boolean flags for available and conso and add sortval
            $enabledReports = $global->enabledReports();
            foreach ($enabledReports as $name => $rpt) {
                $rec[$name] = $rpt;
                // Requested is true if report is assigned to any group or institution (other than conso)
                $cnxCount = count($rpt['insts']) + count($rpt['groups']);
                $rec[$name]['requested'] = ($cnxCount > 0);
                // Set sortval ( 1=conso, 2=available, 3=requested, 4=not-available )
                if (!$rec[$name]['available']) {
                    $rec[$name]['sortval'] = 4;
                } else if ($rec[$name]['conso']) {
                    $rec[$name]['sortval'] = 1;
                } else {
                    $rec[$name]['sortval'] = ($rec[$name]['requested']) ? 3 : 2;
                }
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

        // Add institutions and groups, depending on role(s) to provider select options in the reportDialog
        if ($thisUser->isConsoAdmin()) {
            $options['institutions'] = Institution::where('is_active',1)->get(['id','name']);
            $options['groups'] = InstitutionGroup::get(['id','name']);
        } else {
            $inst_ids = $thisUser->adminInsts();
            $options['institutions'] = Institution::where('is_active',1)->whereIn('id',$inst_ids)->get(['id','name']);
            $options['groups'] = InstitutionGroup::whereIn('id',$groupIds)->get(['id','name']);
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
        $consoAdmin = $thisUser->isConsoAdmin();
        // abort_unless($thisUser->isConsoAdmin(), 403);

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
        $gp = GlobalProvider::with('connections','connections.reports')->where('id',$input['id'])->first();
        if (!$gp) {
            return response()->json(['result' => false, 'msg' => 'Global Platform reference error']);
        }

        // If consoAdmin requests conso-wide, update/create it first
        $newCnx = array('global_id' => $gp->id, 'is_active' => $gp->is_active, 'inst_id' => null, 'group_id' => null);
        $consoCnx = $gp->connections->where('inst_id',1)->first(); 
        $conso_ids = ($consoCnx) ? $consoCnx->reports->pluck('id')->toArray() : array();
        if ($consoAdmin && $flags['conso']) {
            if ( !$consoCnx ) {
                $newCnx['inst_id'] = 1;
                $consoCnx = Connection::create($newCnx);
            }
            if ( !in_array($report->id,$conso_ids) ) {
                $consoCnx->reports()->attach($report->id);
                $conso_ids = $cnx->consoCnx->reports->pluck('id')->toArray();
            }
            // Remove the report from all other connections with this report defined
            $res = $gp->updateReports($conso_ids, "detach");

            $flags['insts'] = array(1); // force these to cause existing connections to be reset
            $flags['groups'] = array();
        }
        // Create new (inst) connections as needed, based on role(s)
        $adminInsts = $thisUser->adminInsts();
        foreach ($flags['insts'] as $inst) {
            if ($consoAdmin || in_array($inst,$adminInsts)) {
                $cnx = $gp->connections->where('inst_id',$inst)->first();
                if (!$cnx) {
                    $newCnx['inst_id'] = $inst;
                    $cnx = Connection::create($newCnx);
                }
                if ( !in_array($report->id,$cnx->reports->pluck('id')->toArray()) ) {
                    $cnx->reports()->attach($report->id);
                }
            }
        }
        // Create new (group) connections as needed, based on role(s)
        $newCnx['inst_id'] = null;
        $adminGroups = $thisUser->adminGroups();
        foreach ($flags['groups'] as $group) {
            if (in_array($group,$adminGroups)) {
                $cnx = $gp->connections->where('group_id',$group)->first();
                if (!$cnx) {
                    $newCnx['group_id'] = $group;
                    $cnx = Connection::create($newCnx);
                }
                if ( !in_array($report->id,$cnx->reports->pluck('id')->toArray()) ) {
                    $cnx->reports()->attach($report->id);
                }
            }
        }
        $flags['requested'] = (!$flags['conso'] && (count($flags['insts']) + count($flags['groups'])) > 0);

        // Remove inst/group assignment from connection->reports, depending on role(s)
        // Insts or groups are treated as a request to remove access (user had to click them off)
        $current_insts = $gp->connectedInstitutions();
        $current_groups = $gp->connectedGroups();
        // Setup arrays of current inst and group IDs to remove the report from, depending on role(s)
        // (if $gp was made conso-wide, above, $flags['insts'] now == [1] )
        $del_insts = ($consoAdmin) ? array_diff($current_insts,$flags['insts'])
                                   : array_diff($adminInsts,$flags['insts']);
        $del_groups = ($consoAdmin) ? array_diff($current_groups,$flags['groups']) 
                                    : array_diff($adminGroups,$flags['groups']);
        // Loop through all the provider connections, update one-at-a-time
        foreach ($gp->connections as $cnx) {
            if (!$cnx->canManage()) continue;
            if ( !is_null($cnx->inst_id) ) {
                if (in_array($cnx->inst_id,$del_insts)) {
                    // If detaching Conso-wide, attach the report to all other connections first
                    if ($cnx->inst_id == 1)  $res = $gp->updateReports([$report->id], "attach");
                    $cnx->reports()->detach($report->id);
                }
            } else if ( !is_null($cnx->group_id) ) {
                if (in_array($cnx->group_id,$del_groups)) {
                    $cnx->reports()->detach($report->id);
                }
            }
            // Delete connection record if it now has no reports
            if ( $cnx->reports->count() == 0 ) {
                $cnx->delete();
            }
        }

        // Return success with the flags
        return response()->json(['result' => true, 'msg' => 'Access updated successfully', 'record' => $flags]);
    }

}
