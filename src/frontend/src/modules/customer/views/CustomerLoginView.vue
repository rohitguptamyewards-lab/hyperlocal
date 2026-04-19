<!--
  CustomerLoginView.vue — Phone + OTP login for customer rewards portal.
  Purpose: Customer enters phone, receives OTP via WhatsApp, verifies to see rewards.
  Owner module: CustomerPortal
  API: POST /api/customer/send-otp, POST /api/customer/verify-otp
-->
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCustomerAuthStore } from '@/stores/customerAuth'

const router = useRouter()
const auth   = useCustomerAuthStore()

const phone     = ref('')
const otp       = ref('')
const step      = ref<'phone' | 'otp'>('phone')
const loading   = ref(false)
const error     = ref<string | null>(null)
const devOtp    = ref<string | null>(null)

async function handleSendOtp() {
  error.value   = null
  loading.value = true
  try {
    const code = await auth.sendOtp(phone.value)
    devOtp.value = code // shown only in dev mode
    step.value = 'otp'
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Failed to send OTP. Try again.'
  } finally {
    loading.value = false
  }
}

async function handleVerifyOtp() {
  error.value   = null
  loading.value = true
  try {
    await auth.verifyOtp(phone.value, otp.value)
    router.push('/my-rewards/dashboard')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Invalid OTP. Check and try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 to-white px-4">
    <div class="w-full max-w-sm">
      <!-- Logo -->
      <div class="text-center mb-8">
        <span class="text-2xl font-bold text-gray-900">My</span>
        <span class="text-2xl font-light text-indigo-600"> Rewards</span>
        <p class="mt-2 text-sm text-gray-500">Check your loyalty points across all partner outlets</p>
      </div>

      <!-- Phone step -->
      <form v-if="step === 'phone'" @submit.prevent="handleSendOtp" class="bg-white shadow-lg rounded-2xl px-8 py-8 space-y-5">
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone number</label>
          <input
            v-model="phone"
            type="tel"
            required
            autocomplete="tel"
            placeholder="e.g. 9900001111"
            class="w-full rounded-lg border border-gray-300 px-4 py-3 text-lg tracking-wider focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
          <p class="text-xs text-gray-400 mt-1">We'll send an OTP to your WhatsApp</p>
        </div>

        <button
          type="submit"
          :disabled="loading || !phone.trim()"
          class="w-full bg-indigo-600 text-white rounded-lg px-4 py-3 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ loading ? 'Sending…' : 'Get OTP' }}
        </button>
      </form>

      <!-- OTP step -->
      <form v-else @submit.prevent="handleVerifyOtp" class="bg-white shadow-lg rounded-2xl px-8 py-8 space-y-5">
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <!-- Dev OTP hint -->
        <div v-if="devOtp" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
          Dev mode — OTP: <strong class="font-mono tracking-widest">{{ devOtp }}</strong>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Enter OTP</label>
          <input
            v-model="otp"
            type="text"
            inputmode="numeric"
            maxlength="6"
            required
            placeholder="------"
            class="w-full rounded-lg border border-gray-300 px-4 py-3 text-2xl text-center tracking-[0.5em] font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          />
          <p class="text-xs text-gray-400 mt-1">Sent to {{ phone }} via WhatsApp</p>
        </div>

        <button
          type="submit"
          :disabled="loading || otp.length !== 6"
          class="w-full bg-indigo-600 text-white rounded-lg px-4 py-3 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ loading ? 'Verifying…' : 'Verify & View Rewards' }}
        </button>

        <button
          type="button"
          @click="step = 'phone'; otp = ''; error = null"
          class="w-full text-sm text-gray-500 hover:text-gray-700 transition-colors"
        >
          Change phone number
        </button>
      </form>
    </div>
  </div>
</template>
