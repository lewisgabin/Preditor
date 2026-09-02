<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('lottery_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->text('message');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('retryable')->default(false);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->json('safe_context')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->dateTime('resolved_at', 6)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sync_errors ADD CONSTRAINT sync_errors_type_check CHECK (type IN ('network', 'authentication', 'rate_limit', 'validation', 'persistence', 'unknown'))");
            DB::statement('ALTER TABLE sync_errors ADD CONSTRAINT sync_errors_http_status_check CHECK (http_status IS NULL OR http_status BETWEEN 100 AND 599)');
            DB::statement('ALTER TABLE sync_errors ADD CONSTRAINT sync_errors_attempt_check CHECK (attempt >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_errors');
    }
};
