<?php

use App\Application\Health\GetHealthStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static function (): void {
    Cache::store('redis')->put(GetHealthStatus::SCHEDULER_HEARTBEAT_KEY, now()->utc()->toIso8601String(), now()->addMinutes(5));
})->name('health:scheduler-heartbeat')->everyMinute()->withoutOverlapping();
