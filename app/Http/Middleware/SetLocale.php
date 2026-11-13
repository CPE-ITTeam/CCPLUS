<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported application locales.
     */
    protected array $supportedLocales = ['en', 'fi'];

    /**
     * Set the application locale from the incoming X-Locale header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $explicit = $request->header('X-Locale');
        if ($explicit && in_array(strtolower($explicit), $this->supportedLocales, true)) {
            App::setLocale(strtolower($explicit));
        }

        return $next($request);
    }
}
