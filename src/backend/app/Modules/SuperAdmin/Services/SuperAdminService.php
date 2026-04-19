<?php

namespace App\Modules\SuperAdmin\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Platform-level merchant management queries for the super admin dashboard.
 *
 * Owner module: SuperAdmin
 * Tables read (never written): merchants, merchant_whatsapp_balance,
 *   ewards_integration_requests, merchant_integrations
 */
class SuperAdminService
{
    /**
     * Paginated merchant list with WhatsApp credit balance and eWards status.
     *
     * @param  array{search?: string, page?: int} $filters
     * @return LengthAwarePaginator
     */
    public function listMerchants(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('merchants as m')
            ->leftJoin('merchant_whatsapp_balance as wb', 'wb.merchant_id', '=', 'm.id')
            ->leftJoin('ewards_integration_requests as er', function ($j) {
                $j->on('er.merchant_id', '=', 'm.id')
                  ->whereNull('er.deleted_at')
                  ->whereIn('er.status', ['pending', 'approved']);
            })
            ->leftJoin('merchant_integrations as mi', function ($j) {
                $j->on('mi.merchant_id', '=', 'm.id')
                  ->where('mi.provider', '=', 'ewrds')
                  ->where('mi.is_active', '=', true);
            })
            ->select(
                'm.id',
                'm.name',
                'm.email',
                'm.city',
                'm.category',
                'm.ecosystem_active',
                'm.registration_status',
                DB::raw('COALESCE(wb.balance, 0) as whatsapp_credits'),
                DB::raw('wb.low_balance_alerted'),
                DB::raw('er.status as ewards_request_status'),
                DB::raw('CASE WHEN mi.id IS NOT NULL THEN 1 ELSE 0 END as ewards_active'),
            )
            ->orderBy('m.name');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('m.name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('m.city', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->paginate(30);
    }

    /**
     * Full detail for a single merchant including credit ledger summary.
     */
    public function getMerchant(int $merchantId): object
    {
        $merchant = DB::table('merchants as m')
            ->leftJoin('merchant_whatsapp_balance as wb', 'wb.merchant_id', '=', 'm.id')
            ->where('m.id', $merchantId)
            ->select(
                'm.*',
                DB::raw('COALESCE(wb.balance, 0) as whatsapp_credits'),
                DB::raw('wb.low_balance_alerted'),
                DB::raw('wb.updated_at as balance_updated_at'),
            )
            ->firstOrFail();

        $merchant->outlet_count = DB::table('outlets')
            ->where('merchant_id', $merchantId)
            ->count();

        $merchant->live_partnerships = DB::table('partnership_participants')
            ->join('partnerships', 'partnerships.id', '=', 'partnership_participants.partnership_id')
            ->where('partnership_participants.merchant_id', $merchantId)
            ->where('partnerships.status', 5)
            ->count();

        return $merchant;
    }

    /**
     * Platform stats for the super admin dashboard landing page.
     */
    public function platformStats(): array
    {
        return [
            'total_merchants'               => DB::table('merchants')->count(),
            'ecosystem_active'              => DB::table('merchants')->where('ecosystem_active', true)->count(),
            'live_partnerships'             => DB::table('partnerships')->where('status', 5)->count(),
            'pending_brand_registrations'   => DB::table('merchants')->where('registration_status', 'pending')->count(),
            'pending_ewards_requests'       => DB::table('ewards_integration_requests')
                ->where('status', 'pending')
                ->whereNull('deleted_at')
                ->count(),
            'merchants_low_credits'         => DB::table('merchant_whatsapp_balance')
                ->where('balance', '<=', (int) config('services.whatsapp_credits.low_balance_threshold', 50))
                ->where('balance', '>', 0)
                ->count(),
            'merchants_zero_credits'        => DB::table('merchant_whatsapp_balance')
                ->where('balance', 0)
                ->count(),
        ];
    }
}
