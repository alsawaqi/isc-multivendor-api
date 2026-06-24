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

type SalesTrendPoint = {
  date: string
  label: string
  sales_total: number
  orders_count: number
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
const topProducts = ref<TopSoldProduct[]>([])
const salesTrend = ref<SalesTrendPoint[]>([])

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
const trendTotal = computed(() => salesTrend.value.reduce((sum, row) => sum + Number(row.sales_total || 0), 0))
const trendMax = computed(() => Math.max(...salesTrend.value.map((row) => Number(row.sales_total || 0)), 1))
const topProductsMax = computed(() => Math.max(...topProducts.value.map((row) => Number(row.units_sold || 0)), 1))

const trendPolyline = computed(() => {
  const width = 640
  const height = 210
  const padX = 18
  const padY = 20
  const rows = salesTrend.value

  if (!rows.length) return ""

  return rows.map((row, index) => {
    const x = rows.length === 1
      ? width / 2
      : padX + (index / (rows.length - 1)) * (width - padX * 2)
    const y = height - padY - (Number(row.sales_total || 0) / trendMax.value) * (height - padY * 2)
    return `${x.toFixed(2)},${y.toFixed(2)}`
  }).join(" ")
})

const trendArea = computed(() => {
  if (!trendPolyline.value) return ""
  return `18,190 ${trendPolyline.value} 622,190`
})

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
    topProducts.value = earningsRes.data?.data?.top_products || []
    salesTrend.value = earningsRes.data?.data?.sales_trend || []
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


    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
      <div class="xl:col-span-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
          <div>
            <h3 class="text-sm font-semibold">Sales Trend</h3>
            <p class="text-xs text-slate-500">Daily sales for the selected period</p>
          </div>
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-2 text-right">
            <p class="text-[11px] text-slate-500">Trend Total</p>
            <p class="text-sm font-semibold">{{ money(trendTotal) }}</p>
          </div>
        </div>

        <div class="mt-4 h-[250px] overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900">
          <svg v-if="salesTrend.length" viewBox="0 0 640 210" class="h-full w-full" preserveAspectRatio="none">
            <defs>
              <linearGradient id="vendorSalesFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.24" />
                <stop offset="100%" stop-color="#2563eb" stop-opacity="0.02" />
              </linearGradient>
            </defs>
            <line v-for="y in [40, 80, 120, 160]" :key="y" x1="18" x2="622" :y1="y" :y2="y" stroke="#dbe3ef" stroke-width="1" stroke-dasharray="6 8" />
            <polygon :points="trendArea" fill="url(#vendorSalesFill)" />
            <polyline :points="trendPolyline" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <div v-else class="h-full grid place-items-center text-sm text-slate-500">
            No sales trend data yet.
          </div>
        </div>

        <div v-if="salesTrend.length" class="mt-3 flex justify-between gap-2 text-[11px] text-slate-500">
          <span>{{ salesTrend[0]?.label }}</span>
          <span>{{ salesTrend[Math.floor(salesTrend.length / 2)]?.label }}</span>
          <span>{{ salesTrend[salesTrend.length - 1]?.label }}</span>
        </div>
      </div>

      <div class="xl:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <div class="flex items-start justify-between gap-2">
          <div>
            <h3 class="text-sm font-semibold">Most Sold Products</h3>
            <p class="text-xs text-slate-500">Ranked by units sold</p>
          </div>
          <span v-if="topSoldProduct" class="rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300 px-2 py-1 text-[11px] font-semibold">
            #1 {{ topSoldProduct.units_sold }} sold
          </span>
        </div>

        <div class="mt-4 space-y-3">
          <div v-if="!topProducts.length" class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 text-sm text-slate-500">
            No sold products yet for the selected period.
          </div>

          <div v-for="(product, index) in topProducts" :key="product.product_id" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 p-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold truncate">{{ product.product_name || ("Product #" + product.product_id) }}</p>
                <p class="text-[11px] text-slate-500 truncate">{{ product.product_code || "-" }} · {{ product.orders_count }} order(s)</p>
              </div>
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">#{{ index + 1 }}</span>
            </div>
            <div class="mt-3 h-2 rounded-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div class="h-full rounded-full bg-primary-600" :style="{ width: `${Math.max(8, Math.round((Number(product.units_sold || 0) / topProductsMax) * 100))}%` }"></div>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
              <span class="text-slate-500">{{ product.units_sold }} units</span>
              <span class="font-semibold">{{ money(product.sales_total) }}</span>
            </div>
          </div>
        </div>
      </div>
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
