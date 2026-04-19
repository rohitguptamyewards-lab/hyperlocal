<?php

/**
 * API Routes — Hyperlocal Partnership Network
 *
 * Purpose: Entry point for all API v1 routes.
 * Owner module: Core / bootstrap
 * Integration points: Sanctum authentication middleware (statefulApi)
 *
 * All routes here are automatically prefixed with /api
 * and assigned the 'api' middleware group via bootstrap/app.php.
 *
 * Route grouping follows module boundaries from FLOWCHART.md.
 * Adding a new module = add a new Route::prefix block below.
 */

use App\Models\Merchant;
use App\Models\Outlet;
use App\Modules\Admin\Http\Controllers\AuthController;
use App\Modules\Admin\Http\Controllers\EcosystemController;
use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Modules\Campaign\Http\Controllers\CampaignController;
use App\Modules\CustomerActivation\Http\Controllers\ClaimController;
use App\Modules\CustomerActivation\Http\Controllers\PublicClaimController;
use App\Modules\Discovery\Http\Controllers\DiscoveryController;
use App\Modules\Enablement\Http\Controllers\EnablementController;
use App\Modules\EwardsRequest\Http\Controllers\EwardsRequestController;
use App\Modules\Execution\Http\Controllers\ExecutionController;
use App\Modules\Execution\Http\Controllers\DeliveryStatsController;
use App\Modules\Execution\Http\Controllers\ReminderSettingsController;
use App\Modules\IntegrationHub\Http\Controllers\IntegrationController;
use App\Modules\Ledger\Http\Controllers\LedgerController;
use App\Modules\Member\Http\Controllers\MemberController;
use App\Modules\MerchantSettings\Http\Controllers\MerchantSettingsController;
use App\Modules\Network\Http\Controllers\NetworkController;
use App\Modules\Partnership\Http\Controllers\PartnershipController;
use App\Modules\Partnership\Http\Controllers\AnnouncementController;
use App\Modules\Campaign\Http\Controllers\FollowupCampaignController;
use App\Modules\SuperAdmin\Http\Controllers\BrandRegistrationReviewController;
use App\Modules\SuperAdmin\Http\Controllers\CreditAllocationController;
use App\Modules\SuperAdmin\Http\Controllers\IntegrationRequestController;
use App\Modules\SuperAdmin\Http\Controllers\MerchantManagementController;
use App\Modules\SuperAdmin\Http\Controllers\SuperAdminAuthController;
use App\Modules\Webhook\Http\Controllers\EcosystemWebhookController;
use App\Modules\CustomerPortal\Http\Controllers\CustomerAuthController;
use App\Modules\CustomerPortal\Http\Controllers\CustomerRewardsController;
use App\Modules\PartnerOffers\Http\Controllers\PartnerOfferController;
use App\Modules\PartnerOffers\Http\Controllers\BillOffersPublicController;
use App\Modules\EventTriggers\Http\Controllers\EventIngestionController;
use App\Modules\EventTriggers\Http\Controllers\EventConfigController;
use App\Modules\Growth\Http\Controllers\GrowthController;
use App\Modules\Growth\Http\Controllers\PublicGrowthController;
use App\Modules\Customer\Http\Controllers\CustomerUploadController;
use App\Modules\Registration\Http\Controllers\BrandRegistrationController;
use App\Modules\Network\Http\Controllers\PartnerRatingController;
use App\Modules\Partnership\Http\Controllers\PartnershipAlertController;
use App\Modules\Execution\Http\Controllers\ShareableLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check — unauthenticated
Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'service' => 'hyperlocal-api',
]));

// ── Auth Module (public) ───────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Super Admin Auth (public) ──────────────────────────────────
Route::prefix('super-admin/auth')->group(function () {
    Route::post('/login', [SuperAdminAuthController::class, 'login']);
});

// ── Inbound Webhooks (signed, no user auth) ────────────────────
Route::prefix('webhooks/ecosystem')
    ->middleware(['webhook.verify:ewrds'])
    ->group(function () {
        Route::post('/merchant-exit',       [EcosystemWebhookController::class, 'merchantExit']);
        Route::post('/merchant-reactivate', [EcosystemWebhookController::class, 'merchantReactivate']);
    });

// ── Public QR claim flow (no auth, rate limited) ───────────────
Route::prefix('public')->middleware(['throttle:20,1'])->group(function () {
    Route::get('/partnerships/{uuid}',  [PublicClaimController::class, 'show']);
    Route::post('/claims',              [PublicClaimController::class, 'store'])->middleware('throttle:5,1');
    // Network invite preview — no auth, just returns network info for the landing page
    Route::get('/network-invite/{token}', [NetworkController::class, 'previewInvite']);

    // ── Bill Offers (public, for digital bill integration) ────
    Route::prefix('bill-offers/{merchantUuid}')->group(function () {
        Route::get('/enabled',    [BillOffersPublicController::class, 'enabled']);
        Route::get('/',           [BillOffersPublicController::class, 'index']);
        Route::post('/impressions', [BillOffersPublicController::class, 'recordImpressions']);
        Route::post('/claims/{offerUuid}', [BillOffersPublicController::class, 'recordClaim']);
    });
});

// ── Event Ingestion (public, rate limited) ────────────────────
Route::prefix('events')->middleware(['throttle:60,1'])->group(function () {
    Route::get('/pixel/{merchantKey}',  [EventIngestionController::class, 'pixel']);
    Route::post('/trigger',             [EventIngestionController::class, 'trigger']);
    Route::post('/ingest',              [EventIngestionController::class, 'ingest'])->middleware('verify_event');
});
Route::prefix('connectors')->middleware(['throttle:60,1'])->group(function () {
    Route::post('/shopify/{merchantKey}/orders',     [EventIngestionController::class, 'shopifyOrders']);
    Route::post('/woocommerce/{merchantKey}/orders', [EventIngestionController::class, 'woocommerceOrders']);
});

// ── Public Growth (brand profiles, referral links, marketplace) ──
Route::prefix('public')->group(function () {
    Route::get('/brand/{slug}',    [PublicGrowthController::class, 'brandProfile']);
    Route::get('/r/{code}',        [PublicGrowthController::class, 'referralRedirect']);
    Route::get('/marketplace',     [PublicGrowthController::class, 'marketplace']);
});

// ── Brand Self-Registration (public, no auth) ────────────────
Route::post('/register-brand', [BrandRegistrationController::class, 'register']);

// ── GAP 10: Shareable claim link landing (public, no auth) ───
Route::get('/shared-claim/{code}', [ShareableLinkController::class, 'claimViaLink'])
    ->middleware(['throttle:30,1']);

// ─────────────────────────────────────────────────────────────
// Authenticated routes
// ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn (Request $request) => $request->user());

    // ── Auth Module (protected) ────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // ── Outlets list (for cashier outlet picker) ──────────
    Route::get('/outlets', function (Request $request) {
        $outlets = Outlet::where('merchant_id', $request->user()->merchant_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        return response()->json($outlets);
    });

    // ── Merchants list (for partner selection) ────────────
    Route::get('/merchants', function (Request $request) {
        $merchants = Merchant::where('id', '!=', $request->user()->merchant_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        return response()->json($merchants);
    });

    // Fetch outlets of a specific merchant (used in partnership creation to pick partner outlets)
    Route::get('/merchants/{id}/outlets', function (Request $request, int $id) {
        $outlets = \App\Models\Outlet::where('merchant_id', $id)
            ->where('is_active', true)
            ->select('id', 'name', 'city')
            ->orderBy('name')
            ->get();
        return response()->json($outlets);
    });

    // ── Partnership Module ─────────────────────────────────
    Route::prefix('partnerships')->group(function () {
        Route::get('/tc',         [PartnershipController::class, 'showTC']);
        Route::get('/',           [PartnershipController::class, 'index']);
        Route::post('/',          [PartnershipController::class, 'store']);
        Route::get('/{uuid}',     [PartnershipController::class, 'show']);
        Route::put('/{uuid}',     [PartnershipController::class, 'update']);
        Route::post('/{uuid}/accept',            [PartnershipController::class, 'accept']);
        Route::post('/{uuid}/accept-and-start', [PartnershipController::class, 'acceptAndGoLive']);
        Route::post('/{uuid}/reject',            [PartnershipController::class, 'reject']);
        Route::post('/{uuid}/go-live', [PartnershipController::class, 'goLive']);
        Route::post('/{uuid}/pause',             [PartnershipController::class, 'pause']);
        Route::post('/{uuid}/resume',            [PartnershipController::class, 'resume']);
        Route::post('/{uuid}/my-settings',        [PartnershipController::class, 'updateMySettings']);
        Route::post('/{uuid}/notify-customers',   [PartnershipController::class, 'notifyCustomers']);
        // Enablement sub-resource
        Route::get('/{uuid}/enablement', [EnablementController::class, 'index']);
        Route::post('/{uuid}/enablement/{outletId}/training', [EnablementController::class, 'markTraining']);
        // Ledger sub-resource
        Route::get('/{uuid}/ledger', [LedgerController::class, 'partnershipStatement']);
        // Claim issuance — partner outlets picker
        Route::get('/{uuid}/partner-outlets',  [PartnershipController::class, 'partnerOutlets']);
        Route::post('/{uuid}/fill-offer',       [PartnershipController::class, 'fillOffer']);
        // Redemption history
        Route::get('/{uuid}/redemptions', [PartnershipController::class, 'redemptions']);
        // GAP 7 — Partner ratings
        Route::post('/{uuid}/rate',    [PartnerRatingController::class, 'rate']);
        Route::get('/{uuid}/ratings',  [PartnerRatingController::class, 'getRatings']);
        // GAP 10 — Shareable link generation
        Route::post('/{uuid}/share-link', [ShareableLinkController::class, 'generateLink']);
        // Announcements (GAP 3 -- Auto Partnership Announcement)
        Route::post('/{uuid}/announcements/preview', [AnnouncementController::class, 'preview']);
        Route::post('/{uuid}/announcements/send',    [AnnouncementController::class, 'send']);
        Route::get('/{uuid}/announcements',          [AnnouncementController::class, 'history']);
    });

    // ── GAP 7 — Merchant ratings (received) ───────────────
    Route::get('/merchants/{id}/ratings', [PartnerRatingController::class, 'getMerchantRatings']);

    // ── Partnership alerts (in-app notifications) ──────────
    Route::prefix('partnership-alerts')->group(function () {
        Route::get('/',               [PartnershipAlertController::class, 'index']);
        Route::post('/{id}/read',     [PartnershipAlertController::class, 'markRead']);
        Route::post('/read-all',      [PartnershipAlertController::class, 'markAllRead']);
    });

    // ── CustomerActivation Module ─────────────────────────
    Route::prefix('claims')->group(function () {
        Route::post('/', [ClaimController::class, 'store']);
    });

    // ── Execution Module ───────────────────────────────────
    Route::prefix('execution')->group(function () {
        Route::get('/lookup/{token}',       [ExecutionController::class, 'lookup']);
        Route::post('/approval-request',    [ExecutionController::class, 'requestApproval']);
        Route::post('/redeem',              [ExecutionController::class, 'redeem']);
    });

    // ── Delivery Stats Module (GAP 2) ──────────────────────
    Route::prefix('delivery')->group(function () {
        Route::get('/stats',    [DeliveryStatsController::class, 'stats']);
        Route::get('/failures', [DeliveryStatsController::class, 'recentFailures']);
    });

    // ── Token Expiry Reminder Settings (GAP 4) ────────────
    Route::prefix('reminders')->group(function () {
        Route::get('/settings', [ReminderSettingsController::class, 'show']);
        Route::put('/settings', [ReminderSettingsController::class, 'update']);
    });

    // ── Ledger Module ─────────────────────────────────────
    Route::get('/ledger/summary', [LedgerController::class, 'merchantSummary']);

    // ── Analytics Module ───────────────────────────────────
    Route::prefix('analytics')->group(function () {
        Route::get('/summary',                     [AnalyticsController::class, 'summary']);
        Route::get('/trends',                      [AnalyticsController::class, 'trends']);
        Route::get('/partnerships/{uuid}',         [AnalyticsController::class, 'partnership']);
        Route::get('/partnerships/{uuid}/trend',   [AnalyticsController::class, 'partnershipTrend']);
    });

    // ── Enablement Module ──────────────────────────────────
    Route::get('/enablement/summary', [EnablementController::class, 'summary']);

    // ── Discovery Module ───────────────────────────────────
    Route::prefix('discovery')->group(function () {
        Route::get('/suggestions',               [DiscoveryController::class, 'suggestions']);
        Route::post('/suggestions/{id}/dismiss', [DiscoveryController::class, 'dismiss']);
        Route::get('/search',                    [DiscoveryController::class, 'search']);
    });

    // ── Admin: Ecosystem toggle (E-001, local dev only) ───
    Route::prefix('admin/merchants/{merchantId}/ecosystem')->group(function () {
        Route::post('/deactivate', [EcosystemController::class, 'deactivate']);
        Route::post('/reactivate', [EcosystemController::class, 'reactivate']);
    });

    // ── Merchant Settings ──────────────────────────────────
    Route::prefix('merchant/settings')->group(function () {
        Route::get('/point-valuation',  [MerchantSettingsController::class, 'getPointValuation']);
        Route::post('/point-valuation', [MerchantSettingsController::class, 'setPointValuation']);
        Route::get('/discoverability',  [MerchantSettingsController::class, 'getDiscoverability']);
        Route::post('/discoverability', [MerchantSettingsController::class, 'setDiscoverability']);
    });

    // ── Member Module ──────────────────────────────────────
    Route::prefix('members')->group(function () {
        Route::post('/lookup',  [MemberController::class, 'lookup']);
        Route::post('/opt-out', [MemberController::class, 'optOut']);
    });

    // ── Integration Hub ────────────────────────────────────
    Route::prefix('merchant/integrations')->group(function () {
        Route::get('/',              [IntegrationController::class, 'index']);
        Route::post('/',             [IntegrationController::class, 'upsert']);
        Route::delete('/{provider}', [IntegrationController::class, 'deactivate']);
    });


    // ── Campaign Module ────────────────────────────────────
    Route::prefix('campaigns')->group(function () {
        Route::get('/templates',        [CampaignController::class, 'templates']);
        Route::post('/segment-preview', [CampaignController::class, 'segmentPreview']);
        Route::get('/',                 [CampaignController::class, 'index']);
        Route::post('/',                [CampaignController::class, 'store']);
        Route::get('/{uuid}',           [CampaignController::class, 'show']);
        Route::post('/{uuid}/schedule', [CampaignController::class, 'schedule']);
        Route::post('/{uuid}/run',      [CampaignController::class, 'run']);
        Route::post('/{uuid}/cancel',   [CampaignController::class, 'cancel']);
    });

    // ── Follow-up Campaigns (GAP 6) ──────────────────────
    Route::prefix('followup-campaigns')->group(function () {
        Route::get('/stats', [FollowupCampaignController::class, 'stats']);
        Route::get('/',      [FollowupCampaignController::class, 'index']);
        Route::post('/',     [FollowupCampaignController::class, 'store']);
        Route::put('/{id}',  [FollowupCampaignController::class, 'update']);
    });

    // ── WhatsApp credit balance (merchant self-service) ───
    // Read-only — merchant sees their own balance before sending a campaign.
    Route::get('/merchant/whatsapp-balance', function (\Illuminate\Http\Request $req) {
        $balance = app(\App\Modules\WhatsAppCredit\Services\WhatsAppCreditService::class)
            ->getBalance($req->user()->merchant_id);
        return response()->json(['balance' => $balance]);
    });

    // ── eWards Integration Request (merchant) ─────────────
    Route::prefix('merchant/ewards-request')->group(function () {
        Route::get('/',  [EwardsRequestController::class, 'show']);
        Route::post('/', [EwardsRequestController::class, 'store']);
    });

    // ── Hyperlocal Network Module ──────────────────────────
    Route::prefix('merchant/networks')->group(function () {
        Route::get('/',                  [NetworkController::class, 'index']);
        Route::post('/',                 [NetworkController::class, 'store']);
        Route::get('/{uuid}',            [NetworkController::class, 'show']);
        Route::post('/{uuid}/invite',    [NetworkController::class, 'invite']);
        Route::post('/{uuid}/leave',     [NetworkController::class, 'leave']);
        Route::post('/join/{token}',     [NetworkController::class, 'join']);
    });

    // ── Partner Offers Module ─────────────────────────────
    Route::prefix('partner-offers')->group(function () {
        Route::get('/',                     [PartnerOfferController::class, 'index']);
        Route::post('/',                    [PartnerOfferController::class, 'store']);
        Route::get('/available/{partnershipUuid}', [PartnerOfferController::class, 'availableForPartnership']);
        Route::get('/network/{networkUuid}', [PartnerOfferController::class, 'networkOffers']);
        Route::get('/{uuid}',              [PartnerOfferController::class, 'show']);
        Route::put('/{uuid}',              [PartnerOfferController::class, 'update']);
        Route::post('/{uuid}/toggle',      [PartnerOfferController::class, 'toggle']);
        Route::post('/{uuid}/attach',      [PartnerOfferController::class, 'attach']);
        Route::delete('/{uuid}/attach/{partnershipId}', [PartnerOfferController::class, 'detach']);
        Route::post('/{uuid}/publish',     [PartnerOfferController::class, 'publish']);
        Route::delete('/{uuid}/publish/{networkId}', [PartnerOfferController::class, 'unpublish']);
    });

    // ── Merchant Settings: Bill Offers ────────────────────
    Route::prefix('merchant/settings')->group(function () {
        Route::get('/bill-offers',  [MerchantSettingsController::class, 'getBillOffers']);
        Route::post('/bill-offers', [MerchantSettingsController::class, 'setBillOffers']);
    });

    // ── Event Triggers Module ─────────────────────────────
    Route::get('/event-constants', [EventConfigController::class, 'constants']);
    Route::prefix('event-sources')->group(function () {
        Route::get('/',             [EventConfigController::class, 'listSources']);
        Route::post('/',            [EventConfigController::class, 'createSource']);
        Route::put('/{uuid}',      [EventConfigController::class, 'updateSource']);
        Route::post('/{uuid}/toggle', [EventConfigController::class, 'toggleSource']);
        Route::delete('/{uuid}',   [EventConfigController::class, 'deleteSource']);
    });
    Route::prefix('event-triggers')->group(function () {
        Route::get('/',             [EventConfigController::class, 'listTriggers']);
        Route::post('/',            [EventConfigController::class, 'createTrigger']);
        Route::put('/{uuid}',      [EventConfigController::class, 'updateTrigger']);
        Route::post('/{uuid}/toggle', [EventConfigController::class, 'toggleTrigger']);
        Route::delete('/{uuid}',   [EventConfigController::class, 'deleteTrigger']);
        Route::post('/test',       [EventConfigController::class, 'testEvent']);
    });
    Route::prefix('event-log')->group(function () {
        Route::get('/',    [EventConfigController::class, 'eventLog']);
        Route::get('/{id}', [EventConfigController::class, 'eventLogDetail']);
    });

    // ── Customer Module (CSV upload + list) ─────────────
    Route::prefix('customers')->group(function () {
        Route::get('/',        [CustomerUploadController::class, 'index']);
        Route::post('/upload', [CustomerUploadController::class, 'upload']);
        Route::get('/stats',   [CustomerUploadController::class, 'stats']);
        Route::get('/uploads', [CustomerUploadController::class, 'uploadHistory']);
    });

    // ── Growth Module ─────────────────────────────────────
    Route::prefix('growth')->group(function () {
        Route::get('/health',                       [GrowthController::class, 'healthScores']);
        Route::get('/health/{partnershipUuid}',     [GrowthController::class, 'partnershipHealth']);
        Route::get('/referral/{partnershipUuid}',   [GrowthController::class, 'referralLink']);
        Route::post('/invite',                      [GrowthController::class, 'createInvite']);
        Route::get('/invite-stats',                 [GrowthController::class, 'inviteStats']);
        Route::get('/weekly-digest',                [GrowthController::class, 'weeklyDigest']);
        Route::get('/demand-index',                 [GrowthController::class, 'demandIndex']);
        Route::get('/seasonal-templates',           [GrowthController::class, 'seasonalTemplates']);
        Route::post('/profile',                     [GrowthController::class, 'updateProfile']);
    });

});

// ─────────────────────────────────────────────────────────────
// Super Admin authenticated routes
// ─────────────────────────────────────────────────────────────
Route::prefix('super-admin')
    ->middleware(['auth:sanctum', 'super_admin'])
    ->group(function () {

        Route::get('/auth/me',     [SuperAdminAuthController::class, 'me']);
        Route::post('/auth/logout', [SuperAdminAuthController::class, 'logout']);

        // ── Merchant management ────────────────────────────
        Route::prefix('merchants')->group(function () {
            Route::get('/',            [MerchantManagementController::class, 'index']);
            Route::post('/',           [MerchantManagementController::class, 'store']);
            Route::get('/dashboard',   [MerchantManagementController::class, 'dashboard']);
            Route::get('/{id}',        [MerchantManagementController::class, 'show']);
            Route::put('/{id}',        [MerchantManagementController::class, 'update']);
            Route::get('/{id}/ledger', [MerchantManagementController::class, 'creditLedger']);
        });

        // ── Brand registration review (self-registered brands) ─
        Route::prefix('brand-registrations')->group(function () {
            Route::get('/',              [BrandRegistrationReviewController::class, 'index']);
            Route::post('/{id}/approve', [BrandRegistrationReviewController::class, 'approve']);
            Route::post('/{id}/reject',  [BrandRegistrationReviewController::class, 'reject']);
        });

        // ── WhatsApp credit allocation ─────────────────────
        Route::post('/merchants/{merchantId}/credits', [CreditAllocationController::class, 'allocate']);

        // ── eWards integration requests ────────────────────
        Route::prefix('integration-requests')->group(function () {
            Route::get('/',           [IntegrationRequestController::class, 'index']);
            Route::get('/{id}',       [IntegrationRequestController::class, 'show']);
            Route::post('/{id}/approve', [IntegrationRequestController::class, 'approve']);
            Route::post('/{id}/reject',  [IntegrationRequestController::class, 'reject']);
        });

    });

// ─────────────────────────────────────────────────────────────
// Customer Portal (public OTP auth, rate limited)
// ─────────────────────────────────────────────────────────────
Route::prefix('customer')->middleware(['throttle:20,1'])->group(function () {
    Route::post('/send-otp',   [CustomerAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [CustomerAuthController::class, 'verifyOtp']);

    Route::middleware('customer_auth')->group(function () {
        Route::get('/rewards',  [CustomerRewardsController::class, 'rewards']);
        Route::get('/activity', [CustomerRewardsController::class, 'activity']);
    });
});
