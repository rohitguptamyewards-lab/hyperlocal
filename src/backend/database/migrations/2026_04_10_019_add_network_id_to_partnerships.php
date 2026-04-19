<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only: add nullable network_id FK to partnerships.
 * Existing partnerships are unaffected (network_id = null = standalone bilateral).
 * Partnerships created via a network invite carry the network_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->unsignedBigInteger('network_id')->nullable()->after('id');

            $table->foreign('network_id')->references('id')->on('hyperlocal_networks')->nullOnDelete();
            $table->index('network_id', 'idx_partnerships_network');
        });
    }

    public function down(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropForeign(['network_id']);
            $table->dropIndex('idx_partnerships_network');
            $table->dropColumn('network_id');
        });
    }
};
