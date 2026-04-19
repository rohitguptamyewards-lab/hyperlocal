/**
 * Router — all application routes.
 * Purpose: Maps URL paths to view components, enforces auth guard.
 * Owner module: Core
 */
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSuperAdminAuthStore } from '@/stores/superAdminAuth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Public ──────────────────────────────────────────────
    {
      path: '/login',
      name: 'login',
      component: () => import('@/modules/admin/views/LoginView.vue'),
      meta: { public: true },
    },

    // ── Brand self-registration (public) ──────────────────
    {
      path: '/register',
      name: 'brand-register',
      component: () => import('@/modules/registration/views/BrandRegistrationView.vue'),
      meta: { public: true },
    },

    // ── Pending / rejected approval screen (auth required) ────────────
    {
      path: '/pending-approval',
      name: 'pending-approval',
      component: () => import('@/modules/registration/views/PendingApprovalView.vue'),
      meta: { requiresAuth: true },
    },

    // ── Bill Offers (public, no auth — linked from digital bill) ──
    {
      path: '/bill-offers/:merchantUuid',
      name: 'bill-offers',
      component: () => import('@/modules/partnerOffers/views/BillOffersPublicView.vue'),
      meta: { public: true },
    },

    // ── Public: Brand profile + Marketplace + Referral ───────
    {
      path: '/b/:slug',
      name: 'brand-profile',
      component: () => import('@/modules/growth/views/BrandProfilePublicView.vue'),
      meta: { public: true },
    },
    {
      path: '/marketplace',
      name: 'marketplace',
      component: () => import('@/modules/growth/views/MarketplaceView.vue'),
      meta: { public: true },
    },

    // ── Customer Rewards Portal (phone + OTP auth) ──────────
    {
      path: '/my-rewards',
      name: 'customer-login',
      component: () => import('@/modules/customer/views/CustomerLoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/my-rewards/dashboard',
      name: 'customer-rewards',
      component: () => import('@/modules/customer/views/CustomerRewardsView.vue'),
      meta: { public: true }, // auth is handled by the customer middleware, not Vue router
    },

    // ── Public: customer QR claim landing ───────────────────
    {
      path: '/claim/:uuid',
      name: 'claim-landing',
      component: () => import('@/modules/claim/views/ClaimLandingView.vue'),
      meta: { public: true },
    },

    // ── GAP 10: Shareable link landing ───────────────────────
    {
      path: '/shared/:code',
      name: 'shared-claim',
      component: () => import('@/modules/redemption/views/SharedClaimLandingView.vue'),
      meta: { public: true },
    },

    // ── Super Admin (separate SPA, own auth guard) ───────────
    {
      path: '/super-admin/login',
      name: 'sa-login',
      component: () => import('@/modules/superAdmin/views/SuperAdminLoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/super-admin',
      component: () => import('@/modules/superAdmin/SuperAdminLayout.vue'),
      meta: { requiresSuperAdmin: true },
      children: [
        { path: '', redirect: '/super-admin/dashboard' },
        {
          path: 'dashboard',
          name: 'sa-dashboard',
          component: () => import('@/modules/superAdmin/views/SuperAdminDashboardView.vue'),
        },
        {
          path: 'merchants',
          name: 'sa-merchants',
          component: () => import('@/modules/superAdmin/views/MerchantListView.vue'),
        },
        {
          path: 'merchants/:id',
          name: 'sa-merchant-detail',
          component: () => import('@/modules/superAdmin/views/MerchantDetailView.vue'),
        },
        {
          path: 'brand-registrations',
          name: 'sa-brand-registrations',
          component: () => import('@/modules/superAdmin/views/BrandRegistrationsView.vue'),
        },
        {
          path: 'requests',
          name: 'sa-requests',
          component: () => import('@/modules/superAdmin/views/IntegrationRequestsView.vue'),
        },
      ],
    },

    // ── Public: network join via token (public so unauthenticated brands can see the landing page)
    {
      path: '/networks/join/:token',
      name: 'network-join',
      component: () => import('@/modules/network/views/NetworkJoinView.vue'),
      meta: { public: true },
    },

    // ── Authenticated (inside AppLayout) ────────────────────
    {
      path: '/',
      component: () => import('@/components/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: '/dashboard',
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/modules/analytics/views/DashboardView.vue'),
        },
        {
          path: 'partnerships',
          name: 'partnerships',
          component: () => import('@/modules/partnership/views/PartnershipListView.vue'),
        },
        {
          path: 'partnerships/:uuid',
          name: 'partnership-detail',
          component: () => import('@/modules/partnership/views/PartnershipDetailView.vue'),
        },
        {
          path: 'partnerships/:uuid/analytics',
          name: 'partnership-analytics',
          component: () => import('@/modules/analytics/views/PartnershipAnalyticsView.vue'),
        },
        {
          path: 'partnerships/:uuid/redemptions',
          name: 'partnership-redemptions',
          component: () => import('@/modules/partnership/views/PartnershipRedemptionsView.vue'),
        },
        {
          path: 'partnerships/:uuid/ledger',
          name: 'partnership-ledger',
          component: () => import('@/modules/ledger/views/PartnershipLedgerView.vue'),
        },
        {
          path: 'redeem',
          name: 'redeem-token',
          component: () => import('@/modules/redemption/views/RedeemTokenView.vue'),
        },
        {
          path: 'find-partners',
          name: 'find-partners',
          component: () => import('@/modules/discovery/views/FindPartnersView.vue'),
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/modules/merchantSettings/views/MerchantSettingsView.vue'),
        },
        {
          path: 'settings/reminders',
          name: 'reminder-settings',
          component: () => import('@/modules/settings/views/ReminderSettingsView.vue'),
        },
        {
          path: 'campaigns',
          name: 'campaigns',
          component: () => import('@/modules/campaign/views/CampaignView.vue'),
        },
        {
          path: 'partner-offers',
          name: 'partner-offers',
          component: () => import('@/modules/partnerOffers/views/PartnerOfferListView.vue'),
        },
        {
          path: 'partner-offers/create',
          name: 'partner-offer-create',
          component: () => import('@/modules/partnerOffers/views/PartnerOfferFormView.vue'),
        },
        {
          path: 'partner-offers/:uuid',
          name: 'partner-offer-detail',
          component: () => import('@/modules/partnerOffers/views/PartnerOfferDetailView.vue'),
        },
        {
          path: 'partner-offers/:uuid/edit',
          name: 'partner-offer-edit',
          component: () => import('@/modules/partnerOffers/views/PartnerOfferFormView.vue'),
        },
        {
          path: 'growth',
          name: 'growth',
          component: () => import('@/modules/growth/views/GrowthInsightsView.vue'),
        },
        {
          path: 'event-sources',
          name: 'event-sources',
          component: () => import('@/modules/eventTriggers/views/EventSourceListView.vue'),
        },
        {
          path: 'event-sources/setup',
          name: 'event-source-setup',
          component: () => import('@/modules/eventTriggers/views/EventSourceSetupView.vue'),
        },
        {
          path: 'event-triggers',
          name: 'event-triggers',
          component: () => import('@/modules/eventTriggers/views/EventTriggerListView.vue'),
        },
        {
          path: 'event-triggers/create',
          name: 'event-trigger-create',
          component: () => import('@/modules/eventTriggers/views/EventTriggerFormView.vue'),
        },
        {
          path: 'event-triggers/:uuid/edit',
          name: 'event-trigger-edit',
          component: () => import('@/modules/eventTriggers/views/EventTriggerFormView.vue'),
        },
        {
          path: 'customers',
          name: 'customers',
          component: () => import('@/modules/customers/views/CustomerListView.vue'),
        },
        {
          path: 'networks',
          name: 'networks',
          component: () => import('@/modules/network/views/NetworkListView.vue'),
        },
        {
          path: 'networks/:uuid',
          name: 'network-detail',
          component: () => import('@/modules/network/views/NetworkDetailView.vue'),
        },
        {
          path: 'followup-campaigns',
          name: 'followup-campaigns',
          component: () => import('@/modules/campaigns/views/FollowupCampaignView.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const saAuth = useSuperAdminAuthStore()

  if (to.meta.public) return

  // Super admin routes
  if (to.meta.requiresSuperAdmin) {
    if (!saAuth.token) {
      return { name: 'sa-login' }
    }
    if (!saAuth.admin) {
      try {
        await saAuth.fetchMe()
      } catch {
        // Clear stale token gracefully and redirect
        localStorage.removeItem('sa_token')
        saAuth.token = null
        saAuth.admin = null
        return { name: 'sa-login' }
      }
    }
    return
  }

  // Merchant auth — skip guard for login/public pages
  if (to.name === 'login') return

  if (!auth.token) {
    return { name: 'login' }
  }

  if (!auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      // Clear stale token gracefully — don't let the 401 interceptor
      // race with this redirect
      localStorage.removeItem('token')
      auth.token = null
      auth.user = null
      return { name: 'login' }
    }
  }

  // Block unapproved / rejected merchants — redirect to holding page
  const regStatus = auth.user?.merchant?.registration_status
  const isPending  = regStatus === 'pending' || regStatus === 'rejected'
  if (isPending && to.name !== 'pending-approval') {
    return { name: 'pending-approval' }
  }
  // Approved merchant trying to access pending page — send to dashboard
  if (!isPending && to.name === 'pending-approval') {
    return { name: 'dashboard' }
  }
})

export default router
