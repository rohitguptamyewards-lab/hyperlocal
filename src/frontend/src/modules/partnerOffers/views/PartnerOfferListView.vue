<!--
  PartnerOfferListView.vue — Brand's own offers list + create button.
  Purpose: CRUD for partner offers that can be shown on partner digital bills.
  Owner module: PartnerOffers
  API: GET /api/partner-offers, POST /api/partner-offers/{uuid}/toggle
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()

interface Offer {
  id: number
  uuid: string
  title: string
  coupon_code: string
  discount_type: number
  discount_value: number
  status: number
  expiry_date: string | null
  display_template: string
  active_attachments_count: number
  active_publications_count: number
}

const offers  = ref<Offer[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/partner-offers')
    offers.value = data.data
  } finally {
    loading.value = false
  }
})

async function toggleOffer(offer: Offer) {
  await api.post(`/partner-offers/${offer.uuid}/toggle`)
  offer.status = offer.status === 1 ? 2 : 1
}

function discountLabel(o: Offer): string {
  return o.discount_type === 1 ? `${o.discount_value}% off` : `₹${o.discount_value} off`
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-4xl">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Partner Offers</h1>
        <p class="text-sm text-gray-500 mt-0.5">Create offers that appear on partner digital bills</p>
      </div>
      <button
        v-if="auth.isManager()"
        @click="router.push('/partner-offers/create')"
        class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700"
      >New offer</button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>

    <div v-else-if="offers.length === 0" class="text-center py-20 text-gray-400">
      <p class="text-lg">No offers yet</p>
      <p class="text-sm mt-1">Create your first offer to show on partner bills</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="o in offers"
        :key="o.uuid"
        @click="router.push(`/partner-offers/${o.uuid}`)"
        class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center justify-between cursor-pointer hover:border-indigo-300 hover:shadow-sm transition-all"
        :class="o.status === 2 ? 'opacity-60' : ''"
      >
        <div class="min-w-0 flex-1">
          <p class="font-medium text-gray-900">{{ o.title }}</p>
          <p class="text-xs text-gray-400 mt-0.5">
            Code: <span class="font-mono font-medium text-indigo-600">{{ o.coupon_code }}</span>
            · {{ discountLabel(o) }}
            <span v-if="o.expiry_date"> · Expires {{ new Date(o.expiry_date).toLocaleDateString('en-IN') }}</span>
          </p>
          <p class="text-xs text-gray-300 mt-1">
            {{ o.active_attachments_count }} partnership{{ o.active_attachments_count !== 1 ? 's' : '' }}
            · {{ o.active_publications_count }} network{{ o.active_publications_count !== 1 ? 's' : '' }}
          </p>
        </div>
        <div class="flex items-center gap-3 ml-4">
          <span
            class="text-xs font-medium px-2.5 py-1 rounded-full"
            :class="o.status === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
          >{{ o.status === 1 ? 'Active' : 'Inactive' }}</span>
          <button
            @click.stop="toggleOffer(o)"
            class="text-xs px-3 py-1.5 rounded-lg border transition-colors"
            :class="o.status === 1 ? 'border-gray-300 text-gray-600 hover:bg-gray-50' : 'border-green-300 text-green-700 hover:bg-green-50'"
          >{{ o.status === 1 ? 'Deactivate' : 'Activate' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
