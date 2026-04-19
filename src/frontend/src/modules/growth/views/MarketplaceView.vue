<!--
  MarketplaceView.vue — Public offer marketplace (#13).
  Trending offers across all brands in a city.
  URL: /marketplace — no auth required.
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const http = axios.create({ baseURL: '/api' })

const offers = ref<any[]>([])
const city = ref('Mumbai')
const loading = ref(true)

onMounted(() => fetchOffers())

async function fetchOffers() {
  loading.value = true
  try {
    const { data } = await http.get('/public/marketplace', { params: { city: city.value } })
    offers.value = data.offers ?? []
  } finally {
    loading.value = false
  }
}

function discountBadge(o: any): string {
  return o.discount_type === 1 ? `${o.discount_value}% OFF` : `₹${o.discount_value} OFF`
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-50 to-white">
    <header class="bg-white border-b border-gray-200 px-4 py-4">
      <div class="max-w-2xl mx-auto text-center">
        <span class="text-lg font-bold text-gray-900">Hyperlocal</span>
        <span class="text-lg font-light text-indigo-600"> Marketplace</span>
        <p class="text-sm text-gray-500 mt-1">Offers from brands near you</p>
      </div>
    </header>

    <div class="max-w-2xl mx-auto px-4 py-6">
      <div class="flex gap-3 mb-6">
        <input v-model="city" type="text" placeholder="City" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
        <button @click="fetchOffers" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Search</button>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-400 text-sm">Loading offers…</div>

      <div v-else-if="offers.length === 0" class="text-center py-16 text-gray-400">
        <p class="text-lg">No offers in {{ city }}</p>
        <p class="text-sm mt-1">Try a different city.</p>
      </div>

      <div v-else class="space-y-3">
        <p class="text-xs text-gray-400 mb-2">{{ offers.length }} offer{{ offers.length > 1 ? 's' : '' }} in {{ city }}</p>
        <div v-for="o in offers" :key="o.uuid" class="bg-white rounded-xl border border-gray-200 px-5 py-4">
          <div class="flex items-center justify-between mb-1">
            <div>
              <p class="text-sm font-bold text-gray-900">{{ o.brand_name }}</p>
              <p class="text-xs text-gray-400 capitalize">{{ o.brand_category }}</p>
            </div>
            <span class="text-xs font-bold bg-green-100 text-green-800 px-2.5 py-1 rounded-full">{{ discountBadge(o) }}</span>
          </div>
          <p class="text-sm text-gray-700 mt-1">{{ o.title }}</p>
          <p v-if="o.description" class="text-xs text-gray-500 mt-0.5">{{ o.description }}</p>
          <div class="mt-2 bg-gray-50 border border-dashed border-gray-300 rounded-lg px-3 py-2 text-center">
            <span class="font-mono font-bold text-indigo-600 tracking-wider">{{ o.coupon_code }}</span>
          </div>
          <p v-if="o.expiry_date" class="text-xs text-gray-400 mt-2">Valid till {{ new Date(o.expiry_date).toLocaleDateString('en-IN') }}</p>
        </div>
      </div>
    </div>

    <footer class="py-6 text-center">
      <p class="text-xs text-gray-300">Powered by Hyperlocal Network</p>
      <p class="text-xs text-gray-300 mt-1">Want partner offers on YOUR brand's bills? <a href="/login" class="text-indigo-500 underline">Get started</a></p>
    </footer>
  </div>
</template>
