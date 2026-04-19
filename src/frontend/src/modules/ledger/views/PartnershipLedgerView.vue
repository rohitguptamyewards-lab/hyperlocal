<!--
  PartnershipLedgerView.vue — Monthly ledger statement for one partnership.
  Purpose: Show benefit given vs referral credits per month, with net position.
  Owner module: Ledger
  API: GET /api/partnerships/:uuid/ledger
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const uuid   = route.params.uuid as string

interface LedgerRow {
  period: string           // 'YYYY-MM'
  benefit_given: number
  referral_credits: number
  net: number
}

const rows    = ref<LedgerRow[]>([])
const loading = ref(true)
const error   = ref<string | null>(null)

const totals = computed(() => ({
  benefit_given:    rows.value.reduce((s, r) => s + r.benefit_given, 0),
  referral_credits: rows.value.reduce((s, r) => s + r.referral_credits, 0),
  net:              rows.value.reduce((s, r) => s + r.net, 0),
}))

onMounted(async () => {
  try {
    const { data } = await api.get<{ statement: LedgerRow[] }>(`/partnerships/${uuid}/ledger`)
    rows.value = data.statement
  } catch {
    error.value = 'Failed to load ledger statement.'
  } finally {
    loading.value = false
  }
})

function currency(val: number): string {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency', currency: 'INR', maximumFractionDigits: 0,
  }).format(val)
}

function formatPeriod(ym: string): string {
  const [year, month] = ym.split('-')
  return new Date(Number(year), Number(month) - 1).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="p-4 sm:p-8 max-w-3xl">
    <button @click="router.back()" class="text-sm text-gray-500 hover:text-gray-700 mb-6 flex items-center gap-1">
      ← Back
    </button>

    <h1 class="text-xl font-semibold text-gray-900 mb-1">Ledger statement</h1>
    <p class="text-sm text-gray-400 mb-8">Last 6 months · your perspective as the receiving brand.</p>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>
    <div v-else-if="error" class="text-sm text-red-600">{{ error }}</div>

    <template v-else>
      <div v-if="rows.length === 0" class="bg-gray-50 border border-dashed border-gray-300 rounded-xl p-8 text-center text-sm text-gray-400">
        No ledger entries yet — entries are created automatically when redemptions occur.
      </div>

      <div v-else class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-5 py-3 text-left">Month</th>
              <th class="px-5 py-3 text-right">Benefit given</th>
              <th class="px-5 py-3 text-right">Credits received</th>
              <th class="px-5 py-3 text-right">Net</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="row in rows" :key="row.period">
              <td class="px-5 py-3 text-gray-700 font-medium">{{ formatPeriod(row.period) }}</td>
              <td class="px-5 py-3 text-right text-red-600">{{ currency(row.benefit_given) }}</td>
              <td class="px-5 py-3 text-right text-green-600">{{ currency(row.referral_credits) }}</td>
              <td
                class="px-5 py-3 text-right font-semibold"
                :class="row.net >= 0 ? 'text-green-700' : 'text-red-700'"
              >
                {{ currency(row.net) }}
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 border-t border-gray-200 text-xs font-semibold">
            <tr>
              <td class="px-5 py-3 text-gray-600 uppercase tracking-wide">Total</td>
              <td class="px-5 py-3 text-right text-red-700">{{ currency(totals.benefit_given) }}</td>
              <td class="px-5 py-3 text-right text-green-700">{{ currency(totals.referral_credits) }}</td>
              <td
                class="px-5 py-3 text-right"
                :class="totals.net >= 0 ? 'text-green-700' : 'text-red-700'"
              >
                {{ currency(totals.net) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Net position summary -->
      <div
        class="mt-4 rounded-xl px-5 py-4 text-sm"
        :class="totals.net >= 0 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'"
      >
        <span :class="totals.net >= 0 ? 'text-green-800' : 'text-red-800'">
          Net position: <strong>{{ currency(totals.net) }}</strong>
          {{ totals.net >= 0 ? '— you received more value than you gave.' : '— you gave more value than you received.' }}
        </span>
      </div>
    </template>
  </div>
</template>
