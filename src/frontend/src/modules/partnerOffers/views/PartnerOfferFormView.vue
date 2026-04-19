<!--
  PartnerOfferFormView.vue — Create/edit form with template picker.
  Purpose: Merchant creates or edits an offer with coupon code, discount, and display template.
  Owner module: PartnerOffers
  API: POST /api/partner-offers, PUT /api/partner-offers/{uuid}
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const isEdit = !!route.params.uuid

const form = ref({
  title: '',
  description: '',
  coupon_code: '',
  image_url: '',
  expiry_date: '',
  terms_conditions: '',
  display_template: 'simple',
  // eWards-style fields
  issuance_mode: 'unlimited' as 'unlimited' | 'limited',
  max_issuance: '' as string | number,
  redemption_mode: 'unlimited' as 'unlimited' | 'limited',
  max_redemptions: '' as string | number,
  pos_redemption_type: 'flat' as 'flat' | 'percentage',
  flat_discount_amount: '' as string | number,
  discount_percentage: '' as string | number,
  max_cap_enabled: false,
  max_cap_amount: '' as string | number,
})

const loading = ref(false)
const error   = ref<string | null>(null)

onMounted(async () => {
  if (isEdit) {
    try {
      const { data } = await api.get(`/partner-offers/${route.params.uuid}`)
      const o = data.data
      form.value = {
        title: o.title,
        description: o.description ?? '',
        coupon_code: o.coupon_code,
        image_url: o.image_url ?? '',
        expiry_date: o.expiry_date ?? '',
        terms_conditions: o.terms_conditions ?? '',
        display_template: o.display_template ?? 'simple',
        // eWards-style fields
        issuance_mode: o.max_issuance == null ? 'unlimited' : 'limited',
        max_issuance: o.max_issuance != null ? String(o.max_issuance) : '',
        redemption_mode: o.max_redemptions == null ? 'unlimited' : 'limited',
        max_redemptions: o.max_redemptions != null ? String(o.max_redemptions) : '',
        pos_redemption_type: (o.pos_redemption_type === 'offer' ? 'flat' : o.pos_redemption_type) ?? 'flat',
        flat_discount_amount: o.flat_discount_amount != null ? String(o.flat_discount_amount) : '',
        discount_percentage: o.discount_percentage != null ? String(o.discount_percentage) : '',
        max_cap_enabled: o.max_cap_amount != null,
        max_cap_amount: o.max_cap_amount != null ? String(o.max_cap_amount) : '',
      }
    } catch {
      error.value = 'Failed to load offer.'
    }
  }
})

function generateCode() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
  let code = ''
  for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)]
  form.value.coupon_code = code
}

async function submit() {
  error.value = null
  loading.value = true
  try {
    const payload: Record<string, unknown> = {
      title: form.value.title,
      coupon_code: form.value.coupon_code,
      display_template: form.value.display_template,
      image_url: form.value.image_url || undefined,
      expiry_date: form.value.expiry_date || undefined,
      terms_conditions: form.value.terms_conditions || undefined,
      description: form.value.description || undefined,
      // eWards-style fields
      max_issuance: form.value.issuance_mode === 'limited' && form.value.max_issuance ? Number(form.value.max_issuance) : null,
      max_redemptions: form.value.redemption_mode === 'limited' && form.value.max_redemptions ? Number(form.value.max_redemptions) : null,
      pos_redemption_type: form.value.pos_redemption_type,
      flat_discount_amount: form.value.pos_redemption_type === 'flat' && form.value.flat_discount_amount ? Number(form.value.flat_discount_amount) : null,
      discount_percentage: form.value.pos_redemption_type === 'percentage' && form.value.discount_percentage ? Number(form.value.discount_percentage) : null,
      max_cap_amount: form.value.pos_redemption_type === 'percentage' && form.value.max_cap_enabled && form.value.max_cap_amount ? Number(form.value.max_cap_amount) : null,
    }

    if (isEdit) {
      await api.put(`/partner-offers/${route.params.uuid}`, payload)
      router.push(`/partner-offers/${route.params.uuid}`)
    } else {
      const { data } = await api.post('/partner-offers', payload)
      router.push(`/partner-offers/${data.data.uuid}`)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Failed to save offer.'
  } finally {
    loading.value = false
  }
}

const templates = [
  { key: 'simple', label: 'Simple card list', desc: 'Clean cards with copy button' },
  { key: 'scratch', label: 'Scratch to reveal', desc: 'Gamified scratch card overlay' },
  { key: 'carousel', label: 'Swipeable carousel', desc: 'Horizontal swipe cards' },
]
</script>

<template>
  <div class="p-6 lg:p-8 max-w-2xl">
    <button @click="router.back()" class="text-sm text-gray-500 hover:text-gray-700 mb-4 flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
      Back
    </button>

    <h1 class="text-2xl font-semibold text-gray-900 mb-6">{{ isEdit ? 'Edit offer' : 'Create offer' }}</h1>

    <form @submit.prevent="submit" class="space-y-5">
      <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ error }}</div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Offer title</label>
        <input v-model="form.title" required maxlength="200" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 20% off your first coffee" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
        <textarea v-model="form.description" rows="2" maxlength="2000" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="What the customer gets…" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Coupon code</label>
          <div class="flex gap-2">
            <input v-model="form.coupon_code" required maxlength="50" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono uppercase focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="BREW20OFF" />
            <button type="button" @click="generateCode" class="text-xs bg-gray-100 text-gray-600 px-3 py-2 rounded-lg hover:bg-gray-200">Auto</button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Expiry date (optional)</label>
          <input v-model="form.expiry_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Image URL (optional)</label>
        <input v-model="form.image_url" type="url" maxlength="500" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="https://…" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Terms & conditions (optional)</label>
        <textarea v-model="form.terms_conditions" rows="2" maxlength="5000" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Valid on min bill of ₹200…" />
      </div>

      <!-- eWards-style: Coupon issuance limit -->
      <div class="border border-gray-200 rounded-xl p-4 space-y-3">
        <label class="block text-sm font-medium text-gray-700">Total no. of times this coupon can be issued</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="radio" v-model="form.issuance_mode" value="unlimited" class="accent-indigo-600" />
            Unlimited
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="radio" v-model="form.issuance_mode" value="limited" class="accent-indigo-600" />
            Limited
          </label>
        </div>
        <div v-if="form.issuance_mode === 'limited'">
          <input v-model="form.max_issuance" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 500" />
        </div>
      </div>

      <!-- eWards-style: Coupon redemption limit -->
      <div class="border border-gray-200 rounded-xl p-4 space-y-3">
        <label class="block text-sm font-medium text-gray-700">Total no. of times this coupon can be redeemed</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="radio" v-model="form.redemption_mode" value="unlimited" class="accent-indigo-600" />
            Unlimited
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="radio" v-model="form.redemption_mode" value="limited" class="accent-indigo-600" />
            Limited
          </label>
        </div>
        <div v-if="form.redemption_mode === 'limited'">
          <input v-model="form.max_redemptions" type="number" min="1" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 200" />
        </div>
      </div>

      <!-- eWards-style: POS Redemption Type -->
      <div class="border border-gray-200 rounded-xl p-4 space-y-3">
        <label class="block text-sm font-medium text-gray-700">POS Redemption Type</label>
        <select v-model="form.pos_redemption_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option value="flat">Flat Monetary Discount</option>
          <option value="percentage">Percentage Discount</option>
        </select>

        <!-- Flat Monetary Discount fields -->
        <div v-if="form.pos_redemption_type === 'flat'" class="space-y-2">
          <label class="block text-xs text-gray-600">Enter Discount Amount</label>
          <input v-model="form.flat_discount_amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 100" />
        </div>

        <!-- Percentage Discount fields -->
        <div v-if="form.pos_redemption_type === 'percentage'" class="space-y-3">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Enter Discount Percentage</label>
            <input v-model="form.discount_percentage" type="number" min="0.01" max="100" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 15" />
          </div>
          <div class="flex items-center justify-between">
            <label class="text-xs text-gray-600">Max Cap Permission</label>
            <button
              type="button"
              @click="form.max_cap_enabled = !form.max_cap_enabled"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
              :class="form.max_cap_enabled ? 'bg-indigo-600' : 'bg-gray-300'"
            >
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="form.max_cap_enabled ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>
          <div v-if="form.max_cap_enabled">
            <label class="block text-xs text-gray-600 mb-1">Enter Max Cap</label>
            <input v-model="form.max_cap_amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 500" />
          </div>
        </div>

      </div>

      <!-- Template picker -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Display template</label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <label
            v-for="t in templates"
            :key="t.key"
            class="border rounded-xl p-4 cursor-pointer transition-all text-center"
            :class="form.display_template === t.key ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300'"
          >
            <input type="radio" v-model="form.display_template" :value="t.key" class="sr-only" />
            <p class="text-sm font-medium text-gray-900">{{ t.label }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ t.desc }}</p>
          </label>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="button" @click="router.back()" class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">Cancel</button>
        <button type="submit" :disabled="loading" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
          {{ loading ? 'Saving…' : (isEdit ? 'Update' : 'Create') }}
        </button>
      </div>
    </form>
  </div>
</template>
