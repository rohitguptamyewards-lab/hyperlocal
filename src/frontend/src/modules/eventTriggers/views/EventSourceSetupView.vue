<!--
  EventSourceSetupView.vue — Step-by-step source connection wizard.
  Step 1: Choose type → Step 2: Name it → Step 3: Get key/URL/code snippet.
  Owner module: EventTriggers
  API: POST /api/event-sources
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()
const step = ref(1)
const form = ref({ name: '', source_type: '' })
const result = ref<{ merchant_key: string; trigger_url: string; api_url: string } | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const sourceTypes = [
  { key: 'website', label: 'Website / Thank-you page', desc: 'Add a pixel or script to your checkout success page' },
  { key: 'api', label: 'Server-to-server API', desc: 'Send signed events from your backend' },
  { key: 'shopify', label: 'Shopify', desc: 'Receive order webhooks from Shopify' },
  { key: 'woocommerce', label: 'WooCommerce', desc: 'Receive order webhooks from WooCommerce' },
  { key: 'ewrds', label: 'eWards POS', desc: 'Native eWards receipt integration' },
]

function selectType(key: string) {
  form.value.source_type = key
  form.value.name = sourceTypes.find(s => s.key === key)?.label ?? key
  step.value = 2
}

async function createSource() {
  error.value = null
  loading.value = true
  try {
    const { data } = await api.post('/event-sources', form.value)
    result.value = data
    step.value = 3
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Failed to create source.'
  } finally { loading.value = false }
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text)
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-2xl">
    <button @click="router.push('/event-sources')" class="text-sm text-gray-500 hover:text-gray-700 mb-4 flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
      Back
    </button>

    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Connect Event Source</h1>

    <!-- Step 1: Choose type -->
    <div v-if="step === 1" class="space-y-3">
      <p class="text-sm text-gray-500 mb-4">Where will events come from?</p>
      <button
        v-for="s in sourceTypes" :key="s.key"
        @click="selectType(s.key)"
        class="w-full text-left bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition-all"
      >
        <p class="font-medium text-gray-900">{{ s.label }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ s.desc }}</p>
      </button>
    </div>

    <!-- Step 2: Name it -->
    <div v-else-if="step === 2">
      <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 mb-4">{{ error }}</div>
      <form @submit.prevent="createSource" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Source name</label>
          <input v-model="form.name" required maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
        </div>
        <div class="flex gap-3">
          <button type="button" @click="step = 1" class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Back</button>
          <button type="submit" :disabled="loading" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
            {{ loading ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Step 3: Get credentials -->
    <div v-else-if="step === 3 && result" class="space-y-5">
      <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800">
        Source connected! Use the details below to start sending events.
      </div>

      <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Merchant Key</p>
          <div class="flex items-center gap-2">
            <code class="flex-1 bg-gray-50 rounded-lg px-3 py-2 text-sm font-mono text-indigo-600 break-all">{{ result.merchant_key }}</code>
            <button @click="copyToClipboard(result.merchant_key)" class="text-xs bg-gray-100 px-3 py-2 rounded-lg hover:bg-gray-200">Copy</button>
          </div>
        </div>

        <div v-if="form.source_type === 'website'">
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Pixel URL (add to thank-you page)</p>
          <code class="block bg-gray-50 rounded-lg px-3 py-2 text-xs font-mono text-gray-700 break-all">
            &lt;img src="{{ result.trigger_url }}?event=transaction_completed&amount=AMOUNT&phone=PHONE" /&gt;
          </code>
        </div>

        <div v-if="form.source_type === 'api'">
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">API Endpoint</p>
          <code class="block bg-gray-50 rounded-lg px-3 py-2 text-xs font-mono text-gray-700 break-all">
            POST {{ result.api_url }}<br/>
            Header: X-Merchant-Key: {{ result.merchant_key }}
          </code>
        </div>

        <div v-if="['shopify', 'woocommerce'].includes(form.source_type)">
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Webhook URL</p>
          <code class="block bg-gray-50 rounded-lg px-3 py-2 text-xs font-mono text-gray-700 break-all">
            {{ result.api_url.replace('/events/trigger', `/connectors/${form.source_type}/${result.merchant_key}/orders`) }}
          </code>
        </div>
      </div>

      <div class="flex gap-3">
        <button @click="router.push('/event-triggers/create')" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
          Create a trigger rule →
        </button>
        <button @click="router.push('/event-sources')" class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
          Done
        </button>
      </div>
    </div>
  </div>
</template>
