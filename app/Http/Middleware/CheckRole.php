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
     * @param  String $input_roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $input_roles): Response
    {
        $user = \Auth::user();
        if ($user->hasRole("ServerAdmin")) {
            return $next($request);
        }
        $roles = preg_split($input_roles,', ');
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(401, 'This action is unauthorized.');
    }
}
