<?php

namespace App\Providers;

use App\Extensions\ConsoDbSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Session::extend('ccplus-global', function ($app) {
            $config = $app['config']['session'];
            return new ConsoDbSessionHandler(
                $app['db']->connection($config['connection']),
                $config['table'],
                $config['lifetime'],
                $app['request'],
            );
        });
    }
}
