/**
 * Partnership store — list, detail, action state.
 * Purpose: Cache partnership data and expose mutation actions.
 * Owner module: Partnership
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export interface Partnership {
  id: string
  name: string
  scope_type: number
  offer_structure: 'same' | 'different'
  status: number
  status_label: string
  is_brand_level: boolean
  start_at: string | null
  end_at: string | null
  paused_at: string | null
  paused_reason: string | null
  created_at: string
  updated_at: string
  participants?: Participant[]
  terms?: Terms | null
  rules?: Rules | null
  agreements?: Agreement[]
  tc_version?: string
}

export interface Participant {
  merchant_id: number
  merchant_name: string | null
  outlet_id: number | null
  outlet_name: string | null
  role: number
  approval_status: number
  is_brand_wide: boolean
  is_suspended: boolean
  suspended_at: string | null
  suspension_reason: string | null
  issuing_enabled: boolean
  redemption_enabled: boolean
  campaigns_enabled: boolean
  bill_offers_enabled: boolean
  // per-participant offer (offer_structure = 'different')
  offer_pos_type:    string | null
  offer_flat_amount: number | null
  offer_percentage:  number | null
  offer_max_cap:     number | null
  offer_min_bill:    number | null
  offer_monthly_cap: number | null
  offer_filled:      boolean
}

export interface Terms {
  per_bill_cap_amount: number | null
  per_bill_cap_percent: number | null
  per_bill_cap_points: number | null
  min_bill_amount: number | null
  min_bill_points: number | null
  monthly_cap_amount: number | null
  monthly_cap_points: number | null
  rupees_per_point_at_agreement: number | null
  daily_cap_amount: number | null
  daily_cap_points: number | null
  daily_transaction_count: number | null
  outlet_daily_cap_amount: number | null
  outlet_daily_count: number | null
  outlet_per_bill_cap_amount: number | null
  lifetime_cap_amount: number | null
  lifetime_cap_points: number | null
  notify_on_limit_hit: boolean
  notify_partner_on_limit_hit: boolean
  pause_on_monthly_limit: boolean
  approval_mode: number | null
  version: number
}

export interface Agreement {
  merchant_id: number
  version: string
  accepted_at: string | null
  accepted_by: number | null
}

export interface BlackoutRule {
  type: 'date' | 'weekday'
  value: string | number[]
}

export interface TimeBandRule {
  days: number[]   // 1=Mon … 7=Sun
  from: string     // 'HH:MM'
  to: string       // 'HH:MM'
}

export interface Rules {
  customer_type_rules: Record<string, unknown> | null
  inactivity_days: number | null
  blackout_rules: BlackoutRule[] | null
  time_band_rules: TimeBandRule[] | null
  first_time_only: boolean
  uses_per_customer: number | null
  cooling_period_days: number | null
  version: number
}

export const usePartnershipStore = defineStore('partnership', () => {
  const list = ref<Partnership[]>([])
  const current = ref<Partnership | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  function setCurrent(partnership: Partnership | null): void {
    current.value = partnership

    if (!partnership) {
      return
    }

    const idx = list.value.findIndex((item) => item.id === partnership.id)
    if (idx !== -1) {
      list.value[idx] = partnership
    }
  }

  async function fetchList(status?: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = status ? { status } : {}
      const { data } = await api.get('/partnerships', { params })
      list.value = data.data
    } catch (e: unknown) {
      error.value = 'Failed to load partnerships.'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(uuid: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/partnerships/${uuid}`)
      setCurrent(data.data)
    } catch (e: unknown) {
      error.value = 'Failed to load partnership.'
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, unknown>): Promise<Partnership> {
    const { data } = await api.post('/partnerships', payload)
    list.value.unshift(data.data)
    return data.data
  }

  async function transition(uuid: string, action: string, body: Record<string, unknown> = {}): Promise<Partnership> {
    const { data } = await api.post(`/partnerships/${uuid}/${action}`, body)
    setCurrent(data.data)
    return data.data
  }

  return { list, current, loading, error, fetchList, fetchOne, create, transition, setCurrent }
})
