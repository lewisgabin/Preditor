<?php

return [
    'enabled' => (bool) env('LOTTERY_API_ENABLED', false),
    'provider' => env('LOTTERY_API_PROVIDER', 'fake'),
    'base_url' => env('LOTTERY_API_BASE_URL', env('DRAW_PROVIDER_BASE_URL', '')),
    'endpoint_template' => env('LOTTERY_API_ENDPOINT_TEMPLATE', ''),
    'key' => env('LOTTERY_API_KEY', env('LOTTERY_API_TOKEN', env('DRAW_PROVIDER_TOKEN', ''))),
    'timeout_seconds' => (int) env('LOTTERY_API_TIMEOUT_SECONDS', 10),
    'connect_timeout_seconds' => (int) env('LOTTERY_API_CONNECT_TIMEOUT_SECONDS', 5),
    'retry_attempts' => (int) env('LOTTERY_API_RETRY_ATTEMPTS', 3),
    'retry_backoff_seconds' => (int) env('LOTTERY_API_RETRY_BACKOFF_SECONDS', 5),
    'lookback_days' => (int) env('LOTTERY_API_LOOKBACK_DAYS', 1),
    'reconciliation_days' => (int) env('LOTTERY_API_RECONCILIATION_DAYS', 3),
];
