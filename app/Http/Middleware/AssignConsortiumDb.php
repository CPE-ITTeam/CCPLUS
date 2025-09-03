<?php

namespace App\Http\Middleware;

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
        $session_key = $request->session()->get('ccp_con_key');
        $key = ($session_key=='') ? "con_template" : $session_key;
        config(['database.connections.consodb.database' => 'ccplus_' . $key]);
        return $next($request);
    }
}
