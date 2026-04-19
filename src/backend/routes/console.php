<?php

use App\Modules\Analytics\Console\Commands\UpdateRetentionFlags;
use App\Modules\Discovery\Console\Commands\ComputeRecommendations;
use App\Modules\Enablement\Console\Commands\CheckDormancy;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Analytics: update 30/60/90-day retention flags daily at 2am ──────────────
Schedule::command(UpdateRetentionFlags::class)->dailyAt('02:00');

// ── Discovery: recompute partner fit scores nightly at 1am ───────────────────
Schedule::command(ComputeRecommendations::class)->dailyAt('01:00');

// ── Enablement: dormancy detection daily at 3am ───────────────────────────────
Schedule::command(CheckDormancy::class)->dailyAt('03:00');
