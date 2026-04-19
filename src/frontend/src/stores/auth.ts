/**
 * Auth store — user identity, token, login/logout.
 * Purpose: Single source of truth for authenticated user state.
 * Owner module: Admin
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: number        // 1=admin, 2=manager, 3=cashier
  merchant_id: number
  outlet_id: number | null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  async function login(email: string, password: string): Promise<void> {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('token', data.token)
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout')
    } finally {
      user.value = null
      token.value = null
      localStorage.removeItem('token')
    }
  }

  async function fetchMe(): Promise<void> {
    const { data } = await api.get('/auth/me')
    user.value = data
  }

  function isAdmin(): boolean {
    return user.value?.role === 1
  }

  function isManager(): boolean {
    return user.value?.role === 1 || user.value?.role === 2
  }

  return { user, token, login, logout, fetchMe, isAdmin, isManager }
})
