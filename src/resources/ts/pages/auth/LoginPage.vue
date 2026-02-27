 <!-- resources/ts/pages/auth/LoginPage.vue -->
<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useTheme } from '../../composables/useTheme'
import { useVendorAuth } from '../../composables/useVendorAuth'

const router = useRouter()
const route = useRoute()
const auth = useVendorAuth()

const year = computed(() => new Date().getFullYear())

const form = reactive({
  email: '',     // can be email OR username (we’ll pass it as "login")
  password: '',
  remember: true,
})

const showPassword = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const { isDark, toggleTheme } = useTheme()

const handleSubmit = async () => {
  isSubmitting.value = true
  errorMessage.value = null

  try {
    // IMPORTANT: we pass email field as "login" (email or username)
    await auth.login(form.email, form.password, form.remember)

    // redirect if user tried to access protected route first
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    await router.push(redirect)
  } catch (error: any) {
    errorMessage.value =
      error?.response?.data?.message ||
      error?.response?.data?.errors?.login?.[0] ||
      error?.message ||
      'Login failed. Please check your credentials.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div
    class="relative min-h-screen flex items-center justify-center overflow-hidden bg-slate-50 dark:bg-slate-950 transition-colors duration-300"
  >
    <!-- Theme toggle -->
    <button
      type="button"
      @click="toggleTheme"
      class="z-20 absolute top-4 right-4 inline-flex items-center gap-2 rounded-full border border-slate-200/70 dark:border-slate-700/70 bg-white/80 dark:bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
    >
      <span
        class="h-4 w-4 flex items-center justify-center rounded-full bg-amber-400/90 text-[10px] shadow-sm dark:hidden"
      >
        ☀️
      </span>
      <span
        class="h-4 w-4 hidden dark:flex items-center justify-center rounded-full bg-sky-500/90 text-[10px] shadow-sm"
      >
        🌙
      </span>
      <span class="hidden sm:inline">
        {{ isDark ? 'Dark mode' : 'Light mode' }}
      </span>
    </button>

    <!-- Gradient background blobs -->
    <div
      class="blob blob--top pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_#0ea5e966,_transparent_55%),_radial-gradient(circle_at_bottom,_#22c55e44,_transparent_55%)]"
    ></div>

    <!-- Subtle grid pattern -->
    <div
      class="blob blob--bottom pointer-events-none absolute inset-0 opacity-35 mix-blend-soft-light bg-[linear-gradient(to_right,_rgba(148,163,184,0.18)_1px,_transparent_1px),linear-gradient(to_bottom,_rgba(148,163,184,0.18)_1px,_transparent_1px)] bg-[size:36px_36px]"
    ></div>

    <!-- Main card (wider + animated + tilt) -->
    <div
      class="relative z-10 w-full max-w-3xl md:max-w-4xl mx-4 rounded-3xl bg-white/90 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-700/60 shadow-soft backdrop-blur-xl overflow-hidden animate-card-pop card-tilt transition-colors duration-300"
    >
      <div class="grid md:grid-cols-2">
        <!-- Left branding side -->
        <div
          class="hidden md:flex flex-col justify-between bg-gradient-to-br from-primary-500 to-sky-500 text-white p-8"
        >
          <div class="flex items-center gap-2">
            <div
              class="h-9 w-9 rounded-2xl bg-white/10 flex items-center justify-center shadow-md"
            >
              <span class="text-xl font-semibold tracking-tight">Vsss</span>
            </div>
            <div class="leading-tight">
              <p class="text-sm uppercase tracking-[0.15em] text-white/70">
                Vendor Portal
              </p>
              <p class="text-base font-semibold">
                ISC Multi-Vendor
              </p>
            </div>
          </div>

          <div class="space-y-2">
            <h2 class="text-xl font-semibold">
              Welcome back, partner 👋
            </h2>
            <p class="text-sm text-white/80">
              Manage your products, orders and earnings in a single, clean dashboard.
            </p>
          </div>

          <div class="flex items-center gap-2 text-[11px] text-white/70">
            <span class="h-[1px] flex-1 bg-white/20"></span>
            <span>Secure vendor access</span>
            <span class="h-[1px] flex-1 bg-white/20"></span>
          </div>
        </div>

        <!-- Right form side -->
        <div class="p-8 sm:p-10 bg-white/95 dark:bg-slate-900/80 transition-colors duration-300">
          <div class="mb-6">
            <p
              class="text-xs font-medium tracking-[0.18em] uppercase text-primary-500 dark:text-primary-400"
            >
              Sign in
            </p>
            <h1
              class="mt-1 text-xl sm:text-2xl font-semibold text-slate-900 dark:text-slate-50"
            >
              Vendor Login
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Use your vendor user account created by the ISC admin.
            </p>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-5">
            <!-- Email -->
            <div>
              <label
                for="email"
                class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5"
              >
                Email
              </label>
              <div
                class="flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500 transition-colors"
              >
                <span class="text-slate-400 text-lg">
                  <i class="fa-regular fa-envelope"></i>
                </span>
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  autocomplete="email"
                  required
                  class="w-full bg-transparent text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none"
                  placeholder="you@company.com"
                />
              </div>
            </div>

            <!-- Password -->
            <div>
              <label
                for="password"
                class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5"
              >
                Password
              </label>
              <div
                class="flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500 transition-colors"
              >
                <span class="text-slate-400 text-lg">
                  <i class="fa-solid fa-key"></i>
                </span>
                <input
                  id="password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  class="w-full bg-transparent text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 text-xs font-medium px-1.5 py-1 rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80 transition-colors"
                >
                  {{ showPassword ? 'Hide' : 'Show' }}
                </button>
              </div>
            </div>

            <!-- Remember + Forgot -->
            <div class="flex items-center justify-between gap-2 text-xs">
              <label
                class="inline-flex items-center gap-2 cursor-pointer select-none text-slate-700 dark:text-slate-300"
              >
                <input
                  v-model="form.remember"
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-400 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-primary-500 focus:ring-primary-500"
                />
                <span>Remember this device</span>
              </label>

              <button
                type="button"
                class="text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300 font-medium"
              >
                Forgot password?
              </button>
            </div>

            <!-- Error message -->
            <p v-if="errorMessage" class="text-xs text-red-500 flex items-center gap-2">
              <span
                class="h-4 w-4 rounded-full border border-red-400 flex items-center justify-center text-[10px]"
              >
                !
              </span>
              {{ errorMessage }}
            </p>

            <!-- Submit button -->
            <button
              type="submit"
              :disabled="isSubmitting"
              class="relative w-full inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-primary-600 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-sky-900/40 hover:shadow-lg hover:shadow-sky-900/50 transition-all disabled:opacity-60 disabled:cursor-not-allowed overflow-hidden"
            >
              <span
                v-if="isSubmitting"
                class="h-4 w-4 border-2 border-white/40 border-t-white rounded-full animate-spin mr-2"
              ></span>
              <span>{{ isSubmitting ? 'Signing in...' : 'Sign in to portal' }}</span>
            </button>
          </form>

          <div
            class="mt-6 text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between"
          >
            <span>© {{ year }} ISC Multi-Vendor</span>
            <span class="hidden sm:inline-flex items-center gap-1">
              <span
                class="h-1 w-1 rounded-full bg-emerald-400 animate-pulse"
              ></span>
              <span>Secure connection</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes card-pop {
  0% {
    opacity: 0;
    transform: translateY(24px) scale(0.96);
  }
  60% {
    opacity: 1;
    transform: translateY(0) scale(1.02);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.animate-card-pop {
  animation: card-pop 0.55s cubic-bezier(0.22, 0.61, 0.36, 1) both;
}

/* Floating blobs */
@keyframes blob-float {
  0% {
    transform: translate3d(0, 0, 0) scale(1);
    opacity: 0.92;
  }
  50% {
    transform: translate3d(0, 12px, 0) scale(1.03);
    opacity: 1;
  }
  100% {
    transform: translate3d(0, -10px, 0) scale(1.02);
    opacity: 0.96;
  }
}

.blob {
  animation: blob-float 18s ease-in-out infinite alternate;
  will-change: transform, opacity;
}

.blob--bottom {
  animation-duration: 22s;
  animation-delay: 4s;
}

/* Hover tilt */
.card-tilt {
  transform-style: preserve-3d;
  transform-origin: center;
  transition: transform 0.28s ease, box-shadow 0.28s ease;
}

.card-tilt:hover {
  transform: translateY(-6px) rotateX(3deg) rotateY(-3deg) scale(1.01);
  box-shadow: 0 32px 80px rgba(15, 23, 42, 0.85);
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .animate-card-pop,
  .blob,
  .card-tilt {
    animation: none !important;
    transition: none !important;
    transform: none !important;
  }
}
</style>
