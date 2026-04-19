<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Network (GAP 7 — Partner Rating / Trust System)
 * Adds an aggregate trust_score (average of all partner_ratings.rating) to merchants.
 * Recalculated on each new rating submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->decimal('trust_score', 3, 2)->nullable()->after('ecosystem_active')
                ->comment('0.00–5.00 — avg of partner_ratings');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('trust_score');
        });
    }
};
