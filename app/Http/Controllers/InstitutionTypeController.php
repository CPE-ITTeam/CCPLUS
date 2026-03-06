<?php

namespace App\Http\Controllers;

use App\Models\InstitutionType;
use App\Models\Institution;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InstitutionTypeController extends Controller
{
      /**
       * Display a listing of the resource.
       *
       * @return \Illuminate\Http\Response
       */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $consoAdmin = $thisUser->isConsoAdmin();

        $data = InstitutionType::with(['institutions:id,name,type_id'])->orderBy('name', 'ASC')->get();
        $types = $data->map(function ($type) use ($consoAdmin) {
            return [
                'id' => $type->id, 'name' => $type->name, 'can_edit' => ($type->id >1 && $consoAdmin),
                'can_delete' => ( $type->id>1 && $consoAdmin && $type->institutions->count() == 0 ),
                'institutions' => $type->institutions->toArray()
            ];
        });
        return response()->json(['records' => $types], 200);
    }

      /**
       * Show the form for creating a new resource.
       *
       * @return \Illuminate\Http\Response
       */
    public function create()
    {
        // return view('institutiontypes.create');
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
        if (!$thisUser->isConsoAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Not Authorized']);
        }

        $test = InstitutionType::where('name', '=', $request->input('name'))->first();
        if ($test) {
            return response()->json(['result' => false, 'msg' => 'An existing type with that name already exists']);
        }
        $type = InstitutionType::create(['name' => $request->input('name')]);
        $data = array('id' => $type->id, 'name' => $type->name, 'can_edit' => true, 'can_delete' => true,
                      'institutions' => array() );

        return response()->json(['result' => true, 'msg' => 'New institution type successfully created',
                                 'record' => $data]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // $type = InstitutionType::findOrFail($id);
        // return view('institutiontypes.edit', compact('type'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // $type = InstitutionType::findOrFail($id);
        // return view('institutiontypes.edit', compact('type'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  InstitutionType $type
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, InstitutionType $type)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isConsoAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Not Authorized']);
        }
        $this->validate($request, ['name' => 'required']);

        // Don't save if the name already exists for another ID
        $test = InstitutionType::where('name', '=', $request->input('name'))
                               ->where('id','<>',$type->id)->first();
        if ($test) {
            return response()->json(['result' => false, 'msg' => 'Another type with that name already exists']);
        }

        $type->update([ 'name' => $request->input('name') ]);

        // Return what index() produces
        $type->load('institutions:id,name,type_id');
        $rec = array('id' => $type->id, 'name' => $type->name, 'can_edit' => ($type->id>1),
                     'can_delete' => ( $type->id>1 && $type->institutions->count() == 0 ),
                     'institutions' => $type->institutions->toArray());

        return response()->json(['result' => true, 'msg' => 'Institution type successfully updated',
                                 'record' => $rec]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  InstitutionType $type
     * @return \Illuminate\Http\Response
     */
    public function destroy(InstitutionType $type)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isConsoAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Not Authorized']);
        }

        // Update all institutions that have this type and then delete it
        Institution::where('type_id', $type->id)->update(['type_id' => 1]);
        $type->delete();
        return response()->json(['result' => true, 'msg' => 'Institution type successfully deleted']);
    }

    /**
     * Import institution types from a CSV to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Handle and validate inputs
        $this->validate($request, ['type' => 'required', 'csvfile' => 'required']);
        $type = $request->input('type');
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
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

        // If user requested full replacement, we want to delete the existing types (except id:1)
        // BUT - since Instiution Type is a foreign key for Institution... we can't just trash it.
        if ($request->input('type') == 'Full Replacement') {
            // Get all institutions, and save the current ID => type as a separate array
            $institutions = Institution::get();
            $original_types = array();
            foreach ($institutions as $inst) {
                $original_types[$inst->id] = $inst->type_id;
                $inst->type_id = 1;
                $inst->save();
            }

            // Okay, toss the types
            $num_deleted = InstitutionType::count() - 1;
            InstitutionType::where('id', '<>', 1)->delete();
        } elseif ($request->input('type') != 'Additions & Updates') {
            return response()->json(['result' => false, 'msg' => 'Error - unrecognized import type.']);
        }
        $current_types = InstitutionType::get();

        // Process the input rows
        foreach ($rows as $row) {
            if (isset($row[0])) {
                // Ignore bad/missing ID
                if ($row[0] != "" && is_numeric($row[0]) && $row[0] > 1) {
                    $_tid = intval($row[0]);
                    // If we're adding and the name already exists, skip it
                    if ($request->input('type') == 'Additions & Updates') {
                        $existing_name = $current_types->where("name", "=", $row[1])->first();
                        if (!is_null($existing_name)) {
                            $num_skipped++;
                            continue;
                        }
                    }

                    // Check for an existing ID
                    $existing_type = $current_types->where("id", "=", $_tid)->first();
                    if (!is_null($existing_type)) {
                        if (!is_null($row[1])) {
                            $existing_type->name = $row[1];
                            $existing_type->save();
                            $num_updated++;
                        }
                    } else {
                        // Save the new name
                        if (!is_null($row[1])) {
                            $_name = trim($row[1]);
                            if (strlen($_name) > 0) {
                                $new_type = InstitutionType::create(array('id' => $_tid, 'name' => $_name));
                                $num_created++;
                            }
                        }
                    }
                }
            }
        }

        // Get the new full list of types
        $types = InstitutionType::orderBy('id', 'ASC')->get();
        $new_ids = $types->pluck('id')->values()->toArray();

        // If we're replacing, reset type for institutions if the type still exists,
        // otherwise leave it as 1 (not classified)
        if ($request->input('type') == 'Full Replacement') {
            foreach ($original_types as $id => $type) {
                $inst = $institutions->where('id', $id)->first();
                if (in_array($type, $new_ids)) {
                    $inst->type_id = $type;
                    $inst->save();
                }
            }
        }

        // return the current full list of types with a success message
        $msg  = 'Institution Types imported successfully : ';
        $msg .= ($num_deleted > 0) ? $num_deleted . " removed, " : "";
        $msg .= $num_updated . " updated and " . $num_created . " added";
        if ($num_skipped > 0) {
            $msg .= ($num_skipped > 0) ? " (" . $num_skipped . " existing names skipped)" : ".";
        }
        return response()->json(['result' => true, 'msg' => $msg]);
    }
}
