<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;

class AssignConsortiumDb
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Tenant');
        $sess_key = ($key == '') ? "con_template" : 'ccplus_' . $key;   
        $orig_cnx = config('database.connections.consodb.database');

        // If connection needs resetting 
        if ($sess_key != $orig_cnx) {
            Config::set('database.connections.consodb.database', $sess_key);
            DB::purge('consodb');
            DB::setDefaultConnection('consodb');
        }

        return $next($request);
    }
}
