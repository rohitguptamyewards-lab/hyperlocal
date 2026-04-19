<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Growth
 * Tables for referral links, partnership health, merchant tiers,
 * brand profiles, and sponsored placements.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Referral links per partnership
        Schema::create('partnership_referral_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('code', 20)->unique()->comment('Short shareable code');
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('conversion_count')->default(0);
            $table->timestamps();
            $table->index(['merchant_id', 'partnership_id']);
        });

        // Partnership health scores (computed daily)
        Schema::create('partnership_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->date('scored_at');
            $table->tinyInteger('score')->comment('0-100');
            $table->string('level', 10)->comment('red|yellow|green');
            $table->json('factors')->nullable()->comment('reciprocity, activity, cap_util, roi');
            $table->timestamps();
            $table->unique(['partnership_id', 'scored_at']);
        });

        // Merchant referral invites
        Schema::create('merchant_referral_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_merchant_id')->constrained('merchants');
            $table->string('invitee_phone', 20)->nullable();
            $table->string('invitee_email', 255)->nullable();
            $table->string('invite_token', 64)->unique();
            $table->tinyInteger('status')->default(1)->comment('1=pending 2=signed_up 3=live');
            $table->unsignedInteger('credits_awarded')->default(0);
            $table->timestamps();
        });

        // Merchant tier (free/premium)
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('tier', 20)->default('free')->after('bill_offers_display_mode');
            $table->unsignedInteger('max_partnerships')->default(2)->after('tier');
        });

        // Brand public profile
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique()->after('max_partnerships');
            $table->text('bio')->nullable()->after('slug');
            $table->string('logo_url', 500)->nullable()->after('bio');
            $table->boolean('profile_public')->default(false)->after('logo_url');
        });

        // Sponsored discovery placement
        Schema::create('sponsored_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->decimal('bid_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_placements');
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['tier', 'max_partnerships', 'slug', 'bio', 'logo_url', 'profile_public']);
        });
        Schema::dropIfExists('merchant_referral_invites');
        Schema::dropIfExists('partnership_health_scores');
        Schema::dropIfExists('partnership_referral_links');
    }
};
