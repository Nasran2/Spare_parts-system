<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware
        $middleware->append(\App\Http\Middleware\LockdownIfDbError::class);

        // Route middleware aliases
        $middleware->alias([
            'permission' => \App\Http\Middleware\RequirePermission::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'privacy_mode' => \App\Http\Middleware\CheckPrivacyMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withProviders([
        AppServiceProvider::class, // sets timezone + shares currency symbol
    ])
    ->create();
