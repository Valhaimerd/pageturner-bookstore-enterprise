<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerMiddleware;
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
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'customer' => CustomerMiddleware::class,
            'twofactor' => \App\Http\Middleware\EnsureTwoFactorPassed::class,
            'api.transform.request' => \App\Http\Middleware\TransformApiRequest::class,
            'api.transform.response' => \App\Http\Middleware\TransformApiResponse::class,
            'api.track' => \App\Http\Middleware\TrackApiUsage::class,
        ]);

        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\TrackApiUsage::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
