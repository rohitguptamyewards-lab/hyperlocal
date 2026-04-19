<!--
  PartnershipDetailView.vue — Single partnership with state-machine action buttons.
  Purpose: View details, trigger transitions (accept/go-live/pause/resume/reject).
  Owner module: Partnership
  API: GET /api/partnerships/:uuid, POST /api/partnerships/:uuid/{action}
-->
<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePartnershipStore } from '@/stores/partnership'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import QRCode from 'qrcode'

const route  = useRoute()
const router = useRouter()
const store  = usePartnershipStore()
const auth   = useAuthStore()

const uuid = route.params.uuid as string
const actionLoading = ref<string | null>(null)
const actionError   = ref<string | null>(null)

// ── QR Codes ─────────────────────────────────────────────

interface QrEntry { outletId: number; outletName: string; dataUrl: string }

const qrEntries     = ref<QrEntry[]>([])
const qrOpen        = ref(false)
const qrLoading     = ref(false)

async function openQrSection() {
  if (qrOpen.value) { qrOpen.value = false; return }
  qrOpen.value  = true
  qrLoading.value = true
  qrEntries.value = []
  try {
    const base = window.location.origin
    const myMerchantId = auth.user?.merchant_id
    const participants = p.value?.participants ?? []

    // Generate a QR per outlet on MY side
    const myParticipant = participants.find(pt => pt.merchant_id === myMerchantId)
    if (!myParticipant) return

    if (myParticipant.is_brand_wide) {
      // Brand-wide: fetch own outlets and generate one QR per outlet
      const { data: outlets } = await api.get<{ id: number; name: string }[]>('/outlets')
      for (const o of outlets) {
        const url = `${base}/claim/${uuid}?from=${o.id}`
        const dataUrl = await QRCode.toDataURL(url, { width: 240, margin: 2 })
        qrEntries.value.push({ outletId: o.id, outletName: o.name, dataUrl })
      }
    } else {
      // Outlet-level: one QR for the specific outlet
      const outletId   = myParticipant.outlet_id!
      const outletName = myParticipant.outlet_name ?? `Outlet #${outletId}`
      const url = `${base}/claim/${uuid}?from=${outletId}`
      const dataUrl = await QRCode.toDataURL(url, { width: 240, margin: 2 })
      qrEntries.value.push({ outletId, outletName, dataUrl })
    }
  } finally {
    qrLoading.value = false
  }
}

// ── Claim Issuance ────────────────────────────────────────

interface OutletOption { id: number; name: string; address?: string | null }

const claimForm = ref({ source_outlet_id: null as number | null, target_outlet_id: null as number | null, customer_phone: '' })
const claimToken        = ref<string | null>(null)
const claimLoading      = ref(false)
const claimError        = ref<string | null>(null)
const partnerOutlets    = ref<OutletOption[]>([])
const myOutlets         = ref<OutletOption[]>([])
const claimSectionOpen  = ref(false)

async function openClaimSection() {
  if (claimSectionOpen.value) { claimSectionOpen.value = false; return }
  claimSectionOpen.value = true
  claimToken.value = null
  claimError.value = null
  try {
    const [partnerRes, myRes] = await Promise.all([
      api.get<OutletOption[]>(`/partnerships/${uuid}/partner-outlets`),
      api.get<OutletOption[]>('/outlets'),
    ])
    partnerOutlets.value = partnerRes.data
    myOutlets.value      = myRes.data
    claimForm.value.source_outlet_id = myRes.data[0]?.id ?? null
    claimForm.value.target_outlet_id = partnerRes.data[0]?.id ?? null
  } catch {
    claimError.value = 'Failed to load outlet options.'
  }
}

async function issueClaim() {
  claimError.value  = null
  claimToken.value  = null
  claimLoading.value = true
  try {
    const { data } = await api.post<{ token: string }>('/claims', {
      partnership_uuid:  uuid,
      source_outlet_id:  claimForm.value.source_outlet_id,
      target_outlet_id:  claimForm.value.target_outlet_id,
      customer_phone:    claimForm.value.customer_phone || null,
    })
    claimToken.value = data.token
    claimForm.value.customer_phone = ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    claimError.value = err.response?.data?.message ?? 'Failed to issue claim.'
  } finally {
    claimLoading.value = false
  }
}

// ── Linked Offers ─────────────────────────────────────────
interface LinkedOffer {
  uuid: string
  title: string
  coupon_code: string
  discount_type: number
  discount_value: number
  description: string | null
  status: number
  expiry_date: string | null
  merchant_name?: string
}
const linkedOffers = ref<LinkedOffer[]>([])
const offersLoading = ref(false)

async function fetchLinkedOffers() {
  offersLoading.value = true
  try {
    const { data } = await api.get(`/partner-offers/available/${uuid}`)
    linkedOffers.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch {
    linkedOffers.value = []
  } finally {
    offersLoading.value = false
  }
}

onMounted(async () => {
  await store.fetchOne(uuid)
  checkOnboarding()
  await fetchPointRate()
  fetchLinkedOffers()
  fetchMyRating()
})

const p = computed(() => store.current)

const amAcceptorOnPendingRequest = computed(() => {
  const s = p.value?.status
  if (![2, 3].includes(s ?? 0)) return false
  const myMerchantId = auth.user?.merchant_id
  return (p.value?.participants ?? []).some(pt => pt.role === 2 && pt.merchant_id === myMerchantId)
})

// Proposer seeing NEGOTIATING status means the acceptor proposed counter-terms
const amProposerOnNegotiating = computed(() => {
  if (p.value?.status !== 3) return false
  const myMerchantId = auth.user?.merchant_id
  return (p.value?.participants ?? []).some(pt => pt.role === 1 && pt.merchant_id === myMerchantId)
})

// ── Offer structure helpers ───────────────────────────────
const isOfferDifferent = computed(() => p.value?.offer_structure === 'different')

const myParticipant = computed(() =>
  (p.value?.participants ?? []).find(pt => pt.merchant_id === auth.user?.merchant_id)
)

const myOfferFilled = computed(() => myParticipant.value?.offer_filled ?? false)

// Summarise a participant's offer into a short readable string
function offerSummary(pt: { offer_pos_type?: string | null; offer_flat_amount?: number | null; offer_percentage?: number | null; offer_max_cap?: number | null; offer_min_bill?: number | null; offer_monthly_cap?: number | null; offer_filled?: boolean } | null | undefined): string {
  if (!pt || !pt.offer_filled) return '—'
  if (pt.offer_pos_type === 'flat' && pt.offer_flat_amount) {
    let s = `₹${pt.offer_flat_amount} flat`
    if (pt.offer_min_bill)    s += ` · min ₹${pt.offer_min_bill}`
    if (pt.offer_monthly_cap) s += ` · monthly cap ₹${pt.offer_monthly_cap}`
    return s
  }
  if (pt.offer_pos_type === 'percentage' && pt.offer_percentage) {
    let s = `${pt.offer_percentage}% off`
    if (pt.offer_max_cap)     s += ` (max ₹${pt.offer_max_cap})`
    if (pt.offer_min_bill)    s += ` · min ₹${pt.offer_min_bill}`
    if (pt.offer_monthly_cap) s += ` · monthly cap ₹${pt.offer_monthly_cap}`
    return s
  }
  return 'Offer set'
}

// ── Fill-offer form (acceptor fills their own offer for 'different' mode) ──
const fillOfferForm = ref({
  pos_type:    'flat' as 'flat' | 'percentage',
  flat_amount: '' as string | number,
  percentage:  '' as string | number,
  max_cap:     '' as string | number,
  min_bill:    '' as string | number,
  monthly_cap: '' as string | number,
})
const fillOfferSaving = ref(false)
const fillOfferError  = ref<string | null>(null)
const fillOfferOpen   = ref(false)

async function saveFillOffer() {
  fillOfferError.value  = null
  fillOfferSaving.value = true
  try {
    const payload: Record<string, unknown> = { pos_type: fillOfferForm.value.pos_type }
    if (fillOfferForm.value.pos_type === 'flat')       payload.flat_amount = Number(fillOfferForm.value.flat_amount)
    if (fillOfferForm.value.pos_type === 'percentage') payload.percentage  = Number(fillOfferForm.value.percentage)
    if (fillOfferForm.value.max_cap)     payload.max_cap     = Number(fillOfferForm.value.max_cap)
    if (fillOfferForm.value.min_bill)    payload.min_bill    = Number(fillOfferForm.value.min_bill)
    if (fillOfferForm.value.monthly_cap) payload.monthly_cap = Number(fillOfferForm.value.monthly_cap)

    await api.post(`/partnerships/${uuid}/fill-offer`, payload)
    // Reload partnership so offer_filled flag updates
    await store.fetchOne(uuid)
    fillOfferOpen.value = false
  } catch (err: any) {
    const errors = err.response?.data?.errors
    fillOfferError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to save offer.')
  } finally {
    fillOfferSaving.value = false
  }
}

function openFillOffer() {
  // Pre-populate if already partially filled
  const pt = myParticipant.value
  if (pt) {
    fillOfferForm.value.pos_type    = (pt.offer_pos_type as 'flat' | 'percentage') ?? 'flat'
    fillOfferForm.value.flat_amount = pt.offer_flat_amount ?? ''
    fillOfferForm.value.percentage  = pt.offer_percentage  ?? ''
    fillOfferForm.value.max_cap     = pt.offer_max_cap     ?? ''
    fillOfferForm.value.min_bill    = pt.offer_min_bill    ?? ''
    fillOfferForm.value.monthly_cap = pt.offer_monthly_cap ?? ''
  }
  fillOfferOpen.value = true
}

const proposerName = computed(() =>
  (p.value?.participants ?? []).find(pt => pt.role === 1)?.merchant_name ?? 'Your partner'
)

const proposerParticipant = computed(() => (p.value?.participants ?? []).find(pt => pt.role === 1))
const acceptorParticipant = computed(() => (p.value?.participants ?? []).find(pt => pt.role === 2))

// The "other side" from this user's perspective
const partnerName = computed(() => {
  const myMerchantId = auth.user?.merchant_id
  return (p.value?.participants ?? [])
    .find(pt => pt.merchant_id !== myMerchantId)?.merchant_name ?? 'your partner'
})

const mySideParticipant = computed(() =>
  (p.value?.participants ?? []).find(pt => pt.merchant_id === auth.user?.merchant_id)
)

const canEdit = computed(() =>
  auth.isManager() && [1, 2, 3, 4].includes(p.value?.status ?? 0)
)

const partnerSideSuspended = computed(() =>
  (p.value?.participants ?? [])
    .filter(pt => pt.merchant_id !== auth.user?.merchant_id)
    .some(pt => pt.is_suspended)
)

// ── My settings (permission flags) ────────────────────────

const settingsSaving   = ref(false)
const settingsError    = ref<string | null>(null)
const notifyPrompt     = ref(false)     // show "notify your customers?" after a settings change
const notifyLoading    = ref(false)
const notifyDone       = ref(false)

// Local copies of the three flags, kept in sync from the store
const myIssuing    = ref(true)
const myRedemption = ref(true)
const myCampaigns  = ref(true)

function syncMySettings() {
  const pt = mySideParticipant.value
  if (!pt) return
  myIssuing.value    = pt.issuing_enabled    ?? true
  myRedemption.value = pt.redemption_enabled ?? true
  myCampaigns.value  = pt.campaigns_enabled  ?? true
}

watch(() => mySideParticipant.value, syncMySettings, { immediate: true })

async function saveMySettings() {
  settingsError.value = null
  settingsSaving.value = true
  notifyPrompt.value   = false
  notifyDone.value     = false
  try {
    const { data } = await api.post(`/partnerships/${uuid}/my-settings`, {
      issuing_enabled:    myIssuing.value,
      redemption_enabled: myRedemption.value,
      campaigns_enabled:  myCampaigns.value,
    })
    store.setCurrent(data.data)
    notifyPrompt.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    settingsError.value = err.response?.data?.message ?? 'Failed to save settings.'
  } finally {
    settingsSaving.value = false
  }
}

async function notifyCustomers() {
  notifyLoading.value = true
  try {
    // In production this triggers a WhatsApp/SMS broadcast to this merchant's customer base.
    // For now: mocked — the backend logs it.
    await api.post(`/partnerships/${uuid}/notify-customers`, {
      issuing_enabled:    myIssuing.value,
      redemption_enabled: myRedemption.value,
    })
    notifyDone.value   = true
    notifyPrompt.value = false
  } catch {
    // non-critical — the settings change already saved
    notifyDone.value   = true
    notifyPrompt.value = false
  } finally {
    notifyLoading.value = false
  }
}

// Determine which actions are available for the current user
const actions = computed(() => {
  const s = p.value?.status
  const role = auth.user?.role ?? 0
  const isAdmin = role <= 2

  const myMerchantId = auth.user?.merchant_id
  const participants = p.value?.participants ?? []

  const amProposer = participants.some((pt) => pt.role === 1 && pt.merchant_id === myMerchantId)
  const amAcceptor = participants.some((pt) => pt.role === 2 && pt.merchant_id === myMerchantId)

  const available: { label: string; action: string; style: string; body?: Record<string, unknown> }[] = []

  if (!isAdmin) return available

  if (amAcceptor && [2, 3].includes(s ?? 0)) {
    available.push({ label: 'Accept & Start', action: 'accept-and-start', style: 'green' })
    available.push({ label: 'Accept only', action: 'accept', style: 'indigo' })
  }
  if ((amProposer || amAcceptor) && [2, 3, 4].includes(s ?? 0)) {
    available.push({ label: 'Reject', action: 'reject', style: 'red' })
  }
  if ((amProposer || amAcceptor) && s === 4) {
    available.push({ label: 'Go Live', action: 'go-live', style: 'green' })
  }
  // Pause/Resume now handled by the settings card master toggle below
  // Only show here if user is NOT a manager (read-only) to still convey state


  return available
})

async function doAction(action: string, body: Record<string, unknown> = {}) {
  actionError.value   = null
  actionLoading.value = action
  try {
    await store.transition(uuid, action, body)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    actionError.value = err.response?.data?.message ?? `Action "${action}" failed.`
  } finally {
    actionLoading.value = null
  }
}

// ── Marketing Kit ─────────────────────────────────────────

const marketingKitOpen = ref(false)
const copiedKey        = ref<string | null>(null)

async function copyText(key: string, text: string) {
  try {
    await navigator.clipboard.writeText(text)
    copiedKey.value = key
    setTimeout(() => { copiedKey.value = null }, 2000)
  } catch {
    // clipboard not available in some contexts
  }
}

const whatsappCopy = computed(() => {
  const partner = partnerName.value
  return `🎉 We've teamed up with ${partner}!\n\nAs our valued customer, you can now enjoy an exclusive benefit at ${partner}. Just ask for your partner token the next time you visit us and present it at ${partner} — it's our way of saying thank you.\n\nVisit us to claim yours! 🙌`
})

const instagramCopy = computed(() => {
  const partner = partnerName.value
  const tag     = partner.replace(/[^a-zA-Z0-9]/g, '')
  return `✨ Exciting news — we've partnered with ${partner}!\n\nOur customers now get an exclusive offer at ${partner}. Ask about your partner token at checkout next time you visit us. 🤝\n\n#LocalLove #${tag} #Partnership #ExclusiveOffer`
})

const counterCopy = computed(() => {
  const terms = p.value?.terms
  const offer  = terms?.per_bill_cap_percent
    ? `${terms.per_bill_cap_percent}% off your bill`
    : terms?.per_bill_cap_amount
      ? `₹${terms.per_bill_cap_amount} off your bill`
      : 'an exclusive benefit'
  const minBill = terms?.min_bill_amount
    ? `₹${terms.min_bill_amount}`
    : terms?.min_bill_points
      ? `${terms.min_bill_points} pts (₹${terms.min_bill_amount ?? '—'})`
      : 'none'
  return `Show your partner token from ${partnerName.value} and get ${offer}!\n\nMinimum bill: ${minBill}\nToken valid for 48 hours from issue.\n\nAsk our cashier to scan/enter your token.`
})

// ── Onboarding guide ──────────────────────────────────────

const onboardingDismissed = ref(false)

function dismissOnboarding() {
  localStorage.setItem(`onboarded-${uuid}`, '1')
  onboardingDismissed.value = true
}

function checkOnboarding() {
  onboardingDismissed.value = !!localStorage.getItem(`onboarded-${uuid}`)
}

const showOnboarding = computed(() =>
  p.value?.status === 5 && !onboardingDismissed.value
)

// ── Terms edit ────────────────────────────────────────────

const termsEditing  = ref(false)
const termsSaving   = ref(false)
const termsError    = ref<string | null>(null)

// Denomination modes per field (persists while editing) — pts removed everywhere
type PerBillMode = 'percent' | 'amount'
type CapMode     = 'amount'

const perBillMode    = ref<PerBillMode>('amount')
const minBillMode    = ref<CapMode>('amount')
const monthlyCapMode = ref<CapMode>('amount')

// Current merchant point valuation rate (for live ₹ preview when points mode is selected)
const pointRate = ref<number | null>(null)

async function fetchPointRate() {
  try {
    const { data } = await api.get('/merchant/settings/point-valuation')
    pointRate.value = data.current?.rupees_per_point ?? null
  } catch {
    // non-blocking — preview just won't show
  }
}

const termsForm = ref({
  per_bill_cap_percent:        null as number | null,
  per_bill_cap_amount:         null as number | null,
  per_bill_cap_points:         null as number | null,
  min_bill_amount:             null as number | null,
  min_bill_points:             null as number | null,
  monthly_cap_amount:          null as number | null,
  monthly_cap_points:          null as number | null,
})

function openTermsEdit() {
  const t = p.value?.terms
  // pts mode removed — only % or ₹
  if (t?.per_bill_cap_percent != null) {
    perBillMode.value = 'percent'
    termsForm.value.per_bill_cap_percent = t.per_bill_cap_percent
    termsForm.value.per_bill_cap_amount  = null
    termsForm.value.per_bill_cap_points  = null
  } else {
    perBillMode.value = 'amount'
    termsForm.value.per_bill_cap_amount  = t?.per_bill_cap_amount ?? null
    termsForm.value.per_bill_cap_percent = null
    termsForm.value.per_bill_cap_points  = null
  }

  // Always use ₹ amount mode — pts toggle has been removed from the UI
  minBillMode.value = 'amount'
  termsForm.value.min_bill_amount = t?.min_bill_amount ?? null
  termsForm.value.min_bill_points = null

  monthlyCapMode.value = 'amount'
  termsForm.value.monthly_cap_amount = t?.monthly_cap_amount ?? null
  termsForm.value.monthly_cap_points = null

  termsError.value  = null
  termsEditing.value = true
}

async function saveTerms() {
  termsError.value = null
  termsSaving.value = true
  try {
    // Build payload — only send the active denomination per field, null out the others
    const payload: Record<string, number | null> = {}

    if (perBillMode.value === 'percent') {
      payload.per_bill_cap_percent = termsForm.value.per_bill_cap_percent
      payload.per_bill_cap_amount  = null
      payload.per_bill_cap_points  = null
    } else {
      payload.per_bill_cap_amount  = termsForm.value.per_bill_cap_amount
      payload.per_bill_cap_percent = null
      payload.per_bill_cap_points  = null
    }

    if (minBillMode.value === 'amount') {
      payload.min_bill_amount = termsForm.value.min_bill_amount
      payload.min_bill_points = null
    } else {
      payload.min_bill_points = termsForm.value.min_bill_points
      payload.min_bill_amount = null
    }

    if (monthlyCapMode.value === 'amount') {
      payload.monthly_cap_amount = termsForm.value.monthly_cap_amount
      payload.monthly_cap_points = null
    } else {
      payload.monthly_cap_points = termsForm.value.monthly_cap_points
      payload.monthly_cap_amount = null
    }

    const { data } = await api.put(`/partnerships/${uuid}`, { terms: payload })
    store.setCurrent(data.data)
    termsEditing.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    termsError.value = err.response?.data?.message ?? 'Failed to save terms.'
  } finally {
    termsSaving.value = false
  }
}

function ptsPreview(pts: number | null, rate: number | null): string | null {
  if (!pts || !rate) return null
  return `= ₹${(pts * rate).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`
}

// ── Rules edit ────────────────────────────────────────────

const rulesEditing = ref(false)
const rulesSaving  = ref(false)
const rulesError   = ref<string | null>(null)

const rulesForm = ref({
  first_time_only: false,
  uses_per_customer: null as number | null,
})

function openRulesEdit() {
  const r = p.value?.rules
  rulesForm.value.first_time_only   = r?.first_time_only ?? false
  rulesForm.value.uses_per_customer = r?.uses_per_customer ?? null
  rulesError.value  = null
  rulesEditing.value = true
}

async function saveRules() {
  rulesError.value = null
  rulesSaving.value = true
  try {
    const { data } = await api.put(`/partnerships/${uuid}`, {
      rules: {
        uses_per_customer: rulesForm.value.uses_per_customer,
        first_time_only:   rulesForm.value.first_time_only,
      },
    })
    store.setCurrent(data.data)
    rulesEditing.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    rulesError.value = err.response?.data?.message ?? 'Failed to save rules.'
  } finally {
    rulesSaving.value = false
  }
}

// ── Standard T&C ─────────────────────────────────────────

const tcOpen = ref(false)
const tcText = ref<string | null>(null)

async function openTC() {
  tcOpen.value = true
  if (!tcText.value) {
    try {
      const { data } = await api.get('/partnerships/tc')
      tcText.value = data.text
    } catch {
      tcText.value = 'Unable to load terms. Please contact support.'
    }
  }
}

// T&C acceptance status helpers
const proposerAgreement = computed(() =>
  p.value?.agreements?.find(a => a.merchant_id === proposerParticipant.value?.merchant_id)
)
const acceptorAgreement = computed(() =>
  p.value?.agreements?.find(a => a.merchant_id === acceptorParticipant.value?.merchant_id)
)

// Status helpers
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

const btnClass = (style: string) => ({
  indigo:  'bg-indigo-600 text-white hover:bg-indigo-700',
  green:   'bg-green-600 text-white hover:bg-green-700',
  yellow:  'bg-yellow-500 text-white hover:bg-yellow-600',
  orange:  'bg-orange-500 text-white hover:bg-orange-600',
  red:     'bg-red-600 text-white hover:bg-red-700',
}[style] ?? 'bg-gray-600 text-white')

const roleLabel = (role: number) => ({ 1: 'Proposer', 2: 'Acceptor' }[role] ?? 'Unknown')

// ── Partner Rating (GAP 7) ────────────────────────────────

const ratingValue      = ref(0)
const ratingReview     = ref('')
const ratingSubmitting = ref(false)
const ratingDone       = ref(false)
const ratingError      = ref<string | null>(null)
const existingRating   = ref<{ rating: number; review_text: string | null } | null>(null)

async function fetchMyRating() {
  if (!p.value || ![5, 6].includes(p.value.status)) return
  try {
    const { data } = await api.get(`/partnerships/${uuid}/ratings`)
    if (data.my_rating) {
      existingRating.value = data.my_rating
      ratingValue.value    = data.my_rating.rating
      ratingReview.value   = data.my_rating.review_text ?? ''
    }
  } catch {
    // non-blocking
  }
}

async function submitRating() {
  if (ratingValue.value < 1) return
  ratingError.value      = null
  ratingSubmitting.value = true
  try {
    const { data } = await api.post(`/partnerships/${uuid}/rate`, {
      rating:      ratingValue.value,
      review_text: ratingReview.value || null,
    })
    existingRating.value = data.rating
    ratingDone.value     = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    ratingError.value = err.response?.data?.message ?? 'Failed to submit rating.'
  } finally {
    ratingSubmitting.value = false
  }
}

// ── Shareable Link (GAP 10) ───────────────────────────────

const shareLink        = ref<string | null>(null)
const shareLinkLoading = ref(false)
const shareLinkError   = ref<string | null>(null)
const shareLinkCopied  = ref(false)
const shareLinkOpen    = ref(false)

async function generateShareLink() {
  shareLinkError.value  = null
  shareLinkLoading.value = true
  try {
    const { data } = await api.post(`/partnerships/${uuid}/share-link`)
    shareLink.value     = data.share_url
    shareLinkOpen.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    shareLinkError.value = err.response?.data?.message ?? 'Failed to generate share link.'
  } finally {
    shareLinkLoading.value = false
  }
}

async function copyShareLink() {
  if (!shareLink.value) return
  try {
    await navigator.clipboard.writeText(shareLink.value)
    shareLinkCopied.value = true
    setTimeout(() => { shareLinkCopied.value = false }, 2000)
  } catch {
    // clipboard unavailable
  }
}

function shareViaWhatsApp() {
  if (!shareLink.value) return
  const text = encodeURIComponent(
    `You're invited to claim an exclusive offer!\n\nVisit: ${shareLink.value}`
  )
  window.open(`https://wa.me/?text=${text}`, '_blank')
}

// ── Contradiction surfacing ───────────────────────────────

interface Contradiction {
  message: string
  fix: string
}

const contradictions = computed((): Contradiction[] => {
  const my = mySideParticipant.value
  const partner = (p.value?.participants ?? []).find(pt => pt.merchant_id !== auth.user?.merchant_id)
  if (!my || !partner) return []
  const list: Contradiction[] = []

  // I issue tokens but partner won't redeem them
  if ((my.issuing_enabled ?? true) && !(partner.redemption_enabled ?? true)) {
    list.push({
      message: `You issue tokens to send your customers to ${partnerName.value}, but ${partnerName.value} has disabled redemptions — your customers' tokens will be rejected.`,
      fix: `Ask ${partnerName.value} to turn on "Accept redemptions" on their side, or turn off your "Issue tokens" setting.`,
    })
  }
  // Partner issues tokens but I won't redeem them
  if ((partner.issuing_enabled ?? true) && !(my.redemption_enabled ?? true)) {
    list.push({
      message: `${partnerName.value} is sending their customers to you with tokens, but you have disabled redemptions — those tokens will be rejected at your outlet.`,
      fix: `Turn on "Accept redemptions" in your settings above, or ask ${partnerName.value} to disable their "Issue tokens" setting.`,
    })
  }
  // Partner wants to campaign to my customers but I haven't allowed it
  if ((partner.campaigns_enabled ?? true) === false && (my.campaigns_enabled ?? true)) {
    list.push({
      message: `You allow ${partnerName.value} to send campaigns to your customers, but ${partnerName.value} has not allowed you to campaign to their customers — the flow is one-sided.`,
      fix: `This may be intentional. If you expect reciprocal campaign access, ask ${partnerName.value} to enable "Allow partner campaigns" on their side.`,
    })
  }

  return list
})

// ── Announce to Your Customers (GAP 3) ───────────────────

const announcementOpen          = ref(false)
const announcementText          = ref('')
const announcementRecipientCount = ref(0)
const announcementSending       = ref(false)
const announcementDone          = ref(false)
const pastAnnouncements         = ref<{
  id: number
  message_text: string
  recipient_count: number
  status: string
  sent_at: string | null
  created_at: string
}[]>([])
const announcementError         = ref<string | null>(null)
const announcementPreviewLoading = ref(false)

function defaultAnnouncementText() {
  return `🎉 We've partnered with ${partnerName.value}! Visit us and get your exclusive discount token for ${partnerName.value}. Limited period offer!`
}

async function openAnnouncementSection() {
  if (announcementOpen.value) { announcementOpen.value = false; return }
  announcementOpen.value = true
  announcementError.value = null
  if (!announcementText.value) {
    announcementText.value = defaultAnnouncementText()
  }
  await fetchAnnouncementPreview()
  await fetchPastAnnouncements()
}

async function fetchAnnouncementPreview() {
  if (!announcementText.value) return
  announcementPreviewLoading.value = true
  try {
    const { data } = await api.post(`/partnerships/${uuid}/announcements/preview`, {
      message: announcementText.value,
    })
    announcementRecipientCount.value = data.recipient_count ?? 0
  } catch {
    announcementRecipientCount.value = 0
  } finally {
    announcementPreviewLoading.value = false
  }
}

async function fetchPastAnnouncements() {
  try {
    const { data } = await api.get(`/partnerships/${uuid}/announcements`)
    pastAnnouncements.value = Array.isArray(data) ? data : []
  } catch {
    pastAnnouncements.value = []
  }
}

async function sendAnnouncement() {
  if (!announcementText.value.trim()) return
  announcementError.value  = null
  announcementSending.value = true
  announcementDone.value   = false
  try {
    const { data } = await api.post(`/partnerships/${uuid}/announcements/send`, {
      message: announcementText.value,
    })
    announcementRecipientCount.value = data.recipient_count ?? 0
    announcementDone.value = true
    await fetchPastAnnouncements()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    announcementError.value = err.response?.data?.message ?? 'Failed to send announcement.'
  } finally {
    announcementSending.value = false
  }
}

</script>

<template>
  <div class="p-4 sm:p-8 max-w-3xl">
    <!-- Back -->
    <button @click="router.back()" class="text-sm text-gray-500 hover:text-gray-700 mb-6 flex items-center gap-1">
      ← Back
    </button>

    <!-- Loading / Error -->
    <div v-if="store.loading" class="text-sm text-gray-500 py-12 text-center">Loading…</div>
    <div v-else-if="store.error" class="text-sm text-red-600 py-4">{{ store.error }}</div>

    <template v-else-if="p">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">{{ p.name }}</h1>
          <p class="text-sm text-gray-500 mt-1">
            {{ p.is_brand_level ? 'Brand-wide' : 'Outlet-level' }}
            · Created {{ new Date(p.created_at).toLocaleDateString() }}
          </p>
        </div>
        <span class="text-sm font-medium px-3 py-1 rounded-full" :class="statusClass(p.status)">
          {{ p.status_label }}
        </span>
      </div>

      <!-- Ecosystem inactive banner (E-001) -->
      <div v-if="p.status === 9" class="mb-6 rounded-xl border border-gray-300 bg-gray-100 px-5 py-4">
        <p class="text-sm font-semibold text-gray-700 mb-1">Not in ecosystem</p>
        <p class="text-xs text-gray-500">
          One of the merchants in this partnership has left the network. This partnership has been automatically closed
          and cannot be used for new claims or redemptions. To reactivate, both merchants must be active in the ecosystem
          and a new partnership must be created.
        </p>
      </div>

      <!-- Review request banner (acceptor, REQUESTED / NEGOTIATING) -->
      <div v-if="amAcceptorOnPendingRequest" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 space-y-3">
        <div>
          <p class="text-sm font-semibold text-amber-800 mb-1">
            {{ proposerName }} has invited you to a partnership
          </p>
          <p class="text-xs text-amber-700">
            Review the details below. You can suggest changes before accepting, or accept and start the program immediately.
          </p>
        </div>

        <!-- Proposer's offer (always shown so acceptor knows what THEY'RE offering) -->
        <div class="rounded-lg bg-white border border-amber-200 px-4 py-3 text-xs space-y-1">
          <p class="font-semibold text-amber-900">
            {{ proposerName }}'s offer to your customers:
          </p>
          <p class="text-gray-600">{{ offerSummary(proposerParticipant) }}</p>
        </div>

        <!-- Different offer mode: acceptor must fill their own offer -->
        <template v-if="isOfferDifferent">
          <div class="rounded-lg bg-white border border-amber-200 px-4 py-3 text-xs">
            <div class="flex items-center justify-between mb-1">
              <p class="font-semibold text-amber-900">Your offer to {{ proposerName }}'s customers:</p>
              <button
                v-if="myOfferFilled"
                @click="openFillOffer"
                class="text-indigo-600 hover:text-indigo-800 font-medium"
              >Edit</button>
            </div>
            <p v-if="myOfferFilled" class="text-gray-600">{{ offerSummary(myParticipant) }}</p>
            <p v-else class="text-amber-700 font-medium">
              ⚠ You haven't set your offer yet — please fill it in before accepting.
            </p>
          </div>

          <!-- Fill offer inline form -->
          <div v-if="!myOfferFilled || fillOfferOpen" class="rounded-lg bg-white border border-indigo-200 px-4 py-4 space-y-3">
            <p class="text-xs font-semibold text-indigo-800">Set your offer for {{ proposerName }}'s customers</p>

            <div v-if="fillOfferError" class="rounded bg-red-50 border border-red-200 px-3 py-1.5 text-xs text-red-700">
              {{ fillOfferError }}
            </div>

            <!-- Discount type -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Discount type</label>
              <div class="flex rounded-lg overflow-hidden border border-gray-300 text-xs w-fit">
                <button
                  v-for="t in [{ v: 'flat', l: 'Flat (₹)' }, { v: 'percentage', l: 'Percentage (%)' }]"
                  :key="t.v"
                  type="button"
                  @click="fillOfferForm.pos_type = t.v as 'flat' | 'percentage'"
                  class="px-3 py-1.5 transition-colors"
                  :class="fillOfferForm.pos_type === t.v ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                >{{ t.l }}</button>
              </div>
            </div>

            <!-- Amount -->
            <div v-if="fillOfferForm.pos_type === 'flat'">
              <label class="block text-xs font-medium text-gray-600 mb-1">Discount amount (₹)</label>
              <input v-model="fillOfferForm.flat_amount" type="number" min="0" placeholder="e.g. 150"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div v-else>
              <label class="block text-xs font-medium text-gray-600 mb-1">Discount percentage (%)</label>
              <input v-model="fillOfferForm.percentage" type="number" min="0" max="100" placeholder="e.g. 15"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <!-- Max cap — only for % -->
            <div v-if="fillOfferForm.pos_type === 'percentage'">
              <label class="block text-xs font-medium text-gray-600 mb-1">Max cap per bill (₹) <span class="font-normal text-gray-400">optional</span></label>
              <input v-model="fillOfferForm.max_cap" type="number" min="0" placeholder="Leave blank for no cap"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <!-- Min bill -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Minimum bill (₹) <span class="font-normal text-gray-400">optional</span></label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                <input v-model="fillOfferForm.min_bill" type="number" min="0" placeholder="e.g. 300"
                  class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <!-- Monthly cap -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Monthly cap (₹) <span class="font-normal text-gray-400">optional</span></label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                <input v-model="fillOfferForm.monthly_cap" type="number" min="0" placeholder="e.g. 2000"
                  class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <div class="flex gap-2">
              <button
                @click="saveFillOffer"
                :disabled="fillOfferSaving"
                class="px-4 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
              >{{ fillOfferSaving ? 'Saving…' : 'Save my offer' }}</button>
              <button
                v-if="myOfferFilled && fillOfferOpen"
                @click="fillOfferOpen = false"
                class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-xs rounded-lg hover:bg-gray-50 transition-colors"
              >Cancel</button>
            </div>
          </div>
        </template>

        <ul class="text-xs text-amber-700 space-y-1">
          <li>• <strong>Accept & Start</strong> — accept and go live immediately. Your program starts now.</li>
          <li>• <strong>Accept only</strong> — accept but wait before going live (useful to brief staff first).</li>
          <li>• <strong>Edit Terms</strong> — scroll to Terms below and suggest changes. This moves to Negotiating.</li>
        </ul>
      </div>

      <!-- Proposer review banner — shown when acceptor has proposed counter-terms (Negotiating) -->
      <div v-if="amProposerOnNegotiating && auth.isManager()" class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 space-y-2">
        <p class="text-sm font-semibold text-blue-800">
          {{ partnerName }} has proposed different terms
        </p>
        <p class="text-xs text-blue-700">
          Review the updated Terms section below. You can:
        </p>
        <ul class="text-xs text-blue-700 space-y-1">
          <li>• <strong>Accept & Start</strong> or <strong>Accept only</strong> — agree to the new terms and proceed.</li>
          <li>• <strong>Edit Terms again</strong> — counter-propose your own changes. This keeps the partnership in Negotiating.</li>
          <li>• <strong>Reject</strong> — cancel the partnership if you can't reach agreement.</li>
        </ul>
      </div>

      <!-- Action error -->
      <div v-if="actionError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ actionError }}
      </div>

      <!-- State machine actions (Accept / Reject / Go Live) -->
      <div v-if="actions.length" class="flex flex-wrap gap-2 mb-6">
        <button
          v-for="a in actions"
          :key="a.action"
          :disabled="actionLoading !== null"
          @click="doAction(a.action, a.body ?? {})"
          class="px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 transition-colors"
          :class="btnClass(a.style)"
        >
          {{ actionLoading === a.action ? '…' : a.label }}
        </button>
      </div>

      <!-- ── Partnership settings card (LIVE or PAUSED) ─────────── -->
      <div v-if="[5, 6].includes(p.status) && auth.isManager()" class="bg-white border border-gray-200 rounded-xl mb-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-gray-900">My settings for this partnership</p>
            <p class="text-xs text-gray-400 mt-0.5">Controls only your side — your partner's settings are independent</p>
          </div>
          <!-- Master on/off toggle -->
          <div class="flex items-center gap-3 flex-shrink-0">
            <span class="text-xs text-gray-500">{{ p.status === 5 ? 'Active' : 'Paused' }}</span>
            <button
              :disabled="actionLoading !== null"
              @click="doAction(p.status === 5 ? 'pause' : 'resume')"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:opacity-50 focus:outline-none"
              :class="p.status === 5 ? 'bg-green-500' : 'bg-gray-300'"
              :title="p.status === 5 ? 'Pause this partnership' : 'Resume this partnership'"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="p.status === 5 ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
          </div>
        </div>

        <!-- Paused state callout -->
        <div v-if="p.status === 6" class="mx-5 mt-4 rounded-lg bg-yellow-50 border border-yellow-200 px-4 py-2 text-xs text-yellow-800">
          Partnership is fully paused — no tokens can be issued or redeemed on either side.
          <span v-if="p.paused_reason"> Reason: {{ p.paused_reason }}</span>
          Toggle the switch above to resume.
        </div>

        <!-- Per-flag toggles (only when LIVE) -->
        <div v-if="p.status === 5" class="px-5 py-4 space-y-4">

          <!-- Partner side suspended notice -->
          <div v-if="partnerSideSuspended" class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2 text-xs text-gray-600">
            <strong>{{ partnerName }}</strong> has disabled one or more settings on their side.
          </div>

          <!-- Issuing -->
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800">Issue tokens</p>
              <p class="text-xs text-gray-400 mt-0.5">My cashiers can give tokens to customers to send them to <strong>{{ partnerName }}</strong></p>
            </div>
            <button
              @click="myIssuing = !myIssuing"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0 mt-0.5"
              :class="myIssuing ? 'bg-indigo-600' : 'bg-gray-300'"
            >
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="myIssuing ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>

          <!-- Redemption -->
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800">Accept redemptions</p>
              <p class="text-xs text-gray-400 mt-0.5">My outlets honour tokens presented by <strong>{{ partnerName }}</strong>'s customers</p>
            </div>
            <button
              @click="myRedemption = !myRedemption"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0 mt-0.5"
              :class="myRedemption ? 'bg-indigo-600' : 'bg-gray-300'"
            >
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="myRedemption ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>

          <!-- Campaigns -->
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800">Allow partner campaigns</p>
              <p class="text-xs text-gray-400 mt-0.5"><strong>{{ partnerName }}</strong> can send offers and campaigns to my customer database</p>
              <p class="text-xs text-indigo-400 mt-0.5">Coming soon — will be enforced by the Campaign module</p>
            </div>
            <button
              @click="myCampaigns = !myCampaigns"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0 mt-0.5"
              :class="myCampaigns ? 'bg-indigo-600' : 'bg-gray-300'"
            >
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="myCampaigns ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>

          <!-- Contradiction panel -->
          <div v-if="contradictions.length > 0" class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 space-y-3">
            <p class="text-xs font-semibold text-orange-800">Setting conflicts detected</p>
            <div v-for="(c, i) in contradictions" :key="i" class="space-y-1">
              <p class="text-xs text-orange-700">⚠ {{ c.message }}</p>
              <p class="text-xs text-orange-500">Fix: {{ c.fix }}</p>
            </div>
          </div>

          <!-- Save + error -->
          <div v-if="settingsError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
            {{ settingsError }}
          </div>
          <button
            @click="saveMySettings"
            :disabled="settingsSaving"
            class="w-full bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
          >
            {{ settingsSaving ? 'Saving…' : 'Save my settings' }}
          </button>
        </div>

        <!-- Notify customers prompt (shown after save) -->
        <div v-if="notifyPrompt" class="mx-5 mb-5 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 flex items-center justify-between gap-4">
          <p class="text-xs text-indigo-800">
            Settings saved. Would you like to notify your customers about this change?
          </p>
          <div class="flex gap-2 flex-shrink-0">
            <button
              @click="notifyCustomers"
              :disabled="notifyLoading"
              class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50 whitespace-nowrap"
            >
              {{ notifyLoading ? 'Sending…' : 'Notify customers' }}
            </button>
            <button @click="notifyPrompt = false" class="text-xs text-indigo-400 hover:text-indigo-600">Skip</button>
          </div>
        </div>
        <div v-if="notifyDone" class="mx-5 mb-5 text-xs text-green-600">
          ✓ Customer notification queued successfully.
        </div>
      </div>

      <!-- Linked Offers -->
      <div v-if="p.status === 5 || p.status === 6" class="bg-white border border-gray-200 rounded-xl mb-6 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-gray-900">Linked Offers</p>
            <p class="text-xs text-gray-400 mt-0.5">Offers attached to this partnership — shown on partner digital bills</p>
          </div>
          <router-link to="/partner-offers" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Manage offers →</router-link>
        </div>
        <div v-if="offersLoading" class="px-5 py-6 text-center text-xs text-gray-400">Loading offers…</div>
        <div v-else-if="linkedOffers.length === 0" class="px-5 py-6 text-center">
          <p class="text-sm text-gray-400">No offers linked yet</p>
          <p class="text-xs text-gray-300 mt-1">Go to Offers → Create an offer → Attach it to this partnership</p>
        </div>
        <div v-else class="divide-y divide-gray-100">
          <div v-for="offer in linkedOffers" :key="offer.uuid" class="px-5 py-3 flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-800 truncate">{{ offer.title }}</p>
              <p class="text-xs text-gray-400 mt-0.5">
                Code: <span class="font-mono font-semibold text-indigo-600">{{ offer.coupon_code }}</span>
                · {{ offer.discount_type === 1 ? offer.discount_value + '% off' : '₹' + offer.discount_value + ' off' }}
                <span v-if="offer.expiry_date"> · Expires {{ new Date(offer.expiry_date).toLocaleDateString() }}</span>
              </p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full flex-shrink-0"
              :class="offer.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
              {{ offer.status === 1 ? 'Active' : 'Inactive' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Partnership Agreement (always accessible once terms exist) -->
      <div v-if="p.terms" class="bg-indigo-50 border border-indigo-200 rounded-xl mb-6 px-5 py-4">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-indigo-900">Partnership Agreement</p>
            <p class="text-xs text-indigo-600 mt-1 leading-relaxed">
              Customers of
              <strong>{{ proposerParticipant?.merchant_name ?? 'Proposer' }}</strong>
              visiting
              <strong>{{ acceptorParticipant?.merchant_name ?? 'Acceptor' }}</strong>
              receive
              <span v-if="p.terms.per_bill_cap_percent">
                up to <strong>{{ p.terms.per_bill_cap_percent }}%</strong> off their bill<span v-if="p.terms.per_bill_cap_amount"> (max ₹{{ p.terms.per_bill_cap_amount }}<span v-if="p.terms.per_bill_cap_points"> = {{ p.terms.per_bill_cap_points }} pts</span>)</span></span><span v-else-if="p.terms.per_bill_cap_amount">up to <strong>₹{{ p.terms.per_bill_cap_amount }}</strong><span v-if="p.terms.per_bill_cap_points"> ({{ p.terms.per_bill_cap_points }} pts)</span> off their bill</span><span v-else>an agreed benefit</span>.
              <span v-if="p.terms.min_bill_amount"> Minimum bill: ₹{{ p.terms.min_bill_amount }}<span v-if="p.terms.min_bill_points"> ({{ p.terms.min_bill_points }} pts)</span>.</span>
              <span v-if="p.terms.monthly_cap_amount"> Monthly cap: ₹{{ p.terms.monthly_cap_amount }}<span v-if="p.terms.monthly_cap_points"> ({{ p.terms.monthly_cap_points }} pts)</span>.</span>
              <span v-if="p.terms.rupees_per_point_at_agreement" class="block mt-1 text-indigo-500">Rate locked at ₹{{ p.terms.rupees_per_point_at_agreement }}/pt at agreement time.</span>
            </p>
          </div>
          <div class="flex-shrink-0 text-right">
            <p class="text-xs text-indigo-500 font-medium">Terms v{{ p.terms.version }}</p>
            <p class="text-xs text-indigo-400 mt-0.5">
              {{ p.start_at ? new Date(p.start_at).toLocaleDateString() : 'Effective on live' }}
              <span v-if="p.end_at"> → {{ new Date(p.end_at).toLocaleDateString() }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Analytics + Ledger + Redemptions quick links (LIVE partnerships) -->
      <div v-if="p.status === 5" class="flex gap-2 mb-6">
        <RouterLink
          :to="`/partnerships/${uuid}/analytics`"
          class="text-xs bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
        >
          Analytics
        </RouterLink>
        <RouterLink
          :to="`/partnerships/${uuid}/ledger`"
          class="text-xs bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
        >
          Benefits
        </RouterLink>
        <RouterLink
          :to="`/partnerships/${uuid}/redemptions`"
          class="text-xs bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
        >
          Redemptions
        </RouterLink>
      </div>

      <!-- Onboarding guide (LIVE, shown once per partnership) -->
      <div v-if="showOnboarding" class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4">
        <div class="flex items-start justify-between gap-4 mb-3">
          <div>
            <p class="text-sm font-semibold text-indigo-800">You're live — here's what to do next</p>
            <p class="text-xs text-indigo-600 mt-0.5">Follow these steps to get your first customers flowing within hours.</p>
          </div>
          <button @click="dismissOnboarding" class="text-indigo-400 hover:text-indigo-600 text-xs whitespace-nowrap flex-shrink-0">
            Dismiss
          </button>
        </div>
        <ol class="space-y-2 text-xs text-indigo-800">
          <li class="flex gap-2">
            <span class="font-bold w-4 flex-shrink-0">1.</span>
            <span><strong>Print your QR code</strong> — use the "QR codes for customer self-claim" section below. Place it at your counter or entrance.</span>
          </li>
          <li class="flex gap-2">
            <span class="font-bold w-4 flex-shrink-0">2.</span>
            <span><strong>Configure your POS</strong> — ensure your POS terminal is set up to accept partner tokens. Use the "Issue claim token" section below to generate a test token and verify the end-to-end redemption flow via your POS.</span>
          </li>
          <li class="flex gap-2">
            <span class="font-bold w-4 flex-shrink-0">3.</span>
            <span><strong>Issue a test claim</strong> — use the "Issue claim token" section below to generate a token, then present it at your POS terminal to confirm the end-to-end redemption flow works.</span>
          </li>
          <li class="flex gap-2">
            <span class="font-bold w-4 flex-shrink-0">4.</span>
            <span><strong>Share with your customers</strong> — tell them that {{ proposerName }} customers can now get a benefit at your outlet. A social post or WhatsApp broadcast does the job.</span>
          </li>
          <li class="flex gap-2">
            <span class="font-bold w-4 flex-shrink-0">5.</span>
            <span><strong>Track results</strong> — check Analytics and Ledger after 7 days to see new customers acquired and ROI.</span>
          </li>
        </ol>
      </div>

      <!-- Marketing Kit (LIVE partnerships) -->
      <div v-if="p.status === 5" class="bg-white border border-gray-200 rounded-xl mb-4">
        <button
          @click="marketingKitOpen = !marketingKitOpen"
          class="w-full px-5 py-4 flex items-center justify-between text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors rounded-xl"
        >
          <span>Marketing kit — ready-to-use copy</span>
          <span class="text-xs font-normal text-gray-400">{{ marketingKitOpen ? '▲ Hide' : '▼ Expand' }}</span>
        </button>
        <div v-if="marketingKitOpen" class="px-5 pb-5 border-t border-gray-100 pt-4 space-y-5">

          <!-- WhatsApp broadcast -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">WhatsApp broadcast to your customers</p>
              <button @click="copyText('wa', whatsappCopy)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                {{ copiedKey === 'wa' ? '✓ Copied' : 'Copy' }}
              </button>
            </div>
            <pre class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 whitespace-pre-wrap font-sans leading-relaxed">{{ whatsappCopy }}</pre>
          </div>

          <!-- Instagram / social -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Instagram / social media caption</p>
              <button @click="copyText('ig', instagramCopy)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                {{ copiedKey === 'ig' ? '✓ Copied' : 'Copy' }}
              </button>
            </div>
            <pre class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 whitespace-pre-wrap font-sans leading-relaxed">{{ instagramCopy }}</pre>
          </div>

          <!-- Counter card / table tent -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Counter card / table tent text</p>
              <button @click="copyText('counter', counterCopy)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                {{ copiedKey === 'counter' ? '✓ Copied' : 'Copy' }}
              </button>
            </div>
            <pre class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 whitespace-pre-wrap font-sans leading-relaxed">{{ counterCopy }}</pre>
          </div>

          <!-- Physical checklist -->
          <div>
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Physical materials checklist</p>
            <ul class="text-xs text-gray-600 space-y-1.5">
              <li class="flex gap-2"><span class="text-green-600 font-bold flex-shrink-0">✓</span> Print the QR code (below) — A5 or A6 size, laminated. Place at your counter and entrance.</li>
              <li class="flex gap-2"><span class="text-green-600 font-bold flex-shrink-0">✓</span> Print the counter card text above as a table tent or A6 flyer.</li>
              <li class="flex gap-2"><span class="text-green-600 font-bold flex-shrink-0">✓</span> Brief your cashier: "Ask every customer if they have a partner token from {{ partnerName }}."</li>
              <li class="flex gap-2"><span class="text-green-600 font-bold flex-shrink-0">✓</span> Add a story/highlight on Instagram tagging {{ partnerName }} — cross-promotion doubles the reach.</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- QR Codes (LIVE partnerships only) -->
      <div v-if="p.status === 5" class="bg-white border border-gray-200 rounded-xl mb-4">
        <button
          @click="openQrSection"
          class="w-full px-5 py-4 flex items-center justify-between text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors rounded-xl"
        >
          QR codes for customer self-claim
          <span class="text-xs font-normal text-gray-400">{{ qrOpen ? '▲ Hide' : '▼ Show' }}</span>
        </button>
        <div v-if="qrOpen" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <p class="text-xs text-gray-400 mb-4">
            Display these at your outlet. Customers scan to self-claim a referral token (valid 48h) which they present at the partner's cashier.
          </p>
          <div v-if="qrLoading" class="text-sm text-gray-400 py-4 text-center">Generating…</div>
          <div v-else class="flex flex-wrap gap-6">
            <div v-for="entry in qrEntries" :key="entry.outletId" class="text-center">
              <img :src="entry.dataUrl" :alt="`QR for ${entry.outletName}`" class="rounded-lg border border-gray-200" width="160" />
              <p class="text-xs text-gray-600 mt-2 font-medium">{{ entry.outletName }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Announce to Your Customers (GAP 3, LIVE only) -->
      <div v-if="p.status === 5 && auth.isManager()" class="bg-white border border-gray-200 rounded-xl mb-4">
        <button
          @click="openAnnouncementSection"
          class="w-full px-5 py-4 flex items-center justify-between text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors rounded-xl"
        >
          Announce to Your Customers
          <span class="text-xs font-normal text-gray-400">{{ announcementOpen ? '▲ Hide' : '▼ Expand' }}</span>
        </button>
        <div v-if="announcementOpen" class="px-5 pb-5 border-t border-gray-100 pt-4 space-y-4">
          <p class="text-xs text-gray-400">
            Send a message about this partnership to all customers in your database. Recipients: your own customer list only.
          </p>

          <!-- Success banner -->
          <div v-if="announcementDone" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Announcement sent to {{ announcementRecipientCount.toLocaleString() }} customer{{ announcementRecipientCount !== 1 ? 's' : '' }}!
          </div>

          <!-- Error -->
          <div v-if="announcementError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
            {{ announcementError }}
          </div>

          <!-- Message editor -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs font-medium text-gray-600">Message</label>
              <span class="text-xs text-gray-400">{{ announcementText.length }} / 1000</span>
            </div>
            <textarea
              v-model="announcementText"
              @blur="fetchAnnouncementPreview"
              rows="4"
              maxlength="1000"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
              placeholder="Enter your announcement message…"
            />
          </div>

          <!-- Recipient count -->
          <div class="flex items-center gap-2 text-xs text-gray-500">
            <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            <span v-if="announcementPreviewLoading">Checking recipient count…</span>
            <span v-else>Will reach approximately <strong class="text-gray-700">{{ announcementRecipientCount.toLocaleString() }}</strong> customer{{ announcementRecipientCount !== 1 ? 's' : '' }} in your database</span>
          </div>

          <button
            @click="sendAnnouncement"
            :disabled="announcementSending || !announcementText.trim() || announcementRecipientCount === 0"
            class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
          >
            {{ announcementSending ? 'Sending…' : 'Send Announcement' }}
          </button>

          <!-- Past Announcements -->
          <div v-if="pastAnnouncements.length > 0" class="mt-2">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Past Announcements</p>
            <div class="rounded-lg border border-gray-200 overflow-hidden">
              <table class="w-full text-xs">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="text-left px-3 py-2 text-gray-500 font-medium">Date</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-medium">Status</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-medium">Recipients</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="ann in pastAnnouncements" :key="ann.id" class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-gray-700">{{ new Date(ann.created_at).toLocaleDateString() }}</td>
                    <td class="px-3 py-2">
                      <span class="px-1.5 py-0.5 rounded text-xs font-medium"
                        :class="ann.status === 'sent' ? 'bg-green-100 text-green-700' : ann.status === 'scheduled' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">
                        {{ ann.status }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-right text-gray-600">{{ ann.recipient_count.toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else-if="!announcementPreviewLoading" class="text-xs text-gray-400 italic">No announcements sent yet.</div>
        </div>
      </div>

      <!-- Claim Issuance (LIVE only) -->
      <div v-if="p.status === 5" class="bg-white border border-gray-200 rounded-xl mb-4">
        <button
          @click="openClaimSection"
          class="w-full px-5 py-4 flex items-center justify-between text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors rounded-xl"
        >
          Issue claim token
          <span class="text-xs font-normal text-gray-400">{{ claimSectionOpen ? '▲ Hide' : '▼ Expand' }}</span>
        </button>
        <div v-if="claimSectionOpen" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <div v-if="claimError && !claimLoading" class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
            {{ claimError }}
          </div>

          <!-- Token result -->
          <div v-if="claimToken" class="mb-4 rounded-xl bg-green-50 border border-green-200 px-5 py-4 text-center">
            <p class="text-xs text-green-600 mb-1">Claim token issued</p>
            <p class="text-3xl font-mono font-bold tracking-widest text-green-800">{{ claimToken }}</p>
            <p class="text-xs text-gray-400 mt-2">Valid for 48 hours — give this to the customer to show at the partner outlet.</p>
          </div>

          <div class="grid grid-cols-1 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Source outlet (your outlet issuing the claim)</label>
              <select
                v-model="claimForm.source_outlet_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option v-for="o in myOutlets" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Target outlet (partner outlet where benefit is redeemed)</label>
              <select
                v-model="claimForm.target_outlet_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option v-for="o in partnerOutlets" :key="o.id" :value="o.id">{{ o.name }}<span v-if="o.address"> — {{ o.address }}</span></option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Customer phone <span class="text-gray-400">(optional)</span></label>
              <input
                v-model="claimForm.customer_phone"
                type="tel"
                placeholder="+91 98765 43210"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <button
              @click="issueClaim"
              :disabled="claimLoading || !claimForm.source_outlet_id || !claimForm.target_outlet_id"
              class="mt-1 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ claimLoading ? 'Issuing…' : 'Issue token' }}
            </button>
          </div>
        </div>
      </div>


      <!-- Dates -->
      <div v-if="p.start_at || p.end_at" class="mb-6 flex gap-6 text-sm text-gray-600">
        <span v-if="p.start_at">Starts: {{ new Date(p.start_at).toLocaleDateString() }}</span>
        <span v-if="p.end_at">Ends: {{ new Date(p.end_at).toLocaleDateString() }}</span>
      </div>

      <!-- Participants -->
      <div class="bg-white border border-gray-200 rounded-xl mb-4">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="text-sm font-semibold text-gray-900">Participants</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="(pt, i) in p.participants" :key="i" class="px-5 py-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-gray-700 font-medium">
                {{ pt.merchant_name ?? `Merchant ${pt.merchant_id}` }}
                <span class="text-gray-400 font-normal ml-1">{{ pt.is_brand_wide ? '(brand-wide)' : (pt.outlet_name ?? `outlet #${pt.outlet_id}`) }}</span>
              </span>
              <div class="flex items-center gap-2">
                <span
                  v-if="pt.is_suspended"
                  class="text-xs font-medium px-2 py-0.5 rounded-full bg-orange-100 text-orange-700"
                  :title="pt.suspension_reason ?? 'Side suspended'"
                >Side suspended</span>
                <span class="text-xs font-medium text-gray-500">{{ roleLabel(pt.role) }}</span>
              </div>
            </div>
            <!-- Per-side permission indicators -->
            <div class="flex items-center gap-3 mt-1.5">
              <span
                class="text-xs px-1.5 py-0.5 rounded"
                :class="pt.issuing_enabled ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400 line-through'"
                title="Can this side issue tokens to send customers to the partner?"
              >Issuing</span>
              <span
                class="text-xs px-1.5 py-0.5 rounded"
                :class="pt.redemption_enabled ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400 line-through'"
                title="Can this side redeem tokens presented by partner customers?"
              >Redemption</span>
              <span
                class="text-xs px-1.5 py-0.5 rounded"
                :class="pt.campaigns_enabled ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400 line-through'"
                title="Can this side run campaigns for this partnership?"
              >Campaigns</span>
            </div>
            <!-- Offer details (shown when offer_structure = 'different') -->
            <div v-if="isOfferDifferent" class="mt-2">
              <span class="text-xs text-gray-400">Their offer: </span>
              <span v-if="pt.offer_filled" class="text-xs text-gray-700 font-medium">{{ offerSummary(pt) }}</span>
              <span v-else class="text-xs text-amber-600 font-medium">Not filled yet</span>
            </div>
          </div>
          <div v-if="!p.participants?.length" class="px-5 py-4 text-sm text-gray-400">No participants loaded.</div>
        </div>
      </div>

      <!-- Terms -->
      <div v-if="p.terms" class="bg-white border border-gray-200 rounded-xl mb-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Terms <span class="ml-1 text-xs font-normal text-gray-400">v{{ p.terms.version }}</span></h2>
          <button
            v-if="canEdit && !termsEditing"
            @click="openTermsEdit"
            class="text-xs text-indigo-600 hover:text-indigo-800"
          >
            Edit
          </button>
          <span v-if="termsEditing" class="flex gap-2">
            <button @click="termsEditing = false" class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
            <button @click="saveTerms" :disabled="termsSaving" class="text-xs text-indigo-600 hover:text-indigo-800 disabled:opacity-50">
              {{ termsSaving ? 'Saving…' : 'Save' }}
            </button>
          </span>
        </div>

        <!-- Error -->
        <div v-if="termsError" class="mx-5 mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
          {{ termsError }}
        </div>

        <!-- View mode -->
        <div v-if="!termsEditing" class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
          <!-- Per-bill cap: could be %, ₹, or pts -->
          <div v-if="p.terms.per_bill_cap_percent !== null">
            <p class="text-xs text-gray-400">Cap per bill</p>
            <p class="font-medium text-gray-900">{{ p.terms.per_bill_cap_percent }}%</p>
          </div>
          <div v-if="p.terms.per_bill_cap_amount !== null">
            <p class="text-xs text-gray-400">Max per bill</p>
            <p class="font-medium text-gray-900">₹{{ p.terms.per_bill_cap_amount }}</p>
          </div>
          <!-- Min bill -->
          <div v-if="p.terms.min_bill_amount !== null">
            <p class="text-xs text-gray-400">Min bill</p>
            <p class="font-medium text-gray-900">₹{{ p.terms.min_bill_amount }}</p>
          </div>
          <!-- Monthly cap -->
          <div v-if="p.terms.monthly_cap_amount !== null">
            <p class="text-xs text-gray-400">Monthly cap</p>
            <p class="font-medium text-gray-900">₹{{ p.terms.monthly_cap_amount }}</p>
          </div>
        </div>

        <!-- Edit mode -->
        <div v-else class="px-5 py-4 space-y-5 text-sm">

          <!-- Per-bill cap -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-medium text-gray-600">Cap per bill</label>
              <!-- Denomination selector -->
              <div class="flex rounded-lg overflow-hidden border border-gray-300 text-xs">
                <button
                  v-for="m in (['percent', 'amount'] as PerBillMode[])"
                  :key="m"
                  @click="perBillMode = m"
                  class="px-3 py-1 transition-colors"
                  :class="perBillMode === m ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                >
                  {{ m === 'percent' ? '%' : '₹' }}
                </button>
              </div>
            </div>
            <div v-if="perBillMode === 'percent'">
              <input v-model.number="termsForm.per_bill_cap_percent" type="number" min="0" max="100" placeholder="e.g. 20"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p class="text-xs text-gray-400 mt-1">% of the customer's bill</p>
            </div>
            <div v-else>
              <input v-model.number="termsForm.per_bill_cap_amount" type="number" min="0" placeholder="e.g. 150"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <!-- Min bill -->
          <div>
            <label class="text-xs font-medium text-gray-600 block mb-2">Minimum bill</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
              <input v-model.number="termsForm.min_bill_amount" type="number" min="0" placeholder="e.g. 500"
                class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <!-- Monthly cap -->
          <div>
            <label class="text-xs font-medium text-gray-600 block mb-2">Monthly cap</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
              <input v-model.number="termsForm.monthly_cap_amount" type="number" min="0" placeholder="e.g. 5000"
                class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

        </div>

      </div>

      <!-- Rules -->
      <div v-if="p.rules || canEdit" class="bg-white border border-gray-200 rounded-xl mb-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">
            Rules
            <span v-if="p.rules" class="ml-1 text-xs font-normal text-gray-400">v{{ p.rules.version }}</span>
          </h2>
          <button v-if="canEdit && !rulesEditing" @click="openRulesEdit"
            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit rules</button>
          <div v-if="rulesEditing" class="flex items-center gap-3">
            <button @click="rulesEditing = false" class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
            <button @click="saveRules" :disabled="store.loading"
              class="text-xs font-medium bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ store.loading ? 'Saving…' : 'Save rules' }}
            </button>
          </div>
        </div>

        <!-- VIEW MODE -->
        <div v-if="!rulesEditing && p.rules" class="px-5 py-4 space-y-4 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-400">First time only</p>
              <p class="font-medium text-gray-900">{{ p.rules.first_time_only ? 'Yes' : 'No' }}</p>
            </div>
            <div v-if="p.rules.uses_per_customer !== null">
              <p class="text-xs text-gray-400">Uses per customer</p>
              <p class="font-medium text-gray-900">{{ p.rules.uses_per_customer }}</p>
            </div>
          </div>
        </div>

        <!-- EDIT MODE -->
        <div v-if="rulesEditing" class="px-5 py-4 space-y-5">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Uses per customer</label>
              <input v-model.number="rulesForm.uses_per_customer" type="number" min="1" placeholder="Unlimited"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="flex items-center gap-2 pt-5">
              <input type="checkbox" id="firstTimeOnly" v-model="rulesForm.first_time_only"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <label for="firstTimeOnly" class="text-sm text-gray-700 cursor-pointer">First visit only</label>
            </div>
          </div>
        </div>
      </div>

      <!-- T&C + Agreements -->
      <div class="bg-white border border-gray-200 rounded-xl mb-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Terms & Conditions</h2>
          <button @click="openTC" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
            View standard T&amp;C
          </button>
        </div>
        <div class="px-5 py-4 space-y-3">
          <!-- Proposer agreement status -->
          <div v-if="proposerAgreement" class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs text-gray-400">
                {{ proposerAgreement.merchant_id === auth.user?.merchant_id ? 'Your acceptance' : 'Proposer acceptance' }}
              </p>
              <p class="text-sm font-medium text-gray-900">
                v{{ proposerAgreement.version }}
                <span v-if="proposerAgreement.accepted_at" class="text-xs text-gray-400 font-normal ml-1">
                  on {{ new Date(proposerAgreement.accepted_at).toLocaleDateString() }}
                </span>
              </p>
            </div>
            <span v-if="proposerAgreement.accepted_at"
              class="text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700 flex-shrink-0">
              Accepted
            </span>
            <span v-else class="text-xs font-medium px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 flex-shrink-0">
              Pending
            </span>
          </div>
          <div v-else class="text-xs text-gray-400">No proposer record yet.</div>

          <hr class="border-gray-100" />

          <!-- Acceptor agreement status -->
          <div v-if="acceptorAgreement" class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs text-gray-400">
                {{ acceptorAgreement.merchant_id === auth.user?.merchant_id ? 'Your acceptance' : 'Partner acceptance' }}
              </p>
              <p class="text-sm font-medium text-gray-900">
                v{{ acceptorAgreement.version }}
                <span v-if="acceptorAgreement.accepted_at" class="text-xs text-gray-400 font-normal ml-1">
                  on {{ new Date(acceptorAgreement.accepted_at).toLocaleDateString() }}
                </span>
              </p>
            </div>
            <span v-if="acceptorAgreement.accepted_at"
              class="text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700 flex-shrink-0">
              Accepted
            </span>
            <span v-else class="text-xs font-medium px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 flex-shrink-0">
              Pending
            </span>
          </div>
          <div v-else class="text-xs text-gray-400">Partner has not yet accepted.</div>
        </div>
      </div>

      <!-- GAP 7: Rate Your Partner (LIVE or PAUSED) -->
      <div v-if="p.status === 5 || p.status === 6" class="bg-white border border-gray-200 rounded-xl mb-6 px-5 py-4">
        <p class="text-sm font-semibold text-gray-900 mb-3">Rate Your Partner</p>

        <!-- Already rated confirmation -->
        <div v-if="ratingDone" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 mb-3">
          ✓ Rating saved — thank you for your feedback!
        </div>

        <div v-if="existingRating && !ratingDone" class="rounded-lg bg-indigo-50 border border-indigo-100 px-3 py-2 text-xs text-indigo-700 mb-3">
          You rated this partner {{ existingRating.rating }}/5. You can update your rating below.
        </div>

        <!-- Star picker -->
        <div class="flex items-center gap-1 mb-3">
          <button
            v-for="star in 5"
            :key="star"
            @click="ratingValue = star"
            class="text-2xl focus:outline-none transition-transform hover:scale-110"
            :class="star <= ratingValue ? 'text-yellow-400' : 'text-gray-300'"
            :title="`Rate ${star} star${star > 1 ? 's' : ''}`"
          >
            {{ star <= ratingValue ? '★' : '☆' }}
          </button>
          <span v-if="ratingValue > 0" class="text-xs text-gray-500 ml-2">{{ ratingValue }}/5</span>
        </div>

        <!-- Review text -->
        <textarea
          v-model="ratingReview"
          rows="3"
          placeholder="Optional — share your experience with this partnership…"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3"
        />

        <!-- Error -->
        <div v-if="ratingError" class="mb-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
          {{ ratingError }}
        </div>

        <!-- Submit -->
        <button
          @click="submitRating"
          :disabled="ratingSubmitting || ratingValue < 1"
          class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ ratingSubmitting ? 'Saving…' : (existingRating ? 'Update rating' : 'Submit rating') }}
        </button>
      </div>

      <!-- GAP 10: Shareable Link (LIVE partnerships only) -->
      <div v-if="p.status === 5" class="bg-white border border-gray-200 rounded-xl mb-4">
        <button
          @click="shareLinkOpen = !shareLinkOpen"
          class="w-full px-5 py-4 flex items-center justify-between text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors rounded-xl"
        >
          <span>Shareable link for customers</span>
          <span class="text-xs font-normal text-gray-400">{{ shareLinkOpen ? '▲ Hide' : '▼ Expand' }}</span>
        </button>
        <div v-if="shareLinkOpen" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <p class="text-xs text-gray-400 mb-4">
            Generate a link your customers can tap to view the offer and claim a token — no QR printer needed. Share it via WhatsApp or any channel.
          </p>

          <!-- Error -->
          <div v-if="shareLinkError" class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
            {{ shareLinkError }}
          </div>

          <!-- No link yet -->
          <div v-if="!shareLink">
            <button
              @click="generateShareLink"
              :disabled="shareLinkLoading"
              class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ shareLinkLoading ? 'Generating…' : 'Generate Share Link' }}
            </button>
          </div>

          <!-- Link generated -->
          <div v-else class="space-y-3">
            <!-- Copyable input -->
            <div class="flex items-center gap-2">
              <input
                :value="shareLink"
                readonly
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 focus:outline-none font-mono"
              />
              <button
                @click="copyShareLink"
                class="flex-shrink-0 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors"
              >
                {{ shareLinkCopied ? '✓ Copied' : 'Copy link' }}
              </button>
            </div>

            <!-- Action buttons -->
            <div class="flex flex-wrap gap-2">
              <button
                @click="shareViaWhatsApp"
                class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
              >
                <span>Share via WhatsApp</span>
              </button>
              <button
                @click="generateShareLink"
                class="text-xs text-gray-400 hover:text-gray-600 underline px-2 py-2"
              >
                Regenerate
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- T&C Modal -->
      <Teleport to="body">
        <div v-if="tcOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          @click.self="tcOpen = false">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
              <div>
                <h3 class="text-base font-semibold text-gray-900">Standard Partnership T&amp;C</h3>
                <p class="text-xs text-gray-400 mt-0.5">Version {{ p.tc_version }}</p>
              </div>
              <button @click="tcOpen = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
            </div>
            <div class="overflow-y-auto px-6 py-4 flex-1">
              <p v-if="!tcText" class="text-sm text-gray-400">Loading…</p>
              <pre v-else class="text-xs text-gray-700 whitespace-pre-wrap font-sans leading-relaxed">{{ tcText }}</pre>
            </div>
          </div>
        </div>
      </Teleport>

    </template>
  </div>
</template>
