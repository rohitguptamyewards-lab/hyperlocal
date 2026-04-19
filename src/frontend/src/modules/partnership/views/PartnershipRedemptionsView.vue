<!--
  PartnershipRedemptionsView.vue — Paginated redemption history for one partnership.
  Purpose: Audit trail of all redemptions — date, token, outlet, benefit, customer type, status.
  Owner module: Partnership / Execution
  API: GET /api/partnerships/:uuid/redemptions?page=N
-->
<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const uuid   = route.params.uuid as string

interface Redemption {
  id: string
  date: string
  token: string
  outlet_name: string
  bill_amount: number
  benefit_amount: number
  customer_type: number
  customer_type_label: string
  approval_label: string
  status: number
  status_label: string
}

interface Meta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

const rows    = ref<Redemption[]>([])
const meta    = ref<Meta | null>(null)
const loading = ref(true)
const error   = ref<string | null>(null)
const page    = ref(1)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get(`/partnerships/${uuid}/redemptions`, { params: { page: page.value } })
    rows.value = data.data
    meta.value = data.meta
  } catch {
    error.value = 'Failed to load redemptions.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(page, load)

function currency(val: number): string {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val)
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusClass = (status: number) => ({
  1: 'bg-green-100 text-green-700',
  2: 'bg-gray-100 text-gray-600',
  3: 'bg-red-100 text-red-700',
}[status] ?? 'bg-gray-100 text-gray-600')

const customerTypeClass = (type: number) => ({
  1: 'text-indigo-600',
  2: 'text-gray-600',
  3: 'text-orange-600',
}[type] ?? 'text-gray-600')
</script>

<template>
  <div class="p-4 sm:p-8 max-w-5xl">
    <button @click="router.back()" class="text-sm text-gray-500 hover:text-gray-700 mb-6 flex items-center gap-1">
      ← Back
    </button>

    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Redemption history</h1>
        <p class="text-sm text-gray-400 mt-0.5">Your outlet's redemptions for this partnership.</p>
      </div>
      <span v-if="meta" class="text-sm text-gray-400">{{ meta.total }} total</span>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>
    <div v-else-if="error" class="text-sm text-red-600">{{ error }}</div>

    <template v-else>
      <div v-if="rows.length === 0" class="bg-gray-50 border border-dashed border-gray-300 rounded-xl p-8 text-center text-sm text-gray-400">
        No redemptions yet — they will appear here once customers redeem claim tokens at your outlets.
      </div>

      <div v-else class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-200">
              <tr>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Token</th>
                <th class="px-4 py-3 text-left">Outlet</th>
                <th class="px-4 py-3 text-right">Bill</th>
                <th class="px-4 py-3 text-right">Benefit</th>
                <th class="px-4 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Approval</th>
                <th class="px-4 py-3 text-left">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">{{ formatDate(r.date) }}</td>
                <td class="px-4 py-3 font-mono text-xs font-medium text-gray-800 tracking-widest">{{ r.token }}</td>
                <td class="px-4 py-3 text-gray-700 max-w-[160px] truncate">{{ r.outlet_name }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ currency(r.bill_amount) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-green-700">{{ currency(r.benefit_amount) }}</td>
                <td class="px-4 py-3 text-xs font-medium" :class="customerTypeClass(r.customer_type)">{{ r.customer_type_label }}</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ r.approval_label }}</td>
                <td class="px-4 py-3">
                  <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="statusClass(r.status)">
                    {{ r.status_label }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <button
          @click="page--"
          :disabled="page <= 1"
          class="px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          ← Previous
        </button>
        <span class="text-xs text-gray-400">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button
          @click="page++"
          :disabled="page >= meta.last_page"
          class="px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          Next →
        </button>
      </div>
    </template>
  </div>
</template>
