<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $session_key = session('ccp_key');
        $key = ($session_key=='') ? "con_template" : $session_key;

        // If the session key-derived database setting is different than
        // the current assignment, purge and reconnect 
        $orig_cnx = config('database.connections.consodb.database');
        $sess_key = 'ccplus_' . $key;   
        if ($sess_key != $orig_cnx) {
            config(['database.connections.consodb.database' => $sess_key]);
            DB::purge('consodb');
            DB::reconnect('consodb');
        }

        return $next($request);
    }
}
