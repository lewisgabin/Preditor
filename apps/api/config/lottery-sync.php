<?php

return [
    'automatic_enabled' => filter_var(env('LOTTERY_SYNC_AUTOMATIC_ENABLED', false), FILTER_VALIDATE_BOOL),
    'provider' => env('LOTTERY_SYNC_PROVIDER', env('LOTTERY_API_PROVIDER', 'fake')),
    'interval_minutes' => max(1, (int) env('LOTTERY_SYNC_INTERVAL_MINUTES', 10)),
    'stale_after_minutes' => max(1, (int) env('LOTTERY_SYNC_STALE_AFTER_MINUTES', 20)),
    'final_recheck_enabled' => filter_var(env('LOTTERY_SYNC_FINAL_RECHECK_ENABLED', true), FILTER_VALIDATE_BOOL),
    'final_recheck_time' => preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', (string) env('LOTTERY_SYNC_FINAL_RECHECK_TIME', '23:45')) === 1 ? env('LOTTERY_SYNC_FINAL_RECHECK_TIME', '23:45') : '23:45',
    'status_refresh_seconds' => max(10, (int) env('LOTTERY_SYNC_STATUS_REFRESH_SECONDS', 30)),
];
