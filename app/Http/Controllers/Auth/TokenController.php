<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;

class TokenController extends BaseController
{
    protected $connection = 'globaldb';

    // ConsoAdmin only: pull and return all existing PAT tokens
    public function index(Request $request) {

        $thisUser = $request->user();
        if (!$thisUser->isConsoAdmin()) {
           return response()->json(['result' => false, 'msg' => 'Error - Not authorized.']);
        }

        $_pattern = "PAT_User_";
        $tokens = array();

        // Request is for a single user's token(s)
        $requested_user_id = ($request->user_id) ? ($request->user_id) : null;
        if ($requested_user_id) {
            $user = User::with('tokens')->findOrFail($requested_user_id);
            $token = $user->tokens()->where('name',$_pattern.$requested_user_id)->first();
            if ($token) {
                $tokens[] = array('email' => $user->email, 'token_ID' => $token->id,
                                  'token_expires' => $token->accessToken->expires_at);
            }

        // Get all personal access tokens for the consortium
        } else {

            // Fetch all tokens and their associated users (tokenable)
            $users = User::with('tokens')->whereHas('tokens')->get();
            foreach ($users as $user) {
                $token = $user->tokens()->where('name','like',$_pattern."%")->first();
                if ($token) {
                    $tokens[] = array('email' => $user->email, 'token_ID' => $token->id,
                                    'token_expires' => $token->accessToken->expires_at);
                }
            }
        }

        // Return the tokens
        return response()->json(['result' => true, 'tokens' => $tokens]);
    }

     /**
     * Allow users to Create a new personal access token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON response with token details or error message
     */
    public function store(Request $request) {

        // Get the User record we're creating the client for.
        $thisUser = $request->user();
        $user_id = $request->user()->id;
        $user = User::findOrFail($user_id);

        // Look for an existing token
        $_name = "PAT_User_".$user_id;
        $token = $user->tokens()->where('name',$_name)->first();
        $token_string = '';
        $expires_at = '';

        // Token exists, get the string from the user's record
        if ($token) {
            $token_string = Crypt::decryptString($user->enc_api_token);
            $expires_at = $token->accessToken->expires_at;

        // If token does not exist, create one
        } else {
            try {
                // Create a new 1-year token
                $token = $user->createToken(name: $_name, abilities: ['*'], expiresAt: now()->addYear());                
                $token_string = $token->plainTextToken;
                $expires_at = $token->accessToken->expires_at;
                // Encrypt and save the plain-text string
                $user->enc_api_token = Crypt::encryptString($token_string);
                $user->save();
            } catch (\Exception $e) {
                return response()->json(['result'=>false, 'msg'=>'Create access token failed: '.$e->getMessage()]);
            }
        }

        // Return Token info
        return response()->json(['result' => true, 'token_type' => 'Bearer', 'access_token' => $token_string,
                                 'token_expires' => $expires_at], 200);
    }

    public function show(Request $request, User $user) {
        $thisUser = $request->user();

        // ConsoAdmins can show other users
        if ($thisUser->isConsoAdmin()) {
            $tokens = $user->tokens()->where("name","PAT_User_".$user->id)->get();
        } else {
            $tokens = $thisUser->tokens()->where("name","PAT_User_".$thisUser->id)->get();
        }

        return response()->json(['result' => true, 'tokens' => $tokens]);
    }

    // Destroy all PAT_User tokens for the requesting user
    public function destroy(Request $request)
    {
        $thisUser = $request->user();

        // Delete personal access token
        $thisUser->tokens()->where("name","PAT_User_".$thisUser->id)->delete();

        // Clear encrypted string from the user's record
        $thisUser->enc_api_token = null;
        $thisUser->save();

        return response()->json(['result' => true]);
    }
}
