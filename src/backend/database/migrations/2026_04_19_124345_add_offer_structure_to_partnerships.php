<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add offer_structure to partnerships
        Schema::table('partnerships', function (Blueprint $table) {
            $table->string('offer_structure', 10)->default('same')->after('scope_type'); // 'same' | 'different'
        });

        // Add per-participant offer config to partnership_participants
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->string('offer_pos_type', 20)->nullable()->after('approval_status');   // flat | percentage
            $table->decimal('offer_flat_amount', 10, 2)->nullable()->after('offer_pos_type');
            $table->decimal('offer_percentage', 5, 2)->nullable()->after('offer_flat_amount');
            $table->decimal('offer_max_cap', 10, 2)->nullable()->after('offer_percentage');
            $table->decimal('offer_min_bill', 10, 2)->nullable()->after('offer_max_cap');
            $table->decimal('offer_monthly_cap', 10, 2)->nullable()->after('offer_min_bill');
            $table->unsignedBigInteger('linked_offer_id')->nullable()->after('offer_monthly_cap');
            $table->boolean('offer_filled')->default(false)->after('linked_offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->dropColumn([
                'offer_pos_type', 'offer_flat_amount', 'offer_percentage',
                'offer_max_cap', 'offer_min_bill', 'offer_monthly_cap',
                'linked_offer_id', 'offer_filled',
            ]);
        });

        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropColumn('offer_structure');
        });
    }
};
