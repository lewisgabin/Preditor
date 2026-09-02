<?php

use App\Domain\Draws\Enums\DrawStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires sanctum authentication for all draw read endpoints', function (string $url): void {
    $this->getJson($url)->assertUnauthorized();
})->with(['/api/v1/lotteries', '/api/v1/lotteries/1', '/api/v1/draws', '/api/v1/draws/1', '/api/v1/sync-runs', '/api/v1/sync-runs/1']);

it('lists and filters draws without exposing raw payloads', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $first = Lottery::factory()->create(['external_id' => 4]);
    $second = Lottery::factory()->create(['external_id' => 5]);
    $draw = Draw::factory()->for($first)->create(['draw_date_local' => '2026-09-02', 'status' => DrawStatus::Confirmed]);
    Draw::factory()->for($second)->create(['draw_date_local' => '2026-09-01', 'status' => DrawStatus::Pending]);

    $this->getJson('/api/v1/draws?external_id=4&from=2026-09-02&to=2026-09-02&status=confirmed&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draw->id)
        ->assertJsonMissingPath('data.0.raw_payload')->assertJsonPath('data.0.p1', '00')
        ->assertJsonStructure(['links', 'meta']);

    $this->getJson('/api/v1/draws/'.$draw->id)->assertOk()->assertJsonPath('data.raw_payload.fixture', true);
});

it('validates draw filters and returns paginated authenticated resources', function (): void {
    Sanctum::actingAs(User::factory()->create());
    Lottery::factory()->create(['external_id' => 4]);
    SyncRun::factory()->create();

    $this->getJson('/api/v1/draws?per_page=101')->assertUnprocessable();
    $this->getJson('/api/v1/draws?from=2026-09-03&to=2026-09-02')->assertUnprocessable();
    $this->getJson('/api/v1/lotteries')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    $this->getJson('/api/v1/sync-runs')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
});
