<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from '../../composables/useTheme'
import { useVendorAuth } from '../../composables/useVendorAuth'
import { useVendorNotifications } from '../../composables/useVendorNotifications'

const props = defineProps<{
  vendorName?: string
  vendorEmail?: string
  collapsed?: boolean
}>()

defineEmits<{
  (e: 'toggleSidebar'): void
  (e: 'toggleMobileSidebar'): void
}>()

const router = useRouter()
const auth = useVendorAuth()

const { isDark, toggleTheme } = useTheme()
const search = ref('')

// ---- Identity: prefer the signed-in vendor, fall back to props ----
const vendorNameSafe = computed(() =>
  auth.user.value?.vendor?.Vendor_Name ||
  auth.user.value?.User_Name ||
  props.vendorName ||
  'Vendor'
)
const vendorEmailSafe = computed(() =>
  auth.user.value?.email || props.vendorEmail || ''
)

const initials = computed(() => {
  const name = vendorNameSafe.value
  if (!name) return 'VD'
  return name
    .split(' ')
    .filter(Boolean)
    .map((p) => p.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase()
})

// ---- User menu ----
const userMenuOpen = ref(false)
const menuWrapperRef = ref<HTMLElement | null>(null)
function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value
  if (userMenuOpen.value) notifOpen.value = false
}
function closeUserMenu() {
  userMenuOpen.value = false
}

// ---- Notifications ----
const { notifications, unreadCount, markAllRead, startPolling, stopPolling, fetchNotifications } =
  useVendorNotifications()
const notifOpen = ref(false)
const notifWrapperRef = ref<HTMLElement | null>(null)

async function toggleNotif() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) {
    userMenuOpen.value = false
    await fetchNotifications()
    if (unreadCount.value > 0) await markAllRead()
  }
}
function onNotifClick(n: { url: string | null }) {
  notifOpen.value = false
  if (n.url) router.push(n.url)
}
function formatWhen(value: string | null) {
  if (!value) return ''
  const d = new Date(value)
  return isNaN(d.getTime()) ? '' : d.toLocaleString()
}

// ---- Change password modal ----
const showChangePw = ref(false)
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' })
const pwSubmitting = ref(false)
const pwError = ref<string | null>(null)
const pwSuccess = ref<string | null>(null)

function openChangePw() {
  userMenuOpen.value = false
  pwForm.value = { current_password: '', password: '', password_confirmation: '' }
  pwError.value = null
  pwSuccess.value = null
  showChangePw.value = true
}
function closeChangePw() {
  showChangePw.value = false
}
async function submitChangePw() {
  pwError.value = null
  pwSuccess.value = null
  if (pwForm.value.password !== pwForm.value.password_confirmation) {
    pwError.value = 'New passwords do not match.'
    return
  }
  pwSubmitting.value = true
  try {
    await auth.changePassword({ ...pwForm.value })
    pwSuccess.value = 'Password updated successfully.'
    pwForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e: any) {
    pwError.value =
      e?.response?.data?.errors?.current_password?.[0] ||
      e?.response?.data?.errors?.password?.[0] ||
      e?.response?.data?.message ||
      'Could not update password.'
  } finally {
    pwSubmitting.value = false
  }
}

async function handleLogout() {
  try {
    await auth.logout()
  } finally {
    closeUserMenu()
    await router.push('/')
  }
}

// ---- Outside click / ESC ----
function onDocClick(e: MouseEvent) {
  const target = e.target as Node
  if (userMenuOpen.value && menuWrapperRef.value && !menuWrapperRef.value.contains(target)) closeUserMenu()
  if (notifOpen.value && notifWrapperRef.value && !notifWrapperRef.value.contains(target)) notifOpen.value = false
}
function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    closeUserMenu()
    notifOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKeydown)
  startPolling()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKeydown)
  stopPolling()
})
</script>

<template>
  <header
    class="sticky top-0 z-30 w-full border-b border-slate-200/70 dark:border-slate-800/80 bg-white/90 dark:bg-slate-950/90 backdrop-blur-xl"
  >
    <div class="flex items-center justify-between gap-3 px-3 sm:px-5 h-14 sm:h-16">
      <!-- LEFT: Brand + Hamburger -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <!-- Hamburger (MOBILE – opens slide-in sidebar) -->
        <button
          type="button"
          class="inline-flex lg:hidden items-center justify-center h-9 w-9 rounded-xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-200 shadow-sm hover:shadow-md hover:bg-slate-50 dark:hover:bg-slate-800 transition-all"
          @click="$emit('toggleMobileSidebar')"
          aria-label="Open sidebar"
        >
          <span class="relative flex flex-col justify-center w-4 h-4 gap-[3px]">
            <span class="h-[2px] w-full rounded-full bg-current"></span>
            <span class="h-[2px] w-full rounded-full bg-current"></span>
            <span class="h-[2px] w-full rounded-full bg-current"></span>
          </span>
        </button>

        <!-- Hamburger (DESKTOP/TABLET – collapse/expand) -->
        <button
          type="button"
          class="hidden lg:inline-flex items-center justify-center h-9 w-9 rounded-xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-200 shadow-sm hover:shadow-md hover:bg-slate-50 dark:hover:bg-slate-800 transition-all lg:-ml-1"
          @click="$emit('toggleSidebar')"
          aria-label="Toggle sidebar"
        >
          <span class="relative flex flex-col justify-center w-4 h-4 gap-[3px]">
            <span
              class="h-[2px] w-full rounded-full bg-current transition-transform duration-200"
              :class="collapsed ? 'translate-y-[3px] rotate-45' : ''"
            ></span>
            <span
              class="h-[2px] w-full rounded-full bg-current transition-opacity duration-150"
              :class="collapsed ? 'opacity-0' : 'opacity-80'"
            ></span>
            <span
              class="h-[2px] w-full rounded-full bg-current transition-transform duration-200"
              :class="collapsed ? '-translate-y-[3px] -rotate-45' : ''"
            ></span>
          </span>
        </button>

        <!-- Brand -->
        <div class="hidden sm:flex items-center gap-2 min-w-0">
          <div
            class="h-8 w-8 rounded-2xl bg-gradient-to-br from-primary-500 to-sky-500 flex items-center justify-center text-white text-sm font-semibold shadow-md shadow-sky-900/40"
          >
            V
          </div>
          <div class="leading-tight hidden md:block">
            <p class="text-[11px] uppercase font-semibold tracking-[0.18em] text-slate-400 dark:text-slate-500">
              Vendor Portal
            </p>
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">
              ISC Multi-Vendor
            </p>
          </div>
        </div>
      </div>

      <!-- CENTER: Search -->
      <div class="flex-1 min-w-0 flex justify-center">
        <div
          class="hidden md:flex items-center gap-2 w-full max-w-xl px-3 py-1.5 rounded-full border border-slate-200/80 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/80 text-xs text-slate-500 dark:text-slate-400 shadow-sm focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-400 transition-all"
        >
          <i class="fa-solid fa-magnifying-glass text-slate-400 dark:text-slate-500 text-sm"></i>
          <input
            v-model="search"
            type="text"
            placeholder="Search products, orders, reports..."
            class="flex-1 bg-transparent outline-none text-[13px] text-slate-700 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500"
          />
        </div>
      </div>

      <!-- RIGHT -->
      <div class="flex items-center gap-1 sm:gap-2">
        <!-- Theme toggle -->
        <button
          type="button"
          class="inline-flex items-center justify-center h-9 w-9 rounded-full border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-900/90 text-slate-600 dark:text-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
          @click="toggleTheme"
        >
          <span v-if="isDark">🌙</span>
          <span v-else>☀️</span>
        </button>

        <!-- Notifications -->
        <div class="relative" ref="notifWrapperRef">
          <button
            type="button"
            class="relative inline-flex items-center justify-center h-9 w-9 rounded-full border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-900/90 text-slate-500 dark:text-slate-300 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
            @click="toggleNotif"
            aria-label="Notifications"
          >
            <i class="fa-regular fa-bell text-sm"></i>
            <span
              v-if="unreadCount > 0"
              class="absolute -top-0.5 -right-0.5 h-4 min-w-[16px] rounded-full bg-rose-500 text-[9px] font-semibold text-white flex items-center justify-center px-[3px] shadow-sm"
            >
              {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
          </button>

          <transition name="fade-scale">
            <div
              v-if="notifOpen"
              class="absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl shadow-lg"
            >
              <div class="flex items-center justify-between px-3 py-2 border-b border-slate-200/70 dark:border-slate-700/70 sticky top-0 bg-white/95 dark:bg-slate-900/95">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">Notifications</p>
                <button
                  v-if="notifications.length"
                  type="button"
                  class="text-[11px] font-medium text-primary-600 dark:text-primary-400 hover:underline"
                  @click="markAllRead"
                >
                  Mark all read
                </button>
              </div>

              <div v-if="!notifications.length" class="px-3 py-8 text-center text-xs text-slate-500 dark:text-slate-400">
                You have no notifications yet.
              </div>

              <button
                v-for="n in notifications"
                :key="n.id"
                type="button"
                class="w-full text-left px-3 py-2.5 border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/70 transition-colors"
                @click="onNotifClick(n)"
              >
                <div class="flex items-start gap-2">
                  <span class="mt-1 h-2 w-2 rounded-full flex-none" :class="n.read_at ? 'bg-transparent' : 'bg-rose-500'"></span>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ n.title }}</p>
                    <p v-if="n.message" class="text-[11px] text-slate-500 dark:text-slate-400">{{ n.message }}</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ formatWhen(n.created_at) }}</p>
                  </div>
                </div>
              </button>
            </div>
          </transition>
        </div>

        <!-- User dropdown wrapper -->
        <div class="relative" ref="menuWrapperRef">
          <!-- Trigger -->
          <button
            type="button"
            class="inline-flex items-center gap-2 pl-1 pr-2 py-1.5 rounded-full bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-700 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
            @click="toggleUserMenu"
            :aria-expanded="userMenuOpen ? 'true' : 'false'"
            aria-haspopup="menu"
          >
            <div
              class="h-8 w-8 rounded-full bg-gradient-to-br from-sky-500 to-primary-500 text-[13px] font-semibold text-white flex items-center justify-center"
            >
              {{ initials }}
            </div>
            <div class="hidden sm:flex flex-col items-start leading-tight max-w-[160px]">
              <span class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">
                {{ vendorNameSafe }}
              </span>
              <span class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
                {{ vendorEmailSafe }}
              </span>
            </div>

            <i
              class="fa-solid fa-chevron-down text-[10px] text-slate-500 dark:text-slate-400 ml-1 transition-transform"
              :class="userMenuOpen ? 'rotate-180' : ''"
            ></i>
          </button>

          <!-- Dropdown -->
          <transition name="fade-scale">
            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl shadow-lg overflow-hidden"
              role="menu"
            >
              <div class="px-3 py-2 border-b border-slate-200/70 dark:border-slate-700/70">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">
                  {{ vendorNameSafe }}
                </p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                  {{ vendorEmailSafe }}
                </p>
              </div>

              <button
                type="button"
                class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/70 transition-colors"
                role="menuitem"
                @click="openChangePw"
              >
                <i class="fa-solid fa-key text-slate-500 dark:text-slate-400"></i>
                <span class="font-medium">Change password</span>
              </button>

              <button
                type="button"
                class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/70 transition-colors border-t border-slate-100 dark:border-slate-800"
                role="menuitem"
                @click="handleLogout"
              >
                <i class="fa-solid fa-right-from-bracket text-slate-500 dark:text-slate-400"></i>
                <span class="font-medium">Logout</span>
              </button>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </header>

  <!-- Change password modal -->
  <transition name="fade-scale">
    <div v-if="showChangePw" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">Change password</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="closeChangePw">✕</button>
        </div>

        <form @submit.prevent="submitChangePw" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Current password</label>
            <input
              v-model="pwForm.current_password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">New password</label>
            <input
              v-model="pwForm.password"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Confirm new password</label>
            <input
              v-model="pwForm.password_confirmation"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
            />
          </div>

          <p v-if="pwError" class="text-xs text-red-500">{{ pwError }}</p>
          <p v-if="pwSuccess" class="text-xs text-emerald-600">{{ pwSuccess }}</p>

          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm text-slate-600 dark:text-slate-300" @click="closeChangePw">
              Close
            </button>
            <button
              type="submit"
              :disabled="pwSubmitting"
              class="rounded-xl bg-gradient-to-r from-primary-600 to-sky-500 px-4 py-2 text-sm font-semibold text-white shadow-md disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {{ pwSubmitting ? 'Saving...' : 'Update password' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}
.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.98);
}
</style>
