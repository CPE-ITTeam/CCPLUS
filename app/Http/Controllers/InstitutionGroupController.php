<?php

namespace App\Http\Controllers;

use App\Models\InstitutionGroup;
use App\Models\Institution;
use App\Models\Consortium;
use App\Models\InstitutionType;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InstitutionGroupController extends Controller
{
    /**
     * Return a JSON array of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $filter_options = array('types' => array(), 'institutions' => array());

        // Server/Conso Admins get conso-groups
        if ($thisUser->isConsoAdmin()) {
            $groups = InstitutionGroup::with('institutions:id,name,type_id','typeRestriction')
                                      ->whereNull('user_id')->orderBy('name', 'ASC')->get();
        } else {
            $groupIds = $thisUser->adminGroups();
            // Group Admins get the groups they create plus the ones they can admin
            if (count($groupIds) > 0) {
                $groups = InstitutionGroup::with('institutions:id,name,type_id','typeRestriction')
                                          ->where('user_id',$thisUser->id)->orWhereIn('id',$groupIds)
                                          ->orderBy('name', 'ASC')->get();
            // Return nothing
            } else {
                return response()->json(['records' => [], 'options' => $filter_options, 'result' => true], 200);
            }
        }
        // $all_institutions = Institution::where('is_active',1)->get(['id','name','type_id']);

        // Limit by institutions array based on thisUsers' ability to Admin them
        // (array is used for add operation)
        $limit_insts = array();
        if (!$thisUser->isConsoAdmin()) {
            $limit_insts = $thisUser->adminInsts();
            if ($limit_insts === [1]) $limit_insts = [];
        }

        // Get institution records
        $all_institutions = Institution::where('is_active',1)
                                ->when(count($limit_insts) > 0 , function ($qry) use ($limit_insts) {
                                    return $qry->whereIn('id', $limit_insts);
                                })->orderBy('name', 'ASC')->get(['id','name','type_id']);

        $data = array();
        foreach ($groups as $group) {
            $rec = array('id' => $group->id, 'name' => $group->name, 'type_id' => $group->type_id);
            $rec['type_string'] = ($group->typeRestriction) ? $group->typeRestriction->name : "";
            $rec['institutions'] = $group->institutions->toArray();
            $memberIds = $group->institutions->pluck('id')->toArray();
            $rec['count'] = sizeof($memberIds);
            $rec['can_edit'] = true;
            $rec['can_delete'] = true;
            $data[] = $rec;
        }

        // send institution types for filter option
        $filter_options['types'] = InstitutionType::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        $filter_options['institutions'] = $all_institutions->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();

        // Only consoAdmin and users who Admin a group can create a new group
        if (!$thisUser->isConsoAdmin()) {
            $groupIds = $thisUser->adminGroups();
            if (count($groupIds) == 0) {
                return response()->json(['result' => false, 'msg' => 'Not Authorized']);
            }
        }

        $this->validate($request, [
          'name' => 'required|unique:consodb.institutiongroups,name',
        ]);
        $input = $request->all();

        // Set type_id of type restriction if sent
        $type_id = (isset($input['type'])) ? $input['type'] : null;

        // Check to see if the group already exists
        $user_id = ($thisUser->isConsoAdmin()) ? null : $thisUser->id;
        $exists = InstitutionGroup::where('user_id',$user_id)->where('name',$input['name'])->first();
        if ($exists) {
            return response()->json(['result' => false, 'msg' => 'A Group with that name already exists']);
        }

        // Create the group
        $group = InstitutionGroup::create(['name' => $input['name'], 'type_id' => $type_id, 'user_id' => $user_id]);
        $group->load('typeRestriction');

        // Get all institutions' id and name; limit by input type if not-null
        $all_insts = Institution::orderBy('name', 'ASC')
                                ->when(!is_null($type_id), function ($q) use ($type_id) {
                                    return $q->where('type_id', $type_id);
                                })->get(['id','name','type_id']);

        // if institutions are passed in, go ahead and attach them now
        $new_members = array();
        if (isset($input['institutions'])) {
            foreach ($input['institutions'] as $inst) {
                // Confirm institution exists and check authorization
                $institution = $all_insts->where('id',$inst['id'])->first();
                if ($institution) {
                    if ($institution->canManage()) {
                        $group->institutions()->attach($inst['id']);
                        $new_members[] = $inst['id'];
                    }

                }
            }
            $group->load('institutions');

        // otherwise send back an empty array
        } else {
            $group->institutions = array();
        }
        $group->count = count($new_members);

        if ($group->count() > 0) {
            // Get details for new members
            $newMembers = $all_insts->whereIn('id',$new_members);
            $newMembers->load('institutionGroups');

            // Set the group_string for the institutions
            foreach ($group->institutions as $key => $inst) {
                $instData = $newMembers->where('id',$inst->id)->first();
                if ($instData) {
                    $_string = "";
                    foreach ($instData->institutionGroups as $grp) {
                        $_string .= ($_string == "") ? "" : ", ";
                        $_string .= $grp->name;
                    }
                    $group->institutions[$key]->group_string = $_string;
                }
            }
        }

        $data = array('id' => $group->id, 'name' => $group->name);
        $data['type'] = ($group->typeRestriction) ? $group->typeRestriction->name : "";
        $data['institutions'] = $group->institutions;
        $memberIds = ($group->count > 0) ? $group->institutions->pluck('id')->toArray() : array();
        $data['count'] = $group->count;
        $data['can_edit'] = true;
        $data['can_delete'] = true;

        return response()->json(['result' => true, 'msg' => 'Group created successfully', 'record' => $data]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  InstitutionGroup $group
     * @return JSON
     */
    public function update(Request $request, InstitutionGroup $group)
    {
        $thisUser = auth()->user();

        // Check Authorization
        if (!$thisUser->isConsoAdmin() && !$group->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Not Authorized']);
        }
        $this->validate($request, [ 'name' => 'required' ]);
        $input = $request->all();

        // Update group if name or type was changed
        $args = array();
        if ($group->name != $input['name']) $args['name'] = $input['name']; 
        if ($group->type_id != $input['type_id']) $args['type_id'] = $input['type_id']; 
        if (count($args)>0) {
            try {
                $group->update($args);
            } catch (\Exception $ex) {
                return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
            }
        }

        // Reset membership assignments
        if ($group->institutions()->count() > 0) {
            $group->institutions()->detach();
        }
        // Attach requested insts
        $type_skip = 0;
        if (count($request->institutions)>0) {
            foreach ($request->institutions as $inst) {
                if (!is_null($group->type_id) && $group->type_id != $inst['type_id']) {
                    $type_skip++;
                    continue;
                }
                $group->institutions()->attach($inst['id']);
            }
        }
        $member_ids = $group->institutions->pluck('id')->toArray();

        // Get all institutions' data
        $institutionData = Institution::orderBy('name', 'ASC')->get(['id','name','type_id']);
        $group->load('institutions:id,name,type_id','typeRestriction');
        $group->count = $group->institutions->count();
        $group->type_string = ($group->typeRestriction) ? $group->typeRestriction->name : "";
        $group->can_edit = true;
        $group->can_delete = true;

        $msg = ($type_skip<0) ? "Group updated, but ".$type_skip." institutions skipped or removed due to type restriction"
                              : "Group updated successfully";
        return response()->json(['result' => true, 'msg' => $msg, 'record' => $group]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  InstitutionGroup $group
     * @return JSON
     */
    public function destroy(InstitutionGroup $group)
    {
        $thisUser = auth()->user();

        // Check Authorization
        if (!$thisUser->isConsoAdmin() && !$group->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Not Authorized']);
        }

        // Delete the group
        try {
            $group->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }
        return response()->json(['result' => true, 'msg' => 'Group successfully deleted']);
    }

    /**
     * Import institution groups from a CSV to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Handle and validate inputs
        $this->validate($request, ['type' => 'required', 'csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }
        $type = $request->input('type');
        if ($type != 'Additions & Updates' && $type != 'Full Replacement') {
            return response()->json(['result' => false, 'msg' => 'Error - unrecognized import type.']);
        }

        // Turn the CSV data into an array
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);

        $rows = array_map("str_getcsv", explode("\n", $csvData));

        // If input file is empty, return w/ error string
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }
        $num_deleted = 0;
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;

        // Get all the groups
        $groups = InstitutionGroup::get();

        // Process the input rows
        $group_ids_to_keep = array();
        foreach ($rows as $row) {
            if (isset($row[0])) {
                // Ignore bad/missing ID
                if ($row[0] != "" && is_numeric($row[0])) {
                    $_gid = intval($row[0]);
                    // If we're adding/updating and the name already exists, skip it
                    if ($request->input('type') == 'Additions & Updates') {
                        $existing_name = $groups->where("name", $row[1])->first();
                        if (!is_null($existing_name)) {
                            $num_skipped++;
                            continue;
                        }
                    }

                    // Skip the row (nothing to update/save) if name is empty
                    $_name = trim($row[1]);
                    if (strlen($_name) < 1) {
                        $num_skipped++;
                        continue;
                    }

                    // Get/Setup args for the group
                    $current_group = $groups->where('id', $_gid)->first();
                    if ($current_group) {               // found existing ID
                        if (strlen($_name) < 1) {       // If import-name empty, use current value
                            $_name = trim($current_group->name);
                        } else {                        // trap changing a name to a name that already exists
                            $existing_group = $groups->where("name", $_name)->first();
                            if ($existing_group) {
                                $_name = trim($current_group->name);     // override, use current - no change
                            }
                        }
                    } else {        // existing ID not found, try to find by name
                        $current_group = $groups->where("name", $_name)->first();
                        if ($current_group) {
                            $_name = trim($current_group->name);
                        }
                    }

                    // Update or create the Group record
                    $_data = array('id' => $_gid, 'name' => $_name);
                    if (!$current_group) {      // Create
                        $current_group = InstitutionGroup::create($_data);
                        $groups->push($current_group);
                        $num_created++;
                    } else {                   // Update
                        $current_group->update($_data);
                        $num_updated++;
                    }
                    $group_ids_to_keep[] = $current_group->id;
                }
            }
        }

        // For Full replacement, clean out any groups not seen above
        if ($type == 'Full Replacement') {
            $num_deleted = InstitutionGroup::whereNotIn('id', $group_ids_to_keep)->delete();
        }

        // return the current full list of groups with a success message
        $msg  = 'Institution Groups imported successfully : ';
        $msg .= ($num_deleted > 0) ? $num_deleted . " removed, " : "";
        $msg .= $num_updated . " updated and " . $num_created . " added";
        if ($num_skipped > 0) {
            $msg .= ($num_skipped > 0) ? " (" . $num_skipped . " existing names/ids skipped)" : ".";
        }
        return response()->json(['result' => true, 'msg' => $msg]);
    }


    /**
     * Return an array of "belongs-to" strings for all instatitutions
     *
     * @param  Institution $institutions
     * @return Array $belongsTo
     */
    private function groupsByInst($institutions)
    {
        $belongsTo = [];
        foreach ($institutions as $inst) {
            $string = "";
            foreach ($inst->institutionGroups as $group) {
                $string .= ($string =="") ? "" : ", ";
                $string .= $group->name;
            }
            $belongsTo[] = array('id' => $inst->id, 'groups' => $string);
        }
        return $belongsTo;
    }

}
