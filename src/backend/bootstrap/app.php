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
    ->withCommands([
        \App\Modules\Analytics\Console\Commands\UpdateRetentionFlags::class,
        \App\Modules\Analytics\Console\Commands\BackfillPartnerAttributions::class,
        \App\Modules\Discovery\Console\Commands\ComputeRecommendations::class,
        \App\Modules\Enablement\Console\Commands\CheckDormancy::class,
        \App\Modules\Growth\Console\Commands\ComputeHealthScores::class,
        \App\Modules\Growth\Console\Commands\SendWeeklyDigest::class,
        \App\Modules\Growth\Console\Commands\AutoPauseResume::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable Sanctum stateful API authentication (replaces Kernel.php api group entry in L10)
        $middleware->statefulApi();

        // Named middleware aliases
        $middleware->alias([
            'super_admin'    => \App\Modules\SuperAdmin\Http\Middleware\SuperAdminAuth::class,
            'webhook.verify' => \App\Modules\Webhook\Http\Middleware\VerifyWebhookSignature::class,
            'customer_auth'  => \App\Modules\CustomerPortal\Http\Middleware\CustomerAuth::class,
            'verify_event'   => \App\Modules\EventTriggers\Http\Middleware\VerifyEventSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
