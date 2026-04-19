<?php

namespace App\Providers;

use App\Events\MerchantEcosystemExit;
use App\Events\PartnershipCapExhausted;
use App\Events\PartnershipLive;
use App\Events\RedemptionExecuted;
use App\Modules\Analytics\Listeners\RecordFirstVisitAttribution;
use App\Modules\Analytics\Services\RetentionService;
use App\Modules\Analytics\Services\RoiService;
use App\Modules\Campaign\Services\CampaignService;
use App\Modules\CustomerActivation\Services\ClaimService;
use App\Modules\CustomerActivation\Services\WhatsAppNotifier;
use App\Modules\Discovery\Services\FitScoringService;
use App\Modules\Discovery\Services\RecommendationService;
use App\Modules\Enablement\Listeners\CreateEnablementRowsOnPartnershipLive;
use App\Modules\Enablement\Listeners\UpdateLastUsedAtOnRedemption;
use App\Modules\Enablement\Services\DormancyService;
use App\Modules\EwardsRequest\Services\EwardsRequestService;
use App\Modules\Execution\Services\ApprovalService;
use App\Modules\Execution\Services\RedemptionService;
use App\Modules\IntegrationHub\Services\IntegrationResolverService;
use App\Modules\Ledger\Listeners\CreateLedgerEntryOnRedemption;
use App\Modules\Ledger\Services\StatementService;
use App\Modules\LoyaltyBridge\Services\LoyaltyBridgeService;
use App\Modules\Member\Services\MemberService;
use App\Modules\Network\Services\NetworkService;
use App\Modules\CustomerPortal\Services\OtpService;
use App\Modules\PartnerOffers\Services\PartnerOfferService;
use App\Modules\PartnerOffers\Services\BillOffersService;
use App\Modules\EventTriggers\Services\EventIngestionService;
use App\Modules\EventTriggers\Services\IdentityResolverService;
use App\Modules\EventTriggers\Services\TriggerEngineService;
use App\Modules\EventTriggers\Services\ActionExecutorService;
use App\Modules\Growth\Services\PartnershipHealthService;
use App\Modules\Growth\Services\ReferralService;
use App\Modules\Growth\Services\WeeklyDigestService;
use App\Modules\Growth\Services\DemandIndexService;
use App\Modules\Partnership\Listeners\AutoCloseOnEcosystemExit;
use App\Modules\Partnership\Listeners\AutoPauseOnCapExhausted;
use App\Modules\Partnership\Models\Partnership;
use App\Modules\Partnership\Policies\PartnershipPolicy;
use App\Modules\Partnership\Services\PartnershipService;
use App\Modules\RulesEngine\Services\CapEnforcementService;
use App\Modules\RulesEngine\Services\CustomerClassifier;
use App\Modules\RulesEngine\Services\RulesEngineService;
use App\Modules\SuperAdmin\Services\SuperAdminService;
use App\Modules\Webhook\Services\WebhookSignatureService;
use App\Modules\WhatsAppCredit\Events\LowWhatsAppCreditEvent;
use App\Modules\WhatsAppCredit\Listeners\NotifyMerchantOnLowCredit;
use App\Modules\WhatsAppCredit\Listeners\NotifySuperAdminOnLowCredit;
use App\Modules\WhatsAppCredit\Services\WhatsAppCreditService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RulesEngineService::class, function (): RulesEngineService {
            return new RulesEngineService(
                new CustomerClassifier(),
                new CapEnforcementService(),
            );
        });

        $this->app->singleton(ApprovalService::class, fn () => new ApprovalService());

        $this->app->singleton(RedemptionService::class, function ($app): RedemptionService {
            return new RedemptionService(
                $app->make(RulesEngineService::class),
                new CapEnforcementService(),
                $app->make(ApprovalService::class),
            );
        });

        // ── Ledger ─────────────────────────────────────────────
        $this->app->singleton(StatementService::class, fn () => new StatementService());

        // ── Analytics ──────────────────────────────────────────
        $this->app->singleton(RetentionService::class, fn () => new RetentionService());
        $this->app->singleton(RoiService::class, fn () => new RoiService());

        // ── Partnership services ────────────────────────────────
        $this->app->singleton(PartnershipService::class, fn () => new PartnershipService());

        // ── Discovery ──────────────────────────────────────────
        $this->app->singleton(FitScoringService::class, fn () => new FitScoringService());
        $this->app->singleton(RecommendationService::class, fn ($app) => new RecommendationService(
            $app->make(FitScoringService::class),
        ));

        // ── Enablement ─────────────────────────────────────────
        $this->app->singleton(DormancyService::class, fn () => new DormancyService());

        // ── Member ─────────────────────────────────────────────
        $this->app->singleton(MemberService::class, fn () => new MemberService());

        // ── IntegrationHub ─────────────────────────────────────
        $this->app->singleton(IntegrationResolverService::class, fn () => new IntegrationResolverService());

        // ── LoyaltyBridge ──────────────────────────────────────
        $this->app->singleton(LoyaltyBridgeService::class, fn ($app) => new LoyaltyBridgeService(
            $app->make(IntegrationResolverService::class),
        ));

        // ── Campaign ───────────────────────────────────────────
        $this->app->singleton(CampaignService::class, fn () => new CampaignService());

        // ── WhatsApp Credit ────────────────────────────────────
        $this->app->singleton(WhatsAppCreditService::class, fn () => new WhatsAppCreditService());

        // ── WhatsAppNotifier (injectable, needs WhatsAppCreditService) ──
        $this->app->singleton(WhatsAppNotifier::class, fn ($app) => new WhatsAppNotifier(
            $app->make(WhatsAppCreditService::class),
        ));

        // ── ClaimService (needs MemberService + WhatsAppNotifier) ──
        $this->app->singleton(ClaimService::class, fn ($app) => new ClaimService(
            $app->make(MemberService::class),
            $app->make(WhatsAppNotifier::class),
        ));

        // ── eWards Request ─────────────────────────────────────
        $this->app->singleton(EwardsRequestService::class, fn () => new EwardsRequestService());

        // ── SuperAdmin ─────────────────────────────────────────
        $this->app->singleton(SuperAdminService::class, fn () => new SuperAdminService());

        // ── Webhook ────────────────────────────────────────────
        $this->app->singleton(WebhookSignatureService::class, fn () => new WebhookSignatureService());

        // ── Network ────────────────────────────────────────────
        $this->app->singleton(NetworkService::class, fn () => new NetworkService());

        // ── CustomerPortal ────────────────────────────────────
        $this->app->singleton(OtpService::class, fn () => new OtpService());

        // ── PartnerOffers ─────────────────────────────────────
        $this->app->singleton(PartnerOfferService::class, fn () => new PartnerOfferService());
        $this->app->singleton(BillOffersService::class, fn () => new BillOffersService());

        // ── EventTriggers ─────────────────────────────────────
        $this->app->singleton(EventIngestionService::class, fn () => new EventIngestionService());
        $this->app->singleton(TriggerEngineService::class, fn () => new TriggerEngineService());
        $this->app->singleton(ActionExecutorService::class, fn () => new ActionExecutorService());
        $this->app->singleton(IdentityResolverService::class, fn ($app) => new IdentityResolverService(
            $app->make(\App\Modules\Member\Services\MemberService::class),
        ));

        // ── Growth ────────────────────────────────────────────
        $this->app->singleton(PartnershipHealthService::class, fn () => new PartnershipHealthService());
        $this->app->singleton(ReferralService::class, fn () => new ReferralService());
        $this->app->singleton(WeeklyDigestService::class, fn () => new WeeklyDigestService());
        $this->app->singleton(DemandIndexService::class, fn () => new DemandIndexService());
    }

    public function boot(): void
    {
        // ── Partnership Module policies ─────────────────────────
        Gate::policy(Partnership::class, PartnershipPolicy::class);

        // ── Event → Listener wiring ─────────────────────────────
        // RedemptionExecuted: Ledger + Analytics both consume this
        Event::listen(
            RedemptionExecuted::class,
            CreateLedgerEntryOnRedemption::class,
        );
        Event::listen(
            RedemptionExecuted::class,
            RecordFirstVisitAttribution::class,
        );

        // MerchantEcosystemExit: auto-close all LIVE/PAUSED partnerships (E-001)
        Event::listen(
            MerchantEcosystemExit::class,
            AutoCloseOnEcosystemExit::class,
        );

        // PartnershipCapExhausted: auto-pause the partnership
        Event::listen(
            PartnershipCapExhausted::class,
            AutoPauseOnCapExhausted::class,
        );

        // PartnershipLive: create enablement rows per outlet
        Event::listen(
            PartnershipLive::class,
            CreateEnablementRowsOnPartnershipLive::class,
        );

        // RedemptionExecuted: update last_used_at for enablement tracking
        Event::listen(
            RedemptionExecuted::class,
            UpdateLastUsedAtOnRedemption::class,
        );

        // LowWhatsAppCreditEvent: notify merchant admin + super admin
        Event::listen(LowWhatsAppCreditEvent::class, NotifyMerchantOnLowCredit::class);
        Event::listen(LowWhatsAppCreditEvent::class, NotifySuperAdminOnLowCredit::class);
    }
}
