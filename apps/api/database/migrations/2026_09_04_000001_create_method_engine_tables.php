<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->text('description');
            $table->string('category', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('method_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('method_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('target_lottery_id')->constrained('lotteries')->restrictOnDelete();
            $table->json('source_definition');
            $table->json('operator_definition');
            $table->text('explanation_template');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['method_id', 'version']);
        });
        Schema::create('signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('method_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_lottery_id')->constrained('lotteries')->restrictOnDelete();
            $table->date('target_draw_date_local');
            $table->char('recommended_number', 2);
            $table->string('status', 16);
            $table->dateTime('generated_at', 6);
            $table->dateTime('expires_at', 6)->nullable();
            $table->json('calculation_snapshot');
            $table->timestamps();
            $table->unique(['method_version_id', 'target_lottery_id', 'target_draw_date_local'], 'signals_identity_unique');
            $table->index('target_draw_date_local');
        });
        Schema::create('signal_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('signal_id')->constrained()->restrictOnDelete();
            $table->foreignId('draw_id')->constrained()->restrictOnDelete();
            $table->string('role', 32);
            $table->unsignedSmallInteger('source_order');
            $table->timestamp('created_at')->nullable();
            $table->unique(['signal_id', 'source_order']);
        });
        DB::statement("ALTER TABLE signals ADD CONSTRAINT signals_number_check CHECK (recommended_number REGEXP '^[0-9]{2}$')");
        DB::statement("ALTER TABLE signals ADD CONSTRAINT signals_status_check CHECK (status IN ('generated','expired','cancelled'))");
        DB::statement("ALTER TABLE methods ADD CONSTRAINT methods_category_check CHECK (category IN ('primary','alternative'))");
        DB::statement('ALTER TABLE method_versions ADD CONSTRAINT method_versions_range_check CHECK (version > 0 AND (valid_to IS NULL OR valid_to >= valid_from))');
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_sources');
        Schema::dropIfExists('signals');
        Schema::dropIfExists('method_versions');
        Schema::dropIfExists('methods');
    }
};
