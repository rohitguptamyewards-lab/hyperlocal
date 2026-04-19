<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-900">Brands</h2>
      <button
        @click="openBrandModal"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add Brand
      </button>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-4">
      <input
        v-model="search"
        type="text"
        placeholder="Search by name or email…"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-gray-400"
        @input="debouncedFetch"
      />
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div v-if="loading" class="p-6 text-sm text-gray-500 text-center">Loading…</div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Brand</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">WA Credits</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">eWards</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Ecosystem</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="m in merchants" :key="m.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <button
                class="font-medium text-gray-900 hover:text-indigo-600 hover:underline text-left"
                @click="router.push({ name: 'sa-merchant-detail', params: { id: m.id } })"
              >{{ m.name }}</button>
              <div class="text-xs text-gray-500">{{ m.email }}</div>
            </td>
            <td class="px-4 py-3">
              <span
                class="font-mono text-sm"
                :class="m.whatsapp_credits === 0 ? 'text-red-600 font-bold' : m.whatsapp_credits <= 50 ? 'text-orange-500' : 'text-gray-900'"
              >{{ m.whatsapp_credits ?? 0 }}</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="ewardsBadge(m.ewards_status).class"
              >{{ ewardsBadge(m.ewards_status).label }}</span>
            </td>
            <td class="px-4 py-3">
              <!-- Pending approval -->
              <template v-if="m.registration_status === 'pending'">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700">
                  Pending Approval
                </span>
              </template>
              <!-- Rejected -->
              <template v-else-if="m.registration_status === 'rejected'">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">
                  Rejected
                </span>
              </template>
              <!-- Approved / created by admin -->
              <template v-else>
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="m.ecosystem_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                >{{ m.ecosystem_active ? 'Active' : 'Inactive' }}</span>
              </template>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <!-- Approve / Reject for pending registrations -->
              <template v-if="m.registration_status === 'pending'">
                <button
                  class="text-xs px-2.5 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700 mr-1.5 disabled:opacity-50 transition-colors"
                  :disabled="reviewAction.loading === m.id"
                  @click="approveMerchant(m)"
                >{{ reviewAction.loading === m.id ? '…' : 'Approve' }}</button>
                <button
                  class="text-xs px-2.5 py-1 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 mr-1.5 transition-colors"
                  @click="openRejectModal(m)"
                >Reject</button>
              </template>
              <button
                class="text-xs text-gray-500 hover:text-gray-900 underline"
                @click="openCreditModal(m)"
              >Add Credits</button>
            </td>
          </tr>
          <tr v-if="merchants.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No brands found.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button
            :disabled="meta.current_page === 1"
            class="text-xs px-3 py-1.5 rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50 transition-colors"
            @click="changePage(meta.current_page - 1)"
          >Previous</button>
          <button
            :disabled="meta.current_page === meta.last_page"
            class="text-xs px-3 py-1.5 rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50 transition-colors"
            @click="changePage(meta.current_page + 1)"
          >Next</button>
        </div>
      </div>
    </div>

    <!-- Credit modal -->
    <div v-if="creditModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="font-semibold text-gray-900 mb-1">Allocate WhatsApp Credits</h3>
        <p class="text-sm text-gray-500 mb-4">{{ creditModal.merchant?.name }}</p>

        <div v-if="creditModal.error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
          {{ creditModal.error }}
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Credits to add</label>
          <input
            v-model.number="creditModal.amount"
            type="number"
            min="1"
            max="100000"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
          />
        </div>

        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
          <input
            v-model="creditModal.note"
            type="text"
            maxlength="500"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
          />
        </div>

        <div class="flex gap-3">
          <button
            class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50 transition-colors"
            @click="creditModal.open = false"
          >Cancel</button>
          <button
            :disabled="creditModal.loading || !creditModal.amount"
            class="flex-1 bg-gray-900 text-white text-sm rounded-lg py-2.5 hover:bg-gray-800 disabled:opacity-50 transition-colors"
            @click="allocateCredits"
          >{{ creditModal.loading ? 'Allocating…' : 'Allocate' }}</button>
        </div>
      </div>
    </div>

    <!-- Add Brand modal -->
    <div v-if="brandModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
        <h3 class="font-semibold text-gray-900 mb-1">Add New Brand</h3>
        <p class="text-sm text-gray-500 mb-5">Creates a merchant with one outlet and an admin login.</p>

        <div v-if="brandModal.error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
          {{ brandModal.error }}
        </div>

        <div v-if="brandModal.success" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
          <p class="text-sm font-medium text-green-800">Brand created!</p>
          <p class="text-sm text-green-700 mt-1">
            Login: <span class="font-mono font-medium">{{ brandModal.success.email }}</span> / <span class="font-mono font-medium">{{ brandModal.form.admin_password }}</span>
          </p>
          <button
            class="mt-3 w-full border border-green-300 text-green-700 text-sm rounded-lg py-2 hover:bg-green-100 transition-colors"
            @click="brandModal.open = false"
          >Close</button>
        </div>

        <template v-if="!brandModal.success">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
              <input
                v-model="brandModal.form.name"
                type="text"
                placeholder="e.g. Rohit's Salon"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
              />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select
                  v-model="brandModal.form.category"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
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
                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input
                  v-model="brandModal.form.city"
                  type="text"
                  placeholder="Mumbai"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">First Outlet Name</label>
              <input
                v-model="brandModal.form.outlet_name"
                type="text"
                placeholder="e.g. Andheri Branch"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
              />
            </div>

            <hr class="border-gray-200" />

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Admin Name</label>
              <input
                v-model="brandModal.form.admin_name"
                type="text"
                placeholder="Rohit Kumar"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
              />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                <input
                  v-model="brandModal.form.admin_email"
                  type="email"
                  placeholder="rohit@salon.com"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input
                  v-model="brandModal.form.admin_password"
                  type="text"
                  placeholder="password"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-gray-400"
                />
              </div>
            </div>
          </div>

          <div class="flex gap-3 mt-6">
            <button
              class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50 transition-colors"
              @click="brandModal.open = false"
            >Cancel</button>
            <button
              :disabled="brandModal.loading"
              class="flex-1 bg-gray-900 text-white text-sm rounded-lg py-2.5 hover:bg-gray-800 disabled:opacity-50 transition-colors"
              @click="createBrand"
            >{{ brandModal.loading ? 'Creating…' : 'Create Brand' }}</button>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Reject modal ───────────────────────────────────────── -->
    <div v-if="reviewAction.rejectOpen" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <h3 class="text-base font-semibold text-gray-900">Reject Registration</h3>
        <p class="text-sm text-gray-500">
          Rejecting <span class="font-medium text-gray-800">{{ reviewAction.rejectMerchant?.name }}</span>.
          The brand will be notified with the reason below.
        </p>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-red-500">*</span></label>
          <textarea
            v-model="reviewAction.rejectReason"
            rows="3"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
            placeholder="e.g. Incomplete information, duplicate registration…"
          />
        </div>
        <p v-if="reviewAction.rejectError" class="text-xs text-red-600">{{ reviewAction.rejectError }}</p>
        <div class="flex gap-3">
          <button
            class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50 transition-colors"
            @click="reviewAction.rejectOpen = false"
          >Cancel</button>
          <button
            :disabled="reviewAction.rejectLoading || !reviewAction.rejectReason.trim()"
            class="flex-1 bg-red-600 text-white text-sm rounded-lg py-2.5 hover:bg-red-700 disabled:opacity-50 transition-colors"
            @click="submitReject"
          >{{ reviewAction.rejectLoading ? 'Rejecting…' : 'Reject' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import superAdminApi from '@/services/superAdminApi'

const router = useRouter()

interface Merchant {
  id: number
  name: string
  email: string
  whatsapp_credits: number
  ewards_status: string | null
  ecosystem_active: boolean
  registration_status: string | null
}

const merchants = ref<Merchant[]>([])
const loading = ref(true)
const search = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const creditModal = reactive({
  open: false,
  merchant: null as Merchant | null,
  amount: 100,
  note: '',
  loading: false,
  error: '',
})

const brandModal = reactive({
  open: false,
  loading: false,
  error: '',
  success: null as { id: number; name: string; email: string } | null,
  form: {
    name: '',
    category: '',
    city: '',
    outlet_name: '',
    admin_name: '',
    admin_email: '',
    admin_password: 'password',
  },
})

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function debouncedFetch() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchMerchants(1), 400)
}

async function fetchMerchants(page = 1) {
  loading.value = true
  try {
    const res = await superAdminApi.get('/merchants', {
      params: { page, search: search.value || undefined },
    })
    merchants.value = res.data.data
    meta.value = {
      current_page: res.data.current_page ?? res.data.meta?.current_page ?? 1,
      last_page: res.data.last_page ?? res.data.meta?.last_page ?? 1,
      total: res.data.total ?? res.data.meta?.total ?? 0,
    }
  } finally {
    loading.value = false
  }
}

function changePage(page: number) {
  fetchMerchants(page)
}

function openCreditModal(m: Merchant) {
  creditModal.merchant = m
  creditModal.amount = 100
  creditModal.note = ''
  creditModal.error = ''
  creditModal.open = true
}

async function allocateCredits() {
  if (!creditModal.merchant) return
  creditModal.loading = true
  creditModal.error = ''
  try {
    await superAdminApi.post(`/merchants/${creditModal.merchant.id}/credits`, {
      amount: creditModal.amount,
      note: creditModal.note || undefined,
    })
    creditModal.open = false
    await fetchMerchants(meta.value.current_page)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    creditModal.error = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to allocate credits.')
  } finally {
    creditModal.loading = false
  }
}

function openBrandModal() {
  brandModal.form = {
    name: '',
    category: '',
    city: '',
    outlet_name: '',
    admin_name: '',
    admin_email: '',
    admin_password: 'password',
  }
  brandModal.error = ''
  brandModal.success = null
  brandModal.open = true
}

async function createBrand() {
  brandModal.loading = true
  brandModal.error = ''
  try {
    const res = await superAdminApi.post('/merchants', brandModal.form)
    brandModal.success = res.data
    await fetchMerchants(1)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    brandModal.error = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to create brand.')
  } finally {
    brandModal.loading = false
  }
}

function ewardsBadge(status: string | null) {
  if (status === 'approved') return { label: 'Active', class: 'bg-green-50 text-green-700' }
  if (status === 'pending')  return { label: 'Pending', class: 'bg-yellow-50 text-yellow-700' }
  if (status === 'rejected') return { label: 'Rejected', class: 'bg-red-50 text-red-700' }
  return { label: 'None', class: 'bg-gray-100 text-gray-500' }
}

// ── Approve / Reject ─────────────────────────────────────────
const reviewAction = reactive({
  loading: null as number | null, // merchant id being actioned
  rejectOpen: false,
  rejectMerchant: null as Merchant | null,
  rejectReason: '',
  rejectError: '',
  rejectLoading: false,
})

async function approveMerchant(m: Merchant) {
  reviewAction.loading = m.id
  try {
    await superAdminApi.post(`/brand-registrations/${m.id}/approve`)
    await fetchMerchants(meta.value.current_page)
  } catch {
    // silent — list will refresh anyway
  } finally {
    reviewAction.loading = null
  }
}

function openRejectModal(m: Merchant) {
  reviewAction.rejectMerchant = m
  reviewAction.rejectReason   = ''
  reviewAction.rejectError    = ''
  reviewAction.rejectOpen     = true
}

async function submitReject() {
  if (!reviewAction.rejectMerchant) return
  reviewAction.rejectLoading = true
  reviewAction.rejectError   = ''
  try {
    await superAdminApi.post(`/brand-registrations/${reviewAction.rejectMerchant.id}/reject`, {
      reason: reviewAction.rejectReason,
    })
    reviewAction.rejectOpen = false
    await fetchMerchants(meta.value.current_page)
  } catch (err: any) {
    reviewAction.rejectError = err.response?.data?.message ?? 'Failed to reject.'
  } finally {
    reviewAction.rejectLoading = false
  }
}

onMounted(() => fetchMerchants())
</script>
