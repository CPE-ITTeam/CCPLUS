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
        $filter_options = array('type' => array(), 'institutions' => array());

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
            $rec['type'] = ($group->typeRestriction) ? $group->typeRestriction->name : "";
            $rec['institutions'] = $group->institutions->toArray();
            $memberIds = $group->institutions->pluck('id')->toArray();
            $rec['count'] = sizeof($memberIds);
            $rec['can_edit'] = true;
            $rec['can_delete'] = true;
            $data[] = $rec;
        }

        // send institution types for filter option
        $filter_options['type'] = InstitutionType::get(['id','name'])->toArray();
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
        $group->type = ($group->typeRestriction) ? $group->typeRestriction->name : "";
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
     * Export institution groups from the database.
     */
    public function export()
    {
        // Get all types
        $groups = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();

        // Setup styles array for headers
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $info_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E6');
        $info_sheet->getStyle('A1:E6')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:E6')->getAlignment()->setWrapText(true);
        $top_txt  = "The Institution Groups tab represents a starting place for updating or importing settings.\n";
        $top_txt .= "The table below describes the field datatypes and order that the import expects. Any Import\n";
        $top_txt .= "rows without an ID in column A will be ignored. If required values are missing/invalid within\n";
        $top_txt .= "a given row, the row will be ignored.\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'B' will be ignored.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A8:E8')->applyFromArray($head_style);
        $info_sheet->setCellValue('A8', 'Column Name');
        $info_sheet->setCellValue('B8', 'Data Type');
        $info_sheet->setCellValue('C8', 'Description');
        $info_sheet->setCellValue('D8', 'Required');
        $info_sheet->setCellValue('E8', 'Default');
        $info_sheet->setCellValue('A9', 'Id');
        $info_sheet->setCellValue('B9', 'Integer');
        $info_sheet->setCellValue('C9', 'Unique CC-Plus InstitutionGroup ID');
        $info_sheet->setCellValue('D9', 'Yes');
        $info_sheet->setCellValue('A10', 'Name');
        $info_sheet->setCellValue('B10', 'String');
        $info_sheet->setCellValue('C10', 'Institution Group Name');
        $info_sheet->setCellValue('D10', 'Yes');
        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 11; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the type data into a new sheet
        $group_sheet = $spreadsheet->createSheet();
        $group_sheet->setTitle('Institution Groups');
        $group_sheet->setCellValue('A1', 'Id');
        $group_sheet->setCellValue('B1', 'Name');
        $row = 2;
        foreach ($groups as $group) {
            $group_sheet->getRowDimension($row)->setRowHeight(15);
            $group_sheet->setCellValue('A' . $row, $group->id);
            $group_sheet->setCellValue('B' . $row, $group->name);
            $row++;
        }

        // Auto-size the columns
        $group_sheet->getColumnDimension('A')->setAutoSize(true);
        $group_sheet->getColumnDimension('B')->setAutoSize(true);

        // Give the file a meaningful filename
        $fileName = "CCplus_" . session('con_key', '') . "_InstitutionGroups.xlsx";

        // redirect output to client browser
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
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
        if ($type != 'New Additions' && $type != 'Full Replacement') {
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
                    // If we're adding and the name or id already exists, skip it
                    if ($request->input('type') == 'New Additions') {
                        $existing_id = $groups->where("id", "=", $_gid)->first();
                        $existing_name = $groups->where("name", "=", $row[1])->first();
                        if (!is_null($existing_id) || !is_null($existing_name)) {
                            $num_skipped++;
                            continue;
                        }
                    }

                    // Check/Setup the group name
                    $_name = trim($row[1]);
                    $current_group = $groups->where('id', $_gid)->first();
                    if ($current_group) {               // found existing ID
                        if (strlen($_name) < 1) {       // If import-name empty, use current value
                            $_name = trim($current_group->name);
                        } else {                        // trap changing a name to a name that already exists
                            $existing_group = $groups->where("name", "=", $_name)->first();
                            if ($existing_group) {
                                $_name = trim($current_group->name);     // override, use current - no change
                            }
                        }
                    } else {        // existing ID not found, try to find by name
                        $current_group = $groups->where("name", "=", $_name)->first();
                        if ($current_group) {
                            $_name = trim($current_group->name);
                        }
                    }

                    // Dont update/create anything if name is still empty
                    if (strlen($_name) < 1) {
                        $num_skipped++;
                        continue;
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
            // $original_ids = $groups->pluck('id')->toArray();
            // $ids_to_destroy = array_diff($original_ids, $group_ids_to_keep);
            $num_deleted = InstitutionGroup::whereNotIn('id', $group_ids_to_keep)->delete();
        }

        // Get the new full list of group names
        $new_groups = InstitutionGroup::with('institution:id,name')->orderBy('name', 'ASC')->get();

        // Rebuild the groups-membership strings for all institutions
        $institutions = Institution::with('institutionGroups')->orderBy('name', 'ASC')->get(['id','name']);
        $belongsTo = $this->groupsByInst($institutions);

        // return the current full list of groups with a success message
        $msg  = 'Institution Groups imported successfully : ';
        $msg .= ($num_deleted > 0) ? $num_deleted . " removed, " : "";
        $msg .= $num_updated . " updated and " . $num_created . " added";
        if ($num_skipped > 0) {
            $msg .= ($num_skipped > 0) ? " (" . $num_skipped . " existing names/ids skipped)" : ".";
        }
        return response()->json(['result' => true, 'msg' => $msg, 'groups' => $new_groups->toArray(), 'belongsTo' => $belongsTo]);
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
