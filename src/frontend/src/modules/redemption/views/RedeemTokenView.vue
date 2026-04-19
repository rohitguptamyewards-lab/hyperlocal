<!--
  RedeemTokenView.vue — Token lookup + redemption flow.
  Purpose: Allows merchant staff to look up a claim token, verify benefit, and redeem.
  Cashier only needs token + bill amount. Partnership and outlet are auto-resolved from the claim.
  Owner module: Redemption
  API: GET /api/execution/lookup/{token}, POST /api/execution/approval-request, POST /api/execution/redeem
-->
<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/api'

// ── Data ────────────────────────────────────────────────────
interface LookupResult {
  allowed: boolean
  benefit_amount: number
  customer_type: number
  customer_type_label: string
  requires_approval: boolean
  partnership_uuid?: string
  partnership_name?: string
  outlet_id?: number
  reason_code?: string
  reason_display?: string
  fallback_help?: string
}
interface RedeemResult {
  redemption_id: string
  allowed: boolean
  benefit_amount: number
  customer_type: number
  customer_type_label: string
  duplicate: boolean
}

const step = ref<'lookup' | 'confirm' | 'approval' | 'success'>('lookup')

// Step 1: Lookup — only token + bill amount needed
const token = ref('')
const billAmount = ref<number | ''>('')
const lookupResult = ref<LookupResult | null>(null)
const lookupError = ref('')
const lookupLoading = ref(false)

// Resolved from lookup response
const resolvedPartnershipUuid = ref('')
const resolvedPartnershipName = ref('')
const resolvedOutletId = ref<number>(0)

// Step 2: Approval
const approvalCode = ref('')
const approvalLoading = ref(false)
const approvalSent = ref(false)
const approvalError = ref('')
const generatedCode = ref('')

// Step 3: Redeem
const transactionId = ref('')
const billId = ref('')
const redeemLoading = ref(false)
const redeemError = ref('')
const redeemResult = ref<RedeemResult | null>(null)

// ── Step 1: Lookup ──────────────────────────────────────────
async function handleLookup() {
  lookupError.value = ''
  lookupResult.value = null

  if (!token.value.trim()) { lookupError.value = 'Token is required'; return }
  if (!billAmount.value || Number(billAmount.value) <= 0) { lookupError.value = 'Enter a valid bill amount'; return }

  lookupLoading.value = true
  try {
    const { data } = await api.get(`/execution/lookup/${encodeURIComponent(token.value.trim().toUpperCase())}`, {
      params: {
        bill_amount: Number(billAmount.value),
      },
    })
    lookupResult.value = data

    if (data.allowed) {
      resolvedPartnershipUuid.value = data.partnership_uuid ?? ''
      resolvedPartnershipName.value = data.partnership_name ?? ''
      resolvedOutletId.value = data.outlet_id ?? 0
      transactionId.value = `TXN_${Date.now()}`
      step.value = data.requires_approval ? 'approval' : 'confirm'
    }
  } catch (e: any) {
    const msg = e.response?.data?.reason_display
      || e.response?.data?.message
      || e.response?.data?.errors?.claim_token?.[0]
      || 'Lookup failed'
    lookupError.value = msg
    if (e.response?.data?.allowed === false) {
      lookupResult.value = e.response.data
    }
  } finally {
    lookupLoading.value = false
  }
}

// ── Step 2: Request approval code ───────────────────────────
async function requestApproval() {
  approvalError.value = ''
  approvalLoading.value = true
  try {
    const { data } = await api.post('/execution/approval-request', {
      claim_token: token.value.trim().toUpperCase(),
    })
    generatedCode.value = data.code
    approvalSent.value = true
  } catch (e: any) {
    approvalError.value = e.response?.data?.message || 'Failed to request approval'
  } finally {
    approvalLoading.value = false
  }
}

function proceedWithApproval() {
  if (!approvalCode.value.trim()) { approvalError.value = 'Enter the approval code'; return }
  step.value = 'confirm'
}

// ── Step 3: Redeem ──────────────────────────────────────────
async function handleRedeem() {
  redeemError.value = ''
  redeemLoading.value = true
  try {
    const payload: Record<string, any> = {
      claim_token: token.value.trim().toUpperCase(),
      bill_amount: Number(billAmount.value),
      transaction_id: transactionId.value,
    }
    if (billId.value.trim()) payload.bill_id = billId.value.trim()
    if (approvalCode.value.trim()) payload.approval_code = approvalCode.value.trim()

    const { data } = await api.post('/execution/redeem', payload)
    redeemResult.value = data
    step.value = 'success'
  } catch (e: any) {
    redeemError.value =
      e.response?.data?.errors?.claim_token?.[0]
      || e.response?.data?.message
      || 'Redemption failed'
  } finally {
    redeemLoading.value = false
  }
}

// ── Reset ───────────────────────────────────────────────────
function resetAll() {
  step.value = 'lookup'
  token.value = ''
  billAmount.value = ''
  transactionId.value = ''
  billId.value = ''
  approvalCode.value = ''
  approvalSent.value = false
  generatedCode.value = ''
  lookupResult.value = null
  lookupError.value = ''
  redeemResult.value = null
  redeemError.value = ''
  approvalError.value = ''
  resolvedPartnershipUuid.value = ''
  resolvedPartnershipName.value = ''
  resolvedOutletId.value = 0
}

const customerTypeBadge = (type: number) => ({
  1: { label: 'New Customer', cls: 'bg-green-100 text-green-700' },
  2: { label: 'Existing', cls: 'bg-gray-100 text-gray-600' },
  3: { label: 'Reactivated', cls: 'bg-blue-100 text-blue-700' },
}[type] ?? { label: 'Unknown', cls: 'bg-gray-100 text-gray-600' })
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 sm:px-6 py-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Redeem Token</h1>
      <p class="mt-1 text-sm text-gray-500">
        Enter the customer's claim token and bill amount to process a redemption.
      </p>
    </div>

    <!-- Step indicator -->
    <div class="flex items-center gap-2 mb-8">
      <div
        v-for="(s, i) in [
          { key: 'lookup', label: '1. Lookup' },
          { key: 'confirm', label: '2. Confirm & Redeem' },
          { key: 'success', label: '3. Done' },
        ]"
        :key="s.key"
        class="flex items-center gap-2"
      >
        <span v-if="i > 0" class="w-8 h-px bg-gray-300" />
        <span
          class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
          :class="
            step === s.key || (s.key === 'confirm' && step === 'approval')
              ? 'bg-indigo-100 text-indigo-700'
              : step === 'success' || (s.key === 'lookup' && step !== 'lookup')
                ? 'bg-green-100 text-green-700'
                : 'bg-gray-100 text-gray-500'
          "
        >
          {{ s.label }}
        </span>
      </div>
    </div>

    <!-- ═══ STEP 1: Lookup ═══ -->
    <div v-if="step === 'lookup'" class="bg-white rounded-xl border border-gray-200 shadow-sm">
      <div class="px-6 py-5 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Token Lookup</h2>
        <p class="text-sm text-gray-500 mt-0.5">Enter the customer's token and their bill amount.</p>
      </div>

      <div class="px-6 py-5 space-y-5">
        <!-- Token -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Claim Token</label>
          <input
            v-model="token"
            type="text"
            placeholder="e.g. HLP4X9K2"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase tracking-widest font-mono text-lg"
            @keyup.enter="handleLookup"
          />
        </div>

        <!-- Bill amount -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Bill Amount</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">&#8377;</span>
            <input
              v-model.number="billAmount"
              type="number"
              step="0.01"
              min="0.01"
              placeholder="500.00"
              class="block w-full rounded-lg border border-gray-300 pl-7 pr-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
              @keyup.enter="handleLookup"
            />
          </div>
        </div>

        <!-- Error -->
        <div v-if="lookupError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p class="text-sm text-red-700 font-medium">{{ lookupError }}</p>
          <p v-if="lookupResult?.fallback_help" class="text-xs text-red-500 mt-1">{{ lookupResult.fallback_help }}</p>
        </div>

        <!-- Denied result -->
        <div v-if="lookupResult && !lookupResult.allowed" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <span class="text-sm font-medium text-amber-800">{{ lookupResult.reason_display || 'Token not eligible' }}</span>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100">
        <button
          @click="handleLookup"
          :disabled="lookupLoading"
          class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-6 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition-colors"
        >
          <svg v-if="lookupLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          {{ lookupLoading ? 'Looking up...' : 'Look Up Token' }}
        </button>
      </div>
    </div>

    <!-- ═══ STEP 2a: Approval required ═══ -->
    <div v-if="step === 'approval'" class="bg-white rounded-xl border border-gray-200 shadow-sm">
      <div class="px-6 py-5 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Manager Approval Required</h2>
        <p class="text-sm text-gray-500 mt-0.5">This is a high-value redemption. A manager approval code is needed.</p>
      </div>

      <div class="px-6 py-5 space-y-5">
        <!-- Benefit summary -->
        <div class="rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3 space-y-2">
          <div v-if="resolvedPartnershipName" class="flex items-center justify-between">
            <span class="text-sm text-indigo-700">Partnership</span>
            <span class="text-sm font-medium text-indigo-800">{{ resolvedPartnershipName }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-indigo-700">Benefit amount</span>
            <span class="text-lg font-bold text-indigo-800">&#8377;{{ lookupResult?.benefit_amount?.toFixed(2) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-indigo-700">Customer type</span>
            <span
              class="inline-block text-xs rounded px-2 py-0.5 font-medium"
              :class="customerTypeBadge(lookupResult?.customer_type ?? 0).cls"
            >{{ lookupResult?.customer_type_label }}</span>
          </div>
        </div>

        <!-- Request code -->
        <div v-if="!approvalSent">
          <button
            @click="requestApproval"
            :disabled="approvalLoading"
            class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 disabled:opacity-50 transition-colors"
          >
            <svg v-if="approvalLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ approvalLoading ? 'Sending...' : 'Request Approval Code' }}
          </button>
        </div>

        <!-- Code sent -->
        <div v-if="approvalSent" class="space-y-4">
          <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3">
            <p class="text-sm text-green-700 font-medium">Approval code sent to manager.</p>
            <p v-if="generatedCode" class="text-xs text-green-600 mt-1">
              Dev mode code: <span class="font-mono font-bold text-green-800">{{ generatedCode }}</span>
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Enter Approval Code</label>
            <input
              v-model="approvalCode"
              type="text"
              maxlength="6"
              placeholder="6-digit code"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono text-lg tracking-[0.3em] text-center focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
              @keyup.enter="proceedWithApproval"
            />
          </div>
        </div>

        <div v-if="approvalError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p class="text-sm text-red-700">{{ approvalError }}</p>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100 flex items-center gap-3">
        <button
          @click="step = 'lookup'"
          class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
        >
          Back
        </button>
        <button
          v-if="approvalSent"
          @click="proceedWithApproval"
          class="px-6 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors"
        >
          Continue to Redeem
        </button>
      </div>
    </div>

    <!-- ═══ STEP 2b: Confirm & Redeem ═══ -->
    <div v-if="step === 'confirm'" class="bg-white rounded-xl border border-gray-200 shadow-sm">
      <div class="px-6 py-5 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Confirm Redemption</h2>
        <p class="text-sm text-gray-500 mt-0.5">Review details and process the redemption.</p>
      </div>

      <div class="px-6 py-5 space-y-5">
        <!-- Summary card -->
        <div class="rounded-lg bg-gradient-to-br from-indigo-50 to-purple-50 border border-indigo-100 p-5 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Token</span>
            <span class="font-mono font-bold text-gray-900 tracking-wider">{{ token.toUpperCase() }}</span>
          </div>
          <div v-if="resolvedPartnershipName" class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Partnership</span>
            <span class="text-sm font-medium text-gray-900">{{ resolvedPartnershipName }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Bill Amount</span>
            <span class="text-sm font-medium text-gray-900">&#8377;{{ Number(billAmount).toFixed(2) }}</span>
          </div>
          <hr class="border-indigo-200" />
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-indigo-700">Benefit to Customer</span>
            <span class="text-xl font-bold text-indigo-700">&#8377;{{ lookupResult?.benefit_amount?.toFixed(2) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Customer Type</span>
            <span
              class="inline-block text-xs rounded px-2 py-0.5 font-medium"
              :class="customerTypeBadge(lookupResult?.customer_type ?? 0).cls"
            >{{ lookupResult?.customer_type_label }}</span>
          </div>
        </div>

        <!-- Transaction ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
          <input
            v-model="transactionId"
            type="text"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
          />
          <p class="text-xs text-gray-400 mt-1">Auto-generated. Replace with POS transaction ID if available.</p>
        </div>

        <!-- Bill ID (optional) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Bill ID <span class="text-gray-400">(optional)</span></label>
          <input
            v-model="billId"
            type="text"
            placeholder="External bill reference"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
          />
        </div>

        <!-- Error -->
        <div v-if="redeemError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p class="text-sm text-red-700 font-medium">{{ redeemError }}</p>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100 flex items-center gap-3">
        <button
          @click="step = 'lookup'"
          class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
        >
          Back
        </button>
        <button
          @click="handleRedeem"
          :disabled="redeemLoading"
          class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-6 py-2.5 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 transition-colors"
        >
          <svg v-if="redeemLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ redeemLoading ? 'Processing...' : 'Confirm Redemption' }}
        </button>
      </div>
    </div>

    <!-- ═══ STEP 3: Success ═══ -->
    <div v-if="step === 'success'" class="bg-white rounded-xl border border-gray-200 shadow-sm">
      <div class="px-6 py-8 text-center">
        <!-- Check icon -->
        <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>

        <h2 class="text-xl font-bold text-gray-900 mb-1">Redemption Successful!</h2>
        <p v-if="redeemResult?.duplicate" class="text-sm text-amber-600 font-medium mb-4">
          (Duplicate — this transaction was already processed)
        </p>

        <div class="inline-block rounded-lg bg-green-50 border border-green-200 px-6 py-4 text-left space-y-2 mt-2">
          <div class="flex items-center justify-between gap-8">
            <span class="text-sm text-gray-600">Redemption ID</span>
            <span class="text-xs font-mono text-gray-500">{{ redeemResult?.redemption_id?.slice(0, 8) }}...</span>
          </div>
          <div v-if="resolvedPartnershipName" class="flex items-center justify-between gap-8">
            <span class="text-sm text-gray-600">Partnership</span>
            <span class="text-sm font-medium text-gray-700">{{ resolvedPartnershipName }}</span>
          </div>
          <div class="flex items-center justify-between gap-8">
            <span class="text-sm text-gray-600">Benefit Applied</span>
            <span class="text-lg font-bold text-green-700">&#8377;{{ redeemResult?.benefit_amount?.toFixed(2) }}</span>
          </div>
          <div class="flex items-center justify-between gap-8">
            <span class="text-sm text-gray-600">Customer</span>
            <span
              class="inline-block text-xs rounded px-2 py-0.5 font-medium"
              :class="customerTypeBadge(redeemResult?.customer_type ?? 0).cls"
            >{{ redeemResult?.customer_type_label }}</span>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100 text-center">
        <button
          @click="resetAll"
          class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
          </svg>
          Redeem Another Token
        </button>
      </div>
    </div>
  </div>
</template>
