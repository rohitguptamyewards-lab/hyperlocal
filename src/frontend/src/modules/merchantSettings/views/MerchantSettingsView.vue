<!--
  MerchantSettingsView.vue — Merchant-level settings: point valuation history + update.
  Purpose: Let admins/managers declare the value of 1 loyalty point in ₹, view full history.
  Owner module: MerchantSettings
  API: GET /merchant/settings/point-valuation, POST /merchant/settings/point-valuation
  Integration: Partnership terms use rupees_per_point_at_agreement (locked at agreement time)
-->
<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()

interface ValuationRecord {
  id: number
  rupees_per_point: number
  effective_from: string
  confirmed_by: string
  note: string
  created_at: string
}

const current  = ref<ValuationRecord | null>(null)
const history  = ref<ValuationRecord[]>([])
const loading  = ref(true)
const loadErr  = ref<string | null>(null)

// ── Discoverability ──────────────────────────────────────────
const openToPartnerships  = ref(true)
const discSaving          = ref(false)
const discSaveOk          = ref(false)
const discErr             = ref<string | null>(null)

async function fetchDiscoverability() {
  try {
    const { data } = await api.get('/merchant/settings/discoverability')
    openToPartnerships.value = data.open_to_partnerships
  } catch {
    // non-blocking — default stays true
  }
}

async function saveDiscoverability() {
  discSaving.value = true
  discSaveOk.value = false
  discErr.value    = null
  try {
    const { data } = await api.post('/merchant/settings/discoverability', {
      open_to_partnerships: openToPartnerships.value,
    })
    openToPartnerships.value = data.open_to_partnerships
    discSaveOk.value = true
    setTimeout(() => { discSaveOk.value = false }, 3000)
  } catch {
    discErr.value = 'Failed to save. Please try again.'
  } finally {
    discSaving.value = false
  }
}

// Change form
const showForm   = ref(false)
const newRate    = ref<number | null>(null)
const newNote    = ref('')
const confirming = ref(false)   // second-step confirmation
const saving     = ref(false)
const saveErr    = ref<string | null>(null)
const saveOk     = ref(false)

async function fetchValuation() {
  loading.value = true
  loadErr.value = null
  try {
    const { data } = await api.get('/merchant/settings/point-valuation')
    current.value = data.current ?? null
    history.value = data.history ?? []
  } catch {
    loadErr.value = 'Failed to load point valuation settings.'
  } finally {
    loading.value = false
  }
}

// ── WhatsApp Credit Balance ──────────────────────────────────
const waBalance = ref<number | null>(null)

async function fetchWaBalance() {
  try {
    const { data } = await api.get('/merchant/whatsapp-balance')
    waBalance.value = data.balance
  } catch { /* non-blocking */ }
}

// ── eWards Request ───────────────────────────────────────────
interface EwardsRequest {
  uuid: string
  status: string
  notes: string | null
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string
}

const ewardsRequest  = ref<EwardsRequest | null>(null)
const ewardsLoading  = ref(false)
const ewardsNotes    = ref('')
const ewardsSaving   = ref(false)
const ewardsError    = ref<string | null>(null)
const ewardsSuccess  = ref(false)

async function fetchEwardsRequest() {
  ewardsLoading.value = true
  try {
    const { data } = await api.get('/merchant/ewards-request')
    ewardsRequest.value = data.request
  } finally {
    ewardsLoading.value = false
  }
}

async function submitEwardsRequest() {
  ewardsSaving.value = true
  ewardsError.value  = null
  ewardsSuccess.value = false
  try {
    await api.post('/merchant/ewards-request', { notes: ewardsNotes.value || null })
    ewardsSuccess.value = true
    await fetchEwardsRequest()
  } catch (err: any) {
    const errors = err.response?.data?.errors
    ewardsError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to submit request.')
  } finally {
    ewardsSaving.value = false
  }
}

onMounted(() => {
  fetchValuation()
  fetchDiscoverability()
  fetchIntegrations()
  fetchEwardsRequest()
  fetchWaBalance()
})

function openChangeForm() {
  newRate.value  = current.value?.rupees_per_point ?? null
  newNote.value  = ''
  confirming.value = false
  saveErr.value  = null
  saveOk.value   = false
  showForm.value = true
}

function cancelForm() {
  showForm.value   = false
  confirming.value = false
}

function proceedToConfirm() {
  if (!newRate.value || newRate.value <= 0) {
    saveErr.value = 'Enter a valid rate greater than 0.'
    return
  }
  if (!newNote.value.trim() || newNote.value.trim().length < 3) {
    saveErr.value = 'Please provide a reason for the change (at least 3 characters).'
    return
  }
  saveErr.value  = null
  confirming.value = true
}

async function confirmSave() {
  saving.value  = true
  saveErr.value = null
  try {
    await api.post('/merchant/settings/point-valuation', {
      rupees_per_point: newRate.value,
      note:             newNote.value.trim(),
    })
    await fetchValuation()
    saveOk.value     = true
    showForm.value   = false
    confirming.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const errData = err.response?.data
    if (errData?.errors) {
      saveErr.value = Object.values(errData.errors).flat().join(' ')
    } else {
      saveErr.value = errData?.message ?? 'Failed to save. Please try again.'
    }
    confirming.value = false
  } finally {
    saving.value = false
  }
}

const canEdit = computed(() => auth.isManager())

// ── Integrations ─────────────────────────────────────────────
interface Integration {
  id: number
  provider: string
  is_loyalty_source: boolean
  is_active: boolean
  has_config: boolean
  updated_at: string | null
}

const PROVIDER_LABELS: Record<string, string> = {
  ewrds:      'eWards',
  capillary:  'Capillary',
  pos_xyz:    'POS (Generic)',
  generic_pos: 'Generic POS',
}

const integrations     = ref<Integration[]>([])
const intLoading       = ref(true)
const showAddIntegration = ref(false)

const intForm = ref({
  provider:          'ewrds',
  is_loyalty_source: false,
  config: { api_key: '', base_url: '', brand_id: '' },
})
const intSaving  = ref(false)
const intError   = ref<string | null>(null)
const intOk      = ref(false)

async function fetchIntegrations() {
  intLoading.value = true
  try {
    const { data } = await api.get<Integration[]>('/merchant/integrations')
    integrations.value = data
  } catch {
    // non-blocking
  } finally {
    intLoading.value = false
  }
}

async function saveIntegration() {
  intError.value = null
  intOk.value    = false
  intSaving.value = true
  try {
    await api.post('/merchant/integrations', {
      provider:          intForm.value.provider,
      is_loyalty_source: intForm.value.is_loyalty_source,
      config:            intForm.value.config,
    })
    intOk.value          = true
    showAddIntegration.value = false
    await fetchIntegrations()
    setTimeout(() => { intOk.value = false }, 3000)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    intError.value = err.response?.data?.message ?? 'Failed to save integration.'
  } finally {
    intSaving.value = false
  }
}

async function deactivateIntegration(provider: string) {
  try {
    await api.delete(`/merchant/integrations/${provider}`)
    await fetchIntegrations()
  } catch {
    // ignore
  }
}

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
}

function fmtRate(r: number) {
  return r.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}
</script>

<template>
  <div class="p-4 sm:p-8 max-w-2xl">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Merchant Settings</h1>
      <p class="text-sm text-gray-500 mt-1">Configure how your loyalty points are valued across partnerships.</p>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 py-8 text-center">Loading…</div>
    <div v-else-if="loadErr" class="text-sm text-red-600 py-4">{{ loadErr }}</div>

    <template v-else>

      <!-- ── Discoverability card ──────────────────────────── -->
      <div class="bg-white border border-gray-200 rounded-xl mb-6">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="text-sm font-semibold text-gray-900">Partner discoverability</h2>
          <p class="text-xs text-gray-400 mt-0.5">
            Controls whether other merchants can find you in Find Partners search.
          </p>
        </div>
        <div class="px-5 py-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-900">
                {{ openToPartnerships ? 'Visible to other merchants' : 'Hidden from search' }}
              </p>
              <p class="text-xs text-gray-400 mt-0.5">
                {{ openToPartnerships
                  ? 'Other merchants can find and propose tie-ups with you.'
                  : 'You will not appear in any merchant\'s Find Partners results.' }}
              </p>
            </div>
            <!-- Toggle switch -->
            <button
              v-if="canEdit"
              @click="openToPartnerships = !openToPartnerships"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
              :class="openToPartnerships ? 'bg-indigo-600' : 'bg-gray-300'"
              role="switch"
              :aria-checked="openToPartnerships"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="openToPartnerships ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
            <span v-else class="text-xs px-2 py-1 rounded-full"
              :class="openToPartnerships ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
              {{ openToPartnerships ? 'Visible' : 'Hidden' }}
            </span>
          </div>

          <div v-if="canEdit" class="flex items-center gap-3 mt-4">
            <button
              @click="saveDiscoverability"
              :disabled="discSaving"
              class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ discSaving ? 'Saving…' : 'Save' }}
            </button>
            <span v-if="discSaveOk" class="text-xs text-green-700">✓ Saved</span>
            <span v-if="discErr"    class="text-xs text-red-600">{{ discErr }}</span>
          </div>
        </div>
      </div>

      <!-- ── Current valuation card ──────────────────────── -->
      <div class="bg-white border border-gray-200 rounded-xl mb-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Point valuation</h2>
          <button
            v-if="canEdit && !showForm"
            @click="openChangeForm"
            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
          >
            Change
          </button>
        </div>

        <!-- Current rate display -->
        <div class="px-5 py-5">
          <div v-if="current" class="flex items-end gap-4 mb-2">
            <div>
              <p class="text-xs text-gray-400 mb-1">Current rate</p>
              <p class="text-3xl font-bold text-gray-900">₹{{ fmtRate(current.rupees_per_point) }}</p>
              <p class="text-sm text-gray-500 mt-0.5">per point</p>
            </div>
            <div class="pb-1 text-xs text-gray-400 leading-relaxed">
              <p>Effective {{ fmtDate(current.effective_from) }}</p>
              <p>Set by {{ current.confirmed_by }}</p>
              <p v-if="current.note" class="italic">"{{ current.note }}"</p>
            </div>
          </div>
          <div v-else class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3">
            No point valuation set yet. Set one below so merchants can express partnership terms in points.
          </div>

          <!-- Examples when rate is set -->
          <div v-if="current" class="mt-4 rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
            <p class="text-xs font-medium text-indigo-700 mb-2">What this means in partnerships</p>
            <ul class="text-xs text-indigo-600 space-y-1">
              <li>100 pts cap = ₹{{ (100 * current.rupees_per_point).toLocaleString('en-IN', { maximumFractionDigits: 2 }) }} cap per bill</li>
              <li>500 pts min bill = ₹{{ (500 * current.rupees_per_point).toLocaleString('en-IN', { maximumFractionDigits: 2 }) }} minimum</li>
              <li>1,000 pts monthly cap = ₹{{ (1000 * current.rupees_per_point).toLocaleString('en-IN', { maximumFractionDigits: 2 }) }} monthly</li>
            </ul>
          </div>
        </div>

        <!-- Change form -->
        <div v-if="showForm" class="border-t border-gray-100 px-5 py-5">
          <p class="text-sm font-medium text-gray-800 mb-4">
            {{ confirming ? 'Confirm change' : 'Set new point valuation' }}
          </p>

          <template v-if="!confirming">
            <div class="space-y-4">
              <div>
                <label class="block text-xs text-gray-500 mb-1">
                  New rate — ₹ per point <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-500">1 pt =</span>
                  <span class="text-sm text-gray-500">₹</span>
                  <input
                    v-model.number="newRate"
                    type="number"
                    min="0.0001"
                    max="100000"
                    step="0.01"
                    placeholder="e.g. 1.00"
                    class="w-36 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <p v-if="newRate && newRate > 0" class="text-xs text-gray-400 mt-1">
                  → 100 pts = ₹{{ (100 * newRate).toLocaleString('en-IN', { maximumFractionDigits: 2 }) }}
                </p>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">
                  Reason for change <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="newNote"
                  type="text"
                  maxlength="255"
                  placeholder="e.g. Adjusted to match current program value"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <p class="text-xs text-gray-400 mt-1">This is logged for audit — visible to your team.</p>
              </div>
            </div>
            <div v-if="saveErr" class="mt-3 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              {{ saveErr }}
            </div>
            <div class="flex gap-2 mt-4">
              <button
                @click="proceedToConfirm"
                class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
              >
                Continue →
              </button>
              <button @click="cancelForm" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
                Cancel
              </button>
            </div>
          </template>

          <template v-else>
            <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-4 mb-4">
              <p class="text-sm font-semibold text-amber-800 mb-2">Review before saving</p>
              <ul class="text-sm text-amber-700 space-y-1">
                <li>New rate: <strong>₹{{ fmtRate(newRate!) }} per point</strong></li>
                <li>Reason: <em>"{{ newNote }}"</em></li>
              </ul>
              <p class="text-xs text-amber-600 mt-3">
                <strong>Note:</strong> This rate takes effect immediately for new partnerships.
                Existing partnerships keep the rate that was locked at the time of their agreement — this change does not affect them.
              </p>
            </div>
            <div v-if="saveErr" class="mb-3 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              {{ saveErr }}
            </div>
            <div class="flex gap-2">
              <button
                @click="confirmSave"
                :disabled="saving"
                class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
              >
                {{ saving ? 'Saving…' : 'Confirm & save' }}
              </button>
              <button @click="confirming = false" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
                Back
              </button>
            </div>
          </template>
        </div>

        <!-- Save success -->
        <div v-if="saveOk" class="mx-5 mb-5 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
          ✓ Point valuation updated successfully.
        </div>
      </div>

      <!-- ── Integrations card ──────────────────────────── -->
      <div class="bg-white border border-gray-200 rounded-xl mb-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Integrations</h2>
            <p class="text-xs text-gray-400 mt-0.5">Connect eWards, a POS, or other loyalty providers.</p>
          </div>
          <button
            v-if="canEdit && !showAddIntegration"
            @click="showAddIntegration = true"
            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
          >
            + Add
          </button>
        </div>

        <!-- Integration list -->
        <div v-if="intLoading" class="px-5 py-4 text-sm text-gray-400">Loading…</div>
        <div v-else-if="integrations.length === 0 && !showAddIntegration" class="px-5 py-4 text-sm text-gray-400">
          No integrations configured. Add one to sync loyalty balances with an external provider.
        </div>
        <div v-else-if="integrations.length > 0" class="divide-y divide-gray-50">
          <div
            v-for="int in integrations"
            :key="int.id"
            class="px-5 py-3 flex items-center justify-between gap-3"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm font-medium text-gray-900">{{ PROVIDER_LABELS[int.provider] ?? int.provider }}</p>
                <span v-if="int.is_loyalty_source" class="text-xs bg-indigo-100 text-indigo-700 rounded px-2 py-0.5">Loyalty source</span>
                <span
                  class="text-xs rounded px-2 py-0.5"
                  :class="int.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                  {{ int.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <p v-if="int.updated_at" class="text-xs text-gray-400 mt-0.5">
                Updated {{ new Date(int.updated_at).toLocaleDateString() }}
              </p>
            </div>
            <button
              v-if="canEdit && int.is_active"
              @click="deactivateIntegration(int.provider)"
              class="text-xs text-red-500 hover:text-red-700 transition-colors"
            >
              Deactivate
            </button>
          </div>
        </div>

        <!-- Success banner -->
        <div v-if="intOk" class="mx-5 mb-4 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
          ✓ Integration saved.
        </div>

        <!-- Add form -->
        <div v-if="showAddIntegration && canEdit" class="border-t border-gray-100 px-5 py-5 space-y-4">
          <p class="text-sm font-medium text-gray-800">Add integration</p>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Provider</label>
            <select v-model="intForm.provider" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="ewrds">eWards</option>
              <option value="generic_pos">Generic POS</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">API Key</label>
            <input v-model="intForm.config.api_key" type="password" placeholder="sk_live_…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Base URL</label>
            <input v-model="intForm.config.base_url" type="text" placeholder="https://api.provider.com/v1" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div v-if="intForm.provider === 'ewrds'">
            <label class="block text-xs text-gray-500 mb-1">Brand ID</label>
            <input v-model="intForm.config.brand_id" type="text" placeholder="your-brand-id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="intForm.is_loyalty_source" class="accent-indigo-600" />
            Use as loyalty balance source (replaces local ledger for this merchant)
          </label>

          <div v-if="intError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ intError }}
          </div>

          <div class="flex gap-2">
            <button
              @click="saveIntegration"
              :disabled="intSaving || !intForm.config.api_key || !intForm.config.base_url"
              class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ intSaving ? 'Saving…' : 'Save integration' }}
            </button>
            <button @click="showAddIntegration = false" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
              Cancel
            </button>
          </div>
        </div>
      </div>

      <!-- ── Valuation history ────────────────────────────── -->
      <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="text-sm font-semibold text-gray-900">Valuation history</h2>
          <p class="text-xs text-gray-400 mt-0.5">All valuations are immutable — each change creates a new entry.</p>
        </div>

        <div v-if="history.length === 0" class="px-5 py-4 text-sm text-gray-400">
          No history yet.
        </div>

        <div v-else class="divide-y divide-gray-50">
          <div
            v-for="(v, idx) in history"
            :key="v.id"
            class="px-5 py-3 flex items-start justify-between gap-4"
            :class="idx === 0 ? 'bg-indigo-50/50' : ''"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-gray-900">₹{{ fmtRate(v.rupees_per_point) }} / pt</p>
                <span v-if="idx === 0" class="text-xs bg-indigo-100 text-indigo-700 rounded px-2 py-0.5 font-medium">Current</span>
              </div>
              <p class="text-xs text-gray-400 mt-0.5">
                {{ fmtDate(v.effective_from) }} · Set by {{ v.confirmed_by }}
              </p>
              <p v-if="v.note" class="text-xs text-gray-500 mt-0.5 italic">"{{ v.note }}"</p>
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- ── WhatsApp Credit Balance ──────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">
      <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">WhatsApp Credits</h2>
        <p class="text-xs text-gray-400 mt-0.5">Used for campaign messages and claim notifications. Contact your platform admin to top up.</p>
      </div>
      <div class="px-5 py-5 flex items-center gap-4">
        <div
          class="text-3xl font-bold"
          :class="waBalance === 0 ? 'text-red-600' : waBalance !== null && waBalance <= 50 ? 'text-orange-500' : 'text-gray-900'"
        >
          {{ waBalance !== null ? waBalance.toLocaleString() : '—' }}
        </div>
        <div class="text-sm text-gray-500">credits remaining</div>
        <div v-if="waBalance === 0" class="ml-auto text-xs text-orange-600 bg-orange-50 border border-orange-200 rounded-lg px-3 py-1.5 font-medium">
          0 credits — top up to send messages
        </div>
        <div v-else-if="waBalance !== null && waBalance <= 50" class="ml-auto text-xs text-orange-600 bg-orange-50 border border-orange-200 rounded-lg px-3 py-1.5 font-medium">
          Low balance — contact admin
        </div>
      </div>
    </div>

    <!-- ── eWards Integration Request ──────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="text-sm font-semibold text-gray-900">eWards Integration</h2>
          <p class="text-xs text-gray-400 mt-0.5">Request access to sync your eWards customer data and activate loyalty bridging.</p>
        </div>

        <div v-if="ewardsLoading" class="px-5 py-6 text-sm text-gray-400">Loading…</div>

        <div v-else class="px-5 py-5 space-y-4">
          <!-- Already approved -->
          <div v-if="ewardsRequest?.status === 'approved'" class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
            <div class="w-2 h-2 rounded-full bg-green-500 shrink-0"></div>
            <div>
              <p class="text-sm font-medium text-green-800">eWards integration is active</p>
              <p class="text-xs text-green-600 mt-0.5">Approved on {{ fmtDate(ewardsRequest.reviewed_at) }}</p>
            </div>
          </div>

          <!-- Pending -->
          <div v-else-if="ewardsRequest?.status === 'pending'" class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-3">
            <div class="w-2 h-2 rounded-full bg-yellow-400 shrink-0"></div>
            <div>
              <p class="text-sm font-medium text-yellow-800">Request submitted — awaiting review</p>
              <p class="text-xs text-yellow-600 mt-0.5">Submitted {{ fmtDate(ewardsRequest.created_at) }}</p>
            </div>
          </div>

          <!-- Rejected — allow re-apply -->
          <div v-else-if="ewardsRequest?.status === 'rejected'">
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
              <div class="w-2 h-2 rounded-full bg-red-500 shrink-0"></div>
              <div>
                <p class="text-sm font-medium text-red-800">Previous request was rejected</p>
                <p class="text-xs text-red-600 mt-0.5">Reason: {{ ewardsRequest.rejection_reason ?? 'No reason provided.' }}</p>
              </div>
            </div>
            <EwardsRequestForm :notes="ewardsNotes" @update:notes="ewardsNotes = $event" :loading="ewardsSaving" :error="ewardsError" :success="ewardsSuccess" @submit="submitEwardsRequest" />
          </div>

          <!-- No request -->
          <div v-else>
            <p class="text-sm text-gray-600 mb-4">
              Submit a request to enable eWards integration for your account. Once approved by the platform team,
              your eWards customer data will be synced automatically.
            </p>
            <!-- Inline form -->
            <div v-if="ewardsError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-3">{{ ewardsError }}</div>
            <div v-if="ewardsSuccess" class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg p-3 mb-3">Request submitted! You'll be notified when it's reviewed.</div>
            <div class="mb-3">
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
              <textarea v-model="ewardsNotes" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" placeholder="Any details for the platform team…" />
            </div>
            <button
              :disabled="ewardsSaving"
              class="px-4 py-2.5 bg-gray-900 text-white text-sm rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors"
              @click="submitEwardsRequest"
            >{{ ewardsSaving ? 'Submitting…' : 'Request eWards Integration' }}</button>
          </div>
        </div>
      </div>

  </div>
</template>
