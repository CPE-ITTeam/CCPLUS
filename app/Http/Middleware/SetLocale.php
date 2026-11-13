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
     * Set the application locale from the incoming request headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Optional explicit header from frontend (e.g. X-Locale: fi)
        $explicit = $request->header('X-Locale');
        if ($explicit && in_array(strtolower($explicit), $this->supportedLocales, true)) {
            App::setLocale(strtolower($explicit));
            return $next($request);
        }

        // 2. Negotiate best match from Accept-Language (respects q-weights)
        if ($request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage($this->supportedLocales);
            if ($preferred) {
                App::setLocale($preferred);
            }
        }

        return $next($request);
    }
}
