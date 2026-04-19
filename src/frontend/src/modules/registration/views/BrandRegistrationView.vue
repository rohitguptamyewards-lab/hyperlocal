<!--
  BrandRegistrationView.vue — Public brand self-registration page.
  Purpose: Lets new brands sign up for the Hyperlocal Network (pending admin approval).
  Owner module: Registration
-->
<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()

const form = reactive({
  brand_name: '',
  category: '',
  city: '',
  state: '',
  gst_number: '',
  outlet_name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  password: '',
  password_confirmation: '',
})

const loading = ref(false)
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const success = ref(false)

const categories = [
  'Cafe / Restaurant',
  'Salon / Spa',
  'Gym / Fitness',
  'Retail / Fashion',
  'Electronics',
  'Grocery',
  'Healthcare / Pharmacy',
  'Education',
  'Automotive',
  'Home Services',
  'Other',
]

async function submit() {
  error.value = null
  fieldErrors.value = {}
  loading.value = true

  try {
    await api.post('/register-brand', form)
    success.value = true
  } catch (e: any) {
    if (e.response?.status === 422 && e.response?.data?.errors) {
      fieldErrors.value = e.response.data.errors
    }
    error.value = e.response?.data?.message ?? 'Registration failed. Please check your details and try again.'
  } finally {
    loading.value = false
  }
}

function fieldError(field: string): string | null {
  return fieldErrors.value[field]?.[0] ?? null
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-lg">
      <!-- Header -->
      <div class="text-center mb-8">
        <span class="text-2xl font-bold text-gray-900">Hyperlocal</span>
        <span class="text-2xl font-light text-indigo-600"> Network</span>
        <h1 class="mt-3 text-xl font-semibold text-gray-800">Join Hyperlocal Network</h1>
        <p class="mt-1 text-sm text-gray-500">Register your brand and start partnering with local businesses</p>
      </div>

      <!-- Success state -->
      <div v-if="success" class="bg-white shadow rounded-xl px-8 py-10 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Registration Submitted!</h2>
        <p class="text-sm text-gray-600 mb-6">
          Our team will review and activate your account. You will receive an email once your brand is approved.
        </p>
        <RouterLink
          to="/login"
          class="inline-block bg-indigo-600 text-white rounded-lg px-6 py-2.5 text-sm font-medium hover:bg-indigo-700 transition-colors"
        >
          Go to Sign In
        </RouterLink>
      </div>

      <!-- Registration form -->
      <form v-else @submit.prevent="submit" class="bg-white shadow rounded-xl px-8 py-8 space-y-5">
        <!-- Error banner -->
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <!-- Brand Info -->
        <div class="border-b border-gray-100 pb-4">
          <p class="text-sm font-semibold text-gray-700 mb-3">Brand Details</p>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name *</label>
              <input
                v-model="form.brand_name"
                type="text"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="e.g. Brew & Co"
              />
              <p v-if="fieldError('brand_name')" class="mt-1 text-xs text-red-600">{{ fieldError('brand_name') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
              <select
                v-model="form.category"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="" disabled>Select a category</option>
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
              </select>
              <p v-if="fieldError('category')" class="mt-1 text-xs text-red-600">{{ fieldError('category') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                <input
                  v-model="form.city"
                  type="text"
                  required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Mumbai"
                />
                <p v-if="fieldError('city')" class="mt-1 text-xs text-red-600">{{ fieldError('city') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                <input
                  v-model="form.state"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Maharashtra"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">GST Number</label>
              <input
                v-model="form.gst_number"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="22AAAAA0000A1Z5"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Outlet Name *</label>
              <input
                v-model="form.outlet_name"
                type="text"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="e.g. Main Branch - Andheri"
              />
              <p v-if="fieldError('outlet_name')" class="mt-1 text-xs text-red-600">{{ fieldError('outlet_name') }}</p>
            </div>
          </div>
        </div>

        <!-- Contact Info -->
        <div class="border-b border-gray-100 pb-4">
          <p class="text-sm font-semibold text-gray-700 mb-3">Contact Details</p>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contact Name *</label>
              <input
                v-model="form.contact_name"
                type="text"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Your full name"
              />
              <p v-if="fieldError('contact_name')" class="mt-1 text-xs text-red-600">{{ fieldError('contact_name') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
              <input
                v-model="form.contact_email"
                type="email"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="you@yourbrand.com"
              />
              <p v-if="fieldError('contact_email')" class="mt-1 text-xs text-red-600">{{ fieldError('contact_email') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
              <input
                v-model="form.contact_phone"
                type="tel"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="9876543210"
              />
              <p v-if="fieldError('contact_phone')" class="mt-1 text-xs text-red-600">{{ fieldError('contact_phone') }}</p>
            </div>
          </div>
        </div>

        <!-- Password -->
        <div>
          <p class="text-sm font-semibold text-gray-700 mb-3">Set Password</p>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
              <input
                v-model="form.password"
                type="password"
                required
                minlength="8"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
              <p v-if="fieldError('password')" class="mt-1 text-xs text-red-600">{{ fieldError('password') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                required
                minlength="8"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
          </div>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-indigo-600 text-white rounded-lg px-4 py-2.5 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ loading ? 'Submitting...' : 'Register Brand' }}
        </button>
      </form>

      <!-- Sign in link -->
      <p class="mt-6 text-center text-sm text-gray-500">
        Already registered?
        <RouterLink to="/login" class="font-medium text-indigo-600 hover:text-indigo-700">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
