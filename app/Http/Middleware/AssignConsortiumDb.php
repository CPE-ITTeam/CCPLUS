<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use App\Models\Consortium;
use App\Models\GlobalSetting;
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

        // 2. Fallback: pull the active switcher state from the session or auth user
        if (empty($key)) {
            $key = session('ccp_key') ?: (auth()->check() ? auth()->user()->ccp_key : '');
        }

        // 3. Resolve the targeted schema name, defaulting to template when no tenant context exists
        $sess_key = (empty($key) || $key === 'consodb' || $key === 'con_template')
            ? 'ccplus_con_template'
            : 'ccplus_' . $key;

        // 4. Protect ccplus_con_template from accidental tenant data writes when no consortium is selected
        if (
            $sess_key === 'ccplus_con_template' &&
            !$request->isMethod('GET') &&
            !$request->isMethod('HEAD') &&
            !$request->isMethod('OPTIONS') &&
            !$request->is(
                'api/login',
                'api/logout',
                'api/password/*',
                'api/consortia',
                'api/consortia/*',
                'api/server',
                'api/server/*',
                'api/platforms',
                'api/platforms/*',
                'api/global/*'
            )
        ) {
            return response()->json([
                'result' => false,
                'msg'    => 'Please select a consortium before modifying tenant records.',
            ], 422);
        }

        $orig_cnx = config('database.connections.consodb.database');

        // 5. If connection needs resetting, hot-swap the layout dynamically
        if ($sess_key !== $orig_cnx) {
            DB::disconnect('consodb');
            Config::set('database.connections.consodb.database', $sess_key);
            DB::purge('consodb');
            DB::reconnect('consodb');
            DB::setDefaultConnection('consodb');
        }

        // 6. Enforce cascading idle session timeout (User -> Consortium -> Global Default)
        $user = $request->user();
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token) {
                $conso = (!empty($key) && $key !== 'con_template')
                    ? Consortium::where('ccp_key', $key)->first()
                    : null;
                $globalTimeout = (int) (GlobalSetting::where('name', 'session_timeout')->value('value') ?: config('session.lifetime', 120));
                $effectiveTimeout = (int) ($user->session_timeout ?: ($conso?->session_timeout ?: $globalTimeout));

                $lastActivity = $token->last_used_at ?? $token->created_at;
                if ($effectiveTimeout > 0 && $lastActivity && $lastActivity->lt(now()->subMinutes($effectiveTimeout))) {
                    $token->delete();
                    return response()->json(['message' => 'Session expired.'], 401);
                }
            }
        }

        return $next($request);
    }
}
