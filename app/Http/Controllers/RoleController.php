<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Role;
use App\Models\User;
use App\Models\Institution;
use App\Models\Consortium;
use Illuminate\Http\Request;

class RoleController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return JSON
     */
    public function index(Request $request)
    {
        // Admins see all, managers see only their inst, everyone else gets an error
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        // Initialize some arrays/values
        $data = array();
        $limit_to_insts = [];
        $server_admin = config('ccplus.server_admin');

        // Limit by institution based on users's role(s)
        if (!$thisUser->isServerAdmin()) {
            $limit_to_insts = $thisUser->adminInsts();
            if ($limit_to_insts === [1]) $limit_to_insts = [];
        }

        // Setup filtering options for the datatable
        $filter_options = array('statuses' => array('ALL','Active','Inactive'));
        $filter_options['roles'] = Role::where('name','<>','ServerAdmin')->get(['id','name'])->toArray();

        // Pull user records - exclude serverAdmin
        $user_data = User::with('roles','institution:id,name')
                            ->when(count($limit_to_insts)>0, function ($qry) use ($limit_to_insts) {
                                return $qry->whereIn('inst_id', $limit_to_insts);
                            })
                            ->where('email', '<>', $server_admin)
                            ->orderBy('name', 'ASC')->get();

        // Make user role names one string, role IDs into an array, and status to a string for the view
        foreach ($user_data as $user) {

            // exclude users that thisUser cannot manage
            if (!$user->canManage()) continue;

            foreach ($user->allRoles() as $role) {

                // Setup array for this user data
                $rec = array('id' => $user->id, 'role_id' => $role['id'], 'name' => $user->name,
                             'email' => $user->email);
                $rec['role'] = ($role['inst_id']==1) ? 'Consortium '.$role['name'] : $role['name'];
                $rec['status'] = ($user->is_active) ? "Active" : "Inactive";
                $rec['inst_id'] = $role['inst_id'];
                $rec['inst_name'] = $role['inst'];
                $data[] = $rec;
            }
        }

        // Add filtering options for institutions
        $instIds = $user_data->pluck('inst_id')->toArray();
        $filter_options['institutions'] = Institution::whereIn('id',$instIds)->get(['id','name'])->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('roles.create');
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
          'id' => 'required|unique:consodb.roles,id',
          'name' => 'required|unique:consodb.roles,name',
        ]);
        $input = $request->all();
        $role = Role::create($input);

        return redirect()->route('roles.index')
                        ->with('success', 'Role created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $role = Role::findOrFail($id);
        return view('roles.edit', compact('role'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $role = Role::findOrFail($id);
        return view('roles.edit', compact('role'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
        ]);
        $role->name = $request->input('name');
        $role->save();

        return redirect()->route('roles.index')
                       ->with('success', 'Role updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return redirect()->route('roles.index')
                       ->with('success', 'Role deleted successfully');
    }
}
