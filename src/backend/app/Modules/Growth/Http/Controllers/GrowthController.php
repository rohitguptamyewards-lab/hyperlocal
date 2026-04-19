<?php

namespace App\Modules\Growth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Growth\Services\DemandIndexService;
use App\Modules\Growth\Services\PartnershipHealthService;
use App\Modules\Growth\Services\ReferralService;
use App\Modules\Growth\Services\WeeklyDigestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthController extends Controller
{
    public function __construct(
        private readonly PartnershipHealthService $health,
        private readonly ReferralService          $referrals,
        private readonly WeeklyDigestService      $digest,
        private readonly DemandIndexService       $demand,
    ) {}

    // ── Partnership Health (#7) ───────────────────────────

    public function healthScores(Request $request): JsonResponse
    {
        $leaderboard = $this->health->getLeaderboard($request->user()->merchant_id);
        return response()->json(['leaderboard' => $leaderboard]);
    }

    public function partnershipHealth(Request $request, string $partnershipUuid): JsonResponse
    {
        $p = \App\Modules\Partnership\Models\Partnership::where('uuid', $partnershipUuid)->firstOrFail();
        $score = $this->health->computeForPartnership($p->id);
        $repeatRate = $this->health->getRepeatRate($p->id, $request->user()->merchant_id);
        return response()->json(['health' => $score, 'repeat_rate' => $repeatRate]);
    }

    // ── Referral Links (#1) ───────────────────────────────

    public function referralLink(Request $request, string $partnershipUuid): JsonResponse
    {
        $p = \App\Modules\Partnership\Models\Partnership::where('uuid', $partnershipUuid)->firstOrFail();
        $link = $this->referrals->getReferralLink($p->id, $request->user()->merchant_id);
        return response()->json($link);
    }

    // ── Invite Nearby Brand (#5) ──────────────────────────

    public function createInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $result = $this->referrals->createInvite(
            $request->user()->merchant_id,
            $data['phone'] ?? null,
            $data['email'] ?? null,
        );

        return response()->json($result, 201);
    }

    public function inviteStats(Request $request): JsonResponse
    {
        return response()->json($this->referrals->getInviteStats($request->user()->merchant_id));
    }

    // ── Weekly Digest (#3) ────────────────────────────────

    public function weeklyDigest(Request $request): JsonResponse
    {
        $digest = $this->digest->generateForMerchant($request->user()->merchant_id);
        return response()->json($digest);
    }

    // ── Demand Index (#16, #17) ───────────────────────────

    public function demandIndex(Request $request): JsonResponse
    {
        $merchant = \App\Models\Merchant::findOrFail($request->user()->merchant_id);
        $categoryDemand = $this->demand->getCategoryDemand($merchant->city ?? 'Mumbai');
        $untapped = $this->demand->getUntappedDemand($merchant->id);
        $pairPerformance = $this->demand->getCategoryPairPerformance();

        return response()->json([
            'category_demand'   => $categoryDemand,
            'untapped_demand'   => $untapped,
            'pair_performance'  => $pairPerformance,
        ]);
    }

    // ── Brand Profile (#11) ──────────────────────────────

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bio'            => ['nullable', 'string', 'max:2000'],
            'logo_url'       => ['nullable', 'string', 'max:500'],
            'profile_public' => ['sometimes', 'boolean'],
        ]);

        $merchant = \App\Models\Merchant::findOrFail($request->user()->merchant_id);
        if (!$merchant->slug) {
            $data['slug'] = \Illuminate\Support\Str::slug($merchant->name) . '-' . $merchant->id;
        }
        $merchant->update($data);

        return response()->json([
            'slug'           => $merchant->slug,
            'bio'            => $merchant->bio,
            'logo_url'       => $merchant->logo_url,
            'profile_public' => (bool) $merchant->profile_public,
            'profile_url'    => $merchant->profile_public ? config('app.url') . '/b/' . $merchant->slug : null,
        ]);
    }

    // ── Seasonal Templates (#9) ──────────────────────────

    public function seasonalTemplates(): JsonResponse
    {
        $templates = [
            ['key' => 'diwali', 'label' => 'Diwali Special', 'title' => 'Diwali offer from {brand}', 'discount' => '20% OFF', 'season' => 'Oct-Nov'],
            ['key' => 'christmas', 'label' => 'Christmas / New Year', 'title' => 'Holiday special at {brand}', 'discount' => '₹200 OFF', 'season' => 'Dec-Jan'],
            ['key' => 'summer', 'label' => 'Summer Sale', 'title' => 'Beat the heat at {brand}', 'discount' => '15% OFF', 'season' => 'Apr-Jun'],
            ['key' => 'valentines', 'label' => "Valentine's Day", 'title' => "Valentine's treat from {brand}", 'discount' => '₹150 OFF', 'season' => 'Feb'],
            ['key' => 'monsoon', 'label' => 'Monsoon Special', 'title' => 'Rainy day reward at {brand}', 'discount' => '10% OFF', 'season' => 'Jul-Sep'],
            ['key' => 'back_to_school', 'label' => 'Back to School', 'title' => 'Student special at {brand}', 'discount' => '25% OFF', 'season' => 'Jun-Jul'],
        ];

        return response()->json(['templates' => $templates]);
    }
}
