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

  async function logout() {
    await ensureCsrf()
    await api.post('/vendor/auth/logout')
    user.value = null
  }

  return { user, isAuthed, init, login, logout }
}