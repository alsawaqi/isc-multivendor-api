import { ref, computed } from 'vue'
import api from '@/services/api'

const user = ref<any>(null)
const initialized = ref(false)

let csrfPromise: Promise<void> | null = null
async function ensureCsrf() {
  // Sanctum: initialize CSRF cookie once per page load
  if (!csrfPromise) {
    csrfPromise = api.get('/sanctum/csrf-cookie').then(() => undefined)
  }
  await csrfPromise
}

export function useVendorAuth() {
  const isAuthed = computed(() => !!user.value)

  async function init() {
    if (initialized.value) return
    initialized.value = true

    try {
      const { data } = await api.get('/vendor/auth/me')
      user.value = data.user ?? null
    } catch {
      user.value = null
    }
  }

  async function login(login: string, password: string, remember = true) {
    await ensureCsrf() // ✅ required by Sanctum SPA flow
    const { data } = await api.post('/vendor/auth/login', { login, password, remember })
    user.value = data.user
  }

  // Public self-registration. Does NOT sign the vendor in — they must wait for
  // admin approval before they can log in. Accepts FormData (for document uploads)
  // or a plain object; axios sets the right Content-Type automatically.
  async function register(payload: FormData | Record<string, any>) {
    await ensureCsrf()
    const { data } = await api.post('/vendor/auth/register', payload)
    return data
  }

  async function logout() {
    await ensureCsrf()
    await api.post('/vendor/auth/logout')
    user.value = null
  }

  // Re-fetch the current user (e.g. after completing the profile so the
  // profile_complete gate re-evaluates).
  async function refresh() {
    try {
      const { data } = await api.get('/vendor/auth/me')
      user.value = data.user ?? null
    } catch {
      /* keep last known user */
    }
    return user.value
  }

  async function changePassword(payload: {
    current_password: string
    password: string
    password_confirmation: string
  }) {
    await ensureCsrf()
    const { data } = await api.post('/vendor/auth/change-password', payload)
    return data
  }

  return { user, isAuthed, init, login, register, logout, refresh, changePassword }
}