<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_quarantines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('lottery_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('raw_payload');
            $table->string('error_code', 64);
            $table->json('validation_errors');
            $table->dateTime('resolved_at', 6)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_quarantines');
    }
};
