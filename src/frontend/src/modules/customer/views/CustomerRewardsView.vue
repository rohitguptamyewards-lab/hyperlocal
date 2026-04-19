<!--
  CustomerRewardsView.vue — Customer loyalty rewards dashboard.
  Purpose: Shows all loyalty balances, redeemable outlets, and limitations.
  Owner module: CustomerPortal
  API: GET /api/customer/rewards, GET /api/customer/activity
-->
<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCustomerAuthStore } from '@/stores/customerAuth'
import customerApi from '@/services/customerApi'

const router = useRouter()
const auth   = useCustomerAuthStore()

interface RedeemableOutlet {
  partnership_name: string
  partner_name: string
  partner_category: string
  outlet_name: string
  outlet_address: string | null
  cap_percent: number | null
  max_per_bill: number | null
  min_bill: number | null
}

interface MerchantReward {
  merchant_id: number
  merchant_name: string
  merchant_category: string
  balance: number
  rupees_per_point: number | null
  value_in_rupees: number | null
  last_synced_at: string | null
  redeemable_at: RedeemableOutlet[]
}

interface ActivityItem {
  type: 'claim' | 'redemption'
  token?: string
  status?: number
  merchant_name?: string
  source_outlet?: string
  target_outlet?: string
  outlet_name?: string
  bill_amount?: number
  benefit_amount?: number
  customer_type?: number
  issued_at?: string
  redeemed_at?: string
  created_at?: string
}

const tab = ref<'rewards' | 'activity'>('rewards')
const rewards = ref<MerchantReward[]>([])
const activity = ref<ActivityItem[]>([])
const loading = ref(true)
const activityLoading = ref(false)
const error   = ref<string | null>(null)
const expandedMerchant = ref<number | null>(null)

const totalValue = computed(() =>
  rewards.value.reduce((sum, r) => sum + (r.value_in_rupees ?? 0), 0)
)

onMounted(async () => {
  if (!auth.token) {
    router.push('/my-rewards')
    return
  }
  try {
    const { data } = await customerApi.get('/rewards')
    rewards.value = data.rewards
  } catch {
    error.value = 'Failed to load rewards.'
  } finally {
    loading.value = false
  }
})

async function loadActivity() {
  if (activity.value.length > 0) return
  activityLoading.value = true
  try {
    const { data } = await customerApi.get('/activity')
    activity.value = data.activity
  } catch { /* non-critical */ }
  finally { activityLoading.value = false }
}

function switchTab(t: 'rewards' | 'activity') {
  tab.value = t
  if (t === 'activity') loadActivity()
}

function claimStatusLabel(s: number): string {
  return { 1: 'Issued', 2: 'Redeemed', 3: 'Expired', 4: 'Cancelled' }[s] ?? 'Unknown'
}

function customerTypeLabel(t: number): string {
  return { 1: 'New', 2: 'Existing', 3: 'Reactivated' }[t] ?? ''
}

function formatDate(d: string | undefined): string {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

function toggleMerchant(id: number) {
  expandedMerchant.value = expandedMerchant.value === id ? null : id
}

function currency(val: number): string {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val)
}

function categoryIcon(cat: string | null): string {
  const icons: Record<string, string> = {
    cafe: 'M4 4h16v2H4z M4 10h16v10H4z',
    gym: 'M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 5.57 2 7.71 3.43 9.14 2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22 14.86 20.57',
    restaurant: 'M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7z M16 6v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z',
    salon: 'M7 5a7 7 0 0114 0v2a4 4 0 01-4 4H7a4 4 0 01-4-4V5z M12 13v9',
    bookstore: 'M4 19.5A2.5 2.5 0 016.5 17H20 M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z',
  }
  return icons[cat ?? ''] ?? 'M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z'
}

function handleLogout() {
  auth.logout()
  router.push('/my-rewards')
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-white">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-4 py-4">
      <div class="max-w-lg mx-auto flex items-center justify-between">
        <div>
          <span class="text-lg font-bold text-gray-900">My</span>
          <span class="text-lg font-light text-indigo-600"> Rewards</span>
        </div>
        <button @click="handleLogout" class="text-sm text-gray-500 hover:text-red-600">
          Sign out
        </button>
      </div>
    </header>

    <div class="max-w-lg mx-auto px-4 py-6">
      <!-- Total value card -->
      <div v-if="!loading && rewards.length > 0" class="rounded-2xl bg-indigo-600 text-white px-6 py-5 mb-6 shadow-lg">
        <p class="text-sm text-indigo-200 font-medium">Total Rewards Value</p>
        <p class="text-3xl font-bold mt-1">{{ currency(totalValue) }}</p>
        <p class="text-xs text-indigo-300 mt-1">across {{ rewards.length }} brand{{ rewards.length > 1 ? 's' : '' }}</p>
      </div>

      <!-- Tab switcher -->
      <div v-if="!loading" class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6">
        <button
          @click="switchTab('rewards')"
          class="flex-1 text-sm font-medium py-2 rounded-md transition-colors"
          :class="tab === 'rewards' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
        >Rewards</button>
        <button
          @click="switchTab('activity')"
          class="flex-1 text-sm font-medium py-2 rounded-md transition-colors"
          :class="tab === 'activity' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
        >Activity</button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-20 text-gray-400">
        <p class="text-sm">Loading your rewards…</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-12 text-red-600 text-sm">{{ error }}</div>

      <!-- ═══ ACTIVITY TAB ═══ -->
      <div v-else-if="tab === 'activity'">
        <div v-if="activityLoading" class="text-center py-12 text-gray-400 text-sm">Loading activity…</div>
        <div v-else-if="activity.length === 0" class="text-center py-16 text-gray-400">
          <p class="text-lg">No activity yet</p>
          <p class="text-sm mt-1">Your claims and redemptions will appear here.</p>
        </div>
        <div v-else class="space-y-3">
          <div v-for="(a, i) in activity" :key="i" class="bg-white rounded-xl border border-gray-200 px-4 py-3 shadow-sm">
            <div class="flex items-center justify-between mb-1">
              <span
                class="text-xs font-medium px-2 py-0.5 rounded-full"
                :class="a.type === 'redemption' ? 'bg-green-50 text-green-700' : 'bg-indigo-50 text-indigo-700'"
              >{{ a.type === 'redemption' ? 'Redemption' : 'Claim' }}</span>
              <span class="text-xs text-gray-400">{{ formatDate(a.created_at || a.issued_at) }}</span>
            </div>
            <p class="text-sm font-medium text-gray-900">{{ a.merchant_name }}</p>
            <template v-if="a.type === 'redemption'">
              <p class="text-xs text-gray-500">
                Bill: {{ currency(a.bill_amount ?? 0) }} · Benefit: {{ currency(a.benefit_amount ?? 0) }}
              </p>
              <p v-if="a.customer_type" class="text-xs text-gray-400">{{ customerTypeLabel(a.customer_type) }} customer</p>
            </template>
            <template v-else>
              <p class="text-xs text-gray-500">
                Token: <span class="font-mono">{{ a.token }}</span>
                · {{ claimStatusLabel(a.status ?? 1) }}
              </p>
              <p v-if="a.target_outlet" class="text-xs text-gray-400">At {{ a.target_outlet }}</p>
            </template>
          </div>
        </div>
      </div>

      <!-- ═══ REWARDS TAB ═══ -->

      <!-- Empty -->
      <div v-else-if="rewards.length === 0" class="text-center py-20 text-gray-400">
        <p class="text-lg">No rewards yet</p>
        <p class="text-sm mt-1">Visit partner outlets and earn loyalty points!</p>
      </div>

      <!-- Rewards list -->
      <div v-else class="space-y-4">
        <div
          v-for="r in rewards"
          :key="r.merchant_id"
          class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm"
        >
          <!-- Merchant header -->
          <button
            @click="toggleMerchant(r.merchant_id)"
            class="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="categoryIcon(r.merchant_category)" />
                </svg>
              </div>
              <div class="min-w-0 text-left">
                <p class="font-semibold text-gray-900 truncate">{{ r.merchant_name }}</p>
                <p class="text-xs text-gray-400">{{ r.merchant_category }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <p class="text-lg font-bold text-indigo-600">{{ r.balance }} pts</p>
              <p v-if="r.value_in_rupees" class="text-xs text-gray-400">{{ currency(r.value_in_rupees) }}</p>
            </div>
          </button>

          <!-- Expanded: redeemable outlets -->
          <div v-if="expandedMerchant === r.merchant_id" class="border-t border-gray-100 px-5 py-4 bg-gray-50">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">
              Redeemable at {{ r.redeemable_at.length }} outlet{{ r.redeemable_at.length !== 1 ? 's' : '' }}
            </p>

            <div v-if="r.redeemable_at.length === 0" class="text-xs text-gray-400 py-2">
              No active partnerships — points cannot be redeemed right now.
            </div>

            <div v-for="(o, i) in r.redeemable_at" :key="i" class="bg-white rounded-lg border border-gray-200 px-4 py-3 mb-2">
              <p class="text-sm font-medium text-gray-900">{{ o.partner_name }}</p>
              <p class="text-xs text-gray-500">{{ o.outlet_name }}</p>
              <p v-if="o.outlet_address" class="text-xs text-gray-400">{{ o.outlet_address }}</p>

              <!-- Limitations -->
              <div class="flex flex-wrap gap-2 mt-2">
                <span v-if="o.cap_percent" class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">
                  Max {{ o.cap_percent }}% of bill
                </span>
                <span v-if="o.max_per_bill" class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">
                  Max {{ currency(o.max_per_bill) }}/bill
                </span>
                <span v-if="o.min_bill" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                  Min bill {{ currency(o.min_bill) }}
                </span>
              </div>
            </div>

            <p v-if="r.last_synced_at" class="text-xs text-gray-300 mt-2">
              Balance last updated: {{ new Date(r.last_synced_at).toLocaleDateString('en-IN') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
