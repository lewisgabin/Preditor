<?php

use App\Application\Health\GetHealthStatus;

function healthPayload(string $status = 'ok'): array
{
    return [
        'status' => $status,
        'checks' => [
            'application' => ['status' => 'ok'],
            'mysql' => ['status' => $status === 'ok' ? 'ok' : 'degraded'],
            'redis' => ['status' => 'ok'],
            'scheduler' => ['status' => 'ok'],
        ],
        'version' => 'test',
        'git_sha' => 'abc123',
    ];
}

it('returns the public health contract when dependencies are healthy', function () {
    $service = Mockery::mock(GetHealthStatus::class);
    $service->shouldReceive('__invoke')->once()->andReturn(healthPayload());
    $this->app->instance(GetHealthStatus::class, $service);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(healthPayload());
});

it('returns service unavailable without leaking infrastructure details', function () {
    $service = Mockery::mock(GetHealthStatus::class);
    $service->shouldReceive('__invoke')->once()->andReturn(healthPayload('degraded'));
    $this->app->instance(GetHealthStatus::class, $service);

    $response = $this->getJson('/api/health')->assertServiceUnavailable();
    $serialized = $response->getContent();

    expect($serialized)
        ->not->toContain('DB_HOST')
        ->not->toContain('REDIS_HOST')
        ->not->toContain('password')
        ->not->toContain('trace')
        ->not->toContain('exception');
});
