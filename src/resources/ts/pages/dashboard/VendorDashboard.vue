<script setup lang="ts">
import { ref, computed } from 'vue'
import VendorLayout from '../../components/layout/VendorLayout.vue'
import VendorPageHeader from '../../components/layout/VendorPageHeader.vue'
 const pageTitle = ref<string>('Dashboard')
    const pageDescription = ref<string>('Manage your vendor account and activities.')

const recentOrders = ref<Array<{ id: number; code: string; customer: string; date: string; total: string; status: string }>>([
  { id: 1, code: 'ORD-1024', customer: 'Ali Trading LLC', date: 'Today · 10:24', total: '145.000', status: 'Paid' },
  { id: 2, code: 'ORD-1023', customer: 'Global Industrial', date: 'Today · 09:18', total: '89.500', status: 'Pending' },
  { id: 3, code: 'ORD-1022', customer: 'Duqm Projects', date: 'Yesterday · 17:42', total: '320.000', status: 'Paid' },
]);


</script>
<template>

    <VendorLayout vendor-name="Acme Tools" vendor-email="tools@acme.com">

        <VendorPageHeader
          :title="pageTitle"
          :description="pageDescription"
          icon="fa-solid fa-receipt"
        />
        <!-- Stats row -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div
            class="rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 shadow-soft p-4 flex flex-col gap-2"
          >
            <div class="flex items-center justify-between">
              <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Today’s Orders
              </p>
              <span class="inline-flex items-center rounded-full bg-emerald-500/10 text-emerald-500 px-2 py-0.5 text-[10px]">
                +18%
              </span>
            </div>
            <p class="text-xl font-semibold tracking-tight">42</p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
              Compared to yesterday
            </p>
          </div>

          <div
            class="rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 shadow-soft p-4 flex flex-col gap-2"
          >
            <div class="flex items-center justify-between">
              <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Revenue (OMR)
              </p>
              <i class="fa-solid fa-coins text-amber-400"></i>
            </div>
            <p class="text-xl font-semibold tracking-tight">1,245.800</p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
              Pending settlement: <span class="font-semibold">320.500</span>
            </p>
          </div>

          <div
            class="rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 shadow-soft p-4 flex flex-col gap-2"
          >
            <div class="flex items-center justify-between">
              <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Active Products
              </p>
              <i class="fa-solid fa-box-open text-primary-500"></i>
            </div>
            <p class="text-xl font-semibold tracking-tight">128</p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
              5 awaiting approval
            </p>
          </div>

          <div
            class="rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 shadow-soft p-4 flex flex-col gap-2"
          >
            <div class="flex items-center justify-between">
              <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Rating
              </p>
              <i class="fa-solid fa-star text-yellow-400"></i>
            </div>
            <p class="text-xl font-semibold tracking-tight">4.8</p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
              Based on 231 reviews
            </p>
          </div>
        </section>

        <!-- Split layout -->
        <section class="grid gap-4 lg:grid-cols-3">
          <!-- Left: recent orders -->
          <div
            class="lg:col-span-2 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 shadow-soft p-4 sm:p-5"
          >
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">
                Recent Orders
              </h2>
              <button
                type="button"
                class="text-xs text-primary-600 dark:text-primary-400 hover:underline"
              >
                View all
              </button>
            </div>

            <div class="space-y-2 text-xs">
              <div
                v-for="order in recentOrders"
                :key="order.id"
                class="flex items-center justify-between rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 px-3 py-2"
              >
                <div class="flex flex-col">
                  <span class="font-medium text-slate-800 dark:text-slate-100">
                    #{{ order.code }}
                  </span>
                  <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    {{ order.customer }} · {{ order.date }}
                  </span>
                </div>
                <div class="flex items-center gap-4">
                  <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">
                    {{ order.total }} OMR
                  </span>
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    :class="order.status === 'Paid'
                      ? 'bg-emerald-500/10 text-emerald-500'
                      : order.status === 'Pending'
                      ? 'bg-amber-500/10 text-amber-500'
                      : 'bg-slate-500/10 text-slate-400'"
                  >
                    {{ order.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: quick actions -->
          <div
            class="rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 shadow-soft p-4 sm:p-5 flex flex-col gap-3"
          >
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">
              Quick Actions
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Shortcuts to your most common tasks.
            </p>

            <div class="space-y-2 text-xs">
              <button
                type="button"
                class="w-full inline-flex items-center justify-between rounded-xl border border-dashed border-primary-300/70 dark:border-primary-500/50 bg-primary-50/70 dark:bg-primary-500/10 px-3 py-2 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-500/15 transition-colors"
              >
                <span class="flex items-center gap-2 text-primary-700 dark:text-primary-200">
                  <i class="fa-solid fa-plus"></i>
                  <span class="font-medium">Add new product</span>
                </span>
                <span class="text-[11px] text-primary-500 dark:text-primary-200">
                  Ctrl + N
                </span>
              </button>

              <button
                type="button"
                class="w-full inline-flex items-center justify-between rounded-xl border border-slate-200/80 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/70 px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors"
              >
                <span class="flex items-center gap-2">
                  <i class="fa-solid fa-arrow-trend-up text-emerald-400"></i>
                  <span class="font-medium text-slate-700 dark:text-slate-100">
                    View earnings report
                  </span>
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                  Last 30 days
                </span>
              </button>

              <button
                type="button"
                class="w-full inline-flex items-center justify-between rounded-xl border border-slate-200/80 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/70 px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors"
              >
                <span class="flex items-center gap-2">
                  <i class="fa-solid fa-gear text-sky-400"></i>
                  <span class="font-medium text-slate-700 dark:text-slate-100">
                    Store settings
                  </span>
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                  Profile · Shipping
                </span>
              </button>
            </div>
          </div>
        </section>


  </VendorLayout>

</template>


