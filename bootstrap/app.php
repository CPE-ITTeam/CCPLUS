<?php

use App\Http\Middleware\AssignConsortiumDb;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->priority([
            StartSession::class,
            AssignConsortiumDb::class,
            'auth:sanctum',
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->statefulApi();

        // Group Session and ConsortiumDb middleware with Sanctum for protected routes
        $middleware->group('ccplusAuth', [
            AssignConsortiumDb::class,
            'auth:sanctum',
        ]);

        $middleware->prependToGroup('api', [
            SetLocale::class,
            AssignConsortiumDb::class,
            StartSession::class,
        ]);
        $middleware->appendToGroup('web', [
            SetLocale::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // FIX: Exempt both endpoints from CSRF matching to stop the 419 blocks
        $middleware->validateCsrfTokens(except: [
            '/logout',
            '/api/login'
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'assignDb' => AssignConsortiumDb::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
