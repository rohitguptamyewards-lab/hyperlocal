<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Top nav -->
    <nav class="bg-gray-900 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
        <div class="flex items-center gap-6">
          <span class="font-bold text-lg tracking-tight">HyperLocal Admin</span>
          <router-link
            to="/super-admin/dashboard"
            class="text-sm text-gray-300 hover:text-white transition-colors"
            active-class="text-white font-medium"
          >Dashboard</router-link>
          <router-link
            to="/super-admin/merchants"
            class="text-sm text-gray-300 hover:text-white transition-colors"
            active-class="text-white font-medium"
          >Brands</router-link>
          <router-link
            to="/super-admin/brand-registrations"
            class="text-sm text-gray-300 hover:text-white transition-colors"
            active-class="text-white font-medium"
          >Brand Requests</router-link>
          <router-link
            to="/super-admin/requests"
            class="text-sm text-gray-300 hover:text-white transition-colors"
            active-class="text-white font-medium"
          >eWards Requests</router-link>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-400">{{ store.admin?.name }}</span>
          <button
            class="text-sm text-gray-300 hover:text-white"
            @click="handleLogout"
          >Sign out</button>
        </div>
      </div>
    </nav>

    <!-- Page content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <router-view />
    </main>
  </div>
</template>

<script setup lang="ts">
import { useSuperAdminAuthStore } from '@/stores/superAdminAuth'
import { useRouter } from 'vue-router'

const store = useSuperAdminAuthStore()
const router = useRouter()

async function handleLogout() {
  await store.logout()
  router.push('/super-admin/login')
}
</script>
