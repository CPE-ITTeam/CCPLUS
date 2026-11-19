<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $input_roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $input_roles): Response
    {
        $user = \Auth::user();
        if (!$user) {
            abort(401, 'This action is unauthorized.');
        }
        if ($user->hasRole('ServerAdmin')) {
            return $next($request);
        }

        $roles = preg_split('/\s*[,|]\s*/', trim($input_roles), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(401, 'This action is unauthorized.');
    }
}
