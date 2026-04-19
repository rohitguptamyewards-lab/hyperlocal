<!--
  EventTriggerFormView.vue — Create/edit trigger rule.
  Steps: event type → conditions → action → link to offer/partnership → test.
  Owner module: EventTriggers
  API: GET /api/event-constants, POST /api/event-triggers, POST /api/event-triggers/test
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const isEdit = !!route.params.uuid

const form = ref({
  name: '',
  event_source_id: null as number | null,
  event_type: 'transaction_completed',
  condition_json: { min_amount: '' as string | number },
  action_type: 'issue_offer',
  offer_id: null as number | null,
  partnership_id: null as number | null,
})

const eventTypes = ref<Record<string, string>>({})
const actionTypes = ref<Record<string, string>>({})
const sources = ref<{ id: number; name: string }[]>([])
const offers = ref<{ id: number; uuid: string; title: string }[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const testResult = ref<any>(null)

onMounted(async () => {
  const [constRes, srcRes, offerRes] = await Promise.all([
    api.get('/event-constants'),
    api.get('/event-sources'),
    api.get('/partner-offers'),
  ])
  eventTypes.value = constRes.data.event_types
  actionTypes.value = constRes.data.action_types
  sources.value = (srcRes.data.data ?? []).map((s: any) => ({ id: s.id, name: s.name }))
  offers.value = (offerRes.data.data ?? []).map((o: any) => ({ id: o.id, uuid: o.uuid, title: o.title }))

  if (isEdit) {
    try {
      const { data } = await api.get(`/event-triggers`) // fetch all, find by uuid
      const t = data.data?.find((t: any) => t.uuid === route.params.uuid)
      if (t) {
        form.value = {
          name: t.name,
          event_source_id: t.event_source_id,
          event_type: t.event_type,
          condition_json: t.condition_json ?? {},
          action_type: t.action_type,
          offer_id: t.offer_id,
          partnership_id: t.partnership_id,
        }
      }
    } catch {}
  }
})

async function submit() {
  error.value = null
  loading.value = true
  try {
    const payload = {
      ...form.value,
      condition_json: form.value.condition_json.min_amount
        ? { min_amount: Number(form.value.condition_json.min_amount) }
        : null,
    }

    if (isEdit) {
      await api.put(`/event-triggers/${route.params.uuid}`, payload)
    } else {
      await api.post('/event-triggers', payload)
    }
    router.push('/event-triggers')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Failed to save trigger.'
  } finally { loading.value = false }
}

async function runTest() {
  testResult.value = null
  if (!sources.value.length) return

  try {
    // Get a merchant key to test with
    const srcRes = await api.get('/event-sources')
    const firstSource = srcRes.data.data?.[0]
    if (!firstSource) { testResult.value = { status: 'error', message: 'No event source connected.' }; return }

    const { data } = await api.post('/event-triggers/test', {
      merchant_key: firstSource.merchant_key,
      event_type: form.value.event_type,
      amount: form.value.condition_json.min_amount || 500,
      phone: '9900001111',
    })
    testResult.value = data
  } catch (e: any) {
    testResult.value = { status: 'error', message: e.response?.data?.message ?? 'Test failed.' }
  }
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-2xl">
    <button @click="router.push('/event-triggers')" class="text-sm text-gray-500 hover:text-gray-700 mb-4 flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
      Back
    </button>

    <h1 class="text-2xl font-semibold text-gray-900 mb-6">{{ isEdit ? 'Edit trigger' : 'Create trigger' }}</h1>

    <form @submit.prevent="submit" class="space-y-5">
      <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ error }}</div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Trigger name</label>
        <input v-model="form.name" required maxlength="200" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. Issue Brew offer on orders above ₹200" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Event type</label>
          <select v-model="form.event_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Event source (optional)</label>
          <select v-model="form.event_source_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option :value="null">Any source</option>
            <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Condition: minimum amount (optional)</label>
        <input v-model="form.condition_json.min_amount" type="number" min="0" step="1" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. 200 — only trigger for bills above this" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
          <select v-model="form.action_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option v-for="(label, key) in actionTypes" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div v-if="form.action_type === 'issue_offer'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Offer to issue</label>
          <select v-model="form.offer_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option :value="null">Select offer</option>
            <option v-for="o in offers" :key="o.id" :value="o.id">{{ o.title }}</option>
          </select>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="button" @click="runTest" class="border border-amber-300 text-amber-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-50">
          Test event
        </button>
        <div class="flex-1"></div>
        <button type="button" @click="router.back()" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
        <button type="submit" :disabled="loading" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
          {{ loading ? 'Saving…' : (isEdit ? 'Update' : 'Create') }}
        </button>
      </div>

      <!-- Test result -->
      <div v-if="testResult" class="rounded-lg border px-4 py-3 text-sm" :class="testResult.status === 'accepted' || testResult.status === 'test_logged' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
        <p class="font-medium">Test: {{ testResult.status }}</p>
        <p class="text-xs mt-1">{{ testResult.message }}</p>
      </div>
    </form>
  </div>
</template>
