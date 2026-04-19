<!--
  EventSourceListView.vue — List connected event sources + connect new.
  Owner module: EventTriggers
  API: GET /api/event-sources
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()

interface Source {
  id: number; uuid: string; name: string; source_type: string;
  merchant_key: string; status: number; test_mode: boolean;
  triggers_count: number; created_at: string;
}

const sources = ref<Source[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/event-sources')
    sources.value = data.data
  } finally { loading.value = false }
})

async function toggleSource(s: Source) {
  await api.post(`/event-sources/${s.uuid}/toggle`)
  s.status = s.status === 1 ? 2 : 1
}

const sourceTypeLabel: Record<string, string> = {
  website: 'Website', api: 'Server API', shopify: 'Shopify',
  woocommerce: 'WooCommerce', ewrds: 'eWards POS',
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-4xl">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Event Sources</h1>
        <p class="text-sm text-gray-500 mt-0.5">Connect your website, Shopify, or API to trigger partner actions</p>
      </div>
      <button @click="router.push('/event-sources/setup')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
        Connect source
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>

    <div v-else-if="sources.length === 0" class="text-center py-20 text-gray-400">
      <p class="text-lg">No event sources connected</p>
      <p class="text-sm mt-1">Connect your website or ecommerce store to start triggering partner offers</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="s in sources" :key="s.uuid" class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center justify-between">
        <div>
          <p class="font-medium text-gray-900">{{ s.name }}</p>
          <p class="text-xs text-gray-400 mt-0.5">
            {{ sourceTypeLabel[s.source_type] ?? s.source_type }}
            · {{ s.triggers_count }} trigger{{ s.triggers_count !== 1 ? 's' : '' }}
            · Key: <span class="font-mono text-indigo-600">{{ s.merchant_key.substring(0, 15) }}…</span>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="s.status === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'">
            {{ s.status === 1 ? 'Active' : 'Paused' }}
          </span>
          <button @click="toggleSource(s)" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
            {{ s.status === 1 ? 'Pause' : 'Activate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Quick link to triggers -->
    <div class="mt-8 pt-6 border-t border-gray-100">
      <button @click="router.push('/event-triggers')" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
        Manage trigger rules →
      </button>
    </div>
  </div>
</template>
