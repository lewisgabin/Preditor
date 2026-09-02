<?php

use App\Domain\Draws\Enums\SyncErrorType;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\LotterySchedule;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts draw values and exposes persistence relationships', function (): void {
    $draw = Draw::factory()->create();
    LotterySchedule::factory()->for($draw->lottery)->create();
    $run = SyncRun::factory()->for($draw->lottery)->create();
    DrawCorrection::factory()->for($draw)->for($run)->create();
    $error = SyncError::factory()->for($run)->for($draw->lottery)->create();
    $quarantine = DrawQuarantine::factory()->for($run)->for($draw->lottery)->create();

    $draw->refresh();
    expect($draw->p1->value())->toBe('00')->and($draw->raw_payload)->toBeArray()
        ->and($draw->lottery->schedules)->toHaveCount(1)->and($draw->corrections)->toHaveCount(1)
        ->and($run->errors)->toHaveCount(1)->and($run->quarantines)->toHaveCount(1)
        ->and($error->type)->toBe(SyncErrorType::Validation)->and($quarantine->syncRun->is($run))->toBeTrue();
});
