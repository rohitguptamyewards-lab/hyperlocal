<!--
  AppLayout.vue — Sidebar + main content shell.
  Purpose: Wraps all authenticated views with nav and user context.
  Owner module: Core
  Responsive: sidebar hidden on mobile, toggled via hamburger.
-->
<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter, useRoute } from 'vue-router'
import api from '@/services/api'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const sidebarOpen = ref(false)

// Close sidebar on navigation (mobile)
watch(() => route.path, () => { sidebarOpen.value = false })

// ── Partnership alerts (notification bell) ────────────────
interface Alert {
  id: number
  type: string
  title: string
  body: string | null
  read: boolean
  created_at: string
  partnership_uuid: string | null
  partnership_name: string | null
}

const alerts         = ref<Alert[]>([])
const unreadCount    = ref(0)
const alertsOpen     = ref(false)
const alertsLoading  = ref(false)
const alertsContainerRef = ref<HTMLElement | null>(null)

async function fetchAlerts() {
  if (!auth.token) return
  try {
    const { data } = await api.get('/partnership-alerts')
    alerts.value      = data.alerts
    unreadCount.value = data.unread_count
  } catch { /* non-critical */ }
}

async function markRead(id: number) {
  await api.post(`/partnership-alerts/${id}/read`).catch(() => {})
  const a = alerts.value.find(x => x.id === id)
  if (a && !a.read) { a.read = true; unreadCount.value = Math.max(0, unreadCount.value - 1) }
}

async function markAllRead() {
  await api.post('/partnership-alerts/read-all').catch(() => {})
  alerts.value.forEach(a => { a.read = true })
  unreadCount.value = 0
}

function openAlert(a: Alert) {
  markRead(a.id)
  if (a.partnership_uuid) {
    router.push(`/partnerships/${a.partnership_uuid}`)
  }
  alertsOpen.value = false
}

// Close alerts dropdown when clicking outside it
function handleDocClick(e: MouseEvent) {
  if (alertsOpen.value && alertsContainerRef.value && !alertsContainerRef.value.contains(e.target as Node)) {
    alertsOpen.value = false
  }
}

let alertsIntervalId: ReturnType<typeof setInterval>

onMounted(() => {
  fetchAlerts()
  alertsIntervalId = setInterval(fetchAlerts, 60_000)
  document.addEventListener('click', handleDocClick, true)
})

onUnmounted(() => {
  clearInterval(alertsIntervalId)
  document.removeEventListener('click', handleDocClick, true)
})

async function handleLogout() {
  try {
    await auth.logout()
  } finally {
    await router.replace('/login')
  }
}

const navLinks = [
  { name: 'Dashboard',    to: '/dashboard',    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Partnerships', to: '/partnerships',  icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Redeem Token', to: '/redeem',        icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Find Partners', to: '/find-partners', icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
  { name: 'Campaigns',     to: '/campaigns',     icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z' },
  { name: 'Follow-ups',    to: '/followup-campaigns', icon: 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122' },
  { name: 'Offers',        to: '/partner-offers', icon: 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7' },
  { name: 'Growth',        to: '/growth',         icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
  { name: 'Triggers',      to: '/event-sources',  icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  { name: 'Networks',      to: '/networks',      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Customers',    to: '/customers',     icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
  { name: 'Settings',     to: '/settings',           exact: true,  icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Reminders',    to: '/settings/reminders', exact: false, icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
]

const roleLabel = (role: number) => ({ 1: 'Admin', 2: 'Manager', 3: 'Cashier' }[role] ?? 'User')
</script>

<template>
  <div class="flex h-screen bg-gray-100">
    <!-- Mobile overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-black/40 z-30 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 flex flex-col overflow-hidden transform transition-transform duration-200 lg:relative lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo -->
      <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
        <RouterLink to="/dashboard" class="group inline-flex items-center gap-1 min-w-0">
          <span class="text-lg font-bold text-gray-900">Hyperlocal</span>
          <span class="text-lg font-light text-indigo-600 group-hover:text-indigo-700 transition-colors">Network</span>
        </RouterLink>
        <button type="button" class="lg:hidden text-gray-400 hover:text-gray-600" @click="sidebarOpen = false">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Nav links -->
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="(link.exact ? route.path === link.to : route.path.startsWith(link.to))
            ? 'bg-indigo-50 text-indigo-700'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
          </svg>
          {{ link.name }}
        </RouterLink>
      </nav>

      <!-- Notification bell -->
      <div ref="alertsContainerRef" class="shrink-0 px-4 py-2 border-t border-gray-100 relative">
        <button
          @click="alertsOpen = !alertsOpen; if (alertsOpen) fetchAlerts()"
          class="w-full flex items-center gap-2 px-2 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors"
        >
          <span class="relative flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span
              v-if="unreadCount > 0"
              class="absolute -top-1 -right-1 min-w-[16px] h-4 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold px-0.5"
            >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </span>
          <span class="font-medium">Alerts</span>
          <span v-if="unreadCount > 0" class="ml-auto text-xs text-red-500 font-medium">{{ unreadCount }} new</span>
        </button>

        <!-- Alerts dropdown -->
        <div
          v-if="alertsOpen"
          class="absolute bottom-full left-0 right-0 mx-2 mb-1 bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-80 flex flex-col"
        >
          <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
            <p class="text-xs font-semibold text-gray-700">Partnership Alerts</p>
            <button
              v-if="unreadCount > 0"
              @click.stop="markAllRead"
              class="text-xs text-indigo-600 hover:text-indigo-800"
            >Mark all read</button>
          </div>
          <div class="overflow-y-auto flex-1">
            <div v-if="alerts.length === 0" class="px-4 py-6 text-center text-xs text-gray-400">
              No alerts yet
            </div>
            <button
              v-for="a in alerts"
              :key="a.id"
              @click="openAlert(a)"
              class="w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors"
              :class="!a.read ? 'bg-indigo-50/60' : ''"
            >
              <div class="flex items-start gap-2">
                <span
                  v-if="!a.read"
                  class="mt-1.5 w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"
                />
                <span v-else class="mt-1.5 w-2 h-2 flex-shrink-0" />
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium text-gray-900 leading-snug">{{ a.title }}</p>
                  <p v-if="a.body" class="text-xs text-gray-500 mt-0.5 line-clamp-2 leading-snug">{{ a.body }}</p>
                  <p class="text-[10px] text-gray-400 mt-1">{{ new Date(a.created_at).toLocaleDateString() }}</p>
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- User info -->
      <div class="shrink-0 px-4 py-4 border-t border-gray-200 bg-white">
        <div v-if="auth.user" class="mb-3">
          <p class="text-sm font-medium text-gray-900 truncate">{{ auth.user.name }}</p>
          <p class="text-xs text-gray-500 truncate">{{ auth.user.email }}</p>
          <span class="inline-block mt-1 text-xs bg-indigo-100 text-indigo-700 rounded px-2 py-0.5">
            {{ roleLabel(auth.user.role) }}
          </span>
        </div>
        <button
          type="button"
          @click="handleLogout"
          class="w-full text-left text-sm text-gray-500 hover:text-red-600 transition-colors"
        >
          Sign out
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Mobile top bar -->
      <div class="lg:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3">
        <button type="button" @click="sidebarOpen = true" class="text-gray-600 hover:text-gray-900">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
        <RouterLink to="/dashboard" class="inline-flex items-center gap-1 min-w-0">
          <span class="text-sm font-bold text-gray-900">Hyperlocal</span>
          <span class="text-sm font-light text-indigo-600">Network</span>
        </RouterLink>
      </div>

      <main class="flex-1 overflow-auto">
        <RouterView />
      </main>
    </div>
  </div>
</template>
