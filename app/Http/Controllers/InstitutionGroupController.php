<?php

namespace App\Http\Controllers;

use App\InstitutionGroup;
use App\Institution;
use App\Consortium;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

class InstitutionGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $groups = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();

        $data = array();
        foreach ($groups as $group) {
            $members = $group->institutions->pluck('id')->toArray();
            $group->not_members = Institution::whereNotIn('id',$members)->get(['id','name'])->toArray();
            $group->count = sizeof($members);
            $data[] = $group->toArray();
        }
        $cur_instance = Consortium::where('ccp_key', session('ccp_con_key'))->first();
        $conso_name = ($cur_instance) ? $cur_instance->name : "Template";
        return view('institutiongroups.index', compact('conso_name', 'data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Built-in to index Vue component
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
          'name' => 'required|unique:consodb.institutiongroups,name',
        ]);

        // Create the group
        $group = InstitutionGroup::create(['name' => $request->input('name')]);

        // if institutions are passed in, go ahead and attach them now
        $new_members = array();
        if (isset($request->institutions)) {
            foreach ($request->institutions as $inst) {
                $group->institutions()->attach($inst['id']);
                $new_members[] = $inst['id'];
            }
            $group->load('institutions');

        // otherwise send back an empty array
        } else {
            $group->institutions = array();
        }

        // Get alll institutions' data and pull out not-members
        $institutionData = Institution::orderBy('name', 'ASC')->get(['id','name']);
        $group->not_members = $institutionData->except($new_members);
        $group->count = sizeof($new_members);

        // Rebuild the groups-membership strings for all institutions
        $institutionData->load('institutionGroups');
        $belongsTo = $this->groupsByInst($institutionData);

        // Update the group membership string for affected institutions
        foreach ($group->institutions as $key => $inst) {
            $instData = $institutionData->where('id',$inst->id)->first();
            if ($instData) {
                $_string = "";
                foreach ($instData->institutionGroups as $grp) {
                    $_string .= ($_string == "") ? "" : ", ";
                    $_string .= $grp->name;
                }
                $group->institutions[$key]->group_string = $_string;
            }
        }

        return response()->json(['result' => true, 'msg' => 'Group created successfully', 'group' => $group,
                                 'belongsTo' => $belongsTo]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
      //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $group = InstitutionGroup::with('institutions:id,name')->findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
        ]);

        // Update group name
        $group->name = $request->input('name');
        $group->save();

        // Reset membership assignments
        if ($group->institutions()->count() > 0) {
            $group->institutions()->detach();
        }
        foreach ($request->institutions as $inst) {
            $group->institutions()->attach($inst['id']);
        }
        $member_ids = $group->institutions->pluck('id')->toArray();

        // Get all institutions' data and save not-members
        $institutionData = Institution::orderBy('name', 'ASC')->get(['id','name']);
        $group->not_members = $institutionData->except($member_ids);
        $group->load('institutions:id,name');
        $group->count = $group->institutions->count();
        // Rebuild the groups-membership strings for all institutions
        $institutionData->load('institutionGroups');
        $belongsTo = $this->groupsByInst($institutionData);

        return response()->json(['result' => true, 'msg' => 'Group updated successfully', 'group' => $group,
                                 'belongsTo' => $belongsTo]);
    }

    /**
     * Add institutiomns to an existing group
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function extend(Request $request)
    {
        $this->validate($request, [
          'id' => 'required', 'institutions' => 'required'
        ]);

        // Build returned group data the way index() does
        $group = InstitutionGroup::with('institutions:id,name')->findOrFail($request->input('id'));
        $member_institutions = $group->institutions->pluck('id')->toArray();

        // Add membership assignments
        $count = 0;
        foreach ($request->institutions as $inst) {
            if (in_array($inst['id'], $member_institutions)) continue;
            $group->institutions()->attach($inst['id']);
            $count++;
        }
        $group->load('institutions:id,name');
        $group->count = count($member_institutions) + $count;
        $member_institutions = $group->institutions->pluck('id')->toArray();

        // Get all institutions' data and pull out not-members
        $institutionData = Institution::orderBy('name', 'ASC')->get(['id','name']);
        $group->not_members = $institutionData->whereNotIn('id',$member_institutions);

        // Rebuild the groups-membership strings for all institutions
        $institutionData->load('institutionGroups');
        $belongsTo = $this->groupsByInst($institutionData);

        // Update the group membership string for affected institutions
        foreach ($group->institutions as $key => $inst) {
            $instData = $institutionData->where('id',$inst->id)->first();
            if ($instData) {
                $_string = "";
                foreach ($instData->institutionGroups as $grp) {
                    $_string .= ($_string == "") ? "" : ", ";
                    $_string .= $grp->name;
                }
                $group->institutions[$key]->group_string = $_string;
            }
        }

        // Build returned group data the way index() does
        return response()->json(['result' => true, 'msg' => 'Group updated successfully', 'count' => $count, 'group' => $group,
                                 'belongsTo' => $belongsTo]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $group = InstitutionGroup::findOrFail($id);
        $group->delete();

        // Rebuild the groups-membership strings for all institutions
        $institutions = Institution::with('institutionGroups')->orderBy('name', 'ASC')->get(['id','name']);
        $belongsTo = $this->groupsByInst($institutions);

        return response()->json(['result' => true, 'msg' => 'Group successfully deleted', 'belongsTo' => $belongsTo]);
    }

    /**
     * Export institution groups from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     */
    public function export($type)
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
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_InstitutionGroups." . $type;

        // redirect output to client browser
        if ($type == 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // } elseif ($type == 'xls') {
        //     $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        //     header('Content-Type: application/vnd.ms-excel');
        }
        header('Content-Disposition: attachment;filename=' . $fileName);
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
