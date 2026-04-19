<!--
  FindPartnersView.vue — Proactive partner discovery search.
  Purpose: Merchant searches for potential partners by city / category,
           then launches the create-partnership modal pre-filled with the chosen merchant.
  Owner module: Discovery
  API: GET /api/discovery/search?city=&category=
       GET /api/merchants (for cities list)
  Integration: Emits to PartnershipListView create modal via router state
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()

// ── Types ────────────────────────────────────────────────────
interface SearchResult {
  id:                  number
  name:                string
  category:            string | null
  city:                string | null
  outlet_count:        number
  fit_score:           number | null
  confidence_tier:     number | null
  rationale:           string | null
  trust_score:         number | null
  already_partnered:   boolean
  partnership_uuid:    string | null
  partnership_status:  number | null
}

// ── State ─────────────────────────────────────────────────────
const city         = ref('')
const category     = ref('')
const results      = ref<SearchResult[]>([])
const loading      = ref(false)
const searched     = ref(false)
const myCity       = ref('')

// Categories derived from the fit scoring map (must stay in sync with FitScoringService)
const CATEGORIES = [
  'cafe', 'gym', 'restaurant', 'salon', 'bookstore', 'spa', 'yoga',
  'pharmacy', 'cinema', 'retail', 'coworking', 'boutique', 'florist',
  'smoothie', 'stationery', 'sports_apparel', 'clinic',
]

// ── Bootstrap: pre-fill city from own merchant ───────────────
onMounted(async () => {
  try {
    const me = await api.get('/auth/me')
    myCity.value = me.data?.merchant?.city ?? ''
    city.value   = myCity.value
  } catch {
    // non-blocking
  }
})

// ── Search ────────────────────────────────────────────────────
async function doSearch() {
  if (!city.value.trim()) return
  loading.value  = true
  searched.value = false
  results.value  = []
  try {
    const params: Record<string, string> = { city: city.value.trim() }
    if (category.value) params.category = category.value
    const res = await api.get<SearchResult[]>('/discovery/search', { params })
    results.value  = res.data
    searched.value = true
  } finally {
    loading.value = false
  }
}

// ── Tier badge ────────────────────────────────────────────────
const tierLabel = (tier: number | null) => {
  if (tier === 1) return { text: 'Strong fit',  cls: 'bg-green-100 text-green-800' }
  if (tier === 2) return { text: 'Good fit',    cls: 'bg-blue-100 text-blue-800' }
  if (tier === 3) return { text: 'Possible fit',cls: 'bg-gray-100 text-gray-600' }
  return           { text: 'Unscored',          cls: 'bg-gray-100 text-gray-400' }
}

// ── Propose: navigate to partnerships list with prefill state ─
function propose(m: SearchResult) {
  router.push({
    name: 'partnerships',
    state: { prefillMerchantId: m.id, prefillMerchantName: m.name },
  })
}

// ── View existing partnership ─────────────────────────────────
function viewPartnership(m: SearchResult) {
  if (m.partnership_uuid) {
    router.push(`/partnerships/${m.partnership_uuid}`)
  }
}

// ── Partnership status label ──────────────────────────────────
function partnerStatusLabel(status: number | null): { text: string; cls: string } {
  switch (status) {
    case 1: return { text: 'Invited',    cls: 'bg-yellow-50 text-yellow-700' }
    case 2: return { text: 'Proposed',   cls: 'bg-yellow-50 text-yellow-700' }
    case 3: return { text: 'Negotiating',cls: 'bg-blue-50 text-blue-700' }
    case 4: return { text: 'Agreed',     cls: 'bg-blue-50 text-blue-700' }
    case 5: return { text: 'Live',       cls: 'bg-green-50 text-green-700' }
    case 6: return { text: 'Paused',     cls: 'bg-orange-50 text-orange-700' }
    default: return { text: 'Partnered', cls: 'bg-green-50 text-green-700' }
  }
}
</script>

<template>
  <div class="p-4 sm:p-8 max-w-3xl">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Find Partners</h1>
      <p class="text-sm text-gray-500 mt-0.5">
        Search for brands in your city and send them a tie-up proposal.
      </p>
    </div>

    <!-- Search bar -->
    <div class="flex gap-3 mb-8">
      <div class="flex-1">
        <label class="block text-xs font-medium text-gray-500 mb-1">City</label>
        <input
          v-model="city"
          type="text"
          placeholder="e.g. Mumbai"
          @keyup.enter="doSearch"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>
      <div class="w-44">
        <label class="block text-xs font-medium text-gray-500 mb-1">Category (optional)</label>
        <select
          v-model="category"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All categories</option>
          <option v-for="c in CATEGORIES" :key="c" :value="c">
            {{ c.replace('_', ' ') }}
          </option>
        </select>
      </div>
      <div class="flex items-end">
        <button
          @click="doSearch"
          :disabled="loading || !city.trim()"
          class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors whitespace-nowrap"
        >
          {{ loading ? 'Searching…' : 'Search' }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">
      Searching brands in {{ city }}…
    </div>

    <!-- No results -->
    <div
      v-else-if="searched && results.length === 0"
      class="text-center py-16 text-gray-400"
    >
      <p class="text-lg">No brands found</p>
      <p class="text-sm mt-1">
        Try a different city or remove the category filter.
        You can still create a partnership manually from the Partnerships page.
      </p>
    </div>

    <!-- Results -->
    <div v-else-if="results.length > 0" class="space-y-3">
      <p class="text-xs text-gray-400 mb-3">
        {{ results.length }} brand{{ results.length === 1 ? '' : 's' }} found in
        <span class="font-medium text-gray-600">{{ city }}</span>
        <span v-if="category"> · {{ category.replace('_', ' ') }}</span>
      </p>

      <div
        v-for="m in results"
        :key="m.id"
        class="flex items-start justify-between bg-white border rounded-xl px-5 py-4 hover:shadow-sm transition-all"
        :class="m.already_partnered
          ? 'border-green-200 bg-green-50/30'
          : 'border-gray-200 hover:border-indigo-200'"
      >
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-medium text-gray-900">{{ m.name }}</p>

            <!-- Already partnered badge -->
            <span
              v-if="m.already_partnered"
              class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium"
              :class="partnerStatusLabel(m.partnership_status).cls"
            >
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              {{ partnerStatusLabel(m.partnership_status).text }} partner
            </span>

            <!-- Category pill -->
            <span
              v-if="m.category"
              class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 capitalize"
            >
              {{ m.category.replace('_', ' ') }}
            </span>
            <!-- Fit tier badge -->
            <span
              v-if="m.confidence_tier && !m.already_partnered"
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="tierLabel(m.confidence_tier).cls"
            >
              {{ tierLabel(m.confidence_tier).text }}
            </span>
            <!-- Trust score badge -->
            <span
              v-if="m.trust_score !== null && m.trust_score !== undefined"
              class="text-xs px-2 py-0.5 rounded-full bg-yellow-50 text-yellow-700 font-medium"
              title="Partner trust score (avg rating from their past partners)"
            >
              ⭐ {{ Number(m.trust_score).toFixed(1) }}
            </span>
          </div>

          <p class="text-xs text-gray-400 mt-1">
            {{ m.city }}
            <span v-if="m.outlet_count"> · {{ m.outlet_count }} outlet{{ m.outlet_count === 1 ? '' : 's' }}</span>
          </p>

          <!-- Fit score + rationale (only for non-partnered, to keep card clean) -->
          <template v-if="!m.already_partnered">
            <div v-if="m.fit_score !== null" class="mt-2 flex items-center gap-3">
              <div class="flex items-center gap-1.5">
                <div class="h-1.5 w-24 bg-gray-200 rounded-full overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="m.fit_score >= 65 ? 'bg-green-500' : m.fit_score >= 35 ? 'bg-blue-400' : 'bg-gray-400'"
                    :style="{ width: m.fit_score + '%' }"
                  />
                </div>
                <span class="text-xs text-gray-500 font-medium">{{ m.fit_score }}% fit</span>
              </div>
              <span v-if="m.rationale" class="text-xs text-gray-400 truncate max-w-xs">
                {{ m.rationale }}
              </span>
            </div>
            <p v-else class="text-xs text-gray-400 mt-1 italic">
              Fit score not yet computed — will update overnight.
            </p>
          </template>
        </div>

        <!-- CTA: View Partnership if already partnered, otherwise Propose tie-up -->
        <div v-if="auth.isManager()" class="ml-4 flex-shrink-0">
          <button
            v-if="m.already_partnered"
            @click="viewPartnership(m)"
            class="bg-white border border-green-300 text-green-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-50 transition-colors whitespace-nowrap"
          >
            View partnership
          </button>
          <button
            v-else
            @click="propose(m)"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors whitespace-nowrap"
          >
            Propose tie-up
          </button>
        </div>
      </div>
    </div>

    <!-- Pre-search hint -->
    <div v-else class="text-center py-16 text-gray-300">
      <p class="text-sm">Enter a city above and press Search</p>
    </div>
  </div>
</template>
