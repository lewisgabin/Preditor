<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_runs', function (Blueprint $table): void {
            $table->dateTime('started_at', 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('sync_runs')->whereNull('started_at')->update(['started_at' => DB::raw('created_at')]);

        Schema::table('sync_runs', function (Blueprint $table): void {
            $table->dateTime('started_at', 6)->nullable(false)->change();
        });
    }
};
