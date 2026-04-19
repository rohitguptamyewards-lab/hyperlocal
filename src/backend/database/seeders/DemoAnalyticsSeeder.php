<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Seeds realistic demo data for analytics dashboard screenshots.
 * Creates 6 months of redemptions across 3 partnerships.
 */
class DemoAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding demo analytics data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── Create 2 more partnerships (Brew & Co with Bella Salon, Brew & Co with BookNook) ──
        $partnerships = [
            ['id' => 1, 'name' => 'Brew & Co × FitZone', 'existing' => true],
        ];

        // Partnership 2: Brew & Co × Bella Salon
        $p2Uuid = Str::uuid()->toString();
        DB::table('partnerships')->insert([
            'id' => 2, 'uuid' => $p2Uuid, 'merchant_id' => 1,
            'name' => 'Brew & Co × Bella Salon', 'scope_type' => 2, 'status' => 5,
            'start_at' => now()->subMonths(4), 'created_by' => 1, 'updated_by' => 1, 'created_at' => now()->subMonths(4), 'updated_at' => now(),
        ]);
        DB::table('partnership_participants')->insert([
            ['partnership_id' => 2, 'merchant_id' => 1, 'outlet_id' => 1, 'role' => 1, 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['partnership_id' => 2, 'merchant_id' => 3, 'outlet_id' => 3, 'role' => 2, 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $partnerships[] = ['id' => 2, 'name' => 'Brew & Co × Bella Salon', 'existing' => false];

        // Partnership 3: Brew & Co × BookNook
        $p3Uuid = Str::uuid()->toString();
        DB::table('partnerships')->insert([
            'id' => 3, 'uuid' => $p3Uuid, 'merchant_id' => 1,
            'name' => 'Brew & Co × BookNook', 'scope_type' => 2, 'status' => 5,
            'start_at' => now()->subMonths(2), 'created_by' => 1, 'updated_by' => 1, 'created_at' => now()->subMonths(2), 'updated_at' => now(),
        ]);
        DB::table('partnership_participants')->insert([
            ['partnership_id' => 3, 'merchant_id' => 1, 'outlet_id' => 1, 'role' => 1, 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['partnership_id' => 3, 'merchant_id' => 4, 'outlet_id' => 4, 'role' => 2, 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $partnerships[] = ['id' => 3, 'name' => 'Brew & Co × BookNook', 'existing' => false];

        $this->command->info('Created 2 new partnerships');

        // ── Seed 6 months of redemptions for all 3 partnerships ──
        $startRedemptionId = 100; // avoid conflict with existing
        $startAttrId = 100;
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->startOfMonth();
        }

        $redemptionId = $startRedemptionId;
        $attrId = $startAttrId;

        foreach ($partnerships as $p) {
            $pId = $p['id'];
            // Redemptions grow over time
            $baseCount = match ($pId) {
                1 => 15,  // FitZone: most active
                2 => 10,  // Bella Salon: medium
                3 => 5,   // BookNook: newer, less data
                default => 8,
            };

            foreach ($months as $mIdx => $month) {
                // Skip months before partnership started
                if ($pId === 2 && $mIdx < 2) continue; // Salon started 4 months ago
                if ($pId === 3 && $mIdx < 4) continue; // BookNook started 2 months ago

                $count = $baseCount + ($mIdx * 3) + rand(-2, 3); // growing trend
                $count = max(2, $count);

                for ($j = 0; $j < $count; $j++) {
                    $day = rand(1, 28);
                    $date = $month->copy()->addDays($day - 1)->addHours(rand(9, 20))->addMinutes(rand(0, 59));
                    $billAmount = rand(200, 2000);
                    $benefitPct = rand(5, 15) / 100;
                    $benefitAmount = round($billAmount * $benefitPct, 2);

                    // Customer type distribution: 50% new, 30% existing, 20% reactivated
                    $r = rand(1, 10);
                    $customerType = $r <= 5 ? 1 : ($r <= 8 ? 2 : 3);

                    $uuid = Str::uuid()->toString();

                    // Redemption (merchant 1 = Brew & Co is the target for demo)
                    DB::table('partner_redemptions')->insert([
                        'id' => $redemptionId,
                        'uuid' => $uuid,
                        'merchant_id' => 1,
                        'partnership_id' => $pId,
                        'claim_id' => $redemptionId,
                        'outlet_id' => 1,
                        'customer_id' => rand(1000, 9999),
                        'bill_id' => 'BILL-' . $redemptionId,
                        'transaction_id' => 'TXN-' . $redemptionId,
                        'bill_amount' => $billAmount,
                        'benefit_amount' => $benefitAmount,
                        'customer_type' => $customerType,
                        'rule_snapshot' => json_encode(['pct' => $benefitPct * 100, 'max' => 500, 'min_bill' => 200]),
                        'status' => 1,
                        'created_by' => 1, 'updated_by' => 1,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    // Attribution
                    $retained30 = rand(0, 1) && $customerType === 1;
                    $retained60 = $retained30 && rand(0, 1);
                    $retained90 = $retained60 && rand(0, 1);

                    DB::table('partner_attributions')->insert([
                        'id' => $attrId,
                        'partnership_id' => $pId,
                        'redemption_id' => $redemptionId,
                        'customer_id' => rand(1000, 9999),
                        'source_merchant_id' => match ($pId) { 1 => 2, 2 => 3, 3 => 4, default => 2 },
                        'target_merchant_id' => 1,
                        'outlet_id' => 1,
                        'customer_type' => $customerType,
                        'benefit_amount' => $benefitAmount,
                        'attributed_at' => $date,
                        'period_month' => $month->format('Y-m-d'),
                        'retained_30d' => $retained30,
                        'retained_60d' => $retained60,
                        'retained_90d' => $retained90,
                        'retained_30d_at' => $retained30 ? $date->copy()->addDays(rand(5, 25)) : null,
                        'retained_60d_at' => $retained60 ? $date->copy()->addDays(rand(35, 55)) : null,
                        'retained_90d_at' => $retained90 ? $date->copy()->addDays(rand(65, 85)) : null,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    // Ledger entries (double-entry)
                    $periodMonth = $month->format('Y-m-d');

                    // Target merchant's cost (benefit given)
                    DB::table('partner_ledger_entries')->insert([
                        'uuid' => Str::uuid()->toString(),
                        'partnership_id' => $pId,
                        'redemption_id' => $redemptionId,
                        'merchant_id' => 1, // Brew & Co
                        'outlet_id' => 1,
                        'entry_type' => 'benefit_given',
                        'amount' => $benefitAmount,
                        'period_month' => $periodMonth,
                        'created_by' => 1,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    // Source merchant's credit
                    $sourceMerchant = match ($pId) { 1 => 2, 2 => 3, 3 => 4, default => 2 };
                    DB::table('partner_ledger_entries')->insert([
                        'uuid' => Str::uuid()->toString(),
                        'partnership_id' => $pId,
                        'redemption_id' => $redemptionId,
                        'merchant_id' => $sourceMerchant,
                        'outlet_id' => $sourceMerchant, // use merchant_id as outlet placeholder
                        'entry_type' => 'referral_credit',
                        'amount' => $benefitAmount,
                        'period_month' => $periodMonth,
                        'created_by' => 1,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $redemptionId++;
                    $attrId++;
                }
            }
        }

        $total = $redemptionId - $startRedemptionId;
        $this->command->info("Seeded {$total} redemptions with attributions and ledger entries");

        // ── Seed campaigns (skip if table doesn't exist) ──
        if (!\Illuminate\Support\Facades\Schema::hasTable('campaigns')) {
            $this->command->info('Campaigns table not found — skipping campaign seeding');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->command->info('Demo analytics seeding complete!');
            return;
        }
        for ($c = 1; $c <= 5; $c++) {
            $cDate = Carbon::now()->subDays(rand(10, 90));
            $cId = DB::table('campaigns')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'merchant_id' => 1,
                'name' => "Campaign #{$c} — Partnership Offer",
                'template_key' => 'partnership_earn',
                'target_segment' => json_encode(['last_seen_days' => 30]),
                'template_vars' => json_encode(['member_name' => '{{name}}', 'points' => '100', 'issuer_merchant' => 'Brew & Co', 'partner_merchant' => 'FitZone']),
                'status' => 4, // completed
                'scheduled_at' => $cDate,
                'started_at' => $cDate,
                'completed_at' => $cDate->copy()->addMinutes(15),
                'created_by' => 1,
                'created_at' => $cDate,
                'updated_at' => $cDate,
            ]);

            // Campaign sends
            $sendCount = rand(50, 200);
            $sends = [];
            for ($s = 0; $s < $sendCount; $s++) {
                $sendStatus = rand(1, 10) <= 8 ? 4 : (rand(1, 10) <= 7 ? 2 : 3); // 80% delivered, 14% sent, 6% failed
                $sends[] = [
                    'campaign_id' => $cId,
                    'member_id' => $s + 1,
                    'status' => $sendStatus,
                    'sent_at' => $sendStatus >= 2 ? $cDate : null,
                    'created_at' => $cDate,
                    'updated_at' => $cDate,
                ];
            }
            DB::table('campaign_sends')->insert($sends);
        }

        $this->command->info('Seeded 5 campaigns with sends');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info('Demo analytics seeding complete!');
    }
}
