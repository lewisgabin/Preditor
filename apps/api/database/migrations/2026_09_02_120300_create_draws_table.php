<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lottery_id')->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('external_draw_id', 191)->nullable();
            $table->date('draw_date_local');
            $table->dateTime('scheduled_at_utc', 6)->nullable();
            $table->dateTime('drawn_at_utc', 6)->nullable();
            $table->char('p1', 2);
            $table->char('p2', 2);
            $table->char('p3', 2);
            $table->string('status', 32);
            $table->char('source_hash', 64);
            $table->json('raw_payload');
            $table->dateTime('received_at', 6);
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->dateTime('corrected_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['lottery_id', 'provider', 'external_draw_id'], 'draws_provider_external_unique');
            $table->unique(['lottery_id', 'scheduled_at_utc'], 'draws_lottery_scheduled_unique');
            $table->index(['lottery_id', 'draw_date_local']);
            $table->index(['status', 'draw_date_local']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE draws ADD CONSTRAINT draws_status_check CHECK (status IN ('pending', 'confirmed', 'corrected', 'invalid'))");
            DB::statement('ALTER TABLE draws ADD CONSTRAINT draws_identity_check CHECK (external_draw_id IS NOT NULL OR scheduled_at_utc IS NOT NULL)');
            DB::statement("ALTER TABLE draws ADD CONSTRAINT draws_numbers_check CHECK (p1 REGEXP '^[0-9]{2}$' AND p2 REGEXP '^[0-9]{2}$' AND p3 REGEXP '^[0-9]{2}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
