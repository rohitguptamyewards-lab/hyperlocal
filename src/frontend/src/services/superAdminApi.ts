/**
 * Super Admin axios instance.
 * Purpose: Separate HTTP client for super-admin API calls.
 *          Uses 'sa_token' from localStorage — NOT the merchant token.
 *          On 401, redirects to /super-admin/login, not /login.
 * Owner module: SuperAdmin
 */
import axios from 'axios'

const superAdminApi = axios.create({
  baseURL: '/api/super-admin',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

superAdminApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('sa_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

superAdminApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Only clear the SA token — do NOT hard-redirect.
      localStorage.removeItem('sa_token')

      const path = window.location.pathname
      if (path.startsWith('/super-admin') && path !== '/super-admin/login') {
        if (!(window as any).__redirectingToSALogin) {
          (window as any).__redirectingToSALogin = true
          setTimeout(() => {
            (window as any).__redirectingToSALogin = false
            window.location.href = '/super-admin/login'
          }, 100)
        }
      }
    }
    return Promise.reject(error)
  },
)

export default superAdminApi
