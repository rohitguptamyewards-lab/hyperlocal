<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Member
 * Additive: adds member_id FK to partner_claims and partner_redemptions.
 * Existing customer_id columns are retained — no data loss.
 * New rows will populate member_id; old rows remain with member_id = NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_claims', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')
                ->nullable()
                ->after('customer_id')
                ->comment('FK to members table — NULL for pre-migration rows');

            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->index('member_id', 'idx_claims_member_id');
        });

        Schema::table('partner_redemptions', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')
                ->nullable()
                ->after('customer_id')
                ->comment('FK to members table — NULL for pre-migration rows');

            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->index('member_id', 'idx_redemptions_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('partner_claims', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropIndex('idx_claims_member_id');
            $table->dropColumn('member_id');
        });

        Schema::table('partner_redemptions', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropIndex('idx_redemptions_member_id');
            $table->dropColumn('member_id');
        });
    }
};
