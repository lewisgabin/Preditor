<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the draw domain tables and MySQL constraints', function (): void {
    foreach (['lotteries', 'lottery_schedules', 'draws', 'draw_corrections', 'sync_runs', 'sync_errors', 'draw_quarantines'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(DB::connection()->getDriverName())->toBe('mysql');
    $indexes = DB::select("SELECT DISTINCT index_name AS name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'draws'");
    expect(collect($indexes)->pluck('name'))->toContain('draws_provider_external_unique', 'draws_lottery_scheduled_unique');
});
