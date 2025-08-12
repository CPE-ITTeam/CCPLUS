<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use App\Listeners\LogFailedAuthenticationAttempt;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Failed::class => [
           LogFailedAuthenticationAttempt::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Turn off events and listeners being automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
