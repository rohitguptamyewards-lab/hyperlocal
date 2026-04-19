<!--
  MerchantDetailView.vue — Super Admin merchant drill-down.
  Purpose: Full merchant profile, WA credit balance + ledger history,
           partnership summary, eWards status, ecosystem flag.
  Owner module: superAdmin
  API: GET /super-admin/merchants/{id}
       GET /super-admin/merchants/{id}/ledger
       POST /super-admin/merchants/{id}/credits
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import superAdminApi from '@/services/superAdminApi'

const route  = useRoute()
const router = useRouter()
const id     = route.params.id as string

interface Merchant {
  id: number
  uuid: string
  name: string
  email: string
  phone: string | null
  category: string | null
  city: string | null
  state: string | null
  is_active: boolean
  open_to_partnerships: boolean
  ecosystem_active: boolean
  whatsapp_balance: number
  low_balance_alerted: boolean
  outlet_count: number
  live_partnership_count: number
  ewards_status: string | null
}

interface LedgerEntry {
  id: number
  entry_type: 'allocation' | 'consumption'
  credits_delta: number
  balance_after: number
  reference_type: string | null
  note: string | null
  allocated_by: number | null
  created_at: string
}

const merchant     = ref<Merchant | null>(null)
const ledger       = ref<LedgerEntry[]>([])
const ledgerMeta   = ref({ current_page: 1, last_page: 1 })
const loading      = ref(true)
const ledgerLoading = ref(false)
const error        = ref('')

// Credit modal
const creditModal = ref({ open: false, amount: '', note: '', loading: false, error: '' })

// Edit modal
const editModal = ref({
  open: false,
  loading: false,
  error: '',
  form: {
    name: '',
    email: '',
    phone: '',
    category: '',
    city: '',
    state: '',
    is_active: true,
    open_to_partnerships: true,
    ecosystem_active: true,
  },
})

function openEditModal() {
  if (!merchant.value) return
  editModal.value.form = {
    name: merchant.value.name,
    email: merchant.value.email ?? '',
    phone: merchant.value.phone ?? '',
    category: merchant.value.category ?? '',
    city: merchant.value.city ?? '',
    state: merchant.value.state ?? '',
    is_active: merchant.value.is_active,
    open_to_partnerships: merchant.value.open_to_partnerships,
    ecosystem_active: merchant.value.ecosystem_active,
  }
  editModal.value.error = ''
  editModal.value.open = true
}

async function saveEdit() {
  editModal.value.loading = true
  editModal.value.error = ''
  try {
    await superAdminApi.put(`/merchants/${id}`, editModal.value.form)
    editModal.value.open = false
    await fetchMerchant()
  } catch (e: any) {
    const errors = e?.response?.data?.errors
    editModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e?.response?.data?.message ?? 'Failed to update.')
  } finally {
    editModal.value.loading = false
  }
}

async function fetchMerchant() {
  loading.value = true
  error.value   = ''
  try {
    const res     = await superAdminApi.get(`/merchants/${id}`)
    merchant.value = res.data.data ?? res.data
  } catch {
    error.value = 'Failed to load merchant.'
  } finally {
    loading.value = false
  }
}

async function fetchLedger(page = 1) {
  ledgerLoading.value = true
  try {
    const res = await superAdminApi.get(`/merchants/${id}/ledger`, { params: { page } })
    ledger.value     = res.data.data
    ledgerMeta.value = res.data.meta ?? { current_page: res.data.current_page, last_page: res.data.last_page }
  } finally {
    ledgerLoading.value = false
  }
}

async function allocateCredits() {
  const amount = parseInt(creditModal.value.amount)
  if (!amount || amount <= 0) {
    creditModal.value.error = 'Enter a positive amount.'
    return
  }
  creditModal.value.loading = true
  creditModal.value.error   = ''
  try {
    await superAdminApi.post(`/merchants/${id}/credits`, {
      amount,
      note: creditModal.value.note || undefined,
    })
    creditModal.value.open   = false
    creditModal.value.amount = ''
    creditModal.value.note   = ''
    await fetchMerchant()
    await fetchLedger()
  } catch (e: any) {
    creditModal.value.error = e?.response?.data?.message ?? 'Failed to allocate credits.'
  } finally {
    creditModal.value.loading = false
  }
}

function balanceClass(balance: number) {
  if (balance === 0)  return 'text-red-600 font-bold'
  if (balance <= 50)  return 'text-orange-500 font-semibold'
  return 'text-green-600 font-semibold'
}

function entryTypeLabel(type: string) {
  return type === 'allocation' ? 'Top-up' : 'Sent'
}
function entryTypeClass(type: string) {
  return type === 'allocation'
    ? 'bg-green-100 text-green-700'
    : 'bg-gray-100 text-gray-600'
}
function deltaClass(delta: number) {
  return delta > 0 ? 'text-green-600' : 'text-red-500'
}

onMounted(async () => {
  await fetchMerchant()
  await fetchLedger()
})
</script>

<template>
  <div class="p-8 max-w-4xl mx-auto">
    <!-- Back -->
    <button
      class="flex items-center gap-1 text-sm text-gray-400 hover:text-gray-200 mb-6"
      @click="router.back()"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
      Back to Brands
    </button>

    <!-- Loading / Error -->
    <div v-if="loading" class="text-gray-400 text-sm">Loading…</div>
    <div v-else-if="error" class="text-red-400 text-sm">{{ error }}</div>

    <template v-else-if="merchant">
      <!-- ── Merchant Profile ───────────────────────────────── -->
      <div class="bg-gray-800 rounded-xl p-6 mb-6">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-2xl font-bold text-white">{{ merchant.name }}</h1>
            <p class="text-gray-400 text-sm mt-0.5">{{ merchant.email }}</p>
            <p v-if="merchant.phone" class="text-gray-400 text-sm">{{ merchant.phone }}</p>
          </div>
          <div class="flex gap-2 flex-wrap justify-end items-center">
            <button
              @click="openEditModal"
              class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-lg flex items-center gap-1.5 transition-colors"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
              </svg>
              Edit
            </button>
            <span
              class="px-2 py-0.5 rounded text-xs font-medium"
              :class="merchant.ecosystem_active ? 'bg-green-900 text-green-300' : 'bg-gray-700 text-gray-400'"
            >
              {{ merchant.ecosystem_active ? 'Ecosystem Active' : 'Ecosystem Inactive' }}
            </span>
            <span
              class="px-2 py-0.5 rounded text-xs font-medium"
              :class="merchant.is_active ? 'bg-blue-900 text-blue-300' : 'bg-red-900 text-red-400'"
            >
              {{ merchant.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
          <div>
            <p class="text-gray-500 text-xs uppercase tracking-wide">Category</p>
            <p class="text-gray-200 mt-0.5">{{ merchant.category ?? '—' }}</p>
          </div>
          <div>
            <p class="text-gray-500 text-xs uppercase tracking-wide">City</p>
            <p class="text-gray-200 mt-0.5">{{ merchant.city ?? '—' }}{{ merchant.state ? `, ${merchant.state}` : '' }}</p>
          </div>
          <div>
            <p class="text-gray-500 text-xs uppercase tracking-wide">Outlets</p>
            <p class="text-gray-200 mt-0.5">{{ merchant.outlet_count }}</p>
          </div>
          <div>
            <p class="text-gray-500 text-xs uppercase tracking-wide">Live Partnerships</p>
            <p class="text-gray-200 mt-0.5">{{ merchant.live_partnership_count }}</p>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-gray-500 text-xs uppercase tracking-wide">Open to Partnerships</p>
            <p class="text-gray-200 mt-0.5">{{ merchant.open_to_partnerships ? 'Yes' : 'No' }}</p>
          </div>
          <div>
            <p class="text-gray-500 text-xs uppercase tracking-wide">eWards Integration</p>
            <p class="mt-0.5">
              <span
                v-if="merchant.ewards_status"
                class="px-2 py-0.5 rounded text-xs font-medium"
                :class="{
                  'bg-green-900 text-green-300': merchant.ewards_status === 'approved',
                  'bg-yellow-900 text-yellow-300': merchant.ewards_status === 'pending',
                  'bg-red-900 text-red-400': merchant.ewards_status === 'rejected',
                }"
              >{{ merchant.ewards_status }}</span>
              <span v-else class="text-gray-500 text-xs">No request</span>
            </p>
          </div>
        </div>
      </div>

      <!-- ── WhatsApp Credits ───────────────────────────────── -->
      <div class="bg-gray-800 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-white font-semibold">WhatsApp Credits</h2>
          <button
            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg"
            @click="creditModal.open = true"
          >
            Add Credits
          </button>
        </div>

        <div class="flex items-baseline gap-2 mb-2">
          <span class="text-4xl font-bold" :class="balanceClass(merchant.whatsapp_balance)">
            {{ merchant.whatsapp_balance }}
          </span>
          <span class="text-gray-400 text-sm">credits remaining</span>
        </div>

        <p v-if="merchant.whatsapp_balance === 0" class="text-red-400 text-sm">
          No credits — messages are not being sent.
        </p>
        <p v-else-if="merchant.low_balance_alerted" class="text-orange-400 text-sm">
          Low balance — consider topping up.
        </p>

        <!-- Ledger Table -->
        <div v-if="ledger.length > 0" class="mt-6">
          <h3 class="text-gray-400 text-xs uppercase tracking-wide mb-3">Credit History</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-gray-500 text-xs border-b border-gray-700">
                  <th class="pb-2 font-medium">Type</th>
                  <th class="pb-2 font-medium">Credits</th>
                  <th class="pb-2 font-medium">Balance After</th>
                  <th class="pb-2 font-medium">Note / Reference</th>
                  <th class="pb-2 font-medium">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <tr v-for="entry in ledger" :key="entry.id" class="text-gray-300">
                  <td class="py-2.5 pr-4">
                    <span class="px-2 py-0.5 rounded text-xs font-medium" :class="entryTypeClass(entry.entry_type)">
                      {{ entryTypeLabel(entry.entry_type) }}
                    </span>
                  </td>
                  <td class="py-2.5 pr-4 font-mono" :class="deltaClass(entry.credits_delta)">
                    {{ entry.credits_delta > 0 ? '+' : '' }}{{ entry.credits_delta }}
                  </td>
                  <td class="py-2.5 pr-4 font-mono text-gray-400">{{ entry.balance_after }}</td>
                  <td class="py-2.5 pr-4 text-gray-400 text-xs">
                    {{ entry.note ?? entry.reference_type ?? '—' }}
                  </td>
                  <td class="py-2.5 text-gray-500 text-xs">
                    {{ new Date(entry.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- Ledger pagination -->
          <div v-if="ledgerMeta.last_page > 1" class="flex gap-2 mt-4 justify-end">
            <button
              v-if="ledgerMeta.current_page > 1"
              class="px-3 py-1 text-xs bg-gray-700 text-gray-300 rounded hover:bg-gray-600"
              @click="fetchLedger(ledgerMeta.current_page - 1)"
            >Prev</button>
            <span class="px-3 py-1 text-xs text-gray-400">
              {{ ledgerMeta.current_page }} / {{ ledgerMeta.last_page }}
            </span>
            <button
              v-if="ledgerMeta.current_page < ledgerMeta.last_page"
              class="px-3 py-1 text-xs bg-gray-700 text-gray-300 rounded hover:bg-gray-600"
              @click="fetchLedger(ledgerMeta.current_page + 1)"
            >Next</button>
          </div>
        </div>
        <p v-else-if="!ledgerLoading" class="text-gray-500 text-sm mt-4">No credit history yet.</p>
      </div>
    </template>

    <!-- ── Add Credits Modal ──────────────────────────────── -->
    <div
      v-if="creditModal.open"
      class="fixed inset-0 bg-black/60 flex items-center justify-center z-50"
      @click.self="creditModal.open = false"
    >
      <div class="bg-gray-800 rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-white font-semibold mb-4">Add WhatsApp Credits</h3>
        <p class="text-gray-400 text-sm mb-4">
          Adding credits to <span class="text-white font-medium">{{ merchant?.name }}</span>
        </p>

        <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">Amount</label>
        <input
          v-model="creditModal.amount"
          type="number"
          min="1"
          placeholder="e.g. 500"
          class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />

        <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">Note (optional)</label>
        <input
          v-model="creditModal.note"
          type="text"
          placeholder="e.g. Monthly allocation"
          class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />

        <p v-if="creditModal.error" class="text-red-400 text-sm mb-3">{{ creditModal.error }}</p>

        <div class="flex gap-2">
          <button
            class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm rounded-lg"
            :disabled="creditModal.loading"
            @click="allocateCredits"
          >
            {{ creditModal.loading ? 'Saving…' : 'Add Credits' }}
          </button>
          <button
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg"
            @click="creditModal.open = false"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
    <!-- ── Edit Merchant Modal ─────────────────────────────── -->
    <div
      v-if="editModal.open"
      class="fixed inset-0 bg-black/60 flex items-center justify-center z-50"
      @click.self="editModal.open = false"
    >
      <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-white font-semibold mb-4">Edit Brand Details</h3>

        <div class="space-y-4">
          <div>
            <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">Brand Name</label>
            <input
              v-model="editModal.form.name"
              type="text"
              class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">Email</label>
            <input
              v-model="editModal.form.email"
              type="email"
              class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">Phone</label>
            <input
              v-model="editModal.form.phone"
              type="text"
              placeholder="+91..."
              class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">Category</label>
              <select
                v-model="editModal.form.category"
                class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="">Select…</option>
                <option value="cafe">Cafe</option>
                <option value="restaurant">Restaurant</option>
                <option value="salon">Salon</option>
                <option value="gym">Gym</option>
                <option value="bookstore">Bookstore</option>
                <option value="retail">Retail</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">City</label>
              <input
                v-model="editModal.form.city"
                type="text"
                class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-gray-400 text-xs uppercase tracking-wide mb-1">State</label>
            <input
              v-model="editModal.form.state"
              type="text"
              class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div class="flex flex-col gap-3 pt-2">
            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
              <input v-model="editModal.form.is_active" type="checkbox" class="rounded border-gray-500 bg-gray-700 text-indigo-500 focus:ring-indigo-500" />
              Active
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
              <input v-model="editModal.form.open_to_partnerships" type="checkbox" class="rounded border-gray-500 bg-gray-700 text-indigo-500 focus:ring-indigo-500" />
              Open to Partnerships
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
              <input v-model="editModal.form.ecosystem_active" type="checkbox" class="rounded border-gray-500 bg-gray-700 text-indigo-500 focus:ring-indigo-500" />
              Ecosystem Active
            </label>
          </div>
        </div>

        <p v-if="editModal.error" class="text-red-400 text-sm mt-3">{{ editModal.error }}</p>

        <div class="flex gap-2 mt-6">
          <button
            class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm rounded-lg"
            :disabled="editModal.loading"
            @click="saveEdit"
          >
            {{ editModal.loading ? 'Saving…' : 'Save Changes' }}
          </button>
          <button
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg"
            @click="editModal.open = false"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
