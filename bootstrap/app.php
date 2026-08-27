<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Wajib agar logoutOtherDevices mengusir sesi di perangkat lain
        $middleware->authenticateSessions();

        $middleware->alias([
            'subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
            'feature' => \App\Http\Middleware\EnsureFeature::class,
            'owner' => \App\Http\Middleware\EnsureStoreOwner::class,
            'developer' => \App\Http\Middleware\EnsureDeveloper::class,
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'api.sync' => \App\Http\Middleware\EnsureApiSyncFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('subscription:verify-payments')->everyMinute();
    })->create();
