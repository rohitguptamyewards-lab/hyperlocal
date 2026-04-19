<template>
  <div>
    <h2 class="text-xl font-semibold text-gray-900 mb-6">eWards Integration Requests</h2>

    <!-- Filter -->
    <div class="flex gap-3 mb-4">
      <select
        v-model="statusFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
        @change="fetchRequests(1)"
      >
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div v-if="loading" class="p-6 text-sm text-gray-500 text-center">Loading…</div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Merchant</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Submitted</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Notes</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="req in requests" :key="req.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ req.merchant_name }}</div>
              <div class="text-xs text-gray-500">{{ req.merchant_email }}</div>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="statusBadge(req.status).class"
              >{{ statusBadge(req.status).label }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(req.created_at) }}</td>
            <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">{{ req.notes ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="req.status === 'pending'"
                class="text-xs text-blue-600 hover:text-blue-800 underline mr-3"
                @click="openApprove(req)"
              >Approve</button>
              <button
                v-if="req.status === 'pending'"
                class="text-xs text-red-600 hover:text-red-800 underline"
                @click="openReject(req)"
              >Reject</button>
              <span v-if="req.status !== 'pending'" class="text-xs text-gray-400">{{ formatDate(req.reviewed_at) }}</span>
            </td>
          </tr>
          <tr v-if="requests.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No requests found.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button :disabled="meta.current_page === 1" class="text-xs px-3 py-1.5 rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50" @click="fetchRequests(meta.current_page - 1)">Previous</button>
          <button :disabled="meta.current_page === meta.last_page" class="text-xs px-3 py-1.5 rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50" @click="fetchRequests(meta.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="approveModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
        <h3 class="font-semibold text-gray-900 mb-1">Approve eWards Integration</h3>
        <p class="text-sm text-gray-500 mb-4">{{ approveModal.request?.merchant_name }}</p>

        <div v-if="approveModal.error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-4">{{ approveModal.error }}</div>

        <div class="space-y-3 mb-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
            <input v-model="approveModal.apiKey" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
            <input v-model="approveModal.baseUrl" type="url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Brand ID</label>
            <input v-model="approveModal.brandId" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
        </div>

        <div class="flex gap-3">
          <button class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50" @click="approveModal.open = false">Cancel</button>
          <button
            :disabled="approveModal.loading || !approveModal.apiKey || !approveModal.baseUrl || !approveModal.brandId"
            class="flex-1 bg-green-700 text-white text-sm rounded-lg py-2.5 hover:bg-green-800 disabled:opacity-50"
            @click="submitApprove"
          >{{ approveModal.loading ? 'Approving…' : 'Approve' }}</button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejectModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="font-semibold text-gray-900 mb-1">Reject Request</h3>
        <p class="text-sm text-gray-500 mb-4">{{ rejectModal.request?.merchant_name }}</p>

        <div v-if="rejectModal.error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-4">{{ rejectModal.error }}</div>

        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">Rejection reason</label>
          <textarea v-model="rejectModal.reason" rows="3" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>

        <div class="flex gap-3">
          <button class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50" @click="rejectModal.open = false">Cancel</button>
          <button
            :disabled="rejectModal.loading || !rejectModal.reason.trim()"
            class="flex-1 bg-red-600 text-white text-sm rounded-lg py-2.5 hover:bg-red-700 disabled:opacity-50"
            @click="submitReject"
          >{{ rejectModal.loading ? 'Rejecting…' : 'Reject' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import superAdminApi from '@/services/superAdminApi'

interface IntegrationRequest {
  id: number
  merchant_name: string
  merchant_email: string
  status: string
  notes: string | null
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string
}

const requests = ref<IntegrationRequest[]>([])
const loading = ref(true)
const statusFilter = ref('pending')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const approveModal = reactive({
  open: false,
  request: null as IntegrationRequest | null,
  apiKey: '',
  baseUrl: '',
  brandId: '',
  loading: false,
  error: '',
})

const rejectModal = reactive({
  open: false,
  request: null as IntegrationRequest | null,
  reason: '',
  loading: false,
  error: '',
})

async function fetchRequests(page = 1) {
  loading.value = true
  try {
    const res = await superAdminApi.get('/integration-requests', {
      params: { page, status: statusFilter.value || undefined },
    })
    requests.value = res.data.data
    meta.value = res.data.meta
  } finally {
    loading.value = false
  }
}

function openApprove(req: IntegrationRequest) {
  approveModal.request = req
  approveModal.apiKey = ''
  approveModal.baseUrl = ''
  approveModal.brandId = ''
  approveModal.error = ''
  approveModal.open = true
}

function openReject(req: IntegrationRequest) {
  rejectModal.request = req
  rejectModal.reason = ''
  rejectModal.error = ''
  rejectModal.open = true
}

async function submitApprove() {
  if (!approveModal.request) return
  approveModal.loading = true
  approveModal.error = ''
  try {
    await superAdminApi.post(`/integration-requests/${approveModal.request.id}/approve`, {
      api_key: approveModal.apiKey,
      base_url: approveModal.baseUrl,
      brand_id: approveModal.brandId,
    })
    approveModal.open = false
    await fetchRequests(meta.value.current_page)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    approveModal.error = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to approve.')
  } finally {
    approveModal.loading = false
  }
}

async function submitReject() {
  if (!rejectModal.request) return
  rejectModal.loading = true
  rejectModal.error = ''
  try {
    await superAdminApi.post(`/integration-requests/${rejectModal.request.id}/reject`, {
      reason: rejectModal.reason,
    })
    rejectModal.open = false
    await fetchRequests(meta.value.current_page)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    rejectModal.error = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to reject.')
  } finally {
    rejectModal.loading = false
  }
}

function statusBadge(status: string) {
  if (status === 'approved') return { label: 'Approved', class: 'bg-green-50 text-green-700' }
  if (status === 'pending')  return { label: 'Pending', class: 'bg-yellow-50 text-yellow-700' }
  if (status === 'rejected') return { label: 'Rejected', class: 'bg-red-50 text-red-700' }
  return { label: status, class: 'bg-gray-100 text-gray-500' }
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(() => fetchRequests())
</script>
