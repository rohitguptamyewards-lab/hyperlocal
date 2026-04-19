<template>
  <div>
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Platform Overview</h2>

    <div v-if="loading" class="text-sm text-gray-500">Loading…</div>

    <div v-else-if="stats" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-8">
      <StatCard label="Total Brands" :value="stats.total_merchants" />
      <StatCard label="Ecosystem Active" :value="stats.ecosystem_active" />
      <StatCard label="Live Partnerships" :value="stats.live_partnerships" />
      <StatCard label="Pending Registrations" :value="stats.pending_brand_registrations" :alert="stats.pending_brand_registrations > 0" />
      <StatCard label="Pending eWards" :value="stats.pending_ewards_requests" :alert="stats.pending_ewards_requests > 0" />
      <StatCard label="Low Credits" :value="stats.merchants_low_credits" :alert="stats.merchants_low_credits > 0" />
      <StatCard label="Zero Credits" :value="stats.merchants_zero_credits" :alert="stats.merchants_zero_credits > 0" />
    </div>

    <div class="flex gap-4 flex-wrap">
      <router-link
        to="/super-admin/brand-registrations"
        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-sm rounded-lg hover:bg-gray-800 transition-colors"
      >
        Review Brand Registrations
      </router-link>
      <router-link
        to="/super-admin/requests"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors"
      >
        Review eWards Requests
      </router-link>
      <router-link
        to="/super-admin/merchants"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors"
      >
        Manage Brands
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import superAdminApi from '@/services/superAdminApi'

interface Stats {
  total_merchants: number
  ecosystem_active: number
  live_partnerships: number
  pending_brand_registrations: number
  pending_ewards_requests: number
  merchants_low_credits: number
  merchants_zero_credits: number
}

const stats = ref<Stats | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await superAdminApi.get('/merchants/dashboard')
    stats.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<script lang="ts">
// Inline sub-component to avoid extra file
import { defineComponent, h } from 'vue'

const StatCard = defineComponent({
  props: {
    label: String,
    value: Number,
    alert: { type: Boolean, default: false },
  },
  setup(props) {
    return () =>
      h(
        'div',
        { class: `bg-white rounded-xl p-4 shadow-sm border ${props.alert ? 'border-orange-300' : 'border-gray-200'}` },
        [
          h('div', { class: `text-2xl font-bold ${props.alert ? 'text-orange-600' : 'text-gray-900'}` }, String(props.value ?? 0)),
          h('div', { class: 'text-xs text-gray-500 mt-1' }, props.label),
        ],
      )
  },
})

export { StatCard }
</script>
