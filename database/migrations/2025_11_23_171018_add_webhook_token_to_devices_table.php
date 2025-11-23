<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'webhook_token')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->uuid('webhook_token')
                    ->nullable()
                    ->after('brightness');
                $table->unique('webhook_token');
            });
        }

        DB::table('devices')
            ->select('id')
            ->whereNull('webhook_token')
            ->orderBy('id')
            ->lazy()
            ->each(function ($device): void {
                DB::table('devices')
                    ->where('id', $device->id)
                    ->update(['webhook_token' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn('webhook_token');
        });
    }
};
