<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use Illuminate\Http\Request;
use \Illuminate\Contracts\Cache\Factory;

class GlobalSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:ServerAdmin']);
    }

    /**
     * Settings method for GlobalAdmin Controller
     * @param  String $type    // 'mail', 'config', or 'all'
     * @return JSON
     */
    public function index($type)
    {
        // Get global settings, minus the server admin credentials
        $skip_vars = array('server_admin','server_admin_pass','max_name_length');
        $all_settings = GlobalSetting::whereNotIn('name',$skip_vars)->get();
        $records = array();
        foreach ($all_settings as $setting) {
            if ($setting->type == $type || $type == 'all') {
                $records[$setting->name] = $setting->value;
            }
        }
        return response()->json(['records' => $records], 200);
    }

    /**
     * Store / Replace global setting variable values
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function store(Request $request, Factory $cache)
    {
        // Validate form inputs
        $this->validate($request, ['settings' => 'required']);
        $input = $request->all();

        // Get all current settings
        $settings = GlobalSetting::get();
        if (!$settings) {
            return response()->json(['result' => false, 'msg' => 'Error pulling current settings!!']);
        }

        // Save all input values that have a (by-name) matching setting in the table
        foreach ($input['settings'] as $key => $input) {
            $setting = $settings->where('name', $key)->first();
            if ($setting) {
                $setting->value = $input;
                $setting->save();
            }
        }

        // Clear the 'ccplus' section of the cached configuration
        $cache->forget('ccplus');
        return response()->json(['result' => true, 'msg' => 'Settings successfully updated!']);
    }

}
