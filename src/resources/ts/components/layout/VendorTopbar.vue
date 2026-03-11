<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from '../../composables/useTheme'
import { useVendorAuth } from '../../composables/useVendorAuth'

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

const userMenuOpen = ref(false)
const menuWrapperRef = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)

const vendorNameSafe = computed(() => props.vendorName || 'Vendor')
const vendorEmailSafe = computed(() => props.vendorEmail || '')

const initials = computed(() => {
  if (!vendorNameSafe.value) return 'VD'
  return vendorNameSafe.value
    .split(' ')
    .filter(Boolean)
    .map((p) => p.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase()
})

function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value
}

function closeUserMenu() {
  userMenuOpen.value = false
}

async function handleLogout() {
  try {
    await auth.logout()
  } finally {
    closeUserMenu()
    // route to your login page (you currently use "/")
    await router.push('/')
  }
}

// Close menu on outside click or ESC
function onDocClick(e: MouseEvent) {
  if (!userMenuOpen.value) return
  const target = e.target as Node
  const wrapper = menuWrapperRef.value
  if (wrapper && !wrapper.contains(target)) closeUserMenu()
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') closeUserMenu()
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>
 <template>
  <header
    class="sticky top-0 z-30 w-full border-b border-slate-200/70 dark:border-slate-800/80 bg-white/90 dark:bg-slate-950/90 backdrop-blur-xl"
  >
    <div class="flex items-center justify-between gap-3 px-3 sm:px-5 h-14 sm:h-16">
      <!-- LEFT: Brand + Hamburger -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <!-- NEW: Hamburger (MOBILE – opens slide-in sidebar) -->
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

        <!-- EXISTING: Hamburger (DESKTOP/TABLET – collapse/expand) -->
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
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-full bg-white/70 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700 px-2 py-1 text-[10px] font-medium text-slate-500 dark:text-slate-300 shadow-sm"
          >
            <span class="hidden lg:inline">Search</span>
            <span
              class="px-1 rounded-md bg-slate-100 dark:bg-slate-900 border border-slate-200/80 dark:border-slate-700 text-[9px] font-semibold tracking-wide"
            >
              ⌘K
            </span>
          </button>
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
        <button
          type="button"
          class="relative inline-flex items-center justify-center h-9 w-9 rounded-full border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-900/90 text-slate-500 dark:text-slate-300 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
        >
          <i class="fa-regular fa-bell text-sm"></i>
          <span
            class="absolute -top-0.5 -right-0.5 h-4 min-w-[16px] rounded-full bg-rose-500 text-[9px] font-semibold text-white flex items-center justify-center px-[3px] shadow-sm"
          >
            3
          </span>
        </button>

        <!-- User dropdown wrapper -->
        <div class="relative" ref="menuWrapperRef">
          <!-- Trigger -->
          <button
            ref="triggerRef"
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
            <div class="hidden sm:flex flex-col items-start leading-tight max-w-[140px]">
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
              ref="menuRef"
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
