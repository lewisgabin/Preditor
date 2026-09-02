<?php

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Database\Seeders\LotterySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the exact initial lottery catalog idempotently', function (): void {
    $this->seed(LotterySeeder::class);
    $this->seed(LotterySeeder::class);
    expect(Lottery::query()->count())->toBe(10)
        ->and(Lottery::query()->where('external_id', 4)->value('name'))->toBe('Lotería Nacional')
        ->and(Lottery::query()->where('external_id', 29)->value('name'))->toBe('La Suerte 6 PM');
});
