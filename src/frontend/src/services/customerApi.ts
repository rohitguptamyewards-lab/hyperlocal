/**
 * Customer Portal axios instance.
 * Purpose: Separate HTTP client for customer-facing API calls.
 *          Uses 'customer_token' from localStorage.
 *          On 401, redirects to /my-rewards (login page).
 * Owner module: CustomerPortal
 */
import axios from 'axios'

const customerApi = axios.create({
  baseURL: '/api/customer',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

customerApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('customer_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

customerApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('customer_token')

      const path = window.location.pathname
      if (path.startsWith('/my-rewards') || path.startsWith('/customer')) {
        if (!(window as any).__redirectingToCustomerLogin) {
          (window as any).__redirectingToCustomerLogin = true
          setTimeout(() => {
            (window as any).__redirectingToCustomerLogin = false
            window.location.href = '/my-rewards'
          }, 100)
        }
      }
    }
    return Promise.reject(error)
  },
)

export default customerApi
