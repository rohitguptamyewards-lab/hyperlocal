<!--
  DashboardView.vue — Analytics-first landing page.
  Purpose: Key metrics, monthly trends, per-partner breakdown, campaign stats.
  Owner module: Analytics
  Data sources:
    GET /api/analytics/summary (enhanced — includes trends, partner breakdown, campaigns)
    GET /api/discovery/suggestions
    GET /api/partnerships?status=2,3,6 (pending/paused inbox)
-->
<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth   = useAuthStore()
const router = useRouter()

// ── Types ────────────────────────────────────────────────

interface MonthlyTrend {
  month: string
  redemptions: number
  new_customers: number
  benefit_given: number
  revenue: number
}

interface PartnerBreakdown {
  partnership_uuid: string
  name: string
  new_customers: number
  existing_customers: number
  total_redemptions: number
  revenue: number
  benefit_given: number
  customers_sent: number
  roi_score: number | null
}

interface DeliveryStats {
  total_issued: number
  delivered: number
  sent: number
  failed: number
  pending: number
  no_whatsapp: number
  delivery_rate_percent: number
  fallback_sms_count: number
}

interface DeliveryFailure {
  customer_phone: string | null
  token: string
  delivery_status: string
  partnership_name: string
  issued_at: string
}

interface AnalyticsSummary {
  live_partnerships: number
  total_redemptions: number
  new_customers: number
  existing_customers: number
  reactivated_customers: number
  customers_sent: number
  total_benefit_given: number
  total_revenue_from_network: number
  total_loyalty_liability: number
  net_value: number
  campaigns_sent: number
  messages_delivered: number
  monthly_trend: MonthlyTrend[]
  partner_breakdown: PartnerBreakdown[]
}

interface PendingPartnership {
  id: string
  name: string
  status: number
  status_label: string
  participants?: { merchant_id: number; merchant_name: string | null; role: number }[]
  terms?: { per_bill_cap_percent: number | null; per_bill_cap_amount: number | null; monthly_cap_amount: number | null } | null
}

// ── State ────────────────────────────────────────────────

const summary = ref<AnalyticsSummary | null>(null)
const loading = ref(true)
const pendingRequests = ref<PendingPartnership[]>([])
const pausedPartnerships = ref<PendingPartnership[]>([])
const resumingId = ref<string | null>(null)

// ── Delivery status state ────────────────────────────────
const deliveryStats = ref<DeliveryStats | null>(null)
const deliveryFailures = ref<DeliveryFailure[]>([])
const deliveryLoading = ref(true)

// ── Chart helpers ────────────────────────────────────────

const trendMax = computed(() => {
  if (!summary.value?.monthly_trend.length) return 1
  return Math.max(...summary.value.monthly_trend.map(t => t.redemptions), 1)
})

// ── Mount ────────────────────────────────────────────────

onMounted(async () => {
  if (!auth.user) await auth.fetchMe()
  await Promise.allSettled([fetchSummary(), fetchPending(), fetchPaused(), fetchDeliveryStats()])
})

async function fetchSummary() {
  try {
    const { data } = await api.get<AnalyticsSummary>('/analytics/summary')
    summary.value = data
  } finally {
    loading.value = false
  }
}

async function fetchPending() {
  const myId = auth.user?.merchant_id
  try {
    const [r2, r3] = await Promise.all([
      api.get<{ data: PendingPartnership[] }>('/partnerships', { params: { status: 2 } }),
      api.get<{ data: PendingPartnership[] }>('/partnerships', { params: { status: 3 } }),
    ])
    pendingRequests.value = [...r2.data.data, ...r3.data.data].filter(p =>
      p.participants?.some(pt => pt.role === 2 && pt.merchant_id === myId)
    )
  } catch { /* non-critical */ }
}

async function fetchPaused() {
  const myId = auth.user?.merchant_id
  try {
    const { data } = await api.get<{ data: PendingPartnership[] }>('/partnerships', { params: { status: 6 } })
    pausedPartnerships.value = data.data.filter(p =>
      p.participants?.some(pt => pt.merchant_id === myId)
    )
  } catch { /* non-critical */ }
}

async function fetchDeliveryStats() {
  try {
    const [statsRes, failuresRes] = await Promise.all([
      api.get<DeliveryStats>('/delivery/stats'),
      api.get<DeliveryFailure[]>('/delivery/failures'),
    ])
    deliveryStats.value   = statsRes.data
    deliveryFailures.value = failuresRes.data
  } catch { /* non-critical */ }
  finally { deliveryLoading.value = false }
}

async function resumePartnership(id: string) {
  resumingId.value = id
  try { await api.post(`/partnerships/${id}/resume`); await fetchPaused() }
  catch { /* */ }
  finally { resumingId.value = null }
}

function currency(val: number): string {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val)
}

function deliveryStatusBadge(status: string): { label: string; classes: string } {
  const map: Record<string, { label: string; classes: string }> = {
    delivered:    { label: 'Delivered',    classes: 'bg-emerald-100 text-emerald-700' },
    read:         { label: 'Read',         classes: 'bg-emerald-100 text-emerald-700' },
    sent:         { label: 'Sent',         classes: 'bg-blue-100 text-blue-700' },
    pending:      { label: 'Pending',      classes: 'bg-yellow-100 text-yellow-700' },
    failed:       { label: 'Failed',       classes: 'bg-red-100 text-red-700' },
    no_whatsapp:  { label: 'No WhatsApp',  classes: 'bg-gray-100 text-gray-600' },
  }
  return map[status] ?? { label: status, classes: 'bg-gray-100 text-gray-500' }
}

function shortMonth(dateStr: string): string {
  const d = new Date(dateStr)
  return d.toLocaleString('en-IN', { month: 'short' })
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-6xl">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-xl lg:text-2xl font-bold text-gray-900">
        Welcome, {{ auth.user?.name ?? '…' }}
      </h1>
      <p class="text-sm text-gray-400">{{ auth.user?.email }}</p>
    </div>

    <!-- Pending requests alert -->
    <div v-if="pendingRequests.length > 0" class="mb-6">
      <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-sm font-semibold text-amber-800">
            {{ pendingRequests.length }} partnership request{{ pendingRequests.length > 1 ? 's' : '' }} waiting
          </span>
          <span class="text-xs bg-amber-200 text-amber-800 px-2 py-0.5 rounded-full font-medium">Action needed</span>
        </div>
        <div v-for="p in pendingRequests" :key="p.id" class="bg-white rounded-lg border border-amber-100 px-4 py-3 flex items-center justify-between gap-4 mb-2">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ p.name }}</p>
            <p class="text-xs text-gray-400">From {{ p.participants?.find(pt => pt.role === 1)?.merchant_name ?? 'a partner' }}</p>
          </div>
          <button @click="router.push(`/partnerships/${p.id}`)" class="text-xs bg-amber-500 text-white px-3 py-1.5 rounded-lg hover:bg-amber-600">Review →</button>
        </div>
      </div>
    </div>

    <!-- ═══ KEY METRICS (4 cards) ═══ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">New Customers</p>
        <p class="text-3xl font-bold text-indigo-600 mt-1">{{ loading ? '…' : (summary?.new_customers ?? 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">via partnership network</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Network Revenue</p>
        <p class="text-3xl font-bold mt-1" style="color:#34d399;">{{ loading ? '…' : currency(summary?.total_revenue_from_network ?? 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">from partner-referred customers</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Benefit Given</p>
        <p class="text-3xl font-bold text-gray-500 mt-1">{{ loading ? '…' : currency(summary?.total_benefit_given ?? 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">loyalty points redeemed at partner stores</p>
      </div>
      <div class="bg-white rounded-xl border p-5" style="border-color:#d1fae5; background:#f0fdf4;">
        <p class="text-xs font-medium uppercase tracking-wider" style="color:#34d399;">Net Value</p>
        <p class="text-3xl font-bold mt-1" style="color:#059669;">{{ loading ? '…' : currency(summary?.net_value ?? 0) }}</p>
        <p class="text-xs mt-1" style="color:#6ee7b7;">revenue − benefit cost</p>
      </div>
    </div>

    <!-- ═══ MONTHLY TREND CHART ═══ -->
    <div v-if="summary?.monthly_trend?.length" class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Monthly Trend</h2>
      <div class="flex items-end gap-2 h-40">
        <div
          v-for="(m, i) in summary.monthly_trend"
          :key="i"
          class="flex-1 flex flex-col items-center"
        >
          <!-- Bar -->
          <div class="w-full flex gap-1 items-end" style="height: 120px;">
            <!-- Redemptions bar -->
            <div
              class="flex-1 rounded-t-md"
              style="background: #6366F1;"
              :style="{ height: Math.max(4, (m.redemptions / trendMax) * 120) + 'px' }"
              :title="`${m.redemptions} redemptions`"
            />
            <!-- New customers bar -->
            <div
              class="flex-1 rounded-t-md"
              style="background: #818CF8;"
              :style="{ height: Math.max(4, (m.new_customers / trendMax) * 120) + 'px' }"
              :title="`${m.new_customers} new customers`"
            />
          </div>
          <!-- Label -->
          <p class="text-xs text-gray-400 mt-2">{{ shortMonth(m.month) }}</p>
        </div>
      </div>
      <div class="flex gap-4 mt-3 text-xs text-gray-400">
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#6366F1;" /> Redemptions</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#818CF8;" /> New Customers</span>
      </div>
    </div>

    <!-- ═══ CUSTOMER FLOW ═══ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <p class="text-2xl font-bold" style="color:#4F46E5;">{{ summary?.new_customers ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-1">New customers</p>
        <p class="text-xs text-gray-300">First-time visitors via network</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <p class="text-2xl font-bold" style="color:#6366F1;">{{ summary?.existing_customers ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-1">Existing customers</p>
        <p class="text-xs text-gray-300">Already yours — came back via network</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
        <p class="text-2xl font-bold" style="color:#818CF8;">{{ summary?.reactivated_customers ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-1">Reactivated</p>
        <p class="text-xs text-gray-300">Lapsed customers who returned</p>
      </div>
    </div>

    <!-- ═══ PER-PARTNER TABLE ═══ -->
    <div v-if="summary?.partner_breakdown?.length" class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Partnership Performance</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pr-4">Partner</th>
              <th class="pb-3 pr-4">New</th>
              <th class="pb-3 pr-4">Existing</th>
              <th class="pb-3 pr-4">Revenue</th>
              <th class="pb-3 pr-4">Cost</th>
              <th class="pb-3 pr-4">ROI</th>
              <th class="pb-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in summary.partner_breakdown"
              :key="p.partnership_uuid"
              class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer"
              @click="router.push(`/partnerships/${p.partnership_uuid}`)"
            >
              <td class="py-3 pr-4 font-medium text-gray-900">{{ p.name }}</td>
              <td class="py-3 pr-4 text-indigo-600 font-semibold">{{ p.new_customers }}</td>
              <td class="py-3 pr-4 text-gray-500">{{ p.existing_customers }}</td>
              <td class="py-3 pr-4 font-medium" style="color:#059669;">{{ currency(p.revenue) }}</td>
              <td class="py-3 pr-4 text-gray-400">{{ currency(p.benefit_given) }}</td>
              <td class="py-3 pr-4">
                <span
                  v-if="p.roi_score !== null"
                  class="text-xs px-2 py-0.5 rounded-full font-medium"
                  :class="p.roi_score >= 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                >
                  {{ p.roi_score }}x
                </span>
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
              <td class="py-3 text-right">
                <span class="text-xs text-indigo-500 hover:text-indigo-700">View →</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CAMPAIGNS + SENT METRICS ═══ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Campaigns Sent</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ summary?.campaigns_sent ?? 0 }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Messages Delivered</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ summary?.messages_delivered ?? 0 }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Customers Sent</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ summary?.customers_sent ?? 0 }}</p>
        <p class="text-xs text-gray-300 mt-1">customers you sent to partners</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Live Partnerships</p>
        <p class="text-2xl font-bold text-indigo-600 mt-1">{{ summary?.live_partnerships ?? 0 }}</p>
      </div>
    </div>

    <!-- ═══ TOKEN DELIVERY STATUS ═══ -->
    <div class="mb-8">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Token Delivery Status</h2>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Issued -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Issued</p>
          <p class="text-3xl font-bold text-gray-800 mt-1">
            {{ deliveryLoading ? '…' : (deliveryStats?.total_issued ?? 0) }}
          </p>
          <p class="text-xs text-gray-400 mt-1">Total tokens sent</p>
        </div>
        <!-- Delivered -->
        <div class="bg-white rounded-xl border border-emerald-200 p-5" style="background:#f0fdf4;">
          <p class="text-xs font-medium uppercase tracking-wider" style="color:#34d399;">Delivered</p>
          <p class="text-3xl font-bold mt-1" style="color:#059669;">
            {{ deliveryLoading ? '…' : (deliveryStats?.delivered ?? 0) }}
          </p>
          <p class="text-xs mt-1" style="color:#6ee7b7;">
            {{ deliveryLoading ? '' : deliveryStats?.delivery_rate_percent ?? 0 }}% rate
          </p>
        </div>
        <!-- Failed -->
        <div class="bg-white rounded-xl border border-red-100 p-5" style="background:#fff5f5;">
          <p class="text-xs font-medium text-red-400 uppercase tracking-wider">Failed</p>
          <p class="text-3xl font-bold text-red-600 mt-1">
            {{ deliveryLoading ? '…' : (deliveryStats?.failed ?? 0) }}
          </p>
          <p class="text-xs text-red-300 mt-1">
            +{{ deliveryLoading ? 0 : (deliveryStats?.no_whatsapp ?? 0) }} no WhatsApp
          </p>
        </div>
        <!-- Pending -->
        <div class="bg-white rounded-xl border border-yellow-100 p-5" style="background:#fffbeb;">
          <p class="text-xs font-medium text-yellow-600 uppercase tracking-wider">Pending</p>
          <p class="text-3xl font-bold text-yellow-600 mt-1">
            {{ deliveryLoading ? '…' : (deliveryStats?.pending ?? 0) }}
          </p>
          <p class="text-xs text-yellow-400 mt-1">
            {{ deliveryLoading ? '' : (deliveryStats?.fallback_sms_count ?? 0) }} SMS fallback sent
          </p>
        </div>
      </div>

      <!-- Recent failures table -->
      <div v-if="!deliveryLoading && deliveryFailures.length > 0" class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Recent Delivery Failures</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-100">
                <th class="pb-3 pr-4">Phone</th>
                <th class="pb-3 pr-4">Token</th>
                <th class="pb-3 pr-4">Partnership</th>
                <th class="pb-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="f in deliveryFailures"
                :key="f.token"
                class="border-b border-gray-50 last:border-0"
              >
                <td class="py-2.5 pr-4 text-gray-700 font-mono text-xs">
                  {{ f.customer_phone ?? '—' }}
                </td>
                <td class="py-2.5 pr-4 font-mono text-xs text-indigo-600 font-semibold">
                  {{ f.token }}
                </td>
                <td class="py-2.5 pr-4 text-gray-500 text-xs truncate max-w-[160px]">
                  {{ f.partnership_name }}
                </td>
                <td class="py-2.5">
                  <span
                    class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="deliveryStatusBadge(f.delivery_status).classes"
                  >
                    {{ deliveryStatusBadge(f.delivery_status).label }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Empty state when no failures -->
      <div
        v-else-if="!deliveryLoading && deliveryFailures.length === 0 && (deliveryStats?.total_issued ?? 0) > 0"
        class="bg-white rounded-xl border border-gray-200 px-6 py-8 text-center"
      >
        <p class="text-sm text-emerald-600 font-medium">No delivery failures</p>
        <p class="text-xs text-gray-400 mt-1">All tokens delivered successfully</p>
      </div>
    </div>

    <!-- Quick links -->
    <div class="flex gap-3 mb-8">
      <RouterLink to="/partnerships" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">View partnerships</RouterLink>
      <RouterLink to="/find-partners" class="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50">Find partners</RouterLink>
      <RouterLink to="/campaigns" class="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50">Campaigns</RouterLink>
    </div>

    <!-- Paused partnerships -->
    <div v-if="pausedPartnerships.length > 0" class="mb-8">
      <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-5 py-4">
        <span class="text-sm font-semibold text-yellow-800 mb-3 block">
          {{ pausedPartnerships.length }} paused partnership{{ pausedPartnerships.length > 1 ? 's' : '' }}
        </span>
        <div v-for="p in pausedPartnerships" :key="p.id" class="bg-white rounded-lg border border-yellow-100 px-4 py-3 flex items-center justify-between gap-4 mb-2">
          <p class="text-sm font-medium text-gray-900 truncate">{{ p.name }}</p>
          <div class="flex gap-2">
            <button @click="resumePartnership(p.id)" :disabled="resumingId === p.id" class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ resumingId === p.id ? '…' : 'Resume' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
