<?php

namespace App\Modules\CustomerPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partnership\Constants\PartnershipStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer rewards — balances, redeemable outlets, recent activity.
 *
 * All endpoints require CustomerAuth middleware (member_id injected into request).
 *
 * Owner module: CustomerPortal
 * Reads: member_loyalty_balances, merchants, merchant_point_valuations,
 *        partnerships, partnership_participants, partnership_terms, outlets,
 *        partner_claims, partner_redemptions
 */
class CustomerRewardsController extends Controller
{
    /**
     * GET /api/customer/rewards
     *
     * Returns: for each merchant where customer has points,
     * the balance, point value, and all LIVE partner outlets where points can be redeemed.
     */
    public function rewards(Request $request): JsonResponse
    {
        $memberId = $request->input('customer_member_id');

        // Get all balances for this member
        $balances = DB::table('member_loyalty_balances as mlb')
            ->join('merchants as m', 'm.id', '=', 'mlb.merchant_id')
            ->leftJoin('merchant_point_valuations as mpv', function ($join) {
                $join->on('mpv.merchant_id', '=', 'mlb.merchant_id')
                     ->whereRaw('mpv.id = (SELECT MAX(id) FROM merchant_point_valuations WHERE merchant_id = mlb.merchant_id)');
            })
            ->where('mlb.member_id', $memberId)
            ->where('mlb.balance', '>', 0)
            ->select([
                'mlb.merchant_id',
                'm.name as merchant_name',
                'm.category as merchant_category',
                'mlb.balance',
                'mlb.last_synced_at',
                'mpv.rupees_per_point',
            ])
            ->get();

        $result = [];

        foreach ($balances as $b) {
            // Find LIVE partnerships where this merchant participates
            $partnerships = DB::table('partnerships as p')
                ->join('partnership_participants as pp1', function ($join) use ($b) {
                    $join->on('pp1.partnership_id', '=', 'p.id')
                         ->where('pp1.merchant_id', $b->merchant_id)
                         ->whereNull('pp1.deleted_at');
                })
                ->join('partnership_participants as pp2', function ($join) use ($b) {
                    $join->on('pp2.partnership_id', '=', 'p.id')
                         ->where('pp2.merchant_id', '!=', $b->merchant_id)
                         ->whereNull('pp2.deleted_at');
                })
                ->join('partnership_terms as pt', 'pt.partnership_id', '=', 'p.id')
                ->leftJoin('outlets as o', 'o.id', '=', 'pp2.outlet_id')
                ->leftJoin('merchants as pm', 'pm.id', '=', 'pp2.merchant_id')
                ->where('p.status', PartnershipStatus::LIVE)
                ->whereNull('p.deleted_at')
                ->select([
                    'p.uuid as partnership_uuid',
                    'p.name as partnership_name',
                    'pm.name as partner_name',
                    'pm.category as partner_category',
                    'o.name as outlet_name',
                    'o.address as outlet_address',
                    'pt.per_bill_cap_percent',
                    'pt.per_bill_cap_amount',
                    'pt.min_bill_amount',
                ])
                ->get();

            $result[] = [
                'merchant_id'       => $b->merchant_id,
                'merchant_name'     => $b->merchant_name,
                'merchant_category' => $b->merchant_category,
                'balance'           => (float) $b->balance,
                'rupees_per_point'  => $b->rupees_per_point ? (float) $b->rupees_per_point : null,
                'value_in_rupees'   => $b->rupees_per_point ? round($b->balance * $b->rupees_per_point, 2) : null,
                'last_synced_at'    => $b->last_synced_at,
                'redeemable_at'     => $partnerships->map(fn ($p) => [
                    'partnership_name'    => $p->partnership_name,
                    'partner_name'        => $p->partner_name,
                    'partner_category'    => $p->partner_category,
                    'outlet_name'         => $p->outlet_name ?? 'All outlets',
                    'outlet_address'      => $p->outlet_address,
                    'cap_percent'         => $p->per_bill_cap_percent ? (float) $p->per_bill_cap_percent : null,
                    'max_per_bill'        => $p->per_bill_cap_amount ? (float) $p->per_bill_cap_amount : null,
                    'min_bill'            => $p->min_bill_amount ? (float) $p->min_bill_amount : null,
                ])->values(),
            ];
        }

        return response()->json([
            'rewards' => $result,
            'total_merchants' => count($result),
        ]);
    }

    /**
     * GET /api/customer/activity
     *
     * Returns: last 10 claims and redemptions across all merchants.
     */
    public function activity(Request $request): JsonResponse
    {
        $memberId = $request->input('customer_member_id');

        $claims = DB::table('partner_claims as c')
            ->leftJoin('merchants as m', 'm.id', '=', 'c.merchant_id')
            ->leftJoin('outlets as so', 'so.id', '=', 'c.source_outlet_id')
            ->leftJoin('outlets as to2', 'to2.id', '=', 'c.target_outlet_id')
            ->where('c.member_id', $memberId)
            ->orderByDesc('c.created_at')
            ->limit(10)
            ->select([
                DB::raw("'claim' as type"),
                'c.token',
                'c.status',
                'm.name as merchant_name',
                'so.name as source_outlet',
                'to2.name as target_outlet',
                'c.issued_at',
                'c.redeemed_at',
                'c.expires_at',
            ])
            ->get();

        $redemptions = DB::table('partner_redemptions as r')
            ->leftJoin('merchants as m', 'm.id', '=', 'r.merchant_id')
            ->leftJoin('outlets as o', 'o.id', '=', 'r.outlet_id')
            ->where('r.member_id', $memberId)
            ->orderByDesc('r.created_at')
            ->limit(10)
            ->select([
                DB::raw("'redemption' as type"),
                'r.bill_amount',
                'r.benefit_amount',
                'r.customer_type',
                'm.name as merchant_name',
                'o.name as outlet_name',
                'r.created_at',
            ])
            ->get();

        // Merge and sort by date
        $activity = $claims->concat($redemptions)
            ->sortByDesc(fn ($item) => $item->created_at ?? $item->issued_at)
            ->values()
            ->take(10);

        return response()->json(['activity' => $activity]);
    }
}
