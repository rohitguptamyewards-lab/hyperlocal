<!--
  LoginView.vue — Email + password login form.
  Purpose: Authenticates merchant users via /api/auth/login.
  Owner module: Admin
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

const email    = ref('')
const password = ref('')
const error    = ref<string | null>(null)
const loading  = ref(false)

async function submit() {
  error.value   = null
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    // Honour ?redirect= query param (e.g. from network invite landing page)
    const redirect = route.query.redirect as string | undefined
    router.push(redirect && redirect.startsWith('/') ? redirect : '/dashboard')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Login failed. Check your credentials.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <span class="text-2xl font-bold text-gray-900">Hyperlocal</span>
        <span class="text-2xl font-light text-indigo-600"> Network</span>
        <p class="mt-2 text-sm text-gray-500">Partnership Platform</p>
      </div>

      <form @submit.prevent="submit" class="bg-white shadow rounded-xl px-8 py-8 space-y-5">
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="email"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="you@yourbrand.com"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-indigo-600 text-white rounded-lg px-4 py-2.5 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500">
        New to Hyperlocal?
        <RouterLink to="/register" class="font-medium text-indigo-600 hover:text-indigo-700">Register your brand</RouterLink>
      </p>

      <p class="mt-4 text-center text-xs text-gray-400">
        Dev: alice@brewco.com / bob@fitzone.com — password: password
      </p>
    </div>
  </div>
</template>
