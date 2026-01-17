 <template>
  <header class="mb-5 space-y-2">
    <!-- Breadcrumb -->
    <div class="flex items-center text-[11px] text-slate-500 dark:text-slate-400 gap-1.5">
      <i
        class="fa-solid fa-house-chimney text-slate-400 dark:text-slate-500 text-xs"
        aria-hidden="true"
      ></i>
      <span class="text-slate-400">Home</span>
      <span class="text-slate-300 dark:text-slate-600">›</span>
      <span class="text-slate-400">
        Vendor Portal
      </span>
      <span class="text-slate-300 dark:text-slate-600">›</span>
      <span class="font-medium text-slate-600 dark:text-slate-200">
        {{ title }}
      </span>
    </div>

    <!-- Title + optional actions -->
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
        <!-- Optional icon badge -->
        <span
          v-if="icon"
          class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-200 border border-primary-100/80 dark:border-primary-500/40 shadow-sm"
        >
          <i :class="icon" class="text-sm" aria-hidden="true"></i>
        </span>

        <h1
          class="text-xl sm:text-2xl font-semibold text-slate-900 dark:text-slate-50 truncate"
        >
          {{ title }}
        </h1>
      </div>

      <!-- Right side: page-level actions (buttons, etc.) -->
      <div class="flex items-center gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>

    <!-- Description -->
    <p
      v-if="description"
      class="text-sm text-slate-500 dark:text-slate-400"
    >
      {{ description }}
    </p>
  </header>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
interface Props {
  title: string
  description?: string
  /** Middle breadcrumb label, e.g. "E-Commerce" or "Vendor Portal" */
  section?: string
  /** Optional Font Awesome icon class for the title badge, e.g. 'fa-solid fa-receipt' */
  icon?: string
}

const props = withDefaults(defineProps<Props>(), {
  section: 'Vendor Portal',
  description: '',
  icon: '',
})


const activeKey = ref<'overview' | 'products' | 'orders' | 'earnings' | 'settings'>('overview')

const pageTitle = computed(() => {
  switch (activeKey.value) {
    case 'overview': return 'Overview & performance'
    case 'products': return 'Products catalog'
    case 'orders':   return 'Orders & fulfillment'
    case 'earnings': return 'Earnings & payouts'
    case 'settings': return 'Store settings'
    default:         return 'Vendor dashboard'
  }
})

const pageDescription = computed(() => {
  switch (activeKey.value) {
    case 'overview':
      return 'See what’s happening with your store today.'
    case 'products':
      return 'Manage your catalog, pricing and availability.'
    case 'orders':
      return 'View and manage all your customer orders.'
    case 'earnings':
      return 'Track payouts, balances and financial performance.'
    case 'settings':
      return 'Update your store profile, shipping and preferences.'
    default:
      return 'Manage your vendor account and activities.'
  }
})
</script>
