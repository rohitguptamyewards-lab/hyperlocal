<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Merchant Settings
 * Versioned history of each merchant's declared point valuation.
 *
 * Every time a merchant changes how much their loyalty point is worth in
 * rupees, a new row is inserted. The CURRENT valuation is the row with
 * the highest effective_from that is <= NOW().
 *
 * existing partnership_terms rows are NOT affected by revaluations —
 * they lock rupees_per_point_at_agreement at the time of agreement.
 *
 * rupees_per_point: how many rupees ONE loyalty point is worth.
 *   e.g.  1.0000  → 1 pt = ₹1
 *         0.0100  → 100 pts = ₹1
 *         100.00  → 1 pt = ₹100
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_point_valuations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->decimal('rupees_per_point', 12, 4)->comment('₹ value of 1 loyalty point');
            $table->timestamp('effective_from')->useCurrent();
            $table->unsignedBigInteger('confirmed_by')->comment('user_id who confirmed the change');
            $table->string('note', 255)->nullable()->comment('Reason for change — required on update');
            $table->timestamps();

            $table->index(['merchant_id', 'effective_from'], 'idx_merchant_valuation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_point_valuations');
    }
};
