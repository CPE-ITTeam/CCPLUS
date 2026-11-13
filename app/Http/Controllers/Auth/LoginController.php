<?php

namespace App\Http\Controllers\Auth;

use DB;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        // Set the consortium handle and set database connection before attempting to authenticate
        $key = ($request->consortium == '') ? 'con_template' : $request->consortium;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);
        DB::purge('consodb');
        DB::reconnect('consodb');

        // Check credentials
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            // Regenerate the session and store database key in it
            $request->session()->regenerate();
            $user = Auth::user();
            $request->session()->put(['ccp_key' => $key, 'conso_id' => $request->conso_id, 'user_id' => $user->id]);

            // Purge any existing login (web) tokens to keep them from piling up
            $token_name = 'Web_User_' . $user->id;
            $user->tokens()->where('name', $token_name)->delete();
            $user->last_login = now();
            $user->save();

            // Inject value to bypass serialization hook errors
            $userPayload = $user->toArray();
            $userPayload['user_FYmm'] = $user->getFY();

            // Setup return data including the access token, user info, roles, and admin institutions/groups
            $success['token'] = $user->createToken($token_name)->plainTextToken;
            $success['user'] = $userPayload;
            $success['roles'] = $user->allRoles();
            $success['adminInsts'] = $user->adminInsts();
            $success['adminGroups'] = $user->adminGroups();
            $success['consoKey'] = ($key == 'con_template') ? '' : $key;

            return $this->sendResponse($success, __('auth.authenticated'));
        }

        return response()->json(['success' => false, 'message' => __('auth.failed')], 200);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->tokens()->where('name', 'Web_User_' . $user->id)->delete();
            Auth::logout();
        }
        $request->session()->invalidate();
        $request->session()->put(['ccp_key' => '', 'conso_id' => null, 'user_id' => null]);

        return redirect('/login');
    }
}
