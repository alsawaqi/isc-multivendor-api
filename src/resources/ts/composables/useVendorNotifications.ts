import { ref, computed } from 'vue'
import api from '@/services/api'

export interface VendorNotification {
  id: string
  title: string
  message: string | null
  url: string | null
  read_at: string | null
  created_at: string | null
}

// Shared singleton state across the app (one bell)
const items = ref<VendorNotification[]>([])
const unreadCount = ref(0)
const loading = ref(false)
let pollTimer: ReturnType<typeof setInterval> | null = null

export function useVendorNotifications() {
  const notifications = computed(() => items.value)

  async function fetchNotifications() {
    loading.value = true
    try {
      const { data } = await api.get('/vendor/api/notifications')
      items.value = data.data ?? []
      unreadCount.value = data.unread_count ?? 0
    } catch {
      // stay quiet — bell just shows last known state
    } finally {
      loading.value = false
    }
  }

  async function markAllRead() {
    // optimistic
    const had = unreadCount.value
    unreadCount.value = 0
    items.value = items.value.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() }))
    try {
      await api.post('/vendor/api/notifications/mark-as-read')
    } catch {
      // revert count on failure
      unreadCount.value = had
    }
  }

  // Poll every 60s while the portal is open (no realtime broadcaster configured).
  function startPolling() {
    if (pollTimer) return
    fetchNotifications()
    pollTimer = setInterval(fetchNotifications, 60_000)
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  return {
    notifications,
    unreadCount: computed(() => unreadCount.value),
    loading: computed(() => loading.value),
    fetchNotifications,
    markAllRead,
    startPolling,
    stopPolling,
  }
}
