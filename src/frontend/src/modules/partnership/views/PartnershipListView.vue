<!--
  PartnershipListView.vue — List all partnerships + create modal.
  Purpose: Main partnership management screen for merchant admins.
  Owner module: Partnership
  API: GET /api/partnerships, POST /api/partnerships, GET /api/merchants
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePartnershipStore, type Partnership } from '@/stores/partnership'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const store  = usePartnershipStore()
const auth   = useAuthStore()
const router = useRouter()

// ── Create modal state ──────────────────────────────────────
const showCreate    = ref(false)
const createError   = ref<string | null>(null)
const createLoading = ref(false)

const form = ref({
  name: '',
  scope_type: 1,
  partner_merchant_id: '' as string | number,
  offer_structure: 'same' as 'same' | 'different',
  proposer_offer: {
    offer_mode:      'manual' as 'manual' | 'linked',
    linked_offer_id: '' as string | number,
    pos_type:        'flat' as 'flat' | 'percentage',
    flat_amount:     '',
    percentage:      '',
    max_cap:         '',
    min_bill:        '',
    monthly_cap:     '',
  },
  terms: {
    pos_type:           'flat' as 'flat' | 'percentage',
    flat_amount:        '',
    percentage:         '',
    max_cap_enabled:    false,
    max_cap_amount:     '',
    min_bill_amount:    '',
    monthly_cap_amount: '',
  },
})

interface Merchant    { id: number; name: string }
interface Outlet      { id: number; name: string; city?: string }
interface PartnerOffer { id: number; name: string }

const merchants         = ref<Merchant[]>([])
const myOutlets         = ref<Outlet[]>([])
const partnerOutlets    = ref<Outlet[]>([])
const partnerOffers     = ref<PartnerOffer[]>([])
const mySelectedOutlets = ref<number[]>([])
const partnerSelectedOutlets = ref<number[]>([])
const loadingPartnerOutlets  = ref(false)

// Tooltip visibility
const activeTooltip = ref<string | null>(null)

async function loadMerchants() {
  const [merchantRes, outletRes, offersRes] = await Promise.all([
    api.get<Merchant[]>('/merchants'),
    api.get<Outlet[]>('/outlets'),
    api.get<PartnerOffer[]>('/partner-offers').catch(() => ({ data: [] as PartnerOffer[] })),
  ])
  merchants.value  = merchantRes.data
  myOutlets.value  = outletRes.data
  partnerOffers.value = offersRes.data
}

async function onPartnerChange() {
  partnerOutlets.value = []
  partnerSelectedOutlets.value = []
  if (!form.value.partner_merchant_id) return
  loadingPartnerOutlets.value = true
  try {
    const res = await api.get<Outlet[]>(`/merchants/${form.value.partner_merchant_id}/outlets`)
    partnerOutlets.value = res.data
  } catch { /* non-blocking */ } finally {
    loadingPartnerOutlets.value = false
  }
}

function toggleMyOutlet(id: number) {
  const idx = mySelectedOutlets.value.indexOf(id)
  if (idx === -1) mySelectedOutlets.value.push(id)
  else mySelectedOutlets.value.splice(idx, 1)
}

function togglePartnerOutlet(id: number) {
  const idx = partnerSelectedOutlets.value.indexOf(id)
  if (idx === -1) partnerSelectedOutlets.value.push(id)
  else partnerSelectedOutlets.value.splice(idx, 1)
}

async function submitCreate() {
  createError.value   = null
  createLoading.value = true
  try {
    const payload: Record<string, unknown> = {
      name: form.value.name,
      scope_type: form.value.scope_type,
      partner_merchant_id: Number(form.value.partner_merchant_id),
      offer_structure: form.value.offer_structure,
    }
    if (form.value.scope_type === 1 && mySelectedOutlets.value.length > 0)
      payload.proposer_outlet_ids = mySelectedOutlets.value
    if (form.value.scope_type === 1 && partnerSelectedOutlets.value.length > 0)
      payload.acceptor_outlet_ids = partnerSelectedOutlets.value

    // If different offers — send proposer's offer config
    if (form.value.offer_structure === 'different') {
      const po = form.value.proposer_offer
      if (po.offer_mode === 'linked' && po.linked_offer_id) {
        // Linked to a saved partner offer
        payload.proposer_offer = { pos_type: 'flat', linked_offer_id: Number(po.linked_offer_id) }
      } else {
        // Manual configuration
        const proposerOffer: Record<string, unknown> = { pos_type: po.pos_type }
        if (po.pos_type === 'flat' && po.flat_amount)        proposerOffer.flat_amount = Number(po.flat_amount)
        if (po.pos_type === 'percentage' && po.percentage)   proposerOffer.percentage  = Number(po.percentage)
        if (po.max_cap)     proposerOffer.max_cap     = Number(po.max_cap)
        if (po.min_bill)    proposerOffer.min_bill    = Number(po.min_bill)
        if (po.monthly_cap) proposerOffer.monthly_cap = Number(po.monthly_cap)
        payload.proposer_offer = proposerOffer
      }
    }

    // If same offer — send shared terms
    if (form.value.offer_structure === 'same') {
      const t = form.value.terms
      const terms: Record<string, unknown> = {}
      if (t.pos_type === 'flat' && t.flat_amount)
        terms.per_bill_cap_amount = Number(t.flat_amount)
      if (t.pos_type === 'percentage' && t.percentage)
        terms.per_bill_cap_percent = Number(t.percentage)
      if (t.pos_type === 'percentage' && t.max_cap_enabled && t.max_cap_amount)
        terms.partner_monthly_cap = Number(t.max_cap_amount)
      if (t.min_bill_amount)    terms.min_bill_amount    = Number(t.min_bill_amount)
      if (t.monthly_cap_amount) terms.monthly_cap_amount = Number(t.monthly_cap_amount)
      if (Object.keys(terms).length) payload.terms = terms
    }

    const p = await store.create(payload)
    showCreate.value = false
    router.push(`/partnerships/${p.id}`)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    createError.value = err.response?.data?.message ?? 'Failed to create partnership.'
  } finally {
    createLoading.value = false
  }
}

function openCreate() {
  form.value = {
    name: '', scope_type: 1, partner_merchant_id: '',
    offer_structure: 'same',
    proposer_offer: { offer_mode: 'manual', linked_offer_id: '', pos_type: 'flat', flat_amount: '', percentage: '', max_cap: '', min_bill: '', monthly_cap: '' },
    terms: { pos_type: 'flat', flat_amount: '', percentage: '', max_cap_enabled: false, max_cap_amount: '', min_bill_amount: '', monthly_cap_amount: '' },
  }
  mySelectedOutlets.value = []
  partnerSelectedOutlets.value = []
  partnerOutlets.value = []
  createError.value = null
  showCreate.value = true
  loadMerchants()
}

// ── Pause / Resume quick toggle ─────────────────────────────
const toggling = ref<string | null>(null)

async function doToggle(p: Partnership, e: Event) {
  e.stopPropagation()
  toggling.value = p.id
  try {
    const action = p.status === 5 ? 'pause' : 'resume'
    await store.transition(p.id, action)
  } catch {
    // non-blocking — user can go to detail for details
  } finally {
    toggling.value = null
  }
}

// ── Status badge ────────────────────────────────────────────
const statusClass = (status: number) => ({
  1: 'bg-yellow-100 text-yellow-800',
  2: 'bg-blue-100 text-blue-800',
  3: 'bg-orange-100 text-orange-800',
  4: 'bg-purple-100 text-purple-800',
  5: 'bg-green-100 text-green-800',
  6: 'bg-gray-100 text-gray-600',
  7: 'bg-red-100 text-red-700',
  8: 'bg-red-100 text-red-700',
  9: 'bg-gray-200 text-gray-500',
}[status] ?? 'bg-gray-100 text-gray-600')

onMounted(() => {
  store.fetchList()
  // Pre-fill create modal when arriving from Find Partners
  const state = window.history.state as { prefillMerchantId?: number; prefillMerchantName?: string } | null
  if (state?.prefillMerchantId) {
    openCreate()
    form.value.partner_merchant_id = state.prefillMerchantId
    onPartnerChange()
    // Clear so refresh doesn't re-open
    window.history.replaceState({}, '')
  }
})
</script>

<template>
  <div class="p-4 sm:p-8">
    <div class="flex items-start justify-between mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Partnerships</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage your brand partnerships</p>
      </div>
      <button
        v-if="auth.isManager()"
        @click="openCreate"
        class="flex-shrink-0 whitespace-nowrap bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors"
      >
        New partnership
      </button>
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="text-sm text-gray-500 py-12 text-center">Loading…</div>

    <!-- Error -->
    <div v-else-if="store.error" class="text-sm text-red-600 py-4">{{ store.error }}</div>

    <!-- Empty -->
    <div v-else-if="store.list.length === 0" class="text-center py-20 text-gray-400">
      <p class="text-lg">No partnerships yet</p>
      <p class="text-sm mt-1">Create your first partnership to get started</p>
    </div>

    <!-- List -->
    <div v-else class="space-y-3">
      <div
        v-for="p in store.list"
        :key="p.id"
        @click="router.push(`/partnerships/${p.id}`)"
        class="flex items-center justify-between bg-white border rounded-xl px-5 py-4 transition-all cursor-pointer"
        :class="p.status === 9
          ? 'border-gray-200 bg-gray-50 opacity-60 hover:border-gray-300'
          : 'border-gray-200 hover:border-indigo-300 hover:shadow-sm ' + (p.status === 6 ? 'opacity-70' : '')"
      >
        <div class="min-w-0 flex-1">
          <p class="font-medium text-gray-900">{{ p.name }}</p>
          <p class="text-xs text-gray-400 mt-0.5">
            {{ p.is_brand_level ? 'Brand-wide' : 'Outlet-level' }}
            · Updated {{ new Date(p.updated_at).toLocaleDateString() }}
            <span v-if="p.paused_at" class="text-yellow-600"> · Paused</span>
          </p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0 ml-4">
          <span
            class="text-xs font-medium px-2.5 py-1 rounded-full"
            :class="statusClass(p.status)"
          >
            {{ p.status_label }}
          </span>
          <!-- Quick pause/resume toggle — LIVE or PAUSED, managers only -->
          <button
            v-if="[5, 6].includes(p.status) && auth.isManager()"
            @click.stop="doToggle(p, $event)"
            :disabled="toggling === p.id"
            class="text-xs px-3 py-1.5 rounded-lg border transition-colors disabled:opacity-50 whitespace-nowrap"
            :class="p.status === 5
              ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50'
              : 'border-green-300 text-green-700 hover:bg-green-50'"
            :title="p.status === 5 ? 'Pause this partnership' : 'Resume this partnership'"
          >
            {{ toggling === p.id ? '…' : (p.status === 5 ? 'Pause' : 'Resume') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Create modal -->
  <Teleport to="body">
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-5 border-b border-gray-100">
          <h2 class="text-lg font-semibold text-gray-900">New partnership</h2>
        </div>

        <form @submit.prevent="submitCreate" class="px-6 py-5 space-y-4">
          <div v-if="createError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ createError }}
          </div>

          <!-- Partnership name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Partnership name</label>
            <input v-model="form.name" required type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="e.g. Brew x FitZone Bandra" />
          </div>

          <!-- Partner brand -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Partner brand</label>
            <select v-model="form.partner_merchant_id" required @change="onPartnerChange"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="" disabled>Select a brand</option>
              <option v-for="m in merchants" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>

          <!-- Scope -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Scope</label>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.scope_type" :value="1" class="accent-indigo-600" /> Outlet-level
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.scope_type" :value="2" class="accent-indigo-600" /> Brand-wide
              </label>
            </div>
          </div>

          <!-- Outlet pickers — only for outlet-level scope -->
          <div v-if="form.scope_type === 1" class="space-y-3">

            <!-- My outlets — always shows own outlets regardless of partner picked -->
            <div v-if="myOutlets.length > 0" class="border border-gray-200 rounded-xl p-3">
              <p class="text-xs font-semibold text-gray-600 mb-0.5">Your outlets joining this partnership</p>
              <p class="text-xs text-gray-400 mb-2">These are your own outlets — they don't change based on which partner you pick.</p>
              <div class="space-y-1.5">
                <label v-for="o in myOutlets" :key="o.id" class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <input type="checkbox" :checked="mySelectedOutlets.includes(o.id)" @change="toggleMyOutlet(o.id)" class="accent-indigo-600 rounded" />
                  {{ o.name }}
                </label>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">Leave all unchecked to include all your outlets.</p>
            </div>

            <!-- Partner outlets — only shown after a partner brand is selected -->
            <div v-if="Number(form.partner_merchant_id) > 0" class="border border-gray-200 rounded-xl p-3">
              <p class="text-xs font-semibold text-gray-600 mb-2">Partner's outlets in this partnership</p>
              <div v-if="loadingPartnerOutlets" class="text-xs text-gray-400 py-2">Loading…</div>
              <div v-else-if="partnerOutlets.length === 0" class="text-xs text-gray-400 py-1">No active outlets found for this brand.</div>
              <div v-else class="space-y-1.5">
                <label v-for="o in partnerOutlets" :key="o.id" class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <input type="checkbox" :checked="partnerSelectedOutlets.includes(o.id)" @change="togglePartnerOutlet(o.id)" class="accent-indigo-600 rounded" />
                  {{ o.name }}<span v-if="o.city" class="text-xs text-gray-400 ml-1">· {{ o.city }}</span>
                </label>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">Leave all unchecked to include all partner outlets.</p>
            </div>

            <!-- Placeholder shown before a partner brand is selected -->
            <div v-else class="border border-dashed border-gray-200 rounded-xl p-3 text-xs text-gray-400 text-center">
              Select a partner brand above to choose their outlets.
            </div>

          </div>

          <!-- ── Offer structure ─────────────────────────────── -->
          <div class="border-t border-gray-100 pt-4 space-y-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Offer structure</p>
            <div class="flex flex-col gap-2.5">
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="radio" v-model="form.offer_structure" value="same" class="accent-indigo-600 mt-0.5 flex-shrink-0" />
                <span>
                  <span class="text-sm font-medium text-gray-800">Same offer for both brands</span>
                  <span class="block text-xs text-gray-400 mt-0.5">Both brands give the same discount to each other's customers.</span>
                </span>
              </label>
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="radio" v-model="form.offer_structure" value="different" class="accent-indigo-600 mt-0.5 flex-shrink-0" />
                <span>
                  <span class="text-sm font-medium text-gray-800">Different offer for each brand</span>
                  <span class="block text-xs text-gray-400 mt-0.5">Each brand sets their own discount independently.</span>
                </span>
              </label>
            </div>
          </div>

          <!-- ── Your offer (only when offer_structure = 'different') ── -->
          <div v-if="form.offer_structure === 'different'"
            class="border border-indigo-100 bg-indigo-50/40 rounded-xl p-4 space-y-3">
            <p class="text-xs font-semibold text-gray-700">Your offer to partner's customers</p>

            <!-- Link existing offer OR configure manually -->
            <div class="flex gap-4">
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.proposer_offer.offer_mode" value="manual" class="accent-indigo-600" />
                Configure manually
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.proposer_offer.offer_mode" value="linked" class="accent-indigo-600" />
                Link existing offer
              </label>
            </div>

            <!-- Linked offer picker -->
            <div v-if="form.proposer_offer.offer_mode === 'linked'">
              <label class="block text-xs font-medium text-gray-700 mb-1">Select saved offer</label>
              <select v-model="form.proposer_offer.linked_offer_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" disabled>— choose an offer —</option>
                <option v-for="o in partnerOffers" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
              <p v-if="partnerOffers.length === 0" class="text-xs text-amber-600 mt-1.5">
                No saved offers found.
                <router-link to="/partner-offers/new" class="underline text-indigo-600">Create one</router-link>
                first, or switch to manual.
              </p>
            </div>

            <!-- Manual config -->
            <template v-if="form.proposer_offer.offer_mode === 'manual'">
              <!-- Discount type -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Discount type</label>
                <select v-model="form.proposer_offer.pos_type"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="flat">Flat amount (₹)</option>
                  <option value="percentage">Percentage (%)</option>
                </select>
              </div>

              <!-- Flat -->
              <div v-if="form.proposer_offer.pos_type === 'flat'">
                <label class="block text-xs font-medium text-gray-700 mb-1">Discount amount per bill (₹)</label>
                <input v-model="form.proposer_offer.flat_amount" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. 150" />
              </div>

              <!-- Percentage -->
              <div v-if="form.proposer_offer.pos_type === 'percentage'">
                <label class="block text-xs font-medium text-gray-700 mb-1">Discount percentage (%)</label>
                <input v-model="form.proposer_offer.percentage" type="number" min="0" max="100" step="0.1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. 20" />
              </div>

              <!-- Max cap per bill — only for % discount -->
              <div v-if="form.proposer_offer.pos_type === 'percentage'">
                <label class="block text-xs font-medium text-gray-700 mb-1">
                  Max cap per bill (₹) <span class="text-gray-400 font-normal">optional</span>
                </label>
                <input v-model="form.proposer_offer.max_cap" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Leave blank for no cap" />
              </div>

              <!-- Min bill -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                  Minimum bill amount (₹) <span class="text-gray-400 font-normal">optional</span>
                </label>
                <input v-model="form.proposer_offer.min_bill" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. 200" />
              </div>

              <!-- Monthly cap per customer with tooltip -->
              <div>
                <div class="flex items-center gap-1.5 mb-1">
                  <label class="text-xs font-medium text-gray-700">
                    Monthly cap per customer (₹) <span class="text-gray-400 font-normal">optional</span>
                  </label>
                  <div class="relative">
                    <button type="button"
                      @mouseenter="activeTooltip = 'po_monthly_cap'"
                      @mouseleave="activeTooltip = null"
                      @click="activeTooltip = activeTooltip === 'po_monthly_cap' ? null : 'po_monthly_cap'"
                      class="w-4 h-4 rounded-full bg-gray-200 text-gray-500 text-xs flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-600 focus:outline-none">
                      i
                    </button>
                    <div v-if="activeTooltip === 'po_monthly_cap'"
                      class="absolute left-5 top-0 z-10 w-56 rounded-lg bg-gray-900 text-white text-xs px-3 py-2 shadow-lg leading-relaxed">
                      Maximum total discount a single customer can receive from this partnership in one calendar month. E.g. ₹1,000 means once a customer has claimed ₹1,000 this month, no further discount applies.
                      <span class="absolute -left-1.5 top-2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-t-transparent border-b-transparent border-r-gray-900"></span>
                    </div>
                  </div>
                </div>
                <input v-model="form.proposer_offer.monthly_cap" type="number" min="0" step="1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. 1000" />
              </div>
            </template>

            <p class="text-xs text-indigo-500 pt-1">
              After accepting, the partner brand will fill in their own offer.
            </p>
          </div>

          <!-- ── Shared terms (only when offer_structure = 'same') ───── -->
          <div v-if="form.offer_structure === 'same'" class="border-t border-gray-100 pt-4 space-y-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Terms (optional)</p>

            <!-- Discount type -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Discount type</label>
              <select v-model="form.terms.pos_type"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="flat">Flat amount (₹)</option>
                <option value="percentage">Percentage (%)</option>
              </select>
            </div>

            <!-- Flat amount -->
            <div v-if="form.terms.pos_type === 'flat'">
              <label class="block text-xs font-medium text-gray-700 mb-1">Discount amount per bill (₹)</label>
              <input v-model="form.terms.flat_amount" type="number" min="0" step="1"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="e.g. 150" />
            </div>

            <!-- Percentage + max cap -->
            <div v-if="form.terms.pos_type === 'percentage'" class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Discount percentage (%)</label>
                <input v-model="form.terms.percentage" type="number" min="0" max="100" step="0.1"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. 20" />
              </div>
              <!-- Max cap per bill — only for % discount -->
              <template v-if="form.terms.pos_type === 'percentage'">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-medium text-gray-700">Max cap per bill (₹)</label>
                  <button type="button" @click="form.terms.max_cap_enabled = !form.terms.max_cap_enabled"
                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none"
                    :class="form.terms.max_cap_enabled ? 'bg-indigo-600' : 'bg-gray-300'">
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                      :class="form.terms.max_cap_enabled ? 'translate-x-5' : 'translate-x-1'" />
                  </button>
                </div>
                <div v-if="form.terms.max_cap_enabled">
                  <input v-model="form.terms.max_cap_amount" type="number" min="0" step="1"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="e.g. 300" />
                </div>
              </template>
            </div>

            <!-- Min bill -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Minimum bill amount (₹)</label>
              <input v-model="form.terms.min_bill_amount" type="number" min="0" step="1"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="e.g. 200" />
            </div>

            <!-- Monthly cap per customer with tooltip -->
            <div>
              <div class="flex items-center gap-1.5 mb-1">
                <label class="text-xs font-medium text-gray-700">Monthly discount cap per customer (₹)</label>
                <div class="relative">
                  <button type="button"
                    @mouseenter="activeTooltip = 'monthly_cap'"
                    @mouseleave="activeTooltip = null"
                    @click="activeTooltip = activeTooltip === 'monthly_cap' ? null : 'monthly_cap'"
                    class="w-4 h-4 rounded-full bg-gray-200 text-gray-500 text-xs flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-600 focus:outline-none">
                    i
                  </button>
                  <div v-if="activeTooltip === 'monthly_cap'"
                    class="absolute left-5 top-0 z-10 w-56 rounded-lg bg-gray-900 text-white text-xs px-3 py-2 shadow-lg leading-relaxed">
                    In a given month, a single customer can receive at most this much total discount — across one or multiple transactions. E.g. if set to ₹1,000, once a customer has received ₹1,000 in discounts this month, no further discounts apply until next month.
                    <span class="absolute -left-1.5 top-2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-t-transparent border-b-transparent border-r-gray-900"></span>
                  </div>
                </div>
              </div>
              <input v-model="form.terms.monthly_cap_amount" type="number" min="0" step="1"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="e.g. 1000" />
            </div>
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="showCreate = false"
              class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
              Cancel
            </button>
            <button type="submit" :disabled="createLoading"
              class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
              {{ createLoading ? 'Creating…' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
