<template>
  <div class="min-h-screen bg-gray-900 flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-white">HyperLocal</h1>
        <p class="text-sm text-gray-400 mt-1">Super Admin</p>
      </div>

      <form class="bg-white rounded-2xl shadow-xl p-8 space-y-5" @submit.prevent="handleLogin">
        <div v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
          {{ error }}
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-800"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-800"
          />
        </div>

        <button
          type="submit"
          :disabled="store.loading"
          class="w-full bg-gray-900 text-white rounded-lg py-2.5 text-sm font-medium hover:bg-gray-800 disabled:opacity-50 transition-colors"
        >
          {{ store.loading ? 'Signing in…' : 'Sign in' }}
        </button>

        <div class="text-xs text-gray-400 text-center pt-1">
          <span class="font-medium">Email:</span> admin@hyperlocal.internal &nbsp;|&nbsp;
          <span class="font-medium">Password:</span> changeme123
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useSuperAdminAuthStore } from '@/stores/superAdminAuth'

const store = useSuperAdminAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')

async function handleLogin() {
  error.value = ''
  try {
    await store.login(email.value, password.value)
    router.push('/super-admin/dashboard')
  } catch (err: any) {
    error.value = err.response?.data?.message ?? 'Login failed. Please try again.'
  }
}
</script>
