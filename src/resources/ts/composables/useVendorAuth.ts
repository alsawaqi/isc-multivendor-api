import { ref, computed } from 'vue'
import api from '@/services/api'

const user = ref(null)
const initialized = ref(false)

let csrfReady: Promise<void> | null = null
async function ensureCsrf() {
  if (!csrfReady) {
    csrfReady = api.get('/sanctum/csrf-cookie').then(() => undefined).catch(() => undefined)
  }
  await csrfReady
}

export function useVendorAuth() {
  const isAuthed = computed(() => !!user.value)

  async function init() {
    if (initialized.value) return (initialized.value = true)
    initialized.value = true
    try {
      const { data } = await api.get('/vendor/auth/me')
      user.value = data.user
    } catch {
      user.value = null
    }
  }

  async function login(login: string, password: string, remember = true) {
    await ensureCsrf()
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