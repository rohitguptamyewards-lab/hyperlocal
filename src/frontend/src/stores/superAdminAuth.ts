/**
 * Super Admin auth store.
 * Purpose: Manage super admin session (token + profile), completely isolated
 *          from the merchant auth store. Uses a separate localStorage key.
 * Owner module: SuperAdmin
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import superAdminApi from '@/services/superAdminApi'

interface SuperAdminProfile {
  id: number
  name: string
  email: string
  last_login_at: string | null
}

export const useSuperAdminAuthStore = defineStore('superAdminAuth', () => {
  const token = ref<string | null>(localStorage.getItem('sa_token'))
  const admin = ref<SuperAdminProfile | null>(null)
  const loading = ref(false)

  async function login(email: string, password: string, device?: string): Promise<void> {
    loading.value = true
    try {
      const res = await superAdminApi.post('/auth/login', { email, password, device_name: device ?? 'browser' })
      token.value = res.data.token
      admin.value = res.data.admin
      localStorage.setItem('sa_token', res.data.token)
    } finally {
      loading.value = false
    }
  }

  async function fetchMe(): Promise<void> {
    const res = await superAdminApi.get('/auth/me')
    admin.value = res.data
  }

  async function logout(): Promise<void> {
    try {
      await superAdminApi.post('/auth/logout')
    } catch {
      // swallow — we clear locally regardless
    }
    token.value = null
    admin.value = null
    localStorage.removeItem('sa_token')
  }

  return { token, admin, loading, login, fetchMe, logout }
})
