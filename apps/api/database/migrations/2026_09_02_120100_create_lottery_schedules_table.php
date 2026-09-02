<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lottery_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lottery_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('draw_time_local');
            $table->time('sales_close_time_local')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['lottery_id', 'weekday', 'draw_time_local', 'effective_from'], 'lottery_schedules_identity_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE lottery_schedules ADD CONSTRAINT lottery_schedules_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE lottery_schedules ADD CONSTRAINT lottery_schedules_effective_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lottery_schedules');
    }
};
