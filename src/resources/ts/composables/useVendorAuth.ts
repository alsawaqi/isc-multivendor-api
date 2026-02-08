 // resources/ts/composables/useVendorAuth.ts
import { ref, computed } from 'vue'
import api from '@/services/api'

const user = ref<any | null>(null)
const initialized = ref(false)

export function useVendorAuth() {
  const isAuthed = computed(() => !!user.value)

  async function init() {
    if (initialized.value) return
    initialized.value = true

    try {
      const { data } = await api.get('/vendor/auth/me')
      user.value = data.user
    } catch {
      user.value = null
    }
  }

  async function login(login: string, password: string, remember = true) {
    const { data } = await api.post('/vendor/auth/login', { login, password, remember })
    user.value = data.user
  }

  async function logout() {
    await api.post('/vendor/auth/logout')
    user.value = null
  }

  return { user, isAuthed, init, login, logout }
}
