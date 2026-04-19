<!--
  PendingApprovalView.vue — Shown to merchants whose registration is pending or rejected.
  Purpose: Block access to the app until super admin approves the brand.
  Owner module: Registration
-->
<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()

const status         = computed(() => auth.user?.merchant?.registration_status ?? 'pending')
const rejectionReason = computed(() => auth.user?.merchant?.rejection_reason ?? null)

const SUPPORT_EMAIL = 'admin@hyperlocal.internal'

function logout() {
  localStorage.removeItem('token')
  auth.token = null
  auth.user  = null
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4">

    <!-- Logo -->
    <div class="mb-8 text-center">
      <span class="text-2xl font-bold text-gray-900">Hyperlocal</span>
      <span class="text-2xl font-light text-indigo-500 ml-1">Network</span>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8 text-center">

      <!-- Pending state -->
      <template v-if="status === 'pending'">
        <!-- Icon -->
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 border border-amber-200">
          <svg class="h-8 w-8 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Approval Pending</h1>
        <p class="text-sm text-gray-500 leading-relaxed mb-6">
          Your brand registration has been received and is currently under review.
          Please wait for approval or contact our support team for any queries.
        </p>
      </template>

      <!-- Rejected state -->
      <template v-else-if="status === 'rejected'">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 border border-red-200">
          <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Registration Not Approved</h1>
        <p class="text-sm text-gray-500 leading-relaxed mb-3">
          Unfortunately your brand registration was not approved at this time.
        </p>
        <div v-if="rejectionReason" class="mb-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 text-left">
          <span class="font-semibold">Reason: </span>{{ rejectionReason }}
        </div>
        <p class="text-sm text-gray-500 mb-6" v-else>
          Please contact our support team for more information.
        </p>
      </template>

      <!-- Support contact — shown for both states -->
      <div class="rounded-xl bg-indigo-50 border border-indigo-100 px-5 py-4 mb-6">
        <p class="text-xs text-indigo-500 uppercase font-semibold tracking-wide mb-1">Support</p>
        <a :href="`mailto:${SUPPORT_EMAIL}`"
           class="text-sm font-medium text-indigo-700 hover:text-indigo-900 hover:underline break-all">
          {{ SUPPORT_EMAIL }}
        </a>
      </div>

      <!-- Sign out -->
      <button
        @click="logout"
        class="text-sm text-gray-400 hover:text-gray-600 transition-colors"
      >
        Sign out
      </button>

    </div>

    <p class="mt-6 text-xs text-gray-300">Hyperlocal Network · Merchant Platform</p>
  </div>
</template>
