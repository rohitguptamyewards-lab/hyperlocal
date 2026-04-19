/**
 * Customer auth store — phone + OTP verification.
 * Purpose: Manage customer session for the rewards portal.
 * Completely isolated from merchant and super admin auth.
 * Owner module: CustomerPortal
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import customerApi from '@/services/customerApi'

interface CustomerMember {
  id: number
  name: string | null
  phone: string
}

export const useCustomerAuthStore = defineStore('customerAuth', () => {
  const token = ref<string | null>(localStorage.getItem('customer_token'))
  const member = ref<CustomerMember | null>(null)

  async function sendOtp(phone: string): Promise<string | null> {
    const { data } = await customerApi.post('/send-otp', { phone })
    return data.dev_otp ?? null // dev_otp only available in debug mode
  }

  async function verifyOtp(phone: string, otp: string): Promise<void> {
    const { data } = await customerApi.post('/verify-otp', { phone, otp })
    token.value = data.token
    member.value = data.member
    localStorage.setItem('customer_token', data.token)
  }

  function logout(): void {
    token.value = null
    member.value = null
    localStorage.removeItem('customer_token')
  }

  return { token, member, sendOtp, verifyOtp, logout }
})
