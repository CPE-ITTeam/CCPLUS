<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /* Accept one/more key-value pairs and set in the session
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function update(Request $request)
    {
        $input = $request->input('data');
        session($input);
        session()->save();
        return response()->json(['result' => true]);
    }
}
