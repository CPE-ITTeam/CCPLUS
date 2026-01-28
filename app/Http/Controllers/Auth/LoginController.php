<?php

namespace App\Http\Controllers\Auth;

use DB;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Consortium;
use App\Models\UserRole;

/*
|-----------------------------------------------------------------------------------
| Login Controller: 
|     Only two methods here to support GET routes for /login and /logout.
|
| Most of authentication is in Fortify and app/Http/Providers/FortifyServiceProvider
|-----------------------------------------------------------------------------------
*/


class LoginController extends BaseController
{
   /**
    * Login defined by routes/api.php
    *
    * @return \Illuminate\Http\Response
    */
    public function login(Request $request)
    {
        // Set the consortium handle and set databae connection before attempting to authenticate
        $key = ($request->consortium == '') ? "con_template" : $request->consortium;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);
        DB::purge('consodb');
        DB::reconnect('consodb');

        // Check credentials
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])) { 
            // Regenerate the session and store database key in it
            $request->session()->regenerate();
            $user = Auth::user();
            $request->session()->put(['ccp_key'=>$key, 'conso_id'=>$request->conso_id, 'user_id'=>$user->id]);
            $success['token'] =  $user->createToken('CCPlus')->plainTextToken; 
            $success['user'] = $user;
            $success['roles'] = $user->allRoles();
            $success['adminInsts'] =$user->adminInsts();
            $success['adminGroups'] =$user->adminGroups();
            $success['consoKey'] = ($key == "con_template") ? "" : $key;
            return $this->sendResponse($success, 'User successfully authenticated.');
        } else{
            return response()->json(['success' => false, 'message' => 'Login attempt failed!'], 200);
        }
    }

    public function logout(Request $request) {
        // Set the consortium handle from the session
        $key = ($request->consortium == '') ? "con_template" : $request->consortium;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);    
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(['ccp_key'=>'','conso_id'=>null,'user_id' =>null]);
        return redirect('/login');
    }
}
