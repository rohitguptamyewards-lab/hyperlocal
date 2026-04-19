<!--
  CampaignView.vue — Campaign list, create, schedule, and stats.
  Purpose: Merchant campaign management — standardised WhatsApp templates only (V1).
  Owner module: Campaign
  API: GET /campaigns, GET /campaigns/templates, POST /campaigns,
       POST /campaigns/:uuid/schedule, POST /campaigns/:uuid/run,
       POST /campaigns/:uuid/cancel, GET /campaigns/:uuid,
       POST /campaigns/segment-preview,
       GET /partnerships (for partner segment picker),
       GET /merchant/whatsapp-balance
-->
<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()

// ── Types ────────────────────────────────────────────────────
interface Template {
  key: string
  label: string
  required_vars: string[]
  partner_segment: boolean
}

interface Campaign {
  uuid: string
  name: string
  template_key: string
  template_label?: string
  status: number
  scheduled_at: string | null
  started_at: string | null
  completed_at: string | null
  sends?: { pending: number; sent: number; failed: number; delivered: number }
}

interface Partnership {
  uuid: string
  name: string
  partner_name: string
  campaigns_enabled_by_partner: boolean
}

// ── State ────────────────────────────────────────────────────
const campaigns   = ref<Campaign[]>([])
const templates   = ref<Template[]>([])
const partnerships = ref<Partnership[]>([])
const waBalance   = ref<number | null>(null)
const loading     = ref(true)
const loadError   = ref<string | null>(null)

const showCreate  = ref(false)
const selected    = ref<Campaign | null>(null)
const detailLoading = ref(false)

// Create form
const form = ref({
  name:             '',
  template_key:     '',
  template_vars:    {} as Record<string, string>,
  segment_source:   'own' as 'own' | 'partner',
  partner_filter:   'via_partnership' as 'via_partnership' | 'all_customers',
  last_seen_days:   '',
  partnership_uuid: '',
  schedule_mode:    'now' as 'now' | 'later',
  scheduled_at:     '',
})

const creating         = ref(false)
const createError      = ref<string | null>(null)
const createOk         = ref(false)
const segmentCount     = ref<number | null>(null)
const segmentCounting  = ref(false)

// ── Lifecycle ────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([fetchCampaigns(), fetchTemplates()])
})

async function fetchCampaigns() {
  loading.value   = true
  loadError.value = null
  try {
    const { data } = await api.get<{ data: Campaign[] }>('/campaigns')
    campaigns.value = data.data ?? (data as unknown as Campaign[])
  } catch {
    loadError.value = 'Failed to load campaigns.'
  } finally {
    loading.value = false
  }
}

async function fetchTemplates() {
  try {
    const { data } = await api.get<Template[]>('/campaigns/templates')
    templates.value = data
  } catch { /* non-blocking */ }
}

async function fetchDrawerData() {
  await Promise.all([
    fetchPartnerships(),
    fetchWaBalance(),
  ])
}

async function fetchPartnerships() {
  try {
    const { data } = await api.get<{ data: any[] }>('/partnerships', { params: { status: 5 } })
    const myMerchantId = auth.user?.merchant?.id

    partnerships.value = (data.data ?? [])
      .map((p: any) => {
        // Find the partner's participant (not mine) to check campaigns_enabled
        const partnerParticipant = Array.isArray(p.participants)
          ? p.participants.find((pt: any) => Number(pt.merchant_id) !== Number(myMerchantId))
          : null
        return {
          uuid:                        p.uuid ?? p.id,
          name:                        p.name ?? '',
          partner_name:                p.partner_name ?? p.name ?? 'Partnership',
          campaigns_enabled_by_partner: partnerParticipant?.campaigns_enabled !== false,
        }
      })
      .filter((p) => !!p.uuid && p.campaigns_enabled_by_partner)
  } catch { /* non-blocking */ }
}

async function fetchWaBalance() {
  try {
    const { data } = await api.get('/merchant/whatsapp-balance')
    waBalance.value = data.balance
  } catch { /* non-blocking */ }
}

async function fetchDetail(uuid: string) {
  detailLoading.value = true
  try {
    const { data } = await api.get<Campaign>(`/campaigns/${uuid}`)
    selected.value = data
  } finally {
    detailLoading.value = false
  }
}

// ── Template selection ────────────────────────────────────────
function onTemplateChange() {
  form.value.template_vars = {}
  segmentCount.value = null
  // Pre-select partner segment for templates that suggest it
  if (currentTemplate.value?.partner_segment) {
    form.value.segment_source = 'partner'
  } else {
    form.value.segment_source = 'own'
  }
}

const currentTemplate = computed(() =>
  templates.value.find(t => t.key === form.value.template_key) ?? null
)

// ── Segment count preview ─────────────────────────────────────
let segmentDebounce: ReturnType<typeof setTimeout> | null = null

function scheduleSegmentPreview() {
  if (segmentDebounce) clearTimeout(segmentDebounce)
  segmentDebounce = setTimeout(runSegmentPreview, 600)
}

async function runSegmentPreview() {
  const segment = buildSegment()
  // Need at least a partnership_uuid for partner segments
  if (form.value.segment_source === 'partner' && !form.value.partnership_uuid) {
    segmentCount.value = null
    return
  }
  segmentCounting.value = true
  try {
    const { data } = await api.post('/campaigns/segment-preview', { target_segment: segment })
    segmentCount.value = data.count
  } catch {
    segmentCount.value = null
  } finally {
    segmentCounting.value = false
  }
}

watch(
  () => [form.value.segment_source, form.value.last_seen_days, form.value.partnership_uuid, form.value.partner_filter],
  () => scheduleSegmentPreview(),
)

function buildSegment(): Record<string, unknown> {
  if (form.value.segment_source === 'partner' && form.value.partnership_uuid) {
    return {
      source:         'partner',
      partnership_id: form.value.partnership_uuid,
      partner_filter: form.value.partner_filter,
    }
  }
  const seg: Record<string, unknown> = { source: 'own' }
  if (form.value.last_seen_days) {
    seg.last_seen_days = Number(form.value.last_seen_days)
  }
  return seg
}

// ── Create ────────────────────────────────────────────────────
async function submitCreate() {
  createError.value = null
  createOk.value    = false
  creating.value    = true

  try {
    const payload = {
      name:           form.value.name,
      template_key:   form.value.template_key,
      template_vars:  form.value.template_vars,
      target_segment: buildSegment(),
    }

    const { data } = await api.post<{ uuid: string }>('/campaigns', payload)
    const uuid = data.uuid

    if (form.value.schedule_mode === 'later' && form.value.scheduled_at) {
      await api.post(`/campaigns/${uuid}/schedule`, { scheduled_at: form.value.scheduled_at })
    } else {
      await api.post(`/campaigns/${uuid}/run`)
    }

    createOk.value   = true
    showCreate.value = false
    resetForm()
    await fetchCampaigns()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const d = err.response?.data
    createError.value = d?.errors
      ? Object.values(d.errors).flat().join(' ')
      : (d?.message ?? 'Failed to create campaign.')
  } finally {
    creating.value = false
  }
}

function resetForm() {
  form.value = {
    name: '', template_key: '', template_vars: {},
    segment_source: 'own', partner_filter: 'via_partnership',
    last_seen_days: '', partnership_uuid: '',
    schedule_mode: 'now', scheduled_at: '',
  }
  createError.value  = null
  segmentCount.value = null
}

// ── Cancel campaign ───────────────────────────────────────────
async function cancelCampaign(uuid: string) {
  try {
    await api.post(`/campaigns/${uuid}/cancel`)
    await fetchCampaigns()
    if (selected.value?.uuid === uuid) selected.value = null
  } catch { /* ignore */ }
}

// ── Helpers ───────────────────────────────────────────────────
const STATUS_LABELS: Record<number, string> = {
  1: 'Draft', 2: 'Scheduled', 3: 'Running', 4: 'Completed', 5: 'Cancelled',
}
const STATUS_COLOURS: Record<number, string> = {
  1: 'bg-gray-100 text-gray-600',
  2: 'bg-blue-100 text-blue-700',
  3: 'bg-yellow-100 text-yellow-700',
  4: 'bg-green-100 text-green-700',
  5: 'bg-red-100 text-red-600',
}

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const canManage = computed(() => auth.isManager())

const selectedPartnership = computed(() =>
  partnerships.value.find(p => p.uuid === form.value.partnership_uuid) ?? null
)

const minSchedule = computed(() => {
  const d = new Date()
  d.setMinutes(d.getMinutes() + 5)
  return d.toISOString().slice(0, 16)
})

const waBalanceClass = computed(() => {
  if (waBalance.value === null) return 'text-gray-400'
  if (waBalance.value === 0) return 'text-red-600 font-bold'
  if (waBalance.value <= 50) return 'text-orange-500 font-semibold'
  return 'text-green-700'
})
</script>

<template>
  <div class="p-4 sm:p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Campaigns</h1>
        <p class="text-sm text-gray-500 mt-0.5">Send WhatsApp messages to your customers or partner merchant's customers.</p>
      </div>
      <button
        v-if="canManage"
        class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
        @click="showCreate = true; resetForm(); fetchDrawerData()"
      >
        + New campaign
      </button>
    </div>

    <!-- Success banner -->
    <div v-if="createOk" class="mb-4 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
      Campaign created and queued.
    </div>

    <!-- ── Campaign list ──────────────────────────────────── -->
    <div v-if="loading" class="text-sm text-gray-400 py-10 text-center">Loading…</div>
    <div v-else-if="loadError" class="text-sm text-red-600">{{ loadError }}</div>

    <template v-else>
      <div v-if="campaigns.length === 0" class="text-sm text-gray-400 py-10 text-center">
        No campaigns yet. Create your first one above.
      </div>

      <div v-else class="grid gap-3">
        <div
          v-for="c in campaigns"
          :key="c.uuid"
          class="bg-white border border-gray-200 rounded-xl px-5 py-4 cursor-pointer hover:border-indigo-200 transition-colors"
          @click="fetchDetail(c.uuid); selected = c"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-900 truncate">{{ c.name }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ c.template_label ?? c.template_key }}</p>
            </div>
            <span class="shrink-0 text-xs font-medium px-2 py-1 rounded-full" :class="STATUS_COLOURS[c.status]">
              {{ STATUS_LABELS[c.status] }}
            </span>
          </div>
          <div class="flex gap-4 mt-2 text-xs text-gray-400">
            <span v-if="c.scheduled_at">Scheduled {{ fmtDate(c.scheduled_at) }}</span>
            <span v-if="c.completed_at">Completed {{ fmtDate(c.completed_at) }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Campaign detail panel ────────────────────────────── -->
    <div
      v-if="selected"
      class="fixed inset-0 bg-black/30 z-40 flex items-start justify-end"
      @click.self="selected = null"
    >
      <div class="bg-white w-full max-w-md h-full overflow-y-auto shadow-xl">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
          <p class="font-semibold text-gray-900 truncate">{{ selected.name }}</p>
          <button class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="selected = null">×</button>
        </div>

        <div v-if="detailLoading" class="p-6 text-sm text-gray-400 text-center">Loading…</div>

        <div v-else class="p-6 space-y-5">
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium px-2 py-1 rounded-full" :class="STATUS_COLOURS[selected.status]">
              {{ STATUS_LABELS[selected.status] }}
            </span>
            <span class="text-xs text-gray-400">{{ selected.template_label ?? selected.template_key }}</span>
          </div>

          <!-- Send stats -->
          <div v-if="selected.sends" class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
            <div v-for="(val, key) in selected.sends" :key="key" class="bg-gray-50 rounded-lg py-3">
              <p class="text-lg font-bold text-gray-900">{{ val }}</p>
              <p class="text-xs text-gray-400 capitalize">{{ key }}</p>
            </div>
          </div>

          <div class="text-xs text-gray-400 space-y-1">
            <p v-if="selected.scheduled_at">Scheduled: {{ fmtDate(selected.scheduled_at) }}</p>
            <p v-if="selected.started_at">Started: {{ fmtDate(selected.started_at) }}</p>
            <p v-if="selected.completed_at">Completed: {{ fmtDate(selected.completed_at) }}</p>
          </div>

          <button
            v-if="canManage && (selected.status === 1 || selected.status === 2)"
            class="w-full text-sm text-red-600 border border-red-200 rounded-lg px-4 py-2 hover:bg-red-50 transition-colors"
            @click="cancelCampaign(selected!.uuid)"
          >
            Cancel campaign
          </button>
        </div>
      </div>
    </div>

    <!-- ── Create campaign drawer ──────────────────────────── -->
    <div
      v-if="showCreate"
      class="fixed inset-0 bg-black/30 z-40 flex items-start justify-end"
      @click.self="showCreate = false"
    >
      <div class="bg-white w-full max-w-md h-full overflow-y-auto shadow-xl">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
          <p class="font-semibold text-gray-900">New campaign</p>
          <button class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="showCreate = false">×</button>
        </div>

        <div class="p-6 space-y-5">

          <!-- WhatsApp credit balance indicator -->
          <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3 border border-gray-200">
            <span class="text-xs text-gray-500">WhatsApp credits</span>
            <span class="text-sm" :class="waBalanceClass">
              <span v-if="waBalance === null">—</span>
              <span v-else-if="waBalance === 0">0 credits (top up to send live messages)</span>
              <span v-else>{{ waBalance.toLocaleString() }} remaining</span>
            </span>
          </div>

          <!-- Campaign name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Campaign name</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="e.g. July promo for coffee shop customers"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <!-- Template picker -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Message template</label>
            <select
              v-model="form.template_key"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              @change="onTemplateChange"
            >
              <option value="" disabled>Select a template</option>
              <option v-for="t in templates" :key="t.key" :value="t.key">{{ t.label }}</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">V1: standardised templates only — no custom messages.</p>
          </div>

          <!-- Dynamic variable inputs -->
          <div v-if="currentTemplate && currentTemplate.required_vars.length > 0" class="space-y-3">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Template variables</p>

            <div v-for="varName in currentTemplate.required_vars" :key="varName">
              <label class="block text-sm font-medium text-gray-700 mb-1 capitalize">
                {{ varName.replace(/_/g, ' ') }}
              </label>

              <input
                v-model="form.template_vars[varName]"
                type="text"
                :placeholder="`Enter ${varName.replace(/_/g, ' ')}`"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <!-- ── Audience / Segment ──────────────────────── -->
          <div class="space-y-3">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Audience</p>

            <!-- Source toggle -->
            <div class="flex gap-3">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.segment_source" type="radio" value="own" class="accent-indigo-600" />
                My customers
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.segment_source" type="radio" value="partner" class="accent-indigo-600" />
                Partner's customers
              </label>
            </div>

            <!-- Own customers: last_seen filter -->
            <div v-if="form.segment_source === 'own'">
              <label class="block text-sm text-gray-600 mb-1">Last seen within</label>
              <div class="flex items-center gap-2">
                <input
                  v-model="form.last_seen_days"
                  type="number"
                  min="1"
                  placeholder="30"
                  class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <span class="text-sm text-gray-500">days</span>
                <span class="text-xs text-gray-400">(blank = all opted-in)</span>
              </div>
            </div>

            <!-- Partner customers: partnership picker + filter -->
            <div v-if="form.segment_source === 'partner'" class="space-y-3">

              <!-- Partnership dropdown -->
              <div>
                <label class="block text-sm text-gray-600 mb-1">Select live partnership</label>
                <select
                  v-model="form.partnership_uuid"
                  @change="form.partner_filter = 'via_partnership'"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="" disabled>— choose a partnership —</option>
                  <option v-for="p in partnerships" :key="p.uuid" :value="p.uuid">
                    {{ p.partner_name }}  ·  {{ p.name }}
                  </option>
                </select>
                <p v-if="partnerships.length === 0" class="text-xs text-orange-500 mt-1">
                  No live partnerships with campaign targeting enabled.
                  Go live with a partnership first, or ask your partner to enable campaign targeting in their settings.
                </p>
              </div>

              <!-- Customer filter — shown once a partnership is selected -->
              <div v-if="form.partnership_uuid" class="rounded-xl border border-gray-200 p-3 space-y-2.5 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Which customers to target</p>

                <label class="flex items-start gap-2.5 cursor-pointer">
                  <input
                    type="radio"
                    v-model="form.partner_filter"
                    value="via_partnership"
                    class="accent-indigo-600 mt-0.5 flex-shrink-0"
                  />
                  <span>
                    <span class="text-sm font-medium text-gray-800">Customers via this partnership</span>
                    <span class="block text-xs text-gray-400 mt-0.5">
                      Only customers who received or redeemed an offer token through
                      <strong>{{ selectedPartnership?.name }}</strong>.
                      Smaller, highly targeted audience.
                    </span>
                  </span>
                </label>

                <label class="flex items-start gap-2.5 cursor-pointer">
                  <input
                    type="radio"
                    v-model="form.partner_filter"
                    value="all_customers"
                    class="accent-indigo-600 mt-0.5 flex-shrink-0"
                  />
                  <span>
                    <span class="text-sm font-medium text-gray-800">
                      All customers of {{ selectedPartnership?.partner_name ?? 'partner brand' }}
                    </span>
                    <span class="block text-xs text-gray-400 mt-0.5">
                      Every opted-in customer of the partner brand — not just this partnership.
                      Wider reach, useful for brand-level promotions.
                    </span>
                  </span>
                </label>
              </div>

            </div>

            <!-- Estimated audience count -->
            <div class="flex items-center gap-2 text-xs">
              <span class="text-gray-500">Estimated audience:</span>
              <span v-if="segmentCounting" class="text-gray-400">Counting…</span>
              <span v-else-if="segmentCount !== null" class="font-semibold text-indigo-700">
                {{ segmentCount.toLocaleString() }} recipients
              </span>
              <span v-else class="text-gray-400">—</span>
              <button
                v-if="!segmentCounting"
                class="text-indigo-500 hover:text-indigo-700 underline ml-1"
                @click="runSegmentPreview"
              >Refresh</button>
            </div>
          </div>

          <!-- Schedule mode -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Send</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.schedule_mode" type="radio" value="now" class="accent-indigo-600" />
                Run now
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.schedule_mode" type="radio" value="later" class="accent-indigo-600" />
                Schedule
              </label>
            </div>
            <div v-if="form.schedule_mode === 'later'" class="mt-3">
              <input
                v-model="form.scheduled_at"
                type="datetime-local"
                :min="minSchedule"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <!-- Error -->
          <div v-if="createError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ createError }}
          </div>

          <!-- Zero credits notice -->
          <div v-if="waBalance === 0" class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-700">
            You have 0 WhatsApp credits. You can still create and schedule campaigns — ask your platform admin to top up credits before sending.
          </div>

          <button
            :disabled="!form.name || !form.template_key || creating || (form.schedule_mode === 'later' && !form.scheduled_at) || (form.segment_source === 'partner' && !form.partnership_uuid)"
            class="w-full bg-indigo-600 text-white rounded-lg px-4 py-2.5 text-sm font-medium hover:bg-indigo-700 disabled:opacity-40 transition-colors"
            @click="submitCreate"
          >
            {{ creating ? 'Creating…' : (form.schedule_mode === 'now' ? 'Create & run now' : 'Create & schedule') }}
          </button>

        </div>
      </div>
    </div>

  </div>
</template>
