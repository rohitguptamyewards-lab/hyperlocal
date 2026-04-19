<!--
  BrandProfilePublicView.vue — Public brand profile page (#11).
  Shows brand info, active offers, partnership count.
  URL: /b/{slug} — SEO-friendly, shareable.
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const slug = route.params.slug as string
const http = axios.create({ baseURL: '/api' })

const brand = ref<any>(null)
const offers = ref<any[]>([])
const partnershipCount = ref(0)
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await http.get(`/public/brand/${slug}`)
    brand.value = data.brand
    offers.value = data.active_offers ?? []
    partnershipCount.value = data.partnership_count ?? 0
  } catch {
    error.value = 'Brand not found.'
  } finally {
    loading.value = false
  }
})

function discountBadge(o: any): string {
  return o.discount_type === 1 ? `${o.discount_value}% OFF` : `₹${o.discount_value} OFF`
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-white">
    <div class="max-w-lg mx-auto px-4 py-8">
      <div v-if="loading" class="text-center py-20 text-gray-400 text-sm">Loading…</div>
      <div v-else-if="error" class="text-center py-20 text-red-600 text-sm">{{ error }}</div>

      <template v-else-if="brand">
        <!-- Brand header -->
        <div class="text-center mb-8">
          <div v-if="brand.logo_url" class="w-20 h-20 rounded-full mx-auto mb-4 bg-gray-100 overflow-hidden">
            <img :src="brand.logo_url" :alt="brand.name" class="w-full h-full object-cover" />
          </div>
          <div v-else class="w-20 h-20 rounded-full mx-auto mb-4 bg-indigo-100 flex items-center justify-center">
            <span class="text-3xl font-bold text-indigo-600">{{ brand.name?.charAt(0) }}</span>
          </div>
          <h1 class="text-2xl font-bold text-gray-900">{{ brand.name }}</h1>
          <p class="text-sm text-gray-500 mt-1 capitalize">{{ brand.category }} · {{ brand.city }}</p>
          <p v-if="brand.bio" class="text-sm text-gray-600 mt-3 max-w-sm mx-auto">{{ brand.bio }}</p>
          <p class="text-xs text-indigo-600 mt-2">{{ partnershipCount }} active partnership{{ partnershipCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- Active offers -->
        <div v-if="offers.length > 0">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Current Offers</h2>
          <div class="space-y-3">
            <div v-for="o in offers" :key="o.uuid" class="bg-white rounded-xl border border-gray-200 px-5 py-4">
              <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-medium text-gray-900">{{ o.title }}</p>
                <span class="text-xs font-bold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">{{ discountBadge(o) }}</span>
              </div>
              <p v-if="o.description" class="text-xs text-gray-500">{{ o.description }}</p>
              <div class="mt-2 bg-gray-50 border border-dashed border-gray-300 rounded-lg px-3 py-2 text-center">
                <span class="font-mono font-bold text-indigo-600 tracking-wider">{{ o.coupon_code }}</span>
              </div>
              <p v-if="o.expiry_date" class="text-xs text-gray-400 mt-2">Valid till {{ new Date(o.expiry_date).toLocaleDateString('en-IN') }}</p>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12 text-gray-400 text-sm">No active offers right now.</div>
      </template>

      <footer class="mt-12 text-center">
        <p class="text-xs text-gray-300">Powered by Hyperlocal Network</p>
      </footer>
    </div>
  </div>
</template>
