<template>
  <div class="p-4 sm:p-8 max-w-3xl">
    <div v-if="loading" class="text-sm text-gray-400">Loading…</div>

    <div v-else-if="network">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">{{ network.name }}</h1>
          <p v-if="network.description" class="text-sm text-gray-500 mt-1">{{ network.description }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="isOwner"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            @click="showInvite = true"
          >Invite Merchant</button>
          <button
            v-else
            class="px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
            @click="handleLeave"
          >Leave Network</button>
        </div>
      </div>

      <!-- Members -->
      <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="text-sm font-semibold text-gray-900">Members ({{ members.length }})</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="m in members" :key="m.merchant_id" class="px-5 py-3 flex items-center justify-between">
            <div>
              <span class="text-sm font-medium text-gray-900">{{ m.merchant_name }}</span>
              <span v-if="m.is_owner" class="ml-2 text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Owner</span>
            </div>
            <div class="flex items-center gap-3">
              <button
                v-if="m.merchant_id !== auth.user?.merchant_id"
                @click="openPartnershipModal(m)"
                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 font-medium hover:bg-indigo-100 transition-colors"
              >Send Partnership</button>
              <span class="text-xs text-gray-400">Joined {{ formatDate(m.joined_at) }}</span>
            </div>
          </div>
          <div v-if="members.length === 0" class="px-5 py-6 text-sm text-gray-400 text-center">No members yet.</div>
        </div>
      </div>

      <!-- Invite result -->
      <div v-if="inviteResult" class="mt-4 bg-green-50 border border-green-200 rounded-xl px-5 py-4">
        <p class="text-sm font-medium text-green-800 mb-2">Invitation created</p>
        <p class="text-xs text-green-600 mb-2">Share this link with the merchant:</p>
        <div class="flex items-center gap-2">
          <code class="text-xs bg-white border border-green-300 rounded px-3 py-2 flex-1 font-mono break-all">{{ joinUrl(inviteResult.token) }}</code>
          <button class="text-xs px-3 py-2 border border-green-400 rounded hover:bg-green-100" @click="copyLink(inviteResult.token)">{{ copied ? 'Copied!' : 'Copy' }}</button>
        </div>
        <p class="text-xs text-green-500 mt-1">
          Expires {{ formatDate(inviteResult.expires_at) }} · Allows {{ inviteResult.max_uses }} join{{ inviteResult.max_uses === 1 ? '' : 's' }}
          <span v-if="inviteResult.remaining_uses !== null"> · {{ inviteResult.remaining_uses }} remaining</span>
        </p>
      </div>
    </div>

    <!-- Partnership creation modal -->
    <div v-if="showPartnership" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-5 border-b border-gray-100">
          <h2 class="text-lg font-semibold text-gray-900">Send Partnership Proposal</h2>
          <p class="text-sm text-gray-500 mt-0.5">To <span class="font-medium text-gray-700">{{ partnershipTarget?.merchant_name }}</span></p>
        </div>

        <div v-if="partnershipSuccess" class="px-6 py-5">
          <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3">
            <p class="text-sm font-medium text-green-800">Partnership proposal sent!</p>
            <p class="text-xs text-green-600 mt-1">They'll see it in their partnership inbox.</p>
          </div>
          <div class="flex gap-3 mt-4">
            <button
              class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50"
              @click="showPartnership = false"
            >Close</button>
            <button
              class="flex-1 bg-indigo-600 text-white text-sm rounded-lg py-2.5 hover:bg-indigo-700"
              @click="router.push(`/partnerships/${partnershipSuccess}`)"
            >View Partnership</button>
          </div>
        </div>

        <form v-else @submit.prevent="submitPartnership" class="px-6 py-5 space-y-4">
          <div v-if="partnershipError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ partnershipError }}
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Partnership name</label>
            <input
              v-model="partnershipForm.name"
              required
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :placeholder="`e.g. ${auth.user?.name?.split(' ')[0] ?? 'You'} x ${partnershipTarget?.merchant_name ?? 'Partner'}`"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Scope</label>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="partnershipForm.scope_type" :value="1" class="accent-indigo-600" />
                Outlet-level
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="partnershipForm.scope_type" :value="2" class="accent-indigo-600" />
                Brand-wide
              </label>
            </div>
          </div>

          <!-- Outlet picker -->
          <div v-if="partnershipForm.scope_type === 1 && myOutlets.length > 0">
            <label class="block text-sm font-medium text-gray-700 mb-2">Your outlets</label>
            <div class="space-y-1.5">
              <label v-for="o in myOutlets" :key="o.id" class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" :checked="partnershipOutlets.includes(o.id)" @change="toggleOutlet(o.id)" class="accent-indigo-600 rounded" />
                {{ o.name }}
              </label>
            </div>
          </div>

          <div class="border-t border-gray-100 pt-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Terms</p>

            <!-- Tab switcher -->
            <div class="flex rounded-lg overflow-hidden border border-gray-300 text-xs mb-4">
              <button type="button" @click="termsMode = 'link'"
                class="flex-1 px-3 py-2 text-center transition-colors"
                :class="termsMode === 'link' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'">
                Link Existing Offer
              </button>
              <button type="button" @click="termsMode = 'manual'"
                class="flex-1 px-3 py-2 text-center transition-colors"
                :class="termsMode === 'manual' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'">
                Manual Terms
              </button>
            </div>

            <!-- Tab A: Link Existing Offer -->
            <div v-if="termsMode === 'link'" class="space-y-3">
              <div v-if="offersLoading" class="text-xs text-gray-400 text-center py-2">Loading offers...</div>
              <div v-else-if="myOffers.length === 0" class="text-xs text-gray-400 text-center py-2">
                No offers created yet.
                <router-link to="/partner-offers/new" class="text-indigo-600 hover:text-indigo-800 font-medium ml-1">Create one</router-link>
              </div>
              <div v-else>
                <label class="block text-xs text-gray-600 mb-1">Select an offer</label>
                <select v-model="selectedOfferUuid" @change="onOfferSelected"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">-- Choose offer --</option>
                  <option v-for="o in myOffers" :key="o.uuid" :value="o.uuid">
                    {{ o.title }} ({{ o.coupon_code }})
                  </option>
                </select>
              </div>

              <!-- Auto-filled terms preview (read-only when linked) -->
              <div v-if="selectedOfferUuid" class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 space-y-1.5">
                <p class="text-xs font-semibold text-indigo-800">Auto-filled from offer</p>
                <div class="grid grid-cols-2 gap-2 text-xs text-indigo-700">
                  <div v-if="partnershipForm.terms.per_bill_cap_percent">
                    <span class="text-indigo-500">Cap %:</span> {{ partnershipForm.terms.per_bill_cap_percent }}%
                  </div>
                  <div v-if="partnershipForm.terms.per_bill_cap_amount">
                    <span class="text-indigo-500">Cap amount:</span> {{ partnershipForm.terms.per_bill_cap_amount }}
                  </div>
                </div>
                <p class="text-xs text-indigo-400 mt-1">You can still set min bill and monthly cap below.</p>
              </div>

              <!-- Extra fields even in link mode -->
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Min bill amount</label>
                  <input v-model="partnershipForm.terms.min_bill_amount" type="number" min="0" step="1"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Monthly cap</label>
                  <input v-model="partnershipForm.terms.monthly_cap_amount" type="number" min="0" step="1"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 5000" />
                </div>
              </div>
            </div>

            <!-- Tab B: Manual Terms (existing form) -->
            <div v-if="termsMode === 'manual'" class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-600 mb-1">Cap % per bill</label>
                <input v-model="partnershipForm.terms.per_bill_cap_percent" type="number" min="0" max="100" step="0.1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 30" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">Cap per bill</label>
                <input v-model="partnershipForm.terms.per_bill_cap_amount" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 150" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">Min bill amount</label>
                <input v-model="partnershipForm.terms.min_bill_amount" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 200" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">Monthly cap</label>
                <input v-model="partnershipForm.terms.monthly_cap_amount" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 5000" />
              </div>
            </div>
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="showPartnership = false"
              class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Cancel</button>
            <button type="submit" :disabled="partnershipLoading"
              class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
              {{ partnershipLoading ? 'Sending…' : 'Send Proposal' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Invite modal -->
    <div v-if="showInvite" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="font-semibold text-gray-900 mb-4">Invite a Merchant</h3>

        <div v-if="inviteError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-4">{{ inviteError }}</div>

        <div class="space-y-3 mb-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Channel</label>
            <select v-model="inviteForm.channel" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400">
              <option value="link">Shareable link</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="email">Email</option>
            </select>
          </div>
          <div v-if="inviteForm.channel !== 'link'">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ inviteForm.channel === 'email' ? 'Email' : 'Phone number' }}</label>
            <input v-model="inviteForm.contact" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max joins</label>
            <input v-model.number="inviteForm.max_uses" type="number" min="1" max="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
            <p class="text-xs text-gray-400 mt-1">Keep the link reusable until this many brands join.</p>
          </div>
        </div>

        <div class="flex gap-3">
          <button class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50" @click="showInvite = false">Cancel</button>
          <button
            :disabled="inviteLoading"
            class="flex-1 bg-gray-900 text-white text-sm rounded-lg py-2.5 hover:bg-gray-800 disabled:opacity-50"
            @click="submitInvite"
          >{{ inviteLoading ? 'Creating…' : 'Create Invitation' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

interface NetworkMember {
  merchant_id: number
  merchant_name: string
  joined_at: string | null
  is_owner: boolean
}

interface NetworkDetail {
  uuid: string
  name: string
  description: string | null
  owner_merchant_id: number
  status: number
  members: NetworkMember[]
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const network = ref<NetworkDetail | null>(null)
const loading = ref(true)
const members = computed(() => network.value?.members ?? [])
const isOwner = computed(() => network.value?.owner_merchant_id === auth.user?.merchant_id)

// ── Partnership creation from network ───────────────────────
const showPartnership = ref(false)
const partnershipLoading = ref(false)
const partnershipError = ref('')
const partnershipSuccess = ref<string | null>(null)
const partnershipTarget = ref<NetworkMember | null>(null)
const partnershipOutlets = ref<number[]>([])
interface Outlet { id: number; name: string }
const myOutlets = ref<Outlet[]>([])

const partnershipForm = reactive({
  name: '',
  scope_type: 2,
  terms: {
    per_bill_cap_percent: '',
    per_bill_cap_amount: '',
    min_bill_amount: '',
    monthly_cap_amount: '',
  },
})

// ── Link Offer during partnership creation ────────────────
type TermsMode = 'link' | 'manual'
const termsMode = ref<TermsMode>('manual')

interface OfferOption {
  uuid: string
  title: string
  coupon_code: string
  pos_redemption_type: string | null
  flat_discount_amount: number | null
  discount_percentage: number | null
  max_cap_amount: number | null
  discount_type: number
  discount_value: number
}
const myOffers = ref<OfferOption[]>([])
const selectedOfferUuid = ref<string>('')
const offersLoading = ref(false)

async function fetchMyOffers() {
  offersLoading.value = true
  try {
    const res = await api.get('/partner-offers')
    myOffers.value = (res.data.data ?? res.data) as OfferOption[]
  } catch { /* silent */ }
  finally { offersLoading.value = false }
}

function onOfferSelected() {
  const offer = myOffers.value.find(o => o.uuid === selectedOfferUuid.value)
  if (!offer) return
  // Auto-fill terms from the selected offer
  if (offer.pos_redemption_type === 'percentage' && offer.discount_percentage) {
    partnershipForm.terms.per_bill_cap_percent = String(offer.discount_percentage)
    partnershipForm.terms.per_bill_cap_amount = offer.max_cap_amount ? String(offer.max_cap_amount) : ''
  } else if (offer.pos_redemption_type === 'flat' && offer.flat_discount_amount) {
    partnershipForm.terms.per_bill_cap_percent = ''
    partnershipForm.terms.per_bill_cap_amount = String(offer.flat_discount_amount)
  } else {
    // Fallback: use existing discount_type/discount_value
    if (offer.discount_type === 1) {
      partnershipForm.terms.per_bill_cap_percent = String(offer.discount_value)
      partnershipForm.terms.per_bill_cap_amount = ''
    } else {
      partnershipForm.terms.per_bill_cap_percent = ''
      partnershipForm.terms.per_bill_cap_amount = String(offer.discount_value)
    }
  }
}

async function openPartnershipModal(member: NetworkMember) {
  partnershipTarget.value = member
  partnershipError.value = ''
  partnershipSuccess.value = null
  partnershipOutlets.value = []
  partnershipForm.name = ''
  partnershipForm.scope_type = 2
  partnershipForm.terms = { per_bill_cap_percent: '', per_bill_cap_amount: '', min_bill_amount: '', monthly_cap_amount: '' }
  termsMode.value = 'manual'
  selectedOfferUuid.value = ''
  showPartnership.value = true
  try {
    const [outletsRes] = await Promise.all([
      api.get('/outlets'),
      fetchMyOffers(),
    ])
    myOutlets.value = outletsRes.data.data ?? outletsRes.data
  } catch { /* silent */ }
}

function toggleOutlet(id: number) {
  const idx = partnershipOutlets.value.indexOf(id)
  if (idx === -1) partnershipOutlets.value.push(id)
  else partnershipOutlets.value.splice(idx, 1)
}

async function submitPartnership() {
  if (!partnershipTarget.value) return
  partnershipLoading.value = true
  partnershipError.value = ''
  try {
    const payload: Record<string, unknown> = {
      name: partnershipForm.name,
      scope_type: partnershipForm.scope_type,
      partner_merchant_id: partnershipTarget.value.merchant_id,
    }
    if (partnershipForm.scope_type === 1 && partnershipOutlets.value.length > 0) {
      payload.proposer_outlet_ids = partnershipOutlets.value
    }
    const terms: Record<string, unknown> = {}
    if (partnershipForm.terms.per_bill_cap_percent) terms.per_bill_cap_percent = Number(partnershipForm.terms.per_bill_cap_percent)
    if (partnershipForm.terms.per_bill_cap_amount) terms.per_bill_cap_amount = Number(partnershipForm.terms.per_bill_cap_amount)
    if (partnershipForm.terms.min_bill_amount) terms.min_bill_amount = Number(partnershipForm.terms.min_bill_amount)
    if (partnershipForm.terms.monthly_cap_amount) terms.monthly_cap_amount = Number(partnershipForm.terms.monthly_cap_amount)
    if (Object.keys(terms).length) payload.terms = terms
    if (termsMode.value === 'link' && selectedOfferUuid.value) {
      payload.linked_offer_uuid = selectedOfferUuid.value
    }

    const res = await api.post('/partnerships', payload)
    const created = res.data.data ?? res.data
    partnershipSuccess.value = created.uuid ?? created.id
  } catch (e: any) {
    partnershipError.value = e.response?.data?.message ?? 'Failed to create partnership.'
  } finally {
    partnershipLoading.value = false
  }
}

const showInvite = ref(false)
const inviteLoading = ref(false)
const inviteError = ref('')
const inviteForm = reactive({ channel: 'link', contact: '', max_uses: 10 })

interface InviteResult {
  token: string
  expires_at: string
  max_uses: number
  remaining_uses: number | null
}
const inviteResult = ref<InviteResult | null>(null)
const copied = ref(false)

async function fetchNetwork() {
  loading.value = true
  try {
    const res = await api.get(`/merchant/networks/${route.params.uuid}`)
    network.value = res.data.network
  } finally {
    loading.value = false
  }
}

async function submitInvite() {
  inviteLoading.value = true
  inviteError.value = ''
  try {
    const res = await api.post(`/merchant/networks/${network.value!.uuid}/invite`, {
      channel: inviteForm.channel,
      contact: inviteForm.contact || null,
      max_uses: inviteForm.max_uses,
    })
    inviteResult.value = {
      token: res.data.token,
      expires_at: res.data.expires_at,
      max_uses: res.data.max_uses,
      remaining_uses: res.data.remaining_uses,
    }
    showInvite.value = false
  } catch (err: any) {
    const errors = err.response?.data?.errors
    inviteError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to create invitation.')
  } finally {
    inviteLoading.value = false
  }
}

async function handleLeave() {
  if (!confirm('Leave this network?')) return
  await api.post(`/merchant/networks/${network.value!.uuid}/leave`)
  router.push('/networks')
}

function joinUrl(token: string) {
  return `${window.location.origin}/networks/join/${token}`
}

async function copyLink(token: string) {
  await navigator.clipboard.writeText(joinUrl(token))
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(fetchNetwork)
</script>
