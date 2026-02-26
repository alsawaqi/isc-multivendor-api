<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type EarningsSummary = {
  total_orders: number
  gross_sales: number
  commission_total: number
  net_earnings_total: number
  paid_payout_total: number
  pending_payout_total: number
  unpaid_payout_total: number
  requested_payout_total: number
  commission_set_orders: number
  paid_orders_count: number
  requested_orders_count: number
  unpaid_orders_count: number
}


type TopSoldProduct = {
  product_id: number
  product_name?: string | null
  product_code?: string | null
  units_sold: number
  orders_count: number
  sales_total: number
}

type RecentOrder = {
  id: number
  Orders_Placed_Id: number
  Vendor_Order_Code?: string | null
  Status?: string | null
  Payout_Status?: string | null
  Sub_Total?: number
  Commission_Amount?: number
  Expected_Payout_Amount?: number
  Payout_Amount?: number | null
  Payout_At?: string | null
  created_at?: string | null
}

type ProductSummary = {
  pending?: { total?: number }
  approved?: { total?: number }
}

const loading = ref(false)
const error = ref<string | null>(null)

const dateFrom = ref("")
const dateTo = ref("")


const topSoldProduct = ref<TopSoldProduct | null>(null)

const summary = ref<EarningsSummary>({
  total_orders: 0,
  gross_sales: 0,
  commission_total: 0,
  net_earnings_total: 0,
  paid_payout_total: 0,
  pending_payout_total: 0,
  unpaid_payout_total: 0,
  requested_payout_total: 0,
  commission_set_orders: 0,
  paid_orders_count: 0,
  requested_orders_count: 0,
  unpaid_orders_count: 0,
})

const recentOrders = ref<RecentOrder[]>([])
const productSummary = ref<ProductSummary>({})

const approvedProducts = computed(() => Number(productSummary.value?.approved?.total ?? 0))
const pendingProducts = computed(() => Number(productSummary.value?.pending?.total ?? 0))

const money = (v: any) => {
  const n = Number(v ?? 0)
  return Number.isFinite(n) ? n.toFixed(3) : "0.000"
}

const fmtDate = (v?: string | null) => {
  if (!v) return "-"
  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? v : d.toLocaleString()
}

async function fetchDashboard() {
  loading.value = true
  error.value = null
  try {
    const params: any = {}
    if (dateFrom.value) params.date_from = dateFrom.value
    if (dateTo.value) params.date_to = dateTo.value

    const [earningsRes, productsRes] = await Promise.all([
      api.get("/vendor/api/earnings/summary", { params }),
      api.get("/vendor/api/products/summary"),
    ])

    summary.value = earningsRes.data?.data?.summary || summary.value
    recentOrders.value = earningsRes.data?.data?.recent_orders || []
    productSummary.value = productsRes.data || {}
    topSoldProduct.value = earningsRes.data?.data?.top_sold_product || null
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load dashboard data."
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)
</script>

<template>
  <VendorLayout>
    <VendorPageHeader
      title="Vendor Dashboard"
      description="Track your sales, commissions, payouts, and recent vendor orders."
      icon="fa-solid fa-chart-line"
    >
      <template #actions>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="dateFrom"
            type="date"
            class="h-9 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
          />
          <input
            v-model="dateTo"
            type="date"
            class="h-9 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
          />
          <button
            class="h-9 px-3 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-60"
            :disabled="loading"
            @click="fetchDashboard"
          >
            Refresh
          </button>
        </div>
      </template>
    </VendorPageHeader>

    <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      {{ error }}
    </div>

    <!-- Top cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Gross Sales</p>
        <p class="text-2xl font-semibold mt-1">{{ money(summary.gross_sales) }}</p>
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Commission Total</p>
        <p class="text-2xl font-semibold mt-1">{{ money(summary.commission_total) }}</p>
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Net Earnings</p>
        <p class="text-2xl font-semibold mt-1 text-emerald-600">{{ money(summary.net_earnings_total) }}</p>
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Pending Payouts</p>
        <p class="text-2xl font-semibold mt-1 text-amber-600">{{ money(summary.pending_payout_total) }}</p>
      </div>
    </div>

    <!-- Secondary cards -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold">Payout Status</h3>
          <RouterLink to="/payouts" class="text-xs text-primary-600 hover:underline">View payouts</RouterLink>
        </div>
        <div class="mt-3 space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500">Paid Amount</span>
            <span class="font-medium">{{ money(summary.paid_payout_total) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Unpaid Amount</span>
            <span class="font-medium">{{ money(summary.unpaid_payout_total) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Requested Amount</span>
            <span class="font-medium">{{ money(summary.requested_payout_total) }}</span>
          </div>
          <hr class="border-slate-200 dark:border-slate-800" />
          <div class="flex justify-between">
            <span class="text-slate-500">Paid Orders</span>
            <span class="font-medium">{{ summary.paid_orders_count }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Unpaid Orders</span>
            <span class="font-medium">{{ summary.unpaid_orders_count }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Requested Orders</span>
            <span class="font-medium">{{ summary.requested_orders_count }}</span>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold">Products</h3>
          <RouterLink to="/viewproducts" class="text-xs text-primary-600 hover:underline">View products</RouterLink>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3">
            <p class="text-xs text-slate-500">Approved</p>
            <p class="text-xl font-semibold">{{ approvedProducts }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3">
            <p class="text-xs text-slate-500">Pending</p>
            <p class="text-xl font-semibold">{{ pendingProducts }}</p>
          </div>
        </div>
        <div class="mt-3 text-xs text-slate-500">
          Total vendor orders: <span class="font-medium text-slate-700 dark:text-slate-200">{{ summary.total_orders }}</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold">Quick Actions</h3>
        </div>
        <div class="mt-3 grid gap-2">
          <RouterLink
            to="/orders"
            class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
          >
            <i class="fa-solid fa-cart-shopping mr-2"></i> View Orders
          </RouterLink>
          <RouterLink
            to="/payouts"
            class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
          >
            <i class="fa-solid fa-money-bill-transfer mr-2"></i> View Payouts
          </RouterLink>
          <RouterLink
            to="/viewproducts"
            class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
          >
            <i class="fa-solid fa-box-open mr-2"></i> View Products
          </RouterLink>
          <RouterLink
            to="/products"
            class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
          >
            <i class="fa-solid fa-plus mr-2"></i> Add Product
          </RouterLink>
        </div>
      </div>
    </div>


    <div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3">
  <div class="flex items-center justify-between">
    <p class="text-xs text-slate-500">Most Sold Product</p>
    <span v-if="topSoldProduct" class="text-[11px] text-emerald-600 font-medium">
      {{ topSoldProduct.units_sold }} sold
    </span>
  </div>

  <template v-if="topSoldProduct">
    <p class="text-sm font-semibold mt-1 truncate">
      {{ topSoldProduct.product_name || ("Product #" + topSoldProduct.product_id) }}
    </p>
    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
      <div class="rounded-lg bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 px-2 py-1.5">
        <p class="text-slate-500">Orders</p>
        <p class="font-semibold">{{ topSoldProduct.orders_count }}</p>
      </div>
      <div class="rounded-lg bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 px-2 py-1.5">
        <p class="text-slate-500">Sales</p>
        <p class="font-semibold">{{ money(topSoldProduct.sales_total) }}</p>
      </div>
    </div>
    <p v-if="topSoldProduct.product_code" class="mt-2 text-[11px] text-slate-500 truncate">
      Code: {{ topSoldProduct.product_code }}
    </p>
  </template>

  <p v-else class="text-xs text-slate-500 mt-1">
    No sales yet for the selected period.
  </p>
</div>

    <!-- Recent orders -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <h3 class="text-sm font-semibold">Recent Vendor Orders</h3>
        <RouterLink to="/orders" class="text-xs text-primary-600 hover:underline">See all</RouterLink>
      </div>

      <div v-if="loading" class="p-6 text-sm text-slate-500">Loading dashboard...</div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">Vendor Order</th>
              <th class="px-4 py-3 text-left">Order</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Subtotal</th>
              <th class="px-4 py-3 text-right">Commission</th>
              <th class="px-4 py-3 text-right">Net</th>
              <th class="px-4 py-3 text-left">Created</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in recentOrders"
              :key="r.id"
              class="border-t border-slate-100 dark:border-slate-800"
            >
              <td class="px-4 py-3 font-medium">{{ r.Vendor_Order_Code || ("#" + r.id) }}</td>
              <td class="px-4 py-3">#{{ r.Orders_Placed_Id }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-col gap-1">
                  <span class="text-xs rounded-full px-2 py-0.5 w-fit bg-slate-100 dark:bg-slate-800">
                    {{ r.Status || "pending" }}
                  </span>
                  <span class="text-xs rounded-full px-2 py-0.5 w-fit"
                    :class="r.Payout_Status === 'paid'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                      : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                  >
                    payout: {{ r.Payout_Status || 'unpaid' }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-right">{{ money(r.Sub_Total) }}</td>
              <td class="px-4 py-3 text-right">{{ money(r.Commission_Amount) }}</td>
              <td class="px-4 py-3 text-right font-medium">{{ money(r.Expected_Payout_Amount) }}</td>
              <td class="px-4 py-3 text-xs text-slate-500">{{ fmtDate(r.created_at) }}</td>
            </tr>

            <tr v-if="!recentOrders.length">
              <td colspan="7" class="px-4 py-8 text-center text-slate-500 text-sm">
                No recent vendor orders found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </VendorLayout>
</template>