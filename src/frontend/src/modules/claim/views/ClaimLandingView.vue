<!--
  ClaimLandingView.vue — Public customer-facing QR claim page.
  Purpose: Customer scans partnership QR, enters phone, receives claim token.
  Owner module: Claim (public)
  API: GET /api/public/partnerships/:uuid?from=outletId
       POST /api/public/claims
  No auth required — this route is fully public.
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const uuid      = route.params.uuid as string
const fromOutletId = Number(route.query.from ?? 0)

// Use plain axios — no auth token needed
const http = axios.create({ baseURL: '/api' })

interface PartnershipInfo {
  partnership_name: string
  source_outlet: { id: number; name: string } | null
  target_outlets: { id: number; name: string; address?: string | null }[]
}

const info    = ref<PartnershipInfo | null>(null)
const loading = ref(true)
const error   = ref<string | null>(null)

const phone          = ref('')
const targetOutletId = ref<number | null>(null)
const submitting     = ref(false)
const submitError    = ref<string | null>(null)

interface ClaimResult { token: string; expires_at: string; valid_hours: number }
const result = ref<ClaimResult | null>(null)

onMounted(async () => {
  try {
    const { data } = await http.get<PartnershipInfo>(`/public/partnerships/${uuid}`, {
      params: fromOutletId ? { from: fromOutletId } : {},
    })
    info.value = data
    if (data.target_outlets.length === 1) {
      targetOutletId.value = data.target_outlets[0].id
    }
  } catch {
    error.value = 'This partnership is no longer active or the link is invalid.'
  } finally {
    loading.value = false
  }
})

async function submit() {
  submitError.value = null
  submitting.value  = true
  try {
    const { data } = await http.post<ClaimResult>('/public/claims', {
      partnership_uuid: uuid,
      source_outlet_id: fromOutletId || info.value?.source_outlet?.id,
      target_outlet_id: targetOutletId.value,
      customer_phone:   phone.value,
    })
    result.value = data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const errors = err.response?.data?.errors
    submitError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Something went wrong. Please try again.')
  } finally {
    submitting.value = false
  }
}

function formatExpiry(iso: string): string {
  return new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">

      <!-- Brand mark -->
      <div class="text-center mb-8">
        <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Hyperlocal Network</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center text-sm text-gray-400 py-12">Loading…</div>

      <!-- Error / inactive -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center">
        <p class="text-sm font-semibold text-red-700 mb-1">Partnership unavailable</p>
        <p class="text-xs text-red-500">{{ error }}</p>
      </div>

      <!-- Success: token issued -->
      <div v-else-if="result" class="bg-white border border-gray-200 rounded-2xl p-6 text-center shadow-sm">
        <div class="text-green-500 text-3xl mb-3">✓</div>
        <p class="text-sm font-semibold text-gray-800 mb-1">Your referral token</p>
        <p class="text-3xl font-mono font-bold tracking-widest text-gray-900 mt-3 mb-3">{{ result.token }}</p>
        <p class="text-xs text-gray-400">Show this to the cashier at the partner outlet.</p>
        <p class="text-xs text-gray-400 mt-1">Valid for {{ result.valid_hours }} hours · Expires {{ formatExpiry(result.expires_at) }}</p>
      </div>

      <!-- Claim form -->
      <div v-else-if="info" class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <h1 class="text-lg font-semibold text-gray-900 mb-0.5">{{ info.partnership_name }}</h1>
        <p class="text-sm text-gray-400 mb-6">
          Claim your referral benefit
          <span v-if="info.source_outlet"> from {{ info.source_outlet.name }}</span>.
        </p>

        <!-- Target outlet picker (if more than one) -->
        <div v-if="info.target_outlets.length > 1" class="mb-4">
          <label class="block text-xs text-gray-500 mb-1">Where will you redeem?</label>
          <select
            v-model="targetOutletId"
            class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option :value="null" disabled>Select an outlet</option>
            <option v-for="o in info.target_outlets" :key="o.id" :value="o.id">
              {{ o.name }}<span v-if="o.address"> — {{ o.address }}</span>
            </option>
          </select>
        </div>
        <div v-else-if="info.target_outlets.length === 1" class="mb-4 rounded-xl bg-indigo-50 border border-indigo-100 px-4 py-3">
          <p class="text-xs text-indigo-500 mb-0.5">Redeem at</p>
          <p class="text-sm font-medium text-indigo-800">{{ info.target_outlets[0].name }}</p>
        </div>

        <!-- Phone -->
        <div class="mb-4">
          <label class="block text-xs text-gray-500 mb-1">Your phone number</label>
          <input
            v-model="phone"
            type="tel"
            placeholder="+91 98765 43210"
            class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <p class="text-xs text-gray-400 mt-1">Your token will also be sent via WhatsApp.</p>
        </div>

        <div v-if="submitError" class="mb-3 rounded-xl bg-red-50 border border-red-200 px-3 py-2.5 text-xs text-red-700">
          {{ submitError }}
        </div>

        <button
          @click="submit"
          :disabled="!phone || !targetOutletId || submitting"
          class="w-full bg-indigo-600 text-white rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-indigo-700 disabled:opacity-40 transition-colors"
        >
          {{ submitting ? 'Getting your token…' : 'Get my token' }}
        </button>
      </div>

    </div>
  </div>
</template>
