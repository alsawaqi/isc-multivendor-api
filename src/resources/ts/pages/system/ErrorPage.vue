<template>
  <div class="relative min-h-screen flex items-center justify-center overflow-hidden bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <!-- Background -->
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_#ef444466,_transparent_55%),_radial-gradient(circle_at_bottom,_#0ea5e944,_transparent_55%)]"></div>
    <div class="pointer-events-none absolute inset-0 opacity-35 mix-blend-soft-light bg-[linear-gradient(to_right,_rgba(148,163,184,0.18)_1px,_transparent_1px),linear-gradient(to_bottom,_rgba(148,163,184,0.18)_1px,_transparent_1px)] bg-[size:36px_36px]"></div>

    <div class="relative z-10 w-full max-w-xl mx-4 rounded-3xl bg-white/90 dark:bg-slate-900/75 border border-slate-200/80 dark:border-slate-700/60 shadow-soft backdrop-blur-xl overflow-hidden">
      <div class="p-8 sm:p-10">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-medium tracking-[0.18em] uppercase text-rose-500">
              Something went wrong
            </p>
            <h1 class="mt-2 text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-slate-50">
              We couldn’t complete your request
            </h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
              Try again, or go back to a safe page.
            </p>
          </div>

          <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-rose-500 to-orange-500 text-white flex items-center justify-center shadow-md shadow-rose-900/30">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200/70 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/40 p-4">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">
            Error Details
          </p>
          <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-semibold">Code:</span> {{ code }}
          </p>
          <p v-if="message" class="mt-1 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-semibold">Message:</span> {{ message }}
          </p>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row gap-3">
          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:shadow-md transition-all"
            @click="retry"
          >
            <i class="fa-solid fa-rotate-right"></i>
            Retry
          </button>

          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-sky-900/40 hover:shadow-lg transition-all"
            @click="goSafe"
          >
            <i class="fa-solid fa-shield-halved"></i>
            Go to Safe Page
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
import { useVendorAuth } from '../../composables/useVendorAuth'

const route = useRoute()
const router = useRouter()
const auth = useVendorAuth()

const year = computed(() => new Date().getFullYear())

const code = computed(() => (typeof route.query.code === 'string' ? route.query.code : 'UNKNOWN'))
const message = computed(() => (typeof route.query.message === 'string' ? route.query.message : ''))

function retry() {
  // If there is a "from" query, go back there, otherwise reload current route
  const from = typeof route.query.from === 'string' ? route.query.from : ''
  if (from) router.push(from)
  else router.go(0)
}

function goSafe() {
  if (auth.isAuthed.value) router.push({ name: 'vendor.dashboard' })
  else router.push({ name: 'vendor.login' })
}
</script>
