<!--
  ReminderSettingsView.vue — Token expiry reminder configuration.
  Purpose: Let merchants enable/disable expiry reminders, choose lead time, and customise the message.
  Owner module: Settings
  API: GET /api/reminders/settings, PUT /api/reminders/settings
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'

interface ReminderSettings {
  reminder_enabled: boolean
  remind_hours_before: number
  message_template: string | null
}

const settings = ref<ReminderSettings>({
  reminder_enabled:    false,
  remind_hours_before: 12,
  message_template:    null,
})

const loading  = ref(true)
const saving   = ref(false)
const saveOk   = ref(false)
const loadErr  = ref<string | null>(null)
const saveErr  = ref<string | null>(null)

const HOURS_OPTIONS = [3, 6, 12, 24] as const

const DEFAULT_TEMPLATE =
  `Hi! Your token {{token}} for {{partnership_name}} expires in {{hours}} hours. Redeem it before it's gone!`

onMounted(async () => {
  try {
    const { data } = await api.get<ReminderSettings>('/reminders/settings')
    settings.value = data
  } catch {
    loadErr.value = 'Could not load reminder settings. Please refresh and try again.'
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value  = false
  saveOk.value  = false
  saveErr.value = null
  saving.value  = true
  try {
    const { data } = await api.put<ReminderSettings>('/reminders/settings', {
      reminder_enabled:    settings.value.reminder_enabled,
      remind_hours_before: settings.value.remind_hours_before,
      message_template:    settings.value.message_template || null,
    })
    settings.value = data
    saveOk.value   = true
    setTimeout(() => { saveOk.value = false }, 3000)
  } catch (e: any) {
    saveErr.value = e?.response?.data?.message ?? 'Failed to save settings.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="p-6 lg:p-8 max-w-2xl">

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Token Expiry Reminders</h1>
      <p class="text-sm text-gray-400 mt-1">
        Send a WhatsApp reminder to customers before their loyalty token expires.
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-12 text-center text-sm text-gray-400">Loading settings…</div>

    <!-- Load error -->
    <div v-else-if="loadErr" class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
      {{ loadErr }}
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="save" class="space-y-6">

      <!-- Enable toggle -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-gray-900">Enable expiry reminders</p>
            <p class="text-xs text-gray-400 mt-0.5">
              When enabled, a WhatsApp message is sent to the customer before their token expires.
            </p>
          </div>
          <!-- Toggle switch -->
          <button
            type="button"
            role="switch"
            :aria-checked="settings.reminder_enabled"
            @click="settings.reminder_enabled = !settings.reminder_enabled"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            :class="settings.reminder_enabled ? 'bg-indigo-600' : 'bg-gray-200'"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition duration-200"
              :class="settings.reminder_enabled ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>
      </div>

      <!-- Lead-time dropdown -->
      <div
        class="bg-white rounded-xl border border-gray-200 p-5 transition-opacity"
        :class="settings.reminder_enabled ? 'opacity-100' : 'opacity-40 pointer-events-none'"
      >
        <label for="hours-before" class="block text-sm font-semibold text-gray-900 mb-1">
          Send reminder before expiry
        </label>
        <p class="text-xs text-gray-400 mb-3">Choose how far in advance the reminder is sent.</p>
        <select
          id="hours-before"
          v-model="settings.remind_hours_before"
          class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
          <option v-for="h in HOURS_OPTIONS" :key="h" :value="h">
            {{ h }} hour{{ h === 1 ? '' : 's' }} before expiry
          </option>
        </select>
      </div>

      <!-- Message template -->
      <div
        class="bg-white rounded-xl border border-gray-200 p-5 transition-opacity"
        :class="settings.reminder_enabled ? 'opacity-100' : 'opacity-40 pointer-events-none'"
      >
        <label for="message-template" class="block text-sm font-semibold text-gray-900 mb-1">
          Reminder message
        </label>
        <p class="text-xs text-gray-400 mb-3">
          Customise the message sent to your customers.
          Leave blank to use the default template.
          Available variables:
          <code class="bg-gray-100 px-1 rounded text-indigo-600">&#123;&#123;token&#125;&#125;</code>,
          <code class="bg-gray-100 px-1 rounded text-indigo-600">&#123;&#123;partnership_name&#125;&#125;</code>,
          <code class="bg-gray-100 px-1 rounded text-indigo-600">&#123;&#123;hours&#125;&#125;</code>
        </p>
        <textarea
          id="message-template"
          v-model="settings.message_template"
          rows="4"
          :placeholder="DEFAULT_TEMPLATE"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-none"
          maxlength="1000"
        />
        <p class="text-xs text-gray-300 mt-1 text-right">
          {{ (settings.message_template ?? '').length }} / 1000
        </p>
      </div>

      <!-- Save feedback -->
      <div v-if="saveOk" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        Settings saved successfully.
      </div>
      <div v-if="saveErr" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ saveErr }}
      </div>

      <!-- Save button -->
      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="saving"
          class="bg-indigo-600 text-white text-sm font-medium px-6 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ saving ? 'Saving…' : 'Save settings' }}
        </button>
      </div>

    </form>
  </div>
</template>
