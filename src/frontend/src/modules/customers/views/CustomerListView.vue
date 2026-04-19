<!--
  CustomerListView.vue — Customer list + CSV upload management.
  Purpose: Shows merchant's customer database, upload modal, stats, and upload history.
  Owner module: Customer
-->
<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '@/services/api'

// ── State ──────────────────────────────────────────────────
const customers = ref<any[]>([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const loading = ref(false)

const stats = ref({ total: 0, from_uploads: 0, from_tokens: 0 })
const uploads = ref<any[]>([])

const showUploadModal = ref(false)
const uploadFile = ref<File | null>(null)
const uploading = ref(false)
const uploadResult = ref<any>(null)
const uploadError = ref<string | null>(null)

// ── API calls ──────────────────────────────────────────────
async function fetchCustomers(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/customers', { params: { page, search: search.value || undefined } })
    customers.value = data.data
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } catch (e) {
    console.error('Failed to load customers', e)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const { data } = await api.get('/customers/stats')
    stats.value = data
  } catch (e) {
    console.error('Failed to load stats', e)
  }
}

async function fetchUploads() {
  try {
    const { data } = await api.get('/customers/uploads')
    uploads.value = data.data
  } catch (e) {
    console.error('Failed to load upload history', e)
  }
}

// ── Upload handling ────────────────────────────────────────
function onFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  uploadFile.value = input.files?.[0] ?? null
  uploadResult.value = null
  uploadError.value = null
}

async function submitUpload() {
  if (!uploadFile.value) return
  uploading.value = true
  uploadResult.value = null
  uploadError.value = null

  const formData = new FormData()
  formData.append('file', uploadFile.value)

  try {
    const { data } = await api.post('/customers/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    uploadResult.value = data
    // Refresh everything
    fetchCustomers()
    fetchStats()
    fetchUploads()
  } catch (e: any) {
    uploadError.value = e.response?.data?.message ?? 'Upload failed. Please try again.'
  } finally {
    uploading.value = false
  }
}

function closeModal() {
  showUploadModal.value = false
  uploadFile.value = null
  uploadResult.value = null
  uploadError.value = null
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

function sourceLabel(source: string) {
  const map: Record<string, string> = { upload: 'CSV Upload', token_claim: 'Token Claim', manual: 'Manual' }
  return map[source] ?? source
}

function statusColor(status: string) {
  const map: Record<string, string> = {
    completed: 'bg-green-100 text-green-700',
    processing: 'bg-yellow-100 text-yellow-700',
    pending: 'bg-gray-100 text-gray-600',
    failed: 'bg-red-100 text-red-700',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

// ── Search debounce ────────────────────────────────────────
let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => fetchCustomers(1), 350)
})

// ── Init ───────────────────────────────────────────────────
onMounted(() => {
  fetchCustomers()
  fetchStats()
  fetchUploads()
})
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <h1 class="text-2xl font-bold text-gray-900">My Customers</h1>
      <button
        @click="showUploadModal = true"
        class="inline-flex items-center gap-2 bg-indigo-600 text-white rounded-lg px-4 py-2.5 text-sm font-medium hover:bg-indigo-700 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.338-2.32 3 3 0 013.07 3.853A4.494 4.494 0 0118 19.5H6.75z" />
        </svg>
        Upload CSV
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">Total Customers</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">From Uploads</p>
        <p class="mt-1 text-2xl font-semibold text-indigo-600">{{ stats.from_uploads }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">From Token Claims</p>
        <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ stats.from_tokens }}</p>
      </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
      <input
        v-model="search"
        type="text"
        placeholder="Search by name or phone..."
        class="w-full sm:w-80 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
      />
    </div>

    <!-- Customer Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-8">
      <div v-if="loading" class="p-8 text-center text-gray-400 text-sm">Loading...</div>
      <div v-else-if="customers.length === 0" class="p-8 text-center text-gray-400 text-sm">
        No customers found. Upload a CSV to get started.
      </div>
      <table v-else class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Added</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="c in customers" :key="c.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ c.name || '--' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ c.phone }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-block text-xs font-medium rounded px-2 py-0.5"
                :class="c.source === 'upload' ? 'bg-indigo-100 text-indigo-700' : c.source === 'token_claim' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
              >
                {{ sourceLabel(c.source) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(c.created_at) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-gray-200 bg-gray-50">
        <p class="text-sm text-gray-500">
          Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)
        </p>
        <div class="flex gap-2">
          <button
            :disabled="pagination.current_page <= 1"
            @click="fetchCustomers(pagination.current_page - 1)"
            class="px-3 py-1.5 text-sm rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
          >Prev</button>
          <button
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchCustomers(pagination.current_page + 1)"
            class="px-3 py-1.5 text-sm rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
          >Next</button>
        </div>
      </div>
    </div>

    <!-- Upload History -->
    <div>
      <h2 class="text-lg font-semibold text-gray-900 mb-3">Upload History</h2>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div v-if="uploads.length === 0" class="p-6 text-center text-gray-400 text-sm">
          No uploads yet.
        </div>
        <table v-else class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="u in uploads" :key="u.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ u.file_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ u.total_rows }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">{{ u.imported_count }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ u.failed_count }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-block text-xs font-medium rounded px-2 py-0.5" :class="statusColor(u.status)">
                  {{ u.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(u.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Upload Modal -->
    <Teleport to="body">
      <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeModal" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Upload Customers</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Format guide -->
          <div class="mb-4 space-y-3 text-sm">

            <!-- Required vs optional columns -->
            <div class="rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
              <p class="font-semibold text-indigo-800 mb-2">Required columns</p>
              <table class="w-full text-xs text-indigo-700">
                <thead>
                  <tr class="border-b border-indigo-200">
                    <th class="text-left pb-1 font-semibold">Column</th>
                    <th class="text-left pb-1 font-semibold">Required?</th>
                    <th class="text-left pb-1 font-semibold">Accepted header names</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-indigo-100">
                  <tr>
                    <td class="py-1 font-mono font-bold">phone</td>
                    <td class="py-1 text-red-600 font-semibold">Yes</td>
                    <td class="py-1 text-indigo-600">phone, mobile, phone_number, contact</td>
                  </tr>
                  <tr>
                    <td class="py-1 font-mono font-bold">country_code</td>
                    <td class="py-1 text-red-600 font-semibold">Yes</td>
                    <td class="py-1 text-indigo-600">country_code, code, isd</td>
                  </tr>
                  <tr>
                    <td class="py-1 font-mono">name</td>
                    <td class="py-1 text-gray-500">Optional</td>
                    <td class="py-1 text-indigo-600">name, customer_name</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Sample CSV -->
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
              <p class="font-semibold text-gray-700 mb-1.5">Sample CSV format</p>
              <pre class="text-xs text-gray-600 font-mono leading-relaxed whitespace-pre-wrap">phone,country_code,name
6294808540,91,Rahul Sharma
9820001111,91,Priya Mehta
8877665544,91,Amit Joshi</pre>
            </div>

            <!-- Rules -->
            <ul class="text-xs text-gray-500 space-y-1 pl-1">
              <li>• File format: <strong class="text-gray-700">.csv only</strong> · Max size: 5 MB</li>
              <li>• <code class="bg-gray-100 px-0.5 rounded">phone</code> — 10-digit mobile number <strong class="text-gray-700">without</strong> country code</li>
              <li>• <code class="bg-gray-100 px-0.5 rounded">country_code</code> — digits only, e.g. <strong class="text-gray-700">91</strong> for India</li>
              <li>• Spaces and dashes are stripped automatically</li>
              <li>• Duplicate numbers are <strong class="text-gray-700">skipped</strong> — no overwrite</li>
              <li>• Column headers are <strong class="text-gray-700">case-insensitive</strong></li>
            </ul>
          </div>

          <!-- File input -->
          <div class="mb-4">
            <input
              type="file"
              accept=".csv"
              @change="onFileSelect"
              class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
            />
          </div>

          <!-- Upload result -->
          <div v-if="uploadResult" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            <p class="font-medium">Upload completed!</p>
            <p>Total rows: {{ uploadResult.total_rows }} | Imported: {{ uploadResult.imported_count }} | Failed: {{ uploadResult.failed_count }}</p>
          </div>
          <div v-if="uploadError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ uploadError }}
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3">
            <button @click="closeModal" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
            <button
              @click="submitUpload"
              :disabled="!uploadFile || uploading"
              class="bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ uploading ? 'Uploading...' : 'Upload' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
