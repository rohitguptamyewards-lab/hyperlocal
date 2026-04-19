<!--
  BillOffersPublicView.vue — Customer-facing digital bill offers page.
  Purpose: Shows partner coupon codes on the merchant's digital bill.
  Owner module: PartnerOffers
  API: GET /api/public/bill-offers/{merchantUuid}
       POST /api/public/bill-offers/{merchantUuid}/impressions
       POST /api/public/bill-offers/{merchantUuid}/claims/{offerUuid}
  No auth required. Mobile-first. Three display modes: simple, scratch, carousel.
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const merchantUuid = route.params.merchantUuid as string
const http = axios.create({ baseURL: '/api' })

interface Offer {
  uuid: string
  title: string
  description: string | null
  coupon_code: string
  discount_type: number
  discount_value: number
  image_url: string | null
  expiry_date: string | null
  terms_conditions: string | null
  brand_name: string
  brand_category: string | null
}

const merchantName = ref('')
const displayMode  = ref('simple')
const offers       = ref<Offer[]>([])
const loading      = ref(true)
const error        = ref<string | null>(null)
const copiedOffer  = ref<string | null>(null)
const revealedOffers = ref<Set<string>>(new Set())
const expandedTerms  = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await http.get(`/public/bill-offers/${merchantUuid}`)
    merchantName.value = data.merchant?.name ?? ''
    displayMode.value  = data.merchant?.display_mode ?? 'simple'
    offers.value       = data.offers ?? []

    // Record impressions
    if (offers.value.length > 0) {
      const offerIds = offers.value.map(o => {
        // Extract numeric ID from the offers if available, otherwise skip
        return o.uuid
      })
      // Fire and forget
      http.post(`/public/bill-offers/${merchantUuid}/impressions`, {
        offer_ids: offerIds,
        session_id: sessionId(),
      }).catch(() => {})
    }
  } catch {
    error.value = 'Unable to load offers.'
  } finally {
    loading.value = false
  }
})

function sessionId(): string {
  let sid = sessionStorage.getItem('bill_session')
  if (!sid) {
    sid = Math.random().toString(36).substring(2, 18)
    sessionStorage.setItem('bill_session', sid)
  }
  return sid
}

async function copyCode(offer: Offer) {
  try {
    await navigator.clipboard.writeText(offer.coupon_code)
    copiedOffer.value = offer.uuid
    setTimeout(() => { copiedOffer.value = null }, 2000)
    // Record claim
    http.post(`/public/bill-offers/${merchantUuid}/claims/${offer.uuid}`).catch(() => {})
  } catch {
    // Fallback for older browsers
    const textarea = document.createElement('textarea')
    textarea.value = offer.coupon_code
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
    copiedOffer.value = offer.uuid
    setTimeout(() => { copiedOffer.value = null }, 2000)
  }
}

function revealOffer(uuid: string) {
  revealedOffers.value.add(uuid)
}

function discountBadge(o: Offer): string {
  return o.discount_type === 1 ? `${o.discount_value}% OFF` : `₹${o.discount_value} OFF`
}

function toggleTerms(uuid: string) {
  expandedTerms.value = expandedTerms.value === uuid ? null : uuid
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-50 to-white">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-4 py-4">
      <div class="max-w-lg mx-auto text-center">
        <p class="text-xs text-gray-400 uppercase tracking-wider font-medium">Partner offers from</p>
        <p class="text-lg font-bold text-gray-900">{{ merchantName || 'Loading…' }}</p>
      </div>
    </header>

    <div class="max-w-lg mx-auto px-4 py-6">
      <!-- Loading -->
      <div v-if="loading" class="text-center py-20 text-gray-400 text-sm">Loading offers…</div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-12 text-red-600 text-sm">{{ error }}</div>

      <!-- Empty -->
      <div v-else-if="offers.length === 0" class="text-center py-20 text-gray-400">
        <p class="text-lg">No offers available</p>
        <p class="text-sm mt-1">Check back later for partner deals!</p>
      </div>

      <!-- Offers -->
      <template v-else>
        <div class="text-center mb-6">
          <p class="text-lg font-bold text-gray-900">You've unlocked offers from nearby stores!</p>
          <p class="text-sm text-gray-500 mt-1">{{ offers.length }} offer{{ offers.length > 1 ? 's' : '' }} available</p>
        </div>

        <!-- ═══ SIMPLE MODE ═══ -->
        <div v-if="displayMode === 'simple'" class="space-y-4">
          <div v-for="o in offers" :key="o.uuid" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-4">
              <div class="flex items-start justify-between mb-2">
                <div>
                  <p class="text-sm font-bold text-gray-900">{{ o.brand_name }}</p>
                  <p v-if="o.brand_category" class="text-xs text-gray-400">{{ o.brand_category }}</p>
                </div>
                <span class="text-xs font-bold bg-green-100 text-green-800 px-2.5 py-1 rounded-full">{{ discountBadge(o) }}</span>
              </div>
              <p class="text-sm text-gray-700 font-medium">{{ o.title }}</p>
              <p v-if="o.description" class="text-xs text-gray-500 mt-1">{{ o.description }}</p>

              <!-- Coupon code -->
              <div class="mt-3 flex items-center gap-2">
                <div class="flex-1 bg-gray-50 border border-dashed border-gray-300 rounded-lg px-4 py-2.5 text-center">
                  <span class="font-mono text-lg font-bold text-indigo-600 tracking-wider">{{ o.coupon_code }}</span>
                </div>
                <button
                  @click="copyCode(o)"
                  class="px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
                  :class="copiedOffer === o.uuid ? 'bg-green-600 text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                >{{ copiedOffer === o.uuid ? 'Copied!' : 'Copy' }}</button>
              </div>

              <!-- Expiry + T&C -->
              <div class="mt-3 flex items-center justify-between">
                <p v-if="o.expiry_date" class="text-xs text-gray-400">Valid till {{ new Date(o.expiry_date).toLocaleDateString('en-IN') }}</p>
                <button v-if="o.terms_conditions" @click="toggleTerms(o.uuid)" class="text-xs text-indigo-600 hover:text-indigo-800">
                  {{ expandedTerms === o.uuid ? 'Hide T&C' : 'View T&C' }}
                </button>
              </div>
              <p v-if="expandedTerms === o.uuid && o.terms_conditions" class="text-xs text-gray-400 mt-2 bg-gray-50 rounded-lg p-3">{{ o.terms_conditions }}</p>
            </div>
          </div>
        </div>

        <!-- ═══ SCRATCH MODE ═══ -->
        <div v-else-if="displayMode === 'scratch'" class="space-y-4">
          <div v-for="o in offers" :key="o.uuid" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-4">
              <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-gray-900">{{ o.brand_name }}</p>
                <span class="text-xs font-bold bg-amber-100 text-amber-800 px-2.5 py-1 rounded-full">{{ discountBadge(o) }}</span>
              </div>

              <!-- Scratch overlay -->
              <div v-if="!revealedOffers.has(o.uuid)" class="relative">
                <div
                  @click="revealOffer(o.uuid)"
                  class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl px-4 py-8 text-center cursor-pointer hover:from-indigo-600 hover:to-purple-700 transition-all"
                >
                  <p class="text-white font-bold text-lg">Tap to reveal your offer!</p>
                  <p class="text-indigo-200 text-xs mt-1">{{ o.title }}</p>
                </div>
              </div>

              <!-- Revealed content -->
              <div v-else>
                <p class="text-sm text-gray-700 font-medium mb-2">{{ o.title }}</p>
                <div class="flex items-center gap-2">
                  <div class="flex-1 bg-gray-50 border border-dashed border-gray-300 rounded-lg px-4 py-2.5 text-center">
                    <span class="font-mono text-lg font-bold text-indigo-600 tracking-wider">{{ o.coupon_code }}</span>
                  </div>
                  <button
                    @click="copyCode(o)"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    :class="copiedOffer === o.uuid ? 'bg-green-600 text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                  >{{ copiedOffer === o.uuid ? 'Copied!' : 'Copy' }}</button>
                </div>
                <p v-if="o.expiry_date" class="text-xs text-gray-400 mt-2">Valid till {{ new Date(o.expiry_date).toLocaleDateString('en-IN') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══ CAROUSEL MODE ═══ -->
        <div v-else-if="displayMode === 'carousel'" class="overflow-x-auto pb-4 -mx-4 px-4">
          <div class="flex gap-4" :style="{ width: offers.length * 300 + 'px' }">
            <div v-for="o in offers" :key="o.uuid" class="w-72 flex-shrink-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
              <div v-if="o.image_url" class="h-32 bg-gray-100">
                <img :src="o.image_url" class="w-full h-full object-cover" :alt="o.title" />
              </div>
              <div class="px-4 py-4">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-sm font-bold text-gray-900">{{ o.brand_name }}</p>
                  <span class="text-xs font-bold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">{{ discountBadge(o) }}</span>
                </div>
                <p class="text-sm text-gray-700 font-medium">{{ o.title }}</p>
                <div class="mt-3 flex items-center gap-2">
                  <div class="flex-1 bg-gray-50 border border-dashed border-gray-300 rounded-lg px-3 py-2 text-center">
                    <span class="font-mono font-bold text-indigo-600 tracking-wider text-sm">{{ o.coupon_code }}</span>
                  </div>
                  <button
                    @click="copyCode(o)"
                    class="px-3 py-2 rounded-lg text-xs font-medium transition-colors"
                    :class="copiedOffer === o.uuid ? 'bg-green-600 text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                  >{{ copiedOffer === o.uuid ? 'Copied!' : 'Copy' }}</button>
                </div>
                <p v-if="o.expiry_date" class="text-xs text-gray-400 mt-2">Valid till {{ new Date(o.expiry_date).toLocaleDateString('en-IN') }}</p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Footer -->
    <footer class="py-6 text-center">
      <p class="text-xs text-gray-300">Powered by Hyperlocal Network</p>
    </footer>
  </div>
</template>
