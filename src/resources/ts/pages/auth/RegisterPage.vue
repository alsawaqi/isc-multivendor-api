<!-- resources/ts/pages/auth/RegisterPage.vue -->
<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from '../../composables/useTheme'
import { useVendorAuth } from '../../composables/useVendorAuth'

const router = useRouter()
const auth = useVendorAuth()

const year = computed(() => new Date().getFullYear())

const form = reactive({
  company_name: '',
  email: '',
  phone: '',
  cr_number: '',
  vat_number: '',
  business_type: '',
  password: '',
  password_confirmation: '',
})

const businessTypes = [
  'Retailer',
  'Distributor',
  'Wholesaler',
  'Manufacturer',
  'Importer',
  'Services',
  'Other',
]

const showPassword = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const submitted = ref(false)

// Registration document uploads (all optional)
const documentFields = [
  { key: 'cr_document', label: 'Commercial Registration (CR)' },
  { key: 'chamber_of_commerce_document', label: 'Chamber of Commerce' },
  { key: 'activity_license_document', label: 'Activity License' },
  { key: 'rent_contract_document', label: 'Rent Contract' },
] as const

type DocKey = (typeof documentFields)[number]['key']
const files = reactive<Record<DocKey, File | null>>({
  cr_document: null,
  chamber_of_commerce_document: null,
  activity_license_document: null,
  rent_contract_document: null,
})

function onFile(key: DocKey, e: Event) {
  const target = e.target as HTMLInputElement
  files[key] = target.files && target.files.length ? target.files[0] : null
}

const { isDark, toggleTheme } = useTheme()

const handleSubmit = async () => {
  isSubmitting.value = true
  errorMessage.value = null

  if (form.password !== form.password_confirmation) {
    errorMessage.value = 'Passwords do not match.'
    isSubmitting.value = false
    return
  }

  try {
    const fd = new FormData()
    fd.append('company_name', form.company_name)
    fd.append('email', form.email)
    fd.append('phone', form.phone)
    if (form.cr_number) fd.append('cr_number', form.cr_number)
    if (form.vat_number) fd.append('vat_number', form.vat_number)
    if (form.business_type) fd.append('business_type', form.business_type)
    fd.append('password', form.password)
    fd.append('password_confirmation', form.password_confirmation)

    for (const { key } of documentFields) {
      if (files[key]) fd.append(key, files[key] as File)
    }

    await auth.register(fd)
    submitted.value = true
  } catch (error: any) {
    const errors = error?.response?.data?.errors
    errorMessage.value =
      error?.response?.data?.message ||
      (errors ? (Object.values(errors)[0] as string[])?.[0] : null) ||
      error?.message ||
      'Registration failed. Please review your details and try again.'
  } finally {
    isSubmitting.value = false
  }
}

const goToLogin = () => router.push('/')
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

    <!-- Main card -->
    <div
      class="relative z-10 w-full max-w-3xl md:max-w-4xl mx-4 my-8 rounded-3xl bg-white/90 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-700/60 shadow-soft backdrop-blur-xl overflow-hidden animate-card-pop transition-colors duration-300"
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
              Become a partner 🚀
            </h2>
            <p class="text-sm text-white/80">
              Register your business to start selling on ISC. Our team will review
              your application and activate your account.
            </p>
          </div>

          <div class="flex items-center gap-2 text-[11px] text-white/70">
            <span class="h-[1px] flex-1 bg-white/20"></span>
            <span>Approval required before sign in</span>
            <span class="h-[1px] flex-1 bg-white/20"></span>
          </div>
        </div>

        <!-- Right form side -->
        <div class="p-8 sm:p-10 bg-white/95 dark:bg-slate-900/80 transition-colors duration-300">
          <!-- Success state -->
          <div v-if="submitted" class="flex flex-col items-center text-center gap-4 py-6">
            <div class="h-14 w-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl">
              ✓
            </div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">
              Registration submitted
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm">
              Thanks! Your registration is now <strong>pending admin approval</strong>.
              You'll be able to sign in with your email and password once an ISC
              admin approves your account.
            </p>
            <button
              type="button"
              @click="goToLogin"
              class="mt-2 inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-primary-600 to-sky-500 px-6 py-2.5 text-sm font-semibold text-white shadow-md hover:shadow-lg transition-all"
            >
              Back to sign in
            </button>
          </div>

          <!-- Form state -->
          <template v-else>
            <div class="mb-6">
              <p
                class="text-xs font-medium tracking-[0.18em] uppercase text-primary-500 dark:text-primary-400"
              >
                Create account
              </p>
              <h1
                class="mt-1 text-xl sm:text-2xl font-semibold text-slate-900 dark:text-slate-50"
              >
                Vendor Registration
              </h1>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Submit your business details. Approval is required before you can sign in.
              </p>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
              <!-- Company name -->
              <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                  Company / Vendor name <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.company_name"
                  type="text"
                  required
                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                  placeholder="Your business name"
                />
              </div>

              <div class="grid sm:grid-cols-2 gap-4">
                <!-- Email -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Email <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    placeholder="you@company.com"
                  />
                </div>

                <!-- Phone -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Phone <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.phone"
                    type="text"
                    required
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    placeholder="+966 5x xxx xxxx"
                  />
                </div>

                <!-- CR Number -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Commercial Registration (CR) No.
                  </label>
                  <input
                    v-model="form.cr_number"
                    type="text"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    placeholder="CR number"
                  />
                </div>

                <!-- VAT Number -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    VAT Number
                  </label>
                  <input
                    v-model="form.vat_number"
                    type="text"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    placeholder="VAT number"
                  />
                </div>
              </div>

              <!-- Business type -->
              <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                  Business type
                </label>
                <select
                  v-model="form.business_type"
                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                >
                  <option value="">-- Select business type --</option>
                  <option v-for="t in businessTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>

              <div class="grid sm:grid-cols-2 gap-4">
                <!-- Password -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Password <span class="text-red-500">*</span>
                  </label>
                  <div
                    class="flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500"
                  >
                    <input
                      v-model="form.password"
                      :type="showPassword ? 'text' : 'password'"
                      autocomplete="new-password"
                      required
                      minlength="8"
                      class="w-full bg-transparent text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none"
                      placeholder="At least 8 characters"
                    />
                    <button
                      type="button"
                      @click="showPassword = !showPassword"
                      class="text-slate-500 dark:text-slate-400 hover:text-slate-800 text-xs font-medium px-1.5 py-1 rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80"
                    >
                      {{ showPassword ? 'Hide' : 'Show' }}
                    </button>
                  </div>
                </div>

                <!-- Confirm password -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Confirm password <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    placeholder="Re-enter password"
                  />
                </div>
              </div>

              <!-- Documents -->
              <div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                  Documents
                  <span class="font-normal text-slate-400">(optional — PDF or image, max 5MB each)</span>
                </p>
                <div class="grid sm:grid-cols-2 gap-3">
                  <div v-for="doc in documentFields" :key="doc.key">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ doc.label }}</label>
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png,.webp"
                      @change="onFile(doc.key, $event)"
                      class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100"
                    />
                  </div>
                </div>
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
                <span>{{ isSubmitting ? 'Submitting...' : 'Submit registration' }}</span>
              </button>

              <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                Already have an account?
                <RouterLink to="/" class="text-primary-600 dark:text-primary-400 font-medium hover:underline">
                  Sign in
                </RouterLink>
              </p>
            </form>
          </template>

          <div
            class="mt-6 text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between"
          >
            <span>© {{ year }} ISC Multi-Vendor</span>
            <span class="hidden sm:inline-flex items-center gap-1">
              <span class="h-1 w-1 rounded-full bg-emerald-400 animate-pulse"></span>
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

@media (prefers-reduced-motion: reduce) {
  .animate-card-pop,
  .blob {
    animation: none !important;
    transition: none !important;
    transform: none !important;
  }
}
</style>
