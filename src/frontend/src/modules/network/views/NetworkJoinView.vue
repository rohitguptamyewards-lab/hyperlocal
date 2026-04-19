<!--
  NetworkJoinView.vue — Public network invite landing page.

  Accessible without login so any brand (registered or not) can see the
  network details before deciding to sign in / register.

  Flow:
    1. Load network info via GET /api/public/network-invite/{token}  (no auth)
    2. If NOT logged in  → show "Login to join" + "Register" buttons
    3. If logged in but pending/rejected → show "Account pending approval" message
    4. If logged in and approved → show "Join Network" button
       → POST /api/merchant/networks/join/{token}
    5. Success → "You've joined [Network Name]" + "View Network" link

  Owner module: Network
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

// ── Types ──────────────────────────────────────────────────────
interface NetworkPreview {
  network: {
    uuid:         string
    name:         string
    description:  string | null
    owner_name:   string
    member_count: number
  }
  invitation: {
    token:           string
    channel:         string
    remaining_uses:  number | null
    expires_at:      string | null
  }
}

// ── State ──────────────────────────────────────────────────────
const preview   = ref<NetworkPreview | null>(null)
const loading   = ref(true)
const fetchError = ref<string | null>(null)

const joining    = ref(false)
const joined     = ref(false)
const joinError  = ref<string | null>(null)
const joinedUuid = ref<string | null>(null)

// ── Computed auth state ────────────────────────────────────────
const isLoggedIn = computed(() => !!auth.token && !!auth.user)
const regStatus  = computed(() => (auth.user as any)?.merchant?.registration_status ?? null)
const isApproved = computed(() => isLoggedIn.value && regStatus.value !== 'pending' && regStatus.value !== 'rejected')
const isPending  = computed(() => isLoggedIn.value && (regStatus.value === 'pending' || regStatus.value === 'rejected'))

// ── Load network info on mount ─────────────────────────────────
onMounted(async () => {
  // If user has a token but user object not loaded yet, load it silently
  if (auth.token && !auth.user) {
    try { await auth.fetchMe() } catch { /* ignore, user just won't be logged-in state */ }
  }

  try {
    const res = await api.get<NetworkPreview>(`/public/network-invite/${route.params.token}`)
    preview.value = res.data
  } catch (err: any) {
    fetchError.value = err.response?.data?.error
      ?? 'This invitation link is invalid or has expired.'
  } finally {
    loading.value = false
  }
})

// ── Join action ────────────────────────────────────────────────
async function joinNetwork() {
  if (!preview.value) return
  joining.value  = true
  joinError.value = null
  try {
    const res = await api.post(`/merchant/networks/join/${route.params.token}`)
    joinedUuid.value = res.data.network_uuid
    joined.value = true
  } catch (err: any) {
    const errors = err.response?.data?.errors
    joinError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (err.response?.data?.message ?? 'Could not join the network. Please try again.')
  } finally {
    joining.value = false
  }
}

// ── Navigation helpers ─────────────────────────────────────────
function goLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}
function goRegister() {
  router.push({ name: 'brand-register' })
}
function viewNetwork() {
  router.push({ name: 'network-detail', params: { uuid: joinedUuid.value! } })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">

      <!-- Logo / Brand -->
      <div class="text-center mb-8">
        <span class="text-2xl font-bold text-gray-900">Hyperlocal</span>
        <span class="text-2xl font-light text-indigo-600"> Network</span>
        <p class="mt-1 text-sm text-gray-500">Partnership Platform</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="bg-white rounded-2xl shadow-xl p-10 text-center">
        <div class="inline-flex items-center gap-2 text-gray-500 text-sm">
          <svg class="animate-spin w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          Loading invitation…
        </div>
      </div>

      <!-- Invalid / expired link -->
      <div v-else-if="fetchError" class="bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Invitation unavailable</h2>
        <p class="text-sm text-gray-500">{{ fetchError }}</p>
        <button @click="goLogin" class="mt-6 px-5 py-2.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
          Go to login
        </button>
      </div>

      <!-- Success state -->
      <div v-else-if="joined" class="bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 mb-1">You've joined!</h2>
        <p class="text-sm text-gray-500 mb-6">
          Welcome to <strong>{{ preview?.network.name }}</strong>. You can now collaborate with other member brands.
        </p>
        <button
          @click="viewNetwork"
          class="w-full px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors"
        >
          View Network
        </button>
      </div>

      <!-- Main landing card -->
      <div v-else-if="preview" class="bg-white rounded-2xl shadow-xl overflow-hidden">

        <!-- Network header band -->
        <div class="bg-indigo-600 px-6 py-5 text-white">
          <p class="text-xs font-medium text-indigo-200 uppercase tracking-wide mb-1">You've been invited to join</p>
          <h1 class="text-2xl font-bold">{{ preview.network.name }}</h1>
          <p class="text-sm text-indigo-200 mt-1">
            Invited by <span class="font-medium text-white">{{ preview.network.owner_name }}</span>
          </p>
        </div>

        <!-- Network details -->
        <div class="px-6 py-5 border-b border-gray-100">
          <p v-if="preview.network.description" class="text-sm text-gray-600 leading-relaxed">
            {{ preview.network.description }}
          </p>
          <p v-else class="text-sm text-gray-400 italic">No description provided.</p>

          <!-- Stats row -->
          <div class="mt-4 flex gap-6">
            <div class="text-center">
              <p class="text-2xl font-bold text-indigo-600">{{ preview.network.member_count }}</p>
              <p class="text-xs text-gray-400 mt-0.5">Member{{ preview.network.member_count === 1 ? '' : 's' }}</p>
            </div>
          </div>
        </div>

        <!-- Benefits blurb -->
        <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100">
          <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-2">Benefits of joining</p>
          <ul class="space-y-1.5">
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Cross-promote with local partner brands
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Share customer loyalty rewards across the network
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              Run joint campaigns to grow your customer base
            </li>
          </ul>
        </div>

        <!-- CTA section -->
        <div class="px-6 py-5">

          <!-- NOT logged in -->
          <template v-if="!isLoggedIn">
            <p class="text-sm text-gray-600 mb-4 text-center">
              Sign in to your Hyperlocal account to join this network.
            </p>
            <button
              @click="goLogin"
              class="w-full py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors mb-3"
            >
              Login to join
            </button>
            <button
              @click="goRegister"
              class="w-full py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
            >
              Register your brand
            </button>
          </template>

          <!-- Logged in but account pending / rejected -->
          <template v-else-if="isPending">
            <div class="rounded-lg bg-yellow-50 border border-yellow-200 px-4 py-3 text-center">
              <p class="text-sm font-medium text-yellow-800 mb-0.5">Account pending approval</p>
              <p class="text-xs text-yellow-600">
                Your account is being reviewed. Once approved you'll be able to join networks.
              </p>
            </div>
          </template>

          <!-- Logged in and approved — show join button -->
          <template v-else-if="isApproved">
            <p v-if="joinError" class="text-sm text-red-600 mb-3 text-center">{{ joinError }}</p>
            <button
              @click="joinNetwork"
              :disabled="joining"
              class="w-full py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              {{ joining ? 'Joining…' : `Join ${preview.network.name}` }}
            </button>
            <p class="text-xs text-gray-400 text-center mt-2">
              Logged in as {{ auth.user?.name }}
            </p>
          </template>

        </div>
      </div>

    </div>
  </div>
</template>
