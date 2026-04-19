<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Seeds realistic demo data so the dashboard and analytics pages show meaningful metrics.
 *
 * Creates:
 *   - 3 LIVE partnerships with 4 months of redemption history
 *   - 1 NEGOTIATING partnership (pending inbox)
 *   - Claims, redemptions, attributions, ledger entries
 *   - WhatsApp credits for Brew & Co
 *   - Cap counters
 *
 * Run AFTER DevelopmentSeeder:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Merchants (from DevelopmentSeeder):
 *   1=Brew & Co, 2=FitZone, 3=Bella Salon, 4=BookNook, 5=GreenBowl
 * Outlets:
 *   1=Brew Bandra, 2=Brew Andheri, 3=FitZone Bandra, 4=FitZone Andheri,
 *   5=Bella Bandra, 6=BookNook Bandra, 7=GreenBowl Andheri
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ── Partnership 1: Brew & Co ↔ FitZone (LIVE) ────────────
        $p1 = $this->createPartnership(1, 'Brew & Co × FitZone Bandra', 2, 1, 3, 1, 5, $now);

        // ── Partnership 2: Brew & Co ↔ Bella Salon (LIVE) ────────
        $p2 = $this->createPartnership(1, 'Brew & Co × Bella Salon', 3, 1, 5, 1, 5, $now);

        // ── Partnership 3: FitZone ↔ GreenBowl (LIVE) ────────────
        $p3 = $this->createPartnership(2, 'FitZone × GreenBowl', 5, 4, 7, 2, 5, $now);

        // ── Partnership 4: BookNook → Brew & Co (NEGOTIATING) ────
        $this->createPartnership(4, 'BookNook × Brew & Co', 1, 6, 1, 4, 3, $now);

        // ── 4 months of redemption data ──────────────────────────
        $months = [
            $now->copy()->subMonths(3)->startOfMonth(),
            $now->copy()->subMonths(2)->startOfMonth(),
            $now->copy()->subMonths(1)->startOfMonth(),
            $now->copy()->startOfMonth(),
        ];

        // P1: Brew sends customers to FitZone — [total, new, existing]
        $this->seedRedemptions($p1, 1, 2, 1, 3, $months, [
            [8, 3, 5], [12, 5, 7], [18, 7, 11], [15, 6, 9],
        ]);

        // P2: Brew sends customers to Bella Salon
        $this->seedRedemptions($p2, 1, 3, 1, 5, $months, [
            [5, 2, 3], [8, 4, 4], [10, 5, 5], [7, 3, 4],
        ]);

        // P3: FitZone sends customers to GreenBowl
        $this->seedRedemptions($p3, 2, 5, 4, 7, $months, [
            [6, 4, 2], [9, 5, 4], [14, 8, 6], [11, 6, 5],
        ]);

        // ── Reverse direction: partners send customers TO Brew & Co ──
        // P1: FitZone sends customers to Brew
        $this->seedRedemptions($p1, 2, 1, 3, 1, $months, [
            [4, 2, 2], [6, 3, 3], [9, 4, 5], [7, 3, 4],
        ]);

        // P2: Bella sends customers to Brew
        $this->seedRedemptions($p2, 3, 1, 5, 1, $months, [
            [3, 1, 2], [5, 2, 3], [7, 3, 4], [5, 2, 3],
        ]);

        // ── Point valuations for merchants ────────────────────────
        foreach ([1, 2, 3, 5] as $mId) {
            DB::table('merchant_point_valuations')->insertOrIgnore([
                'merchant_id'    => $mId,
                'rupees_per_point' => 0.25,
                'effective_from' => $now->copy()->subMonths(6)->toDateString(),
                'confirmed_by'   => 'System',
                'note'           => 'Default valuation',
                'created_at'     => $now->copy()->subMonths(6),
                'updated_at'     => $now,
            ]);
        }

        // ── Demo members with loyalty balances (for CustomerPortal) ──
        $this->seedMembers();

        // ── Partner offers (for BillOffers module) ────────────────
        $this->seedOffers($p1, $p2, $now);

        // ── WhatsApp credits for Brew & Co ────────────────────────
        DB::table('merchant_whatsapp_balance')->updateOrInsert(
            ['merchant_id' => 1],
            ['balance' => 250, 'updated_at' => $now],
        );
        DB::table('whatsapp_credit_ledger')->insert([
            'merchant_id' => 1, 'entry_type' => 'allocation', 'credits_delta' => 500,
            'balance_after' => 500, 'note' => 'Initial allocation by SuperAdmin',
            'allocated_by' => 1, 'created_at' => $now->copy()->subDays(30),
        ]);
        DB::table('whatsapp_credit_ledger')->insert([
            'merchant_id' => 1, 'entry_type' => 'consumption', 'credits_delta' => -250,
            'balance_after' => 250, 'note' => 'Campaign: Welcome to Brew network',
            'reference_type' => 'campaign_send', 'created_at' => $now->copy()->subDays(15),
        ]);

        $this->command->info('');
        $this->command->info('✓ Demo data seeded');
        $this->command->info('  3 LIVE partnerships with ~150 redemptions across 4 months');
        $this->command->info('  1 NEGOTIATING partnership (pending inbox)');
        $this->command->info('  WhatsApp credits: 250 for Brew & Co');
    }

    private function createPartnership(
        int $merchantId, string $name, int $partnerMerchantId,
        int $proposerOutletId, int $acceptorOutletId, int $userId,
        int $status, Carbon $now,
    ): int {
        $id = DB::table('partnerships')->insertGetId([
            'uuid' => (string) Str::uuid(), 'merchant_id' => $merchantId,
            'name' => $name, 'scope_type' => 1, 'status' => $status,
            'start_at' => $status === 5 ? $now->copy()->subMonths(3) : null,
            'created_by' => $userId, 'updated_by' => $userId,
            'created_at' => $now->copy()->subMonths(4), 'updated_at' => $now,
        ]);

        DB::table('partnership_participants')->insert([
            ['partnership_id' => $id, 'merchant_id' => $merchantId, 'outlet_id' => $proposerOutletId,
             'role' => 1, 'approval_status' => 2, 'approved_by' => $userId,
             'approved_at' => $now->copy()->subMonths(3), 'created_by' => $userId,
             'updated_by' => $userId, 'created_at' => $now->copy()->subMonths(4), 'updated_at' => $now],
            ['partnership_id' => $id, 'merchant_id' => $partnerMerchantId, 'outlet_id' => $acceptorOutletId,
             'role' => 2, 'approval_status' => $status >= 4 ? 2 : 1,
             'approved_by' => $status >= 4 ? $userId : null,
             'approved_at' => $status >= 4 ? $now->copy()->subMonths(3) : null,
             'created_by' => $userId, 'updated_by' => $userId,
             'created_at' => $now->copy()->subMonths(4), 'updated_at' => $now],
        ]);

        DB::table('partnership_terms')->insert([
            'partnership_id' => $id, 'merchant_id' => $merchantId,
            'per_bill_cap_percent' => 20.00, 'per_bill_cap_amount' => 150.00,
            'min_bill_amount' => 200.00, 'monthly_cap_amount' => 10000.00,
            'approval_mode' => 1, 'version' => 1,
            'created_by' => $userId, 'updated_by' => $userId,
            'created_at' => $now->copy()->subMonths(4), 'updated_at' => $now,
        ]);

        return $id;
    }

    private function seedRedemptions(
        int $pId, int $srcMerchant, int $tgtMerchant,
        int $srcOutlet, int $tgtOutlet, array $months, array $data,
    ): void {
        foreach ($months as $i => $monthStart) {
            [$total, $newCount] = $data[$i];
            $period = $monthStart->format('Y-m-01');

            for ($j = 0; $j < $total; $j++) {
                $isNew = $j < $newCount;
                $cType = $isNew ? 1 : 2;
                $bill = rand(250, 1200);
                $benefit = min(round($bill * 0.20, 2), 150.00);
                $day = rand(1, min(28, $monthStart->daysInMonth));
                $ts = $monthStart->copy()->addDays($day - 1)->addHours(rand(9, 20));

                $claimId = DB::table('partner_claims')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'merchant_id' => $tgtMerchant,
                    'partnership_id' => $pId, 'source_outlet_id' => $srcOutlet,
                    'target_outlet_id' => $tgtOutlet,
                    'customer_phone' => '919' . rand(100000000, 999999999),
                    'token' => 'HLP' . strtoupper(Str::random(5)),
                    'status' => 2, 'issued_at' => $ts->copy()->subHours(2),
                    'expires_at' => $ts->copy()->addHours(22), 'redeemed_at' => $ts,
                    'created_by' => 0, 'updated_by' => 0,
                    'created_at' => $ts, 'updated_at' => $ts,
                ]);

                $redId = DB::table('partner_redemptions')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'merchant_id' => $tgtMerchant,
                    'partnership_id' => $pId, 'claim_id' => $claimId,
                    'outlet_id' => $tgtOutlet, 'bill_amount' => $bill,
                    'benefit_amount' => $benefit, 'customer_type' => $cType,
                    'rule_snapshot' => json_encode(['cap' => '20%', 'max' => 150]),
                    'approval_method' => 1, 'status' => 1,
                    'created_by' => 0, 'updated_by' => 0,
                    'created_at' => $ts, 'updated_at' => $ts,
                ]);

                if ($isNew) {
                    DB::table('partner_attributions')->insert([
                        'partnership_id' => $pId, 'redemption_id' => $redId,
                        'source_merchant_id' => $srcMerchant,
                        'target_merchant_id' => $tgtMerchant,
                        'outlet_id' => $tgtOutlet, 'customer_type' => $cType,
                        'benefit_amount' => $benefit, 'attributed_at' => $ts,
                        'period_month' => $period,
                        'retained_30d' => $i < 3 && rand(0, 100) > 40,
                        'retained_60d' => $i < 2 && rand(0, 100) > 55,
                        'retained_90d' => $i < 1 && rand(0, 100) > 70,
                        'created_at' => $ts, 'updated_at' => $ts,
                    ]);
                }

                DB::table('partner_ledger_entries')->insert([
                    ['uuid' => (string) Str::uuid(), 'partnership_id' => $pId,
                     'redemption_id' => $redId, 'merchant_id' => $tgtMerchant,
                     'outlet_id' => $tgtOutlet, 'entry_type' => 'benefit_given',
                     'amount' => $benefit, 'period_month' => $period,
                     'created_by' => 0, 'created_at' => $ts, 'updated_at' => $ts],
                    ['uuid' => (string) Str::uuid(), 'partnership_id' => $pId,
                     'redemption_id' => $redId, 'merchant_id' => $srcMerchant,
                     'outlet_id' => $srcOutlet, 'entry_type' => 'referral_credit',
                     'amount' => $benefit, 'period_month' => $period,
                     'created_by' => 0, 'created_at' => $ts, 'updated_at' => $ts],
                ]);
            }
        }
    }

    /**
     * Create demo members with loyalty balances so the customer portal has data.
     */
    private function seedMembers(): void
    {
        $now = Carbon::now();

        $demoCustomers = [
            ['phone' => '919900001111', 'name' => 'Rahul Sharma',  'balances' => [1 => 450, 2 => 120]],
            ['phone' => '919900002222', 'name' => 'Priya Patel',   'balances' => [1 => 280, 3 => 90]],
            ['phone' => '919900003333', 'name' => 'Amit Kumar',    'balances' => [2 => 350, 5 => 200]],
            ['phone' => '919800001111', 'name' => 'Sneha Desai',   'balances' => [1 => 600]],
            ['phone' => '919800002222', 'name' => 'Rohan Mehta',   'balances' => [1 => 150, 2 => 75, 3 => 40]],
        ];

        foreach ($demoCustomers as $c) {
            $memberId = DB::table('members')->insertGetId([
                'uuid'           => (string) Str::uuid(),
                'phone'          => $c['phone'],
                'name'           => $c['name'],
                'whatsapp_opt_in' => true,
                'last_seen_at'   => $now->copy()->subDays(rand(1, 14)),
                'created_at'     => $now->copy()->subMonths(3),
                'updated_at'     => $now,
            ]);

            foreach ($c['balances'] as $merchantId => $balance) {
                DB::table('member_loyalty_balances')->insert([
                    'member_id'      => $memberId,
                    'merchant_id'    => $merchantId,
                    'balance'        => $balance,
                    'provider'       => 'local',
                    'last_synced_at' => $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }

    /**
     * Seed demo partner offers and attach to partnerships.
     */
    private function seedOffers(int $p1Id, int $p2Id, Carbon $now): void
    {
        // Enable bill offers for FitZone (merchant 2) — so Brew's offers show on FitZone's bills
        DB::table('merchants')->where('id', 2)->update([
            'bill_offers_enabled' => true,
            'bill_offers_display_mode' => 'simple',
        ]);

        // Brew & Co creates two offers
        $offer1Id = DB::table('partner_offers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'merchant_id' => 1,
            'title' => '20% off your first coffee',
            'description' => 'Valid on any beverage at Brew & Co. Show this code at the counter.',
            'coupon_code' => 'BREW20OFF', 'discount_type' => 1, 'discount_value' => 20.00,
            'expiry_date' => $now->copy()->addMonths(2)->toDateString(),
            'terms_conditions' => 'Valid on min bill of ₹200. One use per customer. Not valid with other offers.',
            'display_template' => 'simple', 'status' => 1,
            'created_by' => 1, 'updated_by' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $offer2Id = DB::table('partner_offers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'merchant_id' => 1,
            'title' => 'Free pastry with any drink',
            'description' => 'Get a complimentary pastry when you order any hot or cold beverage.',
            'coupon_code' => 'BREWPASTRY', 'discount_type' => 2, 'discount_value' => 80.00,
            'expiry_date' => $now->copy()->addMonths(1)->toDateString(),
            'display_template' => 'simple', 'status' => 1,
            'created_by' => 1, 'updated_by' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Bella Salon creates an offer
        $offer3Id = DB::table('partner_offers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'merchant_id' => 3,
            'title' => '₹150 off hair styling',
            'description' => 'Flat ₹150 off any hair styling service at Bella Salon Bandra.',
            'coupon_code' => 'BELLA150', 'discount_type' => 2, 'discount_value' => 150.00,
            'expiry_date' => $now->copy()->addMonths(3)->toDateString(),
            'terms_conditions' => 'Valid for new customers only. Booking required.',
            'display_template' => 'simple', 'status' => 1,
            'created_by' => 3, 'updated_by' => 3,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Attach Brew's offers to Partnership 1 (Brew × FitZone) — shows on FitZone's bills
        DB::table('partner_offer_attachments')->insert([
            ['offer_id' => $offer1Id, 'partnership_id' => $p1Id, 'attached_by_merchant_id' => 2,
             'is_active' => true, 'created_by' => 2, 'updated_by' => 2,
             'created_at' => $now, 'updated_at' => $now],
            ['offer_id' => $offer2Id, 'partnership_id' => $p1Id, 'attached_by_merchant_id' => 2,
             'is_active' => true, 'created_by' => 2, 'updated_by' => 2,
             'created_at' => $now, 'updated_at' => $now],
        ]);

        // Attach Bella's offer to Partnership 2 (Brew × Bella) — shows on Brew's bills (if enabled)
        DB::table('partner_offer_attachments')->insert([
            'offer_id' => $offer3Id, 'partnership_id' => $p2Id, 'attached_by_merchant_id' => 1,
            'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
