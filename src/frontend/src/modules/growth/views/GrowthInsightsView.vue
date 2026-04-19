<!--
  GrowthInsightsView.vue — Growth dashboard: health scores, leaderboard, demand index,
  weekly digest, referral stats, invite, brand profile, seasonal templates.
  Consolidates features #3,4,5,6,7,9,10,11,16,17,18 into one page.
  Owner module: Growth
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const leaderboard = ref<any[]>([])
const digest = ref<any>(null)
const demandIndex = ref<any>(null)
const inviteStats = ref<any>(null)
const seasonalTemplates = ref<any[]>([])
const loading = ref(true)
const invitePhone = ref('')
const inviteResult = ref<any>(null)

onMounted(async () => {
  try {
    const [healthRes, digestRes, demandRes, inviteRes, templateRes] = await Promise.allSettled([
      api.get('/growth/health'),
      api.get('/growth/weekly-digest'),
      api.get('/growth/demand-index'),
      api.get('/growth/invite-stats'),
      api.get('/growth/seasonal-templates'),
    ])

    if (healthRes.status === 'fulfilled') leaderboard.value = healthRes.value.data.leaderboard ?? []
    if (digestRes.status === 'fulfilled') digest.value = digestRes.value.data
    if (demandRes.status === 'fulfilled') demandIndex.value = demandRes.value.data
    if (inviteRes.status === 'fulfilled') inviteStats.value = inviteRes.value.data
    if (templateRes.status === 'fulfilled') seasonalTemplates.value = templateRes.value.data.templates ?? []
  } finally {
    loading.value = false
  }
})

async function sendInvite() {
  if (!invitePhone.value.trim()) return
  try {
    const { data } = await api.post('/growth/invite', { phone: invitePhone.value })
    inviteResult.value = data
    invitePhone.value = ''
  } catch { /* */ }
}

function currency(v: number): string {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(v)
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-5xl">
    <h1 class="text-2xl font-semibold text-gray-900 mb-1">Growth Insights</h1>
    <p class="text-sm text-gray-500 mb-6">Health scores, demand insights, referrals, and more</p>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading insights…</div>

    <template v-else>
      <!-- ═══ WEEKLY DIGEST (#3) ═══ -->
      <div v-if="digest" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">This Week's Performance</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ digest.new_customers }}</p>
            <p class="text-xs text-gray-400">New customers</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold" style="color:#34d399;">{{ currency(digest.revenue) }}</p>
            <p class="text-xs text-gray-400">Revenue</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold text-gray-500">{{ currency(digest.benefit_cost) }}</p>
            <p class="text-xs text-gray-400">Benefit cost</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold" style="color:#059669;">{{ currency(digest.net_value) }}</p>
            <p class="text-xs text-gray-400">Net value</p>
          </div>
        </div>
        <div v-if="digest.best_partnership" class="mt-4 pt-4 border-t border-gray-100 text-center">
          <p class="text-xs text-gray-400">Best partnership this week</p>
          <p class="text-sm font-medium text-gray-900">{{ digest.best_partnership.name }} — {{ currency(digest.best_partnership.revenue) }}</p>
        </div>
      </div>

      <!-- ═══ LEADERBOARD (#4) ═══ -->
      <div v-if="leaderboard.length > 0" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Partnership Health Leaderboard</h2>
        <div class="space-y-2">
          <div v-for="(p, i) in leaderboard" :key="i" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <div class="flex items-center gap-3">
              <span class="text-lg font-bold text-gray-300">#{{ i + 1 }}</span>
              <span class="text-sm text-gray-900">{{ p.name }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold" :class="p.level === 'green' ? 'text-green-600' : p.level === 'yellow' ? 'text-amber-500' : 'text-red-500'">{{ p.score }}/100</span>
              <span class="w-3 h-3 rounded-full" :class="p.level === 'green' ? 'bg-green-400' : p.level === 'yellow' ? 'bg-amber-400' : 'bg-red-400'" />
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ DEMAND INDEX (#16, #17) ═══ -->
      <div v-if="demandIndex" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-700 mb-4">Untapped Demand Near You</h2>
          <div v-if="demandIndex.untapped_demand?.length" class="space-y-2">
            <div v-for="d in demandIndex.untapped_demand" :key="d.category" class="flex items-center justify-between py-1">
              <span class="text-sm text-gray-900 capitalize">{{ d.category?.replace('_', ' ') }}</span>
              <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">{{ d.merchant_count }} brand{{ d.merchant_count > 1 ? 's' : '' }}</span>
            </div>
          </div>
          <p v-else class="text-xs text-gray-400">No untapped categories found — you're well connected!</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-700 mb-4">Category Demand in Your City</h2>
          <div v-if="demandIndex.category_demand?.length" class="space-y-2">
            <div v-for="d in demandIndex.category_demand.slice(0, 5)" :key="d.category" class="flex items-center justify-between py-1">
              <span class="text-sm text-gray-900 capitalize">{{ d.category?.replace('_', ' ') }}</span>
              <span class="text-xs text-gray-500">{{ d.redemptions }} redemptions · {{ d.merchants }} brands</span>
            </div>
          </div>
          <p v-else class="text-xs text-gray-400">Not enough data yet.</p>
        </div>
      </div>

      <!-- ═══ INVITE A BRAND (#5) ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Invite a Nearby Brand</h2>
        <p class="text-xs text-gray-400 mb-4">Know a brand that would benefit? Send them an invite via WhatsApp.</p>
        <div class="flex gap-3">
          <input v-model="invitePhone" type="tel" placeholder="Phone number" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          <button @click="sendInvite" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Send invite</button>
        </div>
        <div v-if="inviteResult" class="mt-3 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800">
          Invite sent! Share this link: <a :href="inviteResult.invite_url" class="font-medium underline">{{ inviteResult.invite_url }}</a>
        </div>
        <p v-if="inviteStats" class="text-xs text-gray-400 mt-3">{{ inviteStats.total_invites }} invites sent · {{ inviteStats.converted }} converted</p>
      </div>

      <!-- ═══ SEASONAL TEMPLATES (#9) ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Seasonal Offer Templates</h2>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="t in seasonalTemplates" :key="t.key" class="border border-gray-200 rounded-lg px-4 py-3 hover:border-indigo-300 cursor-pointer transition-colors">
            <p class="text-sm font-medium text-gray-900">{{ t.label }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ t.discount }} · {{ t.season }}</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
