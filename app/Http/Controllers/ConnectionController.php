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
        $consoAdmin = $thisUser->isConsoAdmin();

        // Requesting user needs to be consoAdmin or an admin of a group or multiple insts
        $groupIds = [];
        if (!$consoAdmin) {
            $groupIds = $thisUser->adminGroups();
            $instIds = $thisUser->adminInsts();
            if (count($groupIds) == 0 && count($instIds) <= 1) {
                return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
            }
        }

        // Get all active Global Providers
        $globals = GlobalProvider::with("connections","connections.reports")->where('is_active',1)
                                 ->orderBy('name', 'ASC')->get();

        // Keep track of the last error values for the filter options
        $nh_count = 0;
        $seen_codes = array(0); // pretend we've seen success
        // Map in report connections
        $connections = array();
        foreach ($globals as $global) {
            $rec = array('id' => $global->id, 'platform' => $global->name, 'can_edit' => false, 'can_delete' => false,
                         'reports' => $global->master_reports(), 'result' => null);
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

            // can_delete depends on there being at least ONE connection, and the ability to delete
            // ANY existing (inst or group) connections related to the global. The destroy() method
            // (below) only deletes connections allowed by users' role(s)
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

            // Set report flags for available and conso and add sortval
            $enabledReports = $global->enabledReports();
            foreach ($enabledReports as $name => $rpt) {
                // set export-variable values
                $prefix = strtolower($name);
                if ($rpt['conso'] || in_array(1,$rpt['insts'])) {
                    $rec[$prefix.'_insts'] = [1];
                    $rec[$prefix.'_groups'] = [];
                } else {
                    $rec[$prefix.'_insts'] = $rpt['insts'];
                    $rec[$prefix.'_groups'] = $rpt['groups'];
                }
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
        if ($consoAdmin) {
            $options['institutions'] = Institution::where('is_active',1)->get(['id','name']);
            $options['groups'] = InstitutionGroup::get(['id','name']);
        } else {
            $inst_ids = $thisUser->adminInsts();
            $options['institutions'] = Institution::where('is_active',1)->whereIn('id',$inst_ids)->get(['id','name']);
            $options['groups'] = InstitutionGroup::whereIn('id',$groupIds)->get(['id','name']);
        }
        $options['reports'] = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Return the data array
        return response()->json(['records' => $connections, 'options' => $options, 'result' => true], 200);
    }

    /**
     * Set/update report access/availability
     * (POST method handles store() and update() operations)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function access(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();

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
                $conso_ids = $consoCnx->reports->pluck('id')->toArray();
            }
            // Remove the report from all other connections with this report defined
            $res = $gp->updateReports($conso_ids, "detach");

            $flags['insts'] = array(1); // force these to cause existing connections to be reset
            $flags['groups'] = array();
        } else {
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
                if ($consoAdmin || in_array($group,$adminGroups)) {
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
            if ( $cnx->reports()->count() == 0 ) {
                $cnx->delete();
            }
        }
        // Return success with the flags
        return response()->json(['result' => true, 'msg' => 'Access updated successfully', 'record' => $flags]);
    }

    /**
     * Remove connections to a given platform
     *
     * @param  \App\Models\GlobalProvider $id
     * @return JSON
     */
    public function destroy($id)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        // Get platform and related connections
        $platform = GlobalProvider::with('connections')->findOrFail($id);

        $num_deleted = 0;
        foreach ($platform->connections as $cnx) {
            if ($cnx->canManage()) {
                $cnx->delete();
                $num_deleted++;
            }
        }

        // Return result
        if ($num_deleted == 0) {
            return response()->json(['result' => false, 'msg' => 'No Connections deleted - confirm your role(s)']);
        } else {
            $prefix = ($num_deleted == 1) ? "Connection " : $num_deleted . " connections ";
            return response()->json(['result' => true, 'msg' => $prefix . 'successfully deleted']);
        }
    }

    /**
     * Import connections from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins can import user data
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        // Set role-based limits
        $consoAdmin = $thisUser->isConsoAdmin();
        $adminInsts = $thisUser->adminInsts();
        $adminGroups = $thisUser->adminGroups();

        // Handle and validate inputs
        $this->validate($request, ['csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get all  global platforms and master_reports
        $all_platforms = GlobalProvider::get(['id','name','master_reports']);
        $masterReports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Setup start column (0-based) indeces for master reports
        $rpt_columns = array(3=>2, 2=>4, 1=>6, 4=>8);

        // Process the input rows
        $cnx_skipped = 0;
        $cnx_updated = 0;
        $cnx_created = 0;
        $cnx_deleted = 0;

        // Loop over all input rows - one platform at-a-time
        foreach ($rows as $row) {
            // empty/missing/invalid ID?  skip the row
            $_id = trim($row[0]);
            if ($row[0] == "" || !is_numeric($_id)) {
                $cnx_skipped++;
                continue;
            }

            // Check Platform ID
            $platId = intval($_id);
            $platform = $all_platforms->where("id", $platId)->first();
            if (!$platform) {
                $cnx_skipped++;
                continue;
            }

            // Get connections that the current user is permitted to change/update
            $connections = Connection::with('reports')->where('global_id',$platId)
                                     ->when( !$consoAdmin, function ($qry) use ($adminInsts, $adminGroups) {
                                        $qry->whereIn('inst_id', $adminInsts)
                                            ->orWhereIn('group_id', $adminGroups);
                                     })->get();

            // Loop across all 4 master reports and find/create connections and update connection->report(s)
            $keep_insts = array();
            $keep_groups = array();
            $_created = false;
            $_updated = false;
            foreach ( $rpt_columns as $master_id => $col ) {

                // Set array with the connections attached to this master report
                $rpt_connections = $connections->map( function ($conn) use ($master_id) {
                    if ( in_array($master_id,$conn->reports->pluck('id')->toArray()) ) {
                        return [ 'id' => $conn->id, 'inst_id' => $conn->inst_id, 'group_id' => $conn->group_id ];
                    }
                })->toArray();

                // Ignore columns for master reports the platform does not provide
                if (!in_array($master_id,$platform->master_reports())) {
                    continue;
                }
                $insts = preg_split('/,/', preg_replace('/, /', ',',$row[$col]));
                if (count($insts) > 0) {
                    // If insts includes 1, reset it to an array of just [1]
                    $conso = false;
                    if (in_array(1,$insts) && $thisUser->isConsoAdmin()) {
                        $insts = array(1);
                    }
                    $keep_insts = array_unique(array_merge($keep_insts, $insts));
                    // Loop over insts in the col, add if roles allow and not already defined
                    foreach ($insts as $_inst) {
                        if ($_inst == "") continue;
                        $inst_id = intval($_inst);
                        if ($consoAdmin || in_array($inst_id,$adminInsts)) {
                            $cnx = $connections->where('inst_id',$inst_id)->first();
                            // Create the connection record if it doesn't exist
                            if (!$cnx) {
                                $newCnx = array('global_id'=>$platId, 'is_active'=>1, 'inst_id'=>$inst_id);
                                $cnx = Connection::create($newCnx);
                                $cnx->reports()->attach($master_id);
                                $_created = true;
                                continue;
                            }
                            // Attach the report to the connection if not already set
                            if ( !in_array($master_id,$cnx->reports->pluck('id')->toArray()) ) {
                                $cnx->reports()->attach($master_id);
                                $_updated = true;
                            }
                            if ($inst_id==1) $conso = true;
                        }
                    }
                    // If we just made it conso-wide, skip groups
                    if ($conso) continue;
                }

                // Process groupID(s)
                $groups = preg_split('/,/', preg_replace('/, /', ',',$row[$col+1]));
                if (count($groups)==0) continue;
                $keep_groups = array_unique(array_merge($keep_groups, $groups));
                $newCnx['inst_id'] = null;  // might have been set above
                // Loop over groups in the col, add if roles allow and not already defined
                foreach ($groups as $_group) {
                    if ($_group == "") continue;
                    $group_id = intval($_group);
                    if ($consoAdmin || in_array($group_id,$adminGroups)) {
                        $cnx = $connections->where('group_id',$group_id)->first();
                        // Create the connection record if it doesn't exist
                        if (!$cnx) {
                            $newCnx = array('global_id'=>$platId, 'is_active'=>1, 'group_id'=>$group_id);
                            $cnx = Connection::create($newCnx);
                            $cnx->reports()->attach($master_id);
                            $_created = true;
                            continue;
                        }
                        // Attach the report to the connection if not already set
                        if ( !in_array($master_id,$cnx->reports->pluck('id')->toArray()) ) {
                            $cnx->reports()->attach($master_id);
                            $_updated = true;
                        }
                    }
                }
                // Cleanup/clear connection->report assignments no longer present
                foreach ($rpt_connections as $rcnx) {
                    $cnx = $connections->where('id',$rcnx['id'])->first();
                    if ($cnx && !in_array($cnx['inst_id'],$insts) && !in_array($cnx['group_id'],$groups)) {
                        $cnx->reports()->detach($master_id);
                    }
                }
            }   // Foreach $master_id => $col
            $cnx_created += ($_created) ? 1 : 0;
            $cnx_updated += ($_updated && !$_created) ? 1 : 0;

            // Remove connection records for the platform without report-assignments and those for
            // institutions or groups not seen/assigned (above) - they existed before, user has admin
            // rights on them and they were not present in the import data.
            foreach ($connections as $cnx) {
                if ($cnx->reports->count() == 0 ||
                    ( !is_null($cnx->inst_id) && !in_array($cnx->inst_id,$keep_insts)) ||
                    ( !is_null($cnx->group_id) && !in_array($cnx->group_id,$keep_groups)) ) {
                    $cnx->delete();
                    $cnx_deleted += 1;
                }
            }
        } // for all input rows

        // return the current full list of providers with a success message
        $detail = "";
        $detail .= ($cnx_updated > 0) ? $cnx_updated . " updated" : "";
        if ($cnx_created > 0) {
            $detail .= ($detail != "") ? ", " . $cnx_created . " added" : $cnx_created . " added";
        }
        if ($cnx_deleted > 0) {
            $detail .= ($detail != "") ? ", " . $cnx_deleted . " replaced/removed" : $cnx_deleted . " replaced/removed";
        }
        if ($cnx_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $cnx_skipped . " skipped" : $cnx_skipped . " skipped";
        }
        $msg  = 'Import successful, Connections : ' . $detail;
        return response()->json(['result' => true, 'msg' => $msg]);
    }

}
