<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DB;
use Mail;
use Hash;
use App\Models\User;

// CC+ needs to include the consortium as a part of validating and handling users,
// so we're not using the handy-dandy Laravel trait for doing this
class ForgotPasswordController extends Controller
{
    /**
     * Accept (POST) input from the forgot password form
     *
     * @return response()->json()
     */
    public function submitForgotForm(Request $request)
    {
        $request->validate([ 'email' => 'required', 'consortium' => 'required' ]);

        // Set the consortium handle before querying for email matches
        $key = ($request->consortium == '') ? "con_template" : $request->consortium;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);    

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => "User address not found or invalid"], 200);
        }
        $resets_table = config('database.connections.consodb.database') . ".password_resets";
        $token = Str::random(64);
        DB::table($resets_table)
          ->insert([ 'email' => $user->email, 'token' => $token, 'created_at' => Carbon::now() ]);

        $reset_link = url('/') . '/resetPassForm?key=' . $key . '&token=' . $token;
        Mail::to($request->email)->send(new \App\Mail\ResetPassword($reset_link));
        return response()->json(['message' => "Password reset link has been sent to your email address",
                                 'success' => true,], 200);
    }

    /**
     * Accept (POST) input from the reset password form
     *
     * @return response()->json()
     */
    public function submitResetForm(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required',
            'consortium' => 'required',
            'token' => 'required'
        ]);

        // Set the consortium handle before querying for email matches
        $key = ($request->consortium == '') ? "con_template" : $request->consortium;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);    
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => "User not found or invalid"], 200);
        }

        $resets_table = config('database.connections.consodb.database') . ".password_resets";
        $updatePassword = DB::table($resets_table)->where(['email' => $request->email, 'token' => $request->token])
                            ->first();
        if (!$updatePassword) {
            return response()->json(['success'=>false, 'message'=>"Password reset failed - Invalid token"], 200);
        }
        $user = User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        DB::table($resets_table)->where(['email'=> $request->email])->delete();
        return response()->json(['success'=>true, 'message'=>"Your password has been successfully updated!"], 200);
    }
}
