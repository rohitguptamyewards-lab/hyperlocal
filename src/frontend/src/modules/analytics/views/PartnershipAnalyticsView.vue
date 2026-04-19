<!--
  PartnershipAnalyticsView.vue — Per-partnership deep dive.
  Purpose: Customer breakdown, revenue vs cost, retention funnel, monthly trend, claim conversion.
  Owner module: Analytics
  API: GET /api/analytics/partnerships/:uuid (enhanced with revenue, trends, conversion)
-->
<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const uuid   = route.params.uuid as string

interface MonthlyTrend {
  month: string
  redemptions: number
  new_customers: number
  benefit_given: number
  revenue: number
}

interface Analytics {
  total_redemptions: number
  new_customers: number
  existing_customers: number
  reactivated_customers: number
  customers_sent: number
  total_benefit_given: number
  total_referral_credits: number
  total_revenue: number
  avg_bill_amount: number | null
  cost_per_customer: number | null
  claim_conversion_rate: number | null
  retained_30d_count: number
  retained_60d_count: number
  retained_90d_count: number
  retention_30d_rate: number | null
  retention_60d_rate: number | null
  retention_90d_rate: number | null
  roi_score: number | null
  monthly_trend: MonthlyTrend[]
}

const data    = ref<Analytics | null>(null)
const loading = ref(true)
const error   = ref<string | null>(null)

onMounted(async () => {
  try {
    const res = await api.get<{ analytics: Analytics }>(`/analytics/partnerships/${uuid}`)
    data.value = res.data.analytics
  } catch {
    error.value = 'Failed to load analytics.'
  } finally {
    loading.value = false
  }
})

// Chart helpers
const trendMax = computed(() => {
  if (!data.value?.monthly_trend?.length) return 1
  return Math.max(...data.value.monthly_trend.map(t => t.redemptions), 1)
})

const totalCustomers = computed(() => {
  if (!data.value) return 1
  return Math.max(data.value.new_customers + data.value.existing_customers + data.value.reactivated_customers, 1)
})

function currency(val: number): string {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val)
}

function pct(val: number | null): string {
  return val === null ? '—' : val.toFixed(1) + '%'
}

function shortMonth(dateStr: string): string {
  return new Date(dateStr).toLocaleString('en-IN', { month: 'short' })
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-5xl">
    <button @click="router.back()" class="text-sm text-gray-400 hover:text-gray-600 mb-6 flex items-center gap-1">← Back</button>

    <h1 class="text-xl font-bold text-gray-900 mb-1">Partnership Analytics</h1>
    <p class="text-sm text-gray-400 mb-8">Deep dive into this partnership's performance.</p>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>
    <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>

    <template v-else-if="data">

      <!-- ═══ KEY METRICS (4 cards) ═══ -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">New Customers</p>
          <p class="text-3xl font-bold text-indigo-600 mt-1">{{ data.new_customers }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Revenue</p>
          <p class="text-3xl font-bold mt-1" style="color:#059669;">{{ currency(data.total_revenue) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Benefit Given</p>
          <p class="text-3xl font-bold text-gray-500 mt-1">{{ currency(data.total_benefit_given) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-indigo-200 bg-indigo-50 p-5">
          <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">ROI</p>
          <p class="text-3xl font-bold text-indigo-700 mt-1">{{ data.roi_score !== null ? data.roi_score.toFixed(1) + '×' : '—' }}</p>
        </div>
      </div>

      <!-- ═══ CUSTOMER BREAKDOWN (horizontal bar) ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Customer Breakdown</h2>
        <!-- Stacked bar -->
        <div class="flex rounded-lg overflow-hidden h-8 mb-3">
          <div
            :style="{ width: (data.new_customers / totalCustomers * 100) + '%' }"
            style="background: #4F46E5;"
            class="flex items-center justify-center text-xs text-white font-medium"
          >
            <span v-if="data.new_customers / totalCustomers > 0.15">{{ data.new_customers }}</span>
          </div>
          <div
            :style="{ width: (data.existing_customers / totalCustomers * 100) + '%' }"
            style="background: #6366F1;"
            class="flex items-center justify-center text-xs text-white font-medium"
          >
            <span v-if="data.existing_customers / totalCustomers > 0.15">{{ data.existing_customers }}</span>
          </div>
          <div
            :style="{ width: (data.reactivated_customers / totalCustomers * 100) + '%' }"
            style="background: #A5B4FC;"
            class="flex items-center justify-center text-xs text-white font-medium"
          >
            <span v-if="data.reactivated_customers / totalCustomers > 0.15">{{ data.reactivated_customers }}</span>
          </div>
        </div>
        <div class="flex gap-5 text-xs text-gray-400">
          <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background:#4F46E5;" /> New ({{ data.new_customers }})</span>
          <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background:#6366F1;" /> Existing ({{ data.existing_customers }})</span>
          <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background:#A5B4FC;" /> Reactivated ({{ data.reactivated_customers }})</span>
        </div>
      </div>

      <!-- ═══ MONTHLY TREND ═══ -->
      <div v-if="data.monthly_trend?.length" class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Monthly Trend</h2>
        <div class="flex items-end gap-2 h-32">
          <div v-for="(m, i) in data.monthly_trend" :key="i" class="flex-1 flex flex-col items-center">
            <div class="w-full flex gap-0.5 items-end" style="height: 100px;">
              <div
                class="flex-1 rounded-t-md"
                style="background: #6366F1;"
                :style="{ height: Math.max(4, (m.redemptions / trendMax) * 100) + 'px' }"
                :title="`${m.redemptions} redemptions`"
              />
              <div
                class="flex-1 rounded-t-md"
                style="background: #C7D2FE;"
                :style="{ height: Math.max(4, (m.new_customers / trendMax) * 100) + 'px' }"
                :title="`${m.new_customers} new customers`"
              />
            </div>
            <p class="text-xs text-gray-400 mt-1.5">{{ shortMonth(m.month) }}</p>
          </div>
        </div>
        <div class="flex gap-4 mt-3 text-xs text-gray-400">
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm" style="background:#6366F1;" /> Redemptions</span>
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm" style="background:#C7D2FE;" /> New Customers</span>
        </div>
      </div>

      <!-- ═══ RETENTION FUNNEL ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Retention Funnel <span class="font-normal text-gray-400">(of new customers)</span></h2>
        <div class="space-y-3">
          <div v-for="(item, idx) in [
            { label: '30-day return', count: data.retained_30d_count, rate: data.retention_30d_rate, max: data.new_customers },
            { label: '60-day return', count: data.retained_60d_count, rate: data.retention_60d_rate, max: data.new_customers },
            { label: '90-day return', count: data.retained_90d_count, rate: data.retention_90d_rate, max: data.new_customers },
          ]" :key="idx">
            <div class="flex items-center gap-4">
              <span class="text-sm text-gray-600 w-28">{{ item.label }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                <div
                  class="h-full rounded-full flex items-center justify-end pr-2"
                  :style="{
                    width: Math.max(2, (item.count / Math.max(item.max, 1)) * 100) + '%',
                    background: idx === 0 ? '#4F46E5' : idx === 1 ? '#6366F1' : '#818CF8',
                  }"
                >
                  <span v-if="item.count > 0" class="text-xs text-white font-medium">{{ item.count }}</span>
                </div>
              </div>
              <span class="text-sm font-medium text-gray-700 w-14 text-right">{{ pct(item.rate) }}</span>
            </div>
          </div>
        </div>
        <p v-if="data.new_customers === 0" class="text-xs text-gray-400 mt-3">No new customers yet.</p>
      </div>

      <!-- ═══ OPERATIONAL METRICS ═══ -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Avg Bill</p>
          <p class="text-xl font-bold text-gray-700 mt-1">{{ data.avg_bill_amount ? currency(data.avg_bill_amount) : '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Cost per Customer</p>
          <p class="text-xl font-bold text-gray-700 mt-1">{{ data.cost_per_customer ? currency(data.cost_per_customer) : '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Conversion Rate</p>
          <p class="text-xl font-bold text-gray-700 mt-1">{{ data.claim_conversion_rate !== null ? data.claim_conversion_rate + '%' : '—' }}</p>
          <p class="text-xs text-gray-300 mt-0.5">claims → redemptions</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Customers Sent</p>
          <p class="text-xl font-bold text-gray-700 mt-1">{{ data.customers_sent }}</p>
          <p class="text-xs text-gray-300 mt-0.5">to your partner</p>
        </div>
      </div>

      <!-- ═══ REVENUE vs COST ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Revenue vs Cost</h2>
        <div class="flex items-center gap-6">
          <div class="flex-1">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm text-gray-500">Revenue from partner's customers</span>
              <span class="text-sm font-bold text-gray-800">{{ currency(data.total_revenue) }}</span>
            </div>
            <div class="bg-gray-100 rounded-full h-4 overflow-hidden mb-4">
              <div class="h-full rounded-full" style="background:#4F46E5;" :style="{ width: Math.min(100, (data.total_revenue / Math.max(data.total_revenue, data.total_benefit_given, 1)) * 100) + '%' }" />
            </div>

            <div class="flex items-center justify-between mb-1">
              <span class="text-sm text-gray-500">Benefit given (your cost)</span>
              <span class="text-sm font-bold text-gray-500">{{ currency(data.total_benefit_given) }}</span>
            </div>
            <div class="bg-gray-100 rounded-full h-4 overflow-hidden mb-4">
              <div class="h-full rounded-full" style="background:#C7D2FE;" :style="{ width: Math.min(100, (data.total_benefit_given / Math.max(data.total_revenue, data.total_benefit_given, 1)) * 100) + '%' }" />
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
              <span class="text-sm font-medium text-gray-700">Net value</span>
              <span class="text-lg font-bold" style="color:#059669;">{{ currency(data.total_revenue - data.total_benefit_given) }}</span>
            </div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>
