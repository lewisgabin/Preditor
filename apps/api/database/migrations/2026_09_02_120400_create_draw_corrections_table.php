<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draw_id')->constrained()->restrictOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('before_payload');
            $table->json('after_payload');
            $table->char('before_hash', 64);
            $table->char('after_hash', 64);
            $table->dateTime('detected_at', 6);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_corrections');
    }
};
