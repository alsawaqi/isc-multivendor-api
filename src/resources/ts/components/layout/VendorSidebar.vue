<script setup lang="ts">
import { useRoute, RouterLink } from 'vue-router'

interface MenuItem {
  key: string
  label: string
  icon: string
  description: string
  badge?: string
  to: string
}

const props = defineProps<{
  open: boolean
  activeKey: string    // still accepted, but we don’t rely on it anymore
  collapsed?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'navigate', key: string): void
}>()

const mainItems: MenuItem[] = [
  {
    key: 'overview',
    label: 'Overview',
    icon: 'fa-solid fa-chart-pie',
    description: 'Today’s performance',
    badge: 'Live',
    to: '/dashboard',
  },
  // later:
  // {
  //   key: 'orders',
  //   label: 'Orders',
  //   icon: 'fa-solid fa-cart-shopping',
  //   description: 'Track & fulfill orders',
  //   badge: '12',
  //   to: '/orders',
  // },
  // {
  //   key: 'earnings',
  //   label: 'Earnings',
  //   icon: 'fa-solid fa-coins',
  //   description: 'Payouts & balance',
  //   to: '/earnings',
  // },
]

const secondaryItems: MenuItem[] = [
  {
    key: 'products',
    label: 'Products',
    icon: 'fa-solid fa-box-open',
    description: 'Manage catalog',
    to: '/products',
  },

  {
    key: 'viewproducts',
    label: 'View Products',
    icon: 'fa-solid fa-box-open',
    description: 'Manage catalog',
    to: '/viewproducts',
  },

  
  // {
  //   key: 'settings',
  //   label: 'Settings',
  //   icon: 'fa-solid fa-gear',
  //   description: 'Store & account',
  //   to: '/settings',
  // },
]

const route = useRoute()

const isActive = (item: MenuItem) => {
  return route.path === item.to
}
</script>

<template>
  <!-- DESKTOP SIDEBAR -->
  <aside
    class="hidden lg:flex lg:flex-col h-screen max-h-screen sticky top-0 border-r border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-soft transition-[width] duration-200"
    :class="collapsed ? 'w-20' : 'w-64 xl:w-72'"
  >
    <!-- Brand -->
    <div
      class="h-16 flex items-center gap-3 border-b border-slate-200/80 dark:border-slate-800 px-3"
      :class="collapsed ? 'justify-center' : 'px-4'"
    >
      <div
        class="h-9 w-9 rounded-2xl bg-gradient-to-br from-primary-500 to-sky-500 flex items-center justify-center text-white font-semibold shadow-md shadow-sky-900/40"
      >
        V
      </div>

      <div v-if="!collapsed" class="flex flex-col leading-tight">
        <span
          class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500"
        >
          Vendor Portal
        </span>
        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
          ISC Multi-Vendor
        </span>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto custom-scroll py-4" :class="collapsed ? 'px-2' : 'px-3'">
      <p
        v-if="!collapsed"
        class="px-2 mb-2 text-[10px] uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500"
      >
        Overview
      </p>

      <!-- MAIN ITEMS (DESKTOP) -->
      <RouterLink
        v-for="item in mainItems"
        :key="item.key"
        :to="item.to"
        class="group w-full flex items-center rounded-xl text-left text-sm transition-all duration-150 mb-1.5"
        :class="[
          collapsed ? 'px-2 py-2.5 justify-center' : 'px-3 py-2.5 gap-3',
          isActive(item)
            ? 'bg-primary-50 dark:bg-slate-800/80 text-primary-700 dark:text-slate-50 shadow-[0_0_0_1px_rgba(37,99,235,0.12)]'
            : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900/70 hover:text-slate-800 dark:hover:text-slate-100'
        ]"
        @click="emit('navigate', item.key)"
      >
        <!-- Icon -->
        <span
          class="flex items-center justify-center h-9 w-9 rounded-xl border text-[15px] transition-all duration-150"
          :class="isActive(item)
            ? 'border-primary-400 bg-white text-primary-600 shadow-sm shadow-primary-500/20 dark:bg-slate-900'
            : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 group-hover:border-primary-400/80 group-hover:text-primary-500 group-hover:-translate-y-0.5'
          "
        >
          <i :class="item.icon"></i>
        </span>

        <!-- Text -->
        <div v-if="!collapsed" class="flex flex-col min-w-0">
          <span
            class="font-medium truncate"
            :class="isActive(item) ? 'text-slate-900 dark:text-slate-50' : ''"
          >
            {{ item.label }}
          </span>
          <span class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
            {{ item.description }}
          </span>
        </div>

        <!-- Badge -->
        <span
          v-if="item.badge && !collapsed"
          class="ml-auto inline-flex items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-semibold"
        >
          {{ item.badge }}
        </span>
      </RouterLink>

      <!-- SECONDARY (DESKTOP) -->
      <div class="mt-4 pt-3 border-t border-slate-200/80 dark:border-slate-800">
        <p
          v-if="!collapsed"
          class="px-2 mb-2 text-[10px] uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500"
        >
          Management
        </p>

        <RouterLink
          v-for="item in secondaryItems"
          :key="item.key"
          :to="item.to"
          class="group w-full flex items-center rounded-xl text-left text-sm transition-all duration-150 mb-1.5"
          :class="[
            collapsed ? 'px-2 py-2.5 justify-center' : 'px-3 py-2.5 gap-3',
            isActive(item)
              ? 'bg-primary-50 dark:bg-slate-800/80 text-primary-700 dark:text-slate-50 shadow-[0_0_0_1px_rgba(37,99,235,0.12)]'
              : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900/70 hover:text-slate-800 dark:hover:text-slate-100'
          ]"
          @click="emit('navigate', item.key)"
        >
          <span
            class="flex items-center justify-center h-9 w-9 rounded-xl border text-[15px] transition-all duration-150"
            :class="isActive(item)
              ? 'border-primary-400 bg-white text-primary-600 shadow-sm shadow-primary-500/20 dark:bg-slate-900'
              : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 group-hover:border-primary-400/80 group-hover:text-primary-500 group-hover:-translate-y-0.5'
            "
          >
            <i :class="item.icon"></i>
          </span>

          <div v-if="!collapsed" class="flex flex-col min-w-0">
            <span
              class="font-medium truncate"
              :class="isActive(item) ? 'text-slate-900 dark:text-slate-50' : ''"
            >
              {{ item.label }}
            </span>
            <span class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
              {{ item.description }}
            </span>
          </div>
        </RouterLink>
      </div>
    </nav>

    <!-- FOOTER -->
    <div
      class="px-3 py-3 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/95 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400"
      :class="collapsed ? 'justify-center' : ''"
    >
      <div class="flex items-center gap-2">
        <span class="relative flex h-2.5 w-2.5">
          <span
            class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-70 animate-ping"
          ></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
        </span>
        <span v-if="!collapsed">Connected</span>
      </div>

      <span v-if="!collapsed" class="text-slate-400">
        v1.0.0
      </span>
    </div>
  </aside>

  <!-- MOBILE SIDEBAR -->
  <transition name="fade">
    <div
      v-if="open"
      class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
      @click.self="emit('close')"
    >
      <aside
        class="absolute inset-y-0 left-0 w-72 max-w-[82vw] bg-white dark:bg-slate-950 border-r border-slate-200/80 dark:border-slate-800 shadow-2xl flex flex-col animate-slide-in"
      >
        <!-- Header -->
        <div
          class="h-14 px-4 flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 bg-white/90 dark:bg-slate-950/95"
        >
          <div class="flex items-center gap-2">
            <div
              class="h-8 w-8 rounded-2xl bg-gradient-to-br from-primary-500 to-sky-500 flex items-center justify-center text-white text-sm font-semibold shadow-md"
            >
              V
            </div>
            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
              Vendor Portal
            </span>
          </div>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <!-- Nav (MOBILE) -->
        <nav class="flex-1 overflow-y-auto custom-scroll px-3 py-4 space-y-1 text-sm">
          <p
            class="px-2 mb-2 text-[10px] uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500"
          >
            Overview
          </p>

          <RouterLink
            v-for="item in [...mainItems, ...secondaryItems]"
            :key="item.key"
            :to="item.to"
            class="group w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-sm transition-all duration-150 mb-1"
            :class="isActive(item)
              ? 'bg-primary-50 dark:bg-slate-800/80 text-primary-700 dark:text-slate-50 shadow-[0_0_0_1px_rgba(37,99,235,0.12)]'
              : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900/70 hover:text-slate-800 dark:hover:text-slate-100'
            "
            @click="emit('close')"
          >
            <span
              class="flex items-center justify-center h-9 w-9 rounded-xl border text-[15px] transition-all duration-150"
              :class="isActive(item)
                ? 'border-primary-400 bg-white text-primary-600 shadow-sm shadow-primary-500/20 dark:bg-slate-900'
                : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 group-hover:border-primary-400/80 group-hover:text-primary-500 group-hover:-translate-y-0.5'
              "
            >
              <i :class="item.icon"></i>
            </span>
            <div class="flex flex-col min-w-0">
              <span class="font-medium truncate">
                {{ item.label }}
              </span>
              <span class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
                {{ item.description }}
              </span>
            </div>
            <span
              v-if="item.badge"
              class="ml-auto inline-flex items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-semibold"
            >
              {{ item.badge }}
            </span>
          </RouterLink>
        </nav>

        <!-- Footer -->
        <div
          class="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/95 text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between"
        >
          <span>ISC Multi-Vendor</span>
          <span>Vendor mode</span>
        </div>
      </aside>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.18s ease-out;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes slideIn {
  0% {
    transform: translateX(-18px);
    opacity: 0;
  }
  100% {
    transform: translateX(0);
    opacity: 1;
  }
}
.animate-slide-in {
  animation: slideIn 0.2s ease-out both;
}

/* Thin scrollbars for nav */
.custom-scroll::-webkit-scrollbar {
  width: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background-color: rgba(148, 163, 184, 0.45);
  border-radius: 999px;
}
</style>
