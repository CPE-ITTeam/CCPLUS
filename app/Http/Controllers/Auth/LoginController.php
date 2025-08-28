<?php

namespace App\Http\Controllers\Auth;

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
        // Set the consortium handle before attempting to authenticate
        $key = ($request->consortium == '') ? "con_template" : $request->consortium;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);    

        // Check credentials
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])) { 
            $user = Auth::user();
            $success['token'] =  $user->createToken('MyApp')->plainTextToken; 
            $success['user'] = $user;
            $success['roles'] = $user->allRoles();
            return $this->sendResponse($success, 'User successfully authenticated.');
        } else{ 
            return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
        }
    }

    public function logout(Request $request) {
        Auth::guard('web')->logout();
        session(['ccp_con_key' => '']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
