<?php

namespace App\Http\Controllers;
use DB;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    // Accept a key-value pair and set in the session
    public function updateKey(Request $request)
    {
        $newKey = $request->input('key');
        $newValue = $request->input('value');

        session()->put($newKey, $newValue);
        return response()->json(['result' => true]);
    }
}
