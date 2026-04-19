<!--
  PartnerOfferDetailView.vue — Offer distribution: attach to partnerships, publish to networks.
  Purpose: Shows offer details, attachment/publication toggles, and impression/claim stats.
  Owner module: PartnerOffers
  API: GET /api/partner-offers/{uuid}, POST attach/detach, POST publish/unpublish
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const uuid   = route.params.uuid as string

interface Offer {
  id: number; uuid: string; title: string; coupon_code: string;
  discount_type: number; discount_value: number; status: number;
  description: string | null; expiry_date: string | null; display_template: string;
  attachments: { id: number; partnership_id: number; is_active: boolean; partnership?: { id: number; uuid: string; name: string } }[];
  network_publications: { id: number; network_id: number; is_active: boolean; network?: { id: number; uuid: string; name: string } }[];
}

const offer   = ref<Offer | null>(null)
const stats   = ref({ impressions: 0, claims: 0 })
const loading = ref(true)
const error   = ref<string | null>(null)

// Available partnerships and networks for attaching/publishing
const partnerships = ref<{ uuid: string; name: string }[]>([])
const networks     = ref<{ uuid: string; name: string }[]>([])

async function fetchOffer(): Promise<void> {
  const { data } = await api.get(`/partner-offers/${uuid}`)
  offer.value = data.data
  stats.value = data.stats
}

async function fetchDistributionOptions(): Promise<void> {
  const [partRes, netRes] = await Promise.all([
    api.get('/partnerships', { params: { status: 5 } }),
    api.get('/merchant/networks'),
  ])

  partnerships.value = (partRes.data.data ?? [])
    .map((p: any) => ({
      uuid: p.uuid ?? p.id,
      name: p.partner_name ?? p.name ?? 'Partnership',
    }))
    .filter((p: { uuid: string }) => !!p.uuid)

  networks.value = (netRes.data.data ?? [])
    .map((n: any) => ({
      uuid: n.uuid ?? n.id,
      name: n.name,
    }))
    .filter((n: { uuid: string }) => !!n.uuid)
}

onMounted(async () => {
  try {
    await Promise.all([fetchOffer(), fetchDistributionOptions()])
  } catch {
    error.value = 'Failed to load offer.'
  } finally {
    loading.value = false
  }
})

function isAttached(partnershipUuid: string): boolean {
  return offer.value?.attachments?.some((attachment) => attachment.is_active && attachment.partnership?.uuid === partnershipUuid) ?? false
}

function isPublished(networkUuid: string): boolean {
  return offer.value?.network_publications?.some((publication) => publication.is_active && publication.network?.uuid === networkUuid) ?? false
}

async function toggleAttachment(partnershipUuid: string) {
  if (!offer.value) return
  if (isAttached(partnershipUuid)) {
    await api.delete(`/partner-offers/${uuid}/attach/${partnershipUuid}`)
  } else {
    await api.post(`/partner-offers/${uuid}/attach`, { partnership_id: partnershipUuid })
  }
  await fetchOffer()
}

async function togglePublication(networkUuid: string) {
  if (!offer.value) return
  if (isPublished(networkUuid)) {
    await api.delete(`/partner-offers/${uuid}/publish/${networkUuid}`)
  } else {
    await api.post(`/partner-offers/${uuid}/publish`, { network_id: networkUuid })
  }
  await fetchOffer()
}

function discountLabel(): string {
  if (!offer.value) return ''
  return offer.value.discount_type === 1 ? `${offer.value.discount_value}% off` : `₹${offer.value.discount_value} off`
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-3xl">
    <button @click="router.push('/partner-offers')" class="text-sm text-gray-500 hover:text-gray-700 mb-4 flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
      Back to offers
    </button>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>
    <div v-else-if="error" class="text-sm text-red-600 py-4">{{ error }}</div>

    <template v-else-if="offer">
      <!-- Offer header -->
      <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ offer.title }}</h1>
            <p class="text-sm text-gray-400 mt-1">
              Code: <span class="font-mono font-medium text-indigo-600">{{ offer.coupon_code }}</span>
              · {{ discountLabel() }}
              <span v-if="offer.expiry_date"> · Expires {{ new Date(offer.expiry_date).toLocaleDateString('en-IN') }}</span>
            </p>
            <p v-if="offer.description" class="text-sm text-gray-500 mt-2">{{ offer.description }}</p>
          </div>
          <button @click="router.push(`/partner-offers/${uuid}/edit`)" class="text-xs border border-gray-300 text-gray-600 px-3 py-1.5 rounded-lg hover:bg-gray-50">Edit</button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
          <div class="text-center">
            <p class="text-2xl font-bold text-gray-900">{{ stats.impressions }}</p>
            <p class="text-xs text-gray-400">Impressions</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ stats.claims }}</p>
            <p class="text-xs text-gray-400">Code copies</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold" :class="stats.impressions > 0 ? 'text-green-600' : 'text-gray-400'">
              {{ stats.impressions > 0 ? ((stats.claims / stats.impressions) * 100).toFixed(1) + '%' : '—' }}
            </p>
            <p class="text-xs text-gray-400">Conversion</p>
          </div>
        </div>
      </div>

      <!-- Partnership attachments -->
      <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Partnership distribution</h2>
        <p class="text-xs text-gray-400 mb-4">Toggle ON to show this offer on your partner's digital bills.</p>

        <div v-if="partnerships.length === 0" class="text-xs text-gray-400">No live partnerships available.</div>
        <div v-else class="space-y-2">
          <div v-for="p in partnerships" :key="p.uuid" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <span class="text-sm text-gray-900">{{ p.name }}</span>
            <button
              @click="toggleAttachment(p.uuid)"
              class="text-xs px-3 py-1.5 rounded-lg border transition-colors"
              :class="isAttached(p.uuid) ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-300 text-gray-500 hover:bg-gray-50'"
            >{{ isAttached(p.uuid) ? 'Attached' : 'Attach' }}</button>
          </div>
        </div>
      </div>

      <!-- Network publications -->
      <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Network distribution</h2>
        <p class="text-xs text-gray-400 mb-4">Publish to a network so any member can choose to display your offer.</p>

        <div v-if="networks.length === 0" class="text-xs text-gray-400">You're not in any networks yet.</div>
        <div v-else class="space-y-2">
          <div v-for="n in networks" :key="n.uuid" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <span class="text-sm text-gray-900">{{ n.name }}</span>
            <button
              @click="togglePublication(n.uuid)"
              class="text-xs px-3 py-1.5 rounded-lg border transition-colors"
              :class="isPublished(n.uuid) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-500 hover:bg-gray-50'"
            >{{ isPublished(n.uuid) ? 'Published' : 'Publish' }}</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
