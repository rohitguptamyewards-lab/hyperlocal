<!--
  SharedClaimLandingView.vue — Public landing page for shareable partnership links (GAP 10).
  Purpose: Show partnership details and let customers navigate to the existing claim flow.
  Owner module: Redemption
  API: GET /api/shared-claim/{code}  (public, no auth)
  Route: /shared/:code
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()

const code = route.params.code as string

interface SharedClaimData {
  code:             string
  partnership_uuid: string
  partnership_name: string
  proposer_name:    string
  acceptor_name:    string
  offer_summary:    string | null
  terms: {
    per_bill_cap_percent: number | null
    per_bill_cap_amount:  number | null
    min_bill_amount:      number | null
    monthly_cap_amount:   number | null
  } | null
  claim_url: string
}

const loading = ref(true)
const error   = ref<string | null>(null)
const data    = ref<SharedClaimData | null>(null)

onMounted(async () => {
  try {
    const res = await api.get<SharedClaimData>(`/shared-claim/${code}`)
    data.value = res.data
  } catch {
    error.value = 'This link is invalid or has expired. Please ask your partner for a new link.'
  } finally {
    loading.value = false
  }
})

function claimNow() {
  if (!data.value) return
  // Navigate to the existing claim flow, preserving the ref query param
  const claimPath = data.value.claim_url.replace(/^https?:\/\/[^/]+/, '')
  router.push(claimPath)
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 flex items-center justify-center px-4 py-12">

    <!-- Loading -->
    <div v-if="loading" class="text-center text-gray-400 text-sm">
      <div class="w-8 h-8 border-2 border-indigo-300 border-t-indigo-600 rounded-full animate-spin mx-auto mb-3"></div>
      Loading offer details…
    </div>

    <!-- Error -->
    <div v-else-if="error" class="max-w-md w-full bg-white rounded-2xl shadow-lg px-8 py-10 text-center">
      <div class="text-4xl mb-4">🔗</div>
      <h2 class="text-lg font-semibold text-gray-800 mb-2">Link not found</h2>
      <p class="text-sm text-gray-500">{{ error }}</p>
    </div>

    <!-- Offer card -->
    <div v-else-if="data" class="max-w-md w-full">

      <!-- Header badge -->
      <div class="text-center mb-6">
        <span class="inline-block bg-indigo-600 text-white text-xs font-semibold px-4 py-1.5 rounded-full tracking-wide uppercase">
          Exclusive Partner Offer
        </span>
      </div>

      <!-- Main card -->
      <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

        <!-- Gradient banner -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-8 text-white text-center">
          <p class="text-sm font-medium text-indigo-200 mb-1">You've been invited to claim a token!</p>
          <h1 class="text-2xl font-bold mb-4">{{ data.partnership_name }}</h1>

          <!-- Merchant flow -->
          <div class="flex items-center justify-center gap-3 text-sm">
            <span class="bg-white/20 px-3 py-1.5 rounded-full font-semibold">{{ data.proposer_name }}</span>
            <span class="text-indigo-200 text-lg">→</span>
            <span class="bg-white/20 px-3 py-1.5 rounded-full font-semibold">{{ data.acceptor_name }}</span>
          </div>
        </div>

        <!-- Offer details -->
        <div class="px-8 py-6">
          <!-- Offer summary pill -->
          <div v-if="data.offer_summary" class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 text-center mb-6">
            <p class="text-xs text-green-600 font-medium uppercase tracking-wide mb-1">Your benefit</p>
            <p class="text-xl font-bold text-green-800">{{ data.offer_summary }}</p>
          </div>

          <!-- Terms breakdown -->
          <div v-if="data.terms" class="space-y-2 mb-6">
            <div v-if="data.terms.per_bill_cap_percent !== null" class="flex items-center justify-between text-sm">
              <span class="text-gray-500">Discount</span>
              <span class="font-semibold text-gray-800">{{ data.terms.per_bill_cap_percent }}% off bill</span>
            </div>
            <div v-if="data.terms.per_bill_cap_amount !== null" class="flex items-center justify-between text-sm">
              <span class="text-gray-500">Max discount</span>
              <span class="font-semibold text-gray-800">₹{{ data.terms.per_bill_cap_amount }}</span>
            </div>
            <div v-if="data.terms.min_bill_amount !== null" class="flex items-center justify-between text-sm">
              <span class="text-gray-500">Minimum bill</span>
              <span class="font-semibold text-gray-800">₹{{ data.terms.min_bill_amount }}</span>
            </div>
            <div v-if="data.terms.monthly_cap_amount !== null" class="flex items-center justify-between text-sm">
              <span class="text-gray-500">Monthly cap</span>
              <span class="font-semibold text-gray-800">₹{{ data.terms.monthly_cap_amount }}</span>
            </div>
          </div>

          <!-- How it works -->
          <div class="bg-gray-50 rounded-xl px-4 py-3 mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">How it works</p>
            <ol class="space-y-1.5 text-xs text-gray-600">
              <li class="flex gap-2">
                <span class="text-indigo-500 font-bold flex-shrink-0">1.</span>
                <span>Tap "Claim My Token" below to get your unique discount token.</span>
              </li>
              <li class="flex gap-2">
                <span class="text-indigo-500 font-bold flex-shrink-0">2.</span>
                <span>Visit <strong>{{ data.acceptor_name }}</strong> and show your token at the counter.</span>
              </li>
              <li class="flex gap-2">
                <span class="text-indigo-500 font-bold flex-shrink-0">3.</span>
                <span>Your cashier applies the discount — enjoy your benefit!</span>
              </li>
            </ol>
          </div>

          <!-- CTA -->
          <button
            @click="claimNow"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-base shadow-sm"
          >
            Claim My Token
          </button>

          <p class="text-center text-xs text-gray-400 mt-3">
            Token valid for 48 hours from issue · Presented by {{ data.proposer_name }}
          </p>
        </div>
      </div>

      <!-- Footer brand -->
      <p class="text-center text-xs text-gray-400 mt-6">
        Powered by Hyperlocal Partnership Network
      </p>
    </div>

  </div>
</template>
