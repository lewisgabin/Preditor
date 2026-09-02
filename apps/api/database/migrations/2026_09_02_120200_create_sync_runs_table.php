<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('provider', 64);
            $table->string('trigger', 32);
            $table->foreignId('lottery_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('requested_from')->nullable();
            $table->date('requested_to')->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('items_received')->default(0);
            $table->unsignedInteger('items_inserted')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_unchanged')->default(0);
            $table->unsignedInteger('items_quarantined')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('finished_at', 6)->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sync_runs ADD CONSTRAINT sync_runs_trigger_check CHECK (`trigger` IN ('manual', 'scheduled', 'reconciliation', 'historical'))");
            DB::statement("ALTER TABLE sync_runs ADD CONSTRAINT sync_runs_status_check CHECK (status IN ('queued', 'running', 'succeeded', 'partial', 'failed'))");
            DB::statement('ALTER TABLE sync_runs ADD CONSTRAINT sync_runs_requested_dates_check CHECK (requested_from IS NULL OR requested_to IS NULL OR requested_to >= requested_from)');
            DB::statement('ALTER TABLE sync_runs ADD CONSTRAINT sync_runs_http_status_check CHECK (http_status IS NULL OR http_status BETWEEN 100 AND 599)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
