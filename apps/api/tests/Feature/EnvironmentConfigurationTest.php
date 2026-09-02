<?php

it('uses the required integration services', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(config('cache.default'))->toBe('redis')
        ->and(config('queue.default'))->toBe('redis')
        ->and(config('session.driver'))->toBe('redis')
        ->and(config('app.timezone'))->toBe('America/Santo_Domingo');
});
