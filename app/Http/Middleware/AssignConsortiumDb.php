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
        // 1. Try to read from the X-Tenant header first
        $key = $request->header('X-Tenant');

        // 2. 💡 FALLBACK: If header is empty, pull the active Vue switcher state from the session or auth user
        if (empty($key)) {
            $key = session('ccp_key') ?: (auth()->check() ? auth()->user()->ccp_key : '');
        }

        // 3. Resolve the targeted schema name, defaulting safely to the template if no tenant context exists
        $sess_key = (empty($key) || $key === 'consodb') ? "ccplus_con_template" : 'ccplus_' . $key;   
        $orig_cnx = config('database.connections.consodb.database');

        // 4. If connection needs resetting, hot-swap the layout dynamically
        if ($sess_key != $orig_cnx) {
            // Disconnect old connection states securely
            DB::disconnect('consodb');

            // Overwrite database pointer array properties in-memory
            Config::set('database.connections.consodb.database', $sess_key);
            
            // Purge and reconnect to lock changes to disk
            DB::purge('consodb');
            DB::reconnect('consodb');
            DB::setDefaultConnection('consodb');
        }

        return $next($request);
    }
}
