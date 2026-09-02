<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotteries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('external_id')->unique();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('timezone', 64)->default('America/Santo_Domingo');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotteries');
    }
};
