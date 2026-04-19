<template>
  <div class="p-4 sm:p-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">My Networks</h1>
        <p class="text-sm text-gray-500 mt-1">Hyperlocal brand networks you belong to.</p>
      </div>
      <button
        class="px-4 py-2.5 bg-gray-900 text-white text-sm rounded-lg hover:bg-gray-800 transition-colors"
        @click="showCreate = true"
      >Create Network</button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400">Loading…</div>

    <div v-else-if="networks.length === 0" class="bg-white border border-gray-200 rounded-2xl p-8 text-center">
      <p class="text-sm text-gray-500">You're not a member of any network yet.</p>
      <p class="text-xs text-gray-400 mt-1">Create a network or accept an invitation to get started.</p>
    </div>

    <div v-else class="space-y-3">
      <router-link
        v-for="n in networks"
        :key="n.uuid"
        :to="`/networks/${n.uuid}`"
        class="block bg-white border border-gray-200 rounded-2xl px-5 py-4 hover:border-gray-300 hover:shadow-sm transition-all"
      >
        <div class="flex items-center justify-between">
          <div>
            <div class="font-medium text-gray-900">{{ n.name }}</div>
            <div class="text-xs text-gray-500 mt-0.5">{{ n.members_count ?? 0 }} members</div>
          </div>
          <span
            class="text-xs px-2 py-0.5 rounded-full font-medium"
            :class="n.owner_merchant_id === auth.user?.merchant_id ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-500'"
          >{{ n.owner_merchant_id === auth.user?.merchant_id ? 'Owner' : 'Member' }}</span>
        </div>
        <p v-if="n.description" class="text-xs text-gray-400 mt-2 line-clamp-2">{{ n.description }}</p>
      </router-link>
    </div>

    <!-- Create network modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="font-semibold text-gray-900 mb-4">Create a Network</h3>

        <div v-if="createError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 mb-4">{{ createError }}</div>

        <div class="space-y-3 mb-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Network name</label>
            <input v-model="createForm.name" type="text" maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" placeholder="e.g. Bandra South Brands" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
            <textarea v-model="createForm.description" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
        </div>

        <div class="flex gap-3">
          <button class="flex-1 border border-gray-300 text-gray-700 text-sm rounded-lg py-2.5 hover:bg-gray-50" @click="showCreate = false">Cancel</button>
          <button
            :disabled="createLoading || !createForm.name.trim()"
            class="flex-1 bg-gray-900 text-white text-sm rounded-lg py-2.5 hover:bg-gray-800 disabled:opacity-50"
            @click="submitCreate"
          >{{ createLoading ? 'Creating…' : 'Create' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

interface Network {
  uuid: string
  name: string
  slug: string
  description: string | null
  owner_merchant_id: number
  status: number
  members_count: number | null
  created_at: string
}

const auth = useAuthStore()
const router = useRouter()
const networks = ref<Network[]>([])
const loading = ref(true)
const showCreate = ref(false)
const createLoading = ref(false)
const createError = ref('')
const createForm = reactive({ name: '', description: '' })

async function fetchNetworks() {
  loading.value = true
  try {
    const res = await api.get('/merchant/networks')
    networks.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function submitCreate() {
  createLoading.value = true
  createError.value = ''
  try {
    const res = await api.post('/merchant/networks', {
      name: createForm.name.trim(),
      description: createForm.description.trim() || null,
    })
    showCreate.value = false
    router.push(`/networks/${res.data.network.uuid}`)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    createError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Failed to create network.')
  } finally {
    createLoading.value = false
  }
}

onMounted(fetchNetworks)
</script>
