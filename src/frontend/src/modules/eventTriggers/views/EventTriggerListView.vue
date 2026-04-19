<!--
  EventTriggerListView.vue — List trigger rules with on/off toggles + recent event log.
  Owner module: EventTriggers
  API: GET /api/event-triggers, GET /api/event-log
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()

interface Trigger {
  id: number; uuid: string; name: string; event_type: string;
  action_type: string; is_active: boolean;
  source?: { name: string; source_type: string } | null;
}

interface LogEntry {
  id: number; event_type: string; processing_status: string;
  received_at: string; action_outcome: any;
}

const triggers = ref<Trigger[]>([])
const recentLog = ref<LogEntry[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [trigRes, logRes] = await Promise.all([
      api.get('/event-triggers'),
      api.get('/event-log'),
    ])
    triggers.value = trigRes.data.data
    recentLog.value = logRes.data.data?.slice(0, 10) ?? []
  } finally { loading.value = false }
})

async function toggleTrigger(t: Trigger) {
  await api.post(`/event-triggers/${t.uuid}/toggle`)
  t.is_active = !t.is_active
}

const actionLabel: Record<string, string> = {
  issue_offer: 'Issue offer', send_whatsapp: 'Send WhatsApp',
  make_eligible: 'Make eligible', send_campaign: 'Send campaign',
}

const statusColor: Record<string, string> = {
  completed: 'bg-green-100 text-green-800', failed: 'bg-red-100 text-red-700',
  received: 'bg-blue-100 text-blue-800', processing: 'bg-yellow-100 text-yellow-800',
  duplicate: 'bg-gray-100 text-gray-500', test: 'bg-amber-100 text-amber-700',
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-4xl">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Event Triggers</h1>
        <p class="text-sm text-gray-500 mt-0.5">When an event fires, these rules decide what happens</p>
      </div>
      <button @click="router.push('/event-triggers/create')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
        New trigger
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 py-12 text-center">Loading…</div>

    <template v-else>
      <!-- Triggers -->
      <div v-if="triggers.length === 0" class="text-center py-16 text-gray-400 mb-8">
        <p class="text-lg">No triggers configured</p>
        <p class="text-sm mt-1">Create a trigger to define what happens when events arrive</p>
      </div>

      <div v-else class="space-y-3 mb-8">
        <div v-for="t in triggers" :key="t.uuid" class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center justify-between" :class="!t.is_active ? 'opacity-60' : ''">
          <div>
            <p class="font-medium text-gray-900">{{ t.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
              Event: <span class="font-medium">{{ t.event_type }}</span>
              → Action: <span class="font-medium text-indigo-600">{{ actionLabel[t.action_type] ?? t.action_type }}</span>
              <span v-if="t.source"> · Source: {{ t.source.name }}</span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="t.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'">
              {{ t.is_active ? 'Active' : 'Paused' }}
            </span>
            <button @click="toggleTrigger(t)" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
              {{ t.is_active ? 'Pause' : 'Activate' }}
            </button>
            <button @click="router.push(`/event-triggers/${t.uuid}/edit`)" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
              Edit
            </button>
          </div>
        </div>
      </div>

      <!-- Recent event log -->
      <div class="bg-white border border-gray-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Recent Events</h2>
        <div v-if="recentLog.length === 0" class="text-xs text-gray-400">No events received yet.</div>
        <div v-else class="space-y-2">
          <div v-for="e in recentLog" :key="e.id" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <div>
              <p class="text-sm text-gray-900">{{ e.event_type }}</p>
              <p class="text-xs text-gray-400">{{ new Date(e.received_at).toLocaleString('en-IN') }}</p>
            </div>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="statusColor[e.processing_status] ?? 'bg-gray-100 text-gray-500'">
              {{ e.processing_status }}
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
