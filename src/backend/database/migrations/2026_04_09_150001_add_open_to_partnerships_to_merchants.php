<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration — adds open_to_partnerships flag to merchants.
 *
 * Purpose:  Allows merchants to signal they are discoverable in the Find Partners search.
 * Owner module: Discovery
 * Reversible: YES — column can be dropped safely, no FK deps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->boolean('open_to_partnerships')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('open_to_partnerships');
        });
    }
};
