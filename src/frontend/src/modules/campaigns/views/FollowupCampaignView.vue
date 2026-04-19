<!--
  FollowupCampaignView.vue — Follow-up Campaign management (GAP 6).
  Purpose: Merchants configure automatic follow-up messages for expired unredeemed tokens.
  Owner module: Campaigns
  API: GET /followup-campaigns, POST /followup-campaigns,
       PUT /followup-campaigns/{id}, GET /followup-campaigns/stats,
       GET /partnerships (for partnership filter)
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

// ── Types ────────────────────────────────────────────────────

interface FollowupCampaign {
  id: number
  trigger_type: string
  delay_hours: number
  message_template: string
  is_active: boolean
  sent_count: number
  partnership_id: number | null
  partnership_name: string | null
  created_at: string
  updated_at: string
}

interface Partnership {
  id: number
  uuid: string
  name: string
}

// ── State ────────────────────────────────────────────────────

const campaigns        = ref<FollowupCampaign[]>([])
const partnerships     = ref<Partnership[]>([])
const expiredCount     = ref(0)
const loading          = ref(true)
const loadError        = ref<string | null>(null)

// Modal
const modalOpen        = ref(false)
const modalSaving      = ref(false)
const modalError       = ref<string | null>(null)

const formTriggerType      = ref<'token_expired_unredeemed'>('token_expired_unredeemed')
const formDelayHours       = ref<number>(24)
const formMessageTemplate  = ref('')
const formPartnershipId    = ref<number | null>(null)

const defaultTemplate = 'Hi! Your exclusive discount token from our partnership offer has expired unredeemed. Visit us again to get a fresh token and enjoy your benefit!'

const delayOptions = [
  { value: 6,  label: '6 hours' },
  { value: 12, label: '12 hours' },
  { value: 24, label: '24 hours' },
  { value: 48, label: '48 hours' },
]

// Toggle loading state per campaign id
const togglingId = ref<number | null>(null)

// ── Computed ──────────────────────────────────────────────────

const activeCampaigns   = computed(() => campaigns.value.filter(c => c.is_active))
const inactiveCampaigns = computed(() => campaigns.value.filter(c => !c.is_active))

// ── API calls ─────────────────────────────────────────────────

async function loadData() {
  loading.value   = true
  loadError.value = null
  try {
    const [campaignsRes, statsRes, partnershipsRes] = await Promise.all([
      api.get<FollowupCampaign[]>('/followup-campaigns'),
      api.get<{ expired_unredeemed_count: number }>('/followup-campaigns/stats'),
      api.get<{ data?: Partnership[]; [key: string]: unknown }>('/partnerships'),
    ])
    campaigns.value    = campaignsRes.data ?? []
    expiredCount.value = statsRes.data?.expired_unredeemed_count ?? 0
    const pData        = partnershipsRes.data
    partnerships.value = (Array.isArray(pData) ? pData : (pData?.data ?? [])) as Partnership[]
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    loadError.value = err.response?.data?.message ?? 'Failed to load follow-up campaigns.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  modalError.value          = null
  formTriggerType.value     = 'token_expired_unredeemed'
  formDelayHours.value      = 24
  formMessageTemplate.value = defaultTemplate
  formPartnershipId.value   = null
  modalOpen.value           = true
}

async function saveNewCampaign() {
  if (!formMessageTemplate.value.trim()) {
    modalError.value = 'Message template is required.'
    return
  }
  modalError.value   = null
  modalSaving.value  = true
  try {
    await api.post('/followup-campaigns', {
      trigger_type:     formTriggerType.value,
      delay_hours:      formDelayHours.value,
      message_template: formMessageTemplate.value,
      partnership_id:   formPartnershipId.value || null,
    })
    modalOpen.value = false
    await loadData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    modalError.value = err.response?.data?.message ?? 'Failed to create campaign.'
  } finally {
    modalSaving.value = false
  }
}

async function toggleCampaign(campaign: FollowupCampaign) {
  togglingId.value = campaign.id
  try {
    await api.put(`/followup-campaigns/${campaign.id}`, {
      is_active: !campaign.is_active,
    })
    campaign.is_active = !campaign.is_active
  } catch {
    // silently revert — user can retry
  } finally {
    togglingId.value = null
  }
}

// ── Lifecycle ─────────────────────────────────────────────────

onMounted(loadData)
</script>

<template>
  <div class="p-4 sm:p-8 max-w-3xl">

    <!-- Header -->
    <div class="flex items-start justify-between mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Follow-up Campaigns</h1>
        <p class="text-sm text-gray-500 mt-1">Automatically re-engage customers whose partner tokens expired without redemption.</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex-shrink-0 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
      >
        + Create Campaign
      </button>
    </div>

    <!-- Stat card -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
      <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500">Expired Unredeemed Tokens</p>
          <p class="text-2xl font-bold text-gray-900">{{ expiredCount.toLocaleString() }}</p>
        </div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500">Active Follow-up Rules</p>
          <p class="text-2xl font-bold text-gray-900">{{ activeCampaigns.length }}</p>
        </div>
      </div>
    </div>

    <!-- Loading / Error -->
    <div v-if="loading" class="text-sm text-gray-400 py-8 text-center">Loading…</div>
    <div v-else-if="loadError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 mb-6">
      {{ loadError }}
    </div>

    <!-- Empty state -->
    <div v-else-if="campaigns.length === 0" class="text-center py-16 bg-white border border-gray-200 rounded-xl">
      <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
        </svg>
      </div>
      <p class="text-sm font-medium text-gray-700 mb-1">No follow-up campaigns yet</p>
      <p class="text-xs text-gray-400 mb-4">Create one to automatically reach customers with expired tokens.</p>
      <button
        @click="openCreateModal"
        class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
      >
        Create Follow-up Campaign
      </button>
    </div>

    <!-- Campaign list -->
    <div v-else class="space-y-3">
      <div
        v-for="campaign in campaigns"
        :key="campaign.id"
        class="bg-white border border-gray-200 rounded-xl px-5 py-4"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <!-- Trigger type badge -->
            <div class="flex items-center gap-2 mb-1.5">
              <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-medium">
                Token Expired Unredeemed
              </span>
              <span class="text-xs text-gray-400">after {{ campaign.delay_hours }}h</span>
              <span v-if="campaign.partnership_name" class="text-xs text-gray-400">
                · {{ campaign.partnership_name }}
              </span>
            </div>
            <!-- Message preview -->
            <p class="text-sm text-gray-700 leading-relaxed line-clamp-2">{{ campaign.message_template }}</p>
            <!-- Stats -->
            <p class="text-xs text-gray-400 mt-2">
              Sent {{ campaign.sent_count.toLocaleString() }} time{{ campaign.sent_count !== 1 ? 's' : '' }}
              · Created {{ new Date(campaign.created_at).toLocaleDateString() }}
            </p>
          </div>

          <!-- Active toggle -->
          <div class="flex items-center gap-2 flex-shrink-0 mt-1">
            <span class="text-xs" :class="campaign.is_active ? 'text-green-600' : 'text-gray-400'">
              {{ campaign.is_active ? 'Active' : 'Inactive' }}
            </span>
            <button
              :disabled="togglingId === campaign.id"
              @click="toggleCampaign(campaign)"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none disabled:opacity-50"
              :class="campaign.is_active ? 'bg-indigo-600' : 'bg-gray-300'"
              :title="campaign.is_active ? 'Deactivate' : 'Activate'"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="campaign.is_active ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div
        v-if="modalOpen"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 px-4 pb-4 sm:pb-0"
        @click.self="modalOpen = false"
      >
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden">
          <!-- Modal header -->
          <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Create Follow-up Campaign</h2>
            <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
          </div>

          <!-- Modal body -->
          <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

            <!-- Error -->
            <div v-if="modalError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
              {{ modalError }}
            </div>

            <!-- Trigger type (display only) -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Trigger</label>
              <div class="flex items-center gap-2 px-3 py-2 bg-indigo-50 border border-indigo-200 rounded-lg">
                <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm text-indigo-800 font-medium">Token Expired Unredeemed</span>
              </div>
              <p class="text-xs text-gray-400 mt-1">Fires when a partner claim token expires without being redeemed.</p>
            </div>

            <!-- Delay -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Send follow-up after</label>
              <select
                v-model="formDelayHours"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option v-for="opt in delayOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>

            <!-- Partnership filter -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">
                Partnership filter <span class="text-gray-400 font-normal">(optional — leave blank for all)</span>
              </label>
              <select
                v-model="formPartnershipId"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option :value="null">All partnerships</option>
                <option v-for="p in partnerships" :key="p.uuid" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <!-- Message template -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-medium text-gray-600">Message template</label>
                <span class="text-xs text-gray-400">{{ formMessageTemplate.length }} / 1000</span>
              </div>
              <textarea
                v-model="formMessageTemplate"
                rows="5"
                maxlength="1000"
                placeholder="Enter message template…"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
              />
              <p class="text-xs text-gray-400 mt-1">This message will be sent to customers whose tokens matched the trigger condition.</p>
            </div>
          </div>

          <!-- Modal footer -->
          <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <button
              @click="modalOpen = false"
              class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="saveNewCampaign"
              :disabled="modalSaving || !formMessageTemplate.trim()"
              class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ modalSaving ? 'Saving…' : 'Create Campaign' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
