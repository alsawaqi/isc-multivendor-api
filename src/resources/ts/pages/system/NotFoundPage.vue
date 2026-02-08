<template>
  <div class="relative min-h-screen flex items-center justify-center overflow-hidden bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <!-- Background -->
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_#0ea5e966,_transparent_55%),_radial-gradient(circle_at_bottom,_#22c55e44,_transparent_55%)]"></div>
    <div class="pointer-events-none absolute inset-0 opacity-35 mix-blend-soft-light bg-[linear-gradient(to_right,_rgba(148,163,184,0.18)_1px,_transparent_1px),linear-gradient(to_bottom,_rgba(148,163,184,0.18)_1px,_transparent_1px)] bg-[size:36px_36px]"></div>

    <div class="relative z-10 w-full max-w-xl mx-4 rounded-3xl bg-white/90 dark:bg-slate-900/75 border border-slate-200/80 dark:border-slate-700/60 shadow-soft backdrop-blur-xl overflow-hidden">
      <div class="p-8 sm:p-10">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-medium tracking-[0.18em] uppercase text-primary-500 dark:text-primary-400">
              404 Not Found
            </p>
            <h1 class="mt-2 text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-slate-50">
              This page doesn’t exist
            </h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
              The link may be broken or the page may have been moved.
            </p>
          </div>

          <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-primary-600 to-sky-500 text-white flex items-center justify-center shadow-md shadow-sky-900/30">
            <span class="text-lg font-bold">?</span>
          </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200/70 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/40 p-4">
          <p class="text-xs text-slate-600 dark:text-slate-300">
            Requested path:
            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ currentPath }}</span>
          </p>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row gap-3">
          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:shadow-md transition-all"
            @click="goBack"
          >
            <i class="fa-solid fa-arrow-left"></i>
            Go Back
          </button>

          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-sky-900/40 hover:shadow-lg transition-all"
            @click="goHome"
          >
            <i class="fa-solid fa-house"></i>
            Go Home
          </button>
        </div>

        <p class="mt-6 text-[11px] text-slate-500 dark:text-slate-400">
          © {{ year }} ISC Multi-Vendor
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const year = computed(() => new Date().getFullYear())
const currentPath = computed(() => route.fullPath)

function goBack() {
  // If no history, fallback to home
  if (window.history.length > 1) router.back()
  else router.push({ name: 'vendor.login' })
}

function goHome() {
  router.push({ name: 'vendor.login' })
}
</script>
