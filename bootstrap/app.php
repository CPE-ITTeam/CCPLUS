<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Http\Request;
use \App\Http\Middleware\AssignConsortiumDb;
use \App\Http\Middleware\CheckRole;

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
            StartSession::class,
        ]);
        $middleware->appendToGroup('web', [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/logout'
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
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
