<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type VendorOrderRow = {
  id: number
  Orders_Placed_Id: number
  Vendor_Order_Code?: string | null
  Status?: string | null
  Payout_Status?: string | null
  Sub_Total?: number | string | null
  Commission_Amount?: number | string | null
  Commission_Type?: string | null
  Commission_Value?: number | string | null
  Payout_Amount?: number | string | null
  Payout_At?: string | null
  Payout_Reference?: string | null
  Items_Count?: number
  created_at?: string | null
}

type VendorOrderDetail = {
  vendor_order: any
  order: any
  items: any[]
  finance: {
    sub_total: number
    commission_amount: number
    net_earnings: number
    payout_status: string
    payout_amount: number
    payout_at?: string | null
    payout_reference?: string | null
  }
}

const loading = ref(false)
const detailLoading = ref(false)
const error = ref<string | null>(null)

const rows = ref<VendorOrderRow[]>([])
const page = ref(1)
const perPage = ref(15)
const total = ref(0)

const status = ref("all")
const payoutStatus = ref("all")
const q = ref("")

const detailOpen = ref(false)
const detail = ref<VendorOrderDetail | null>(null)
const selectedId = ref<number | null>(null)

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

const money = (v: any) => {
  const n = Number(v ?? 0)
  return Number.isFinite(n) ? n.toFixed(3) : "0.000"
}

const fmtDate = (v?: string | null) => {
  if (!v) return "-"
  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? v : d.toLocaleString()
}

const calcNet = (r: VendorOrderRow) => {
  const sub = Number(r.Sub_Total ?? 0)
  const comm = Number(r.Commission_Amount ?? 0)
  return Math.max(sub - comm, 0)
}

async function fetchOrders() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get("/vendor/api/orders", {
      params: {
        page: page.value,
        per_page: perPage.value,
        status: status.value,
        payout_status: payoutStatus.value,
        q: q.value.trim() || undefined,
      },
    })

    rows.value = data?.data || []
    total.value = Number(data?.total || 0)
    page.value = Number(data?.current_page || 1)
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load vendor orders."
  } finally {
    loading.value = false
  }
}

async function openDetail(row: VendorOrderRow) {
  detailOpen.value = true
  detailLoading.value = true
  detail.value = null
  selectedId.value = row.id
  error.value = null

  try {
    const { data } = await api.get(`/vendor/api/orders/${row.id}`)
    detail.value = data?.data || null
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load order details."
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  detailOpen.value = false
  detail.value = null
  selectedId.value = null
}

onMounted(fetchOrders)
</script>

<template>
  <VendorLayout>
    <VendorPageHeader
      title="Vendor Orders"
      description="Read-only list of your vendor orders, commission status, and payout state."
      icon="fa-solid fa-cart-shopping"
    >
      <template #actions>
        <button
          class="h-9 px-3 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-60"
          :disabled="loading"
          @click="fetchOrders"
        >
          Refresh
        </button>
      </template>
    </VendorPageHeader>

    <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      {{ error }}
    </div>

    <!-- Filters -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="text-xs text-slate-500">Status</label>
          <select
            v-model="status"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
            @change="page = 1; fetchOrders()"
          >
            <option value="all">All</option>
            <option value="pending">Pending</option>
            <option value="commission_set">Commission Set</option>
          </select>
        </div>

        <div>
          <label class="text-xs text-slate-500">Payout Status</label>
          <select
            v-model="payoutStatus"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
            @change="page = 1; fetchOrders()"
          >
            <option value="all">All</option>
            <option value="unpaid">Unpaid</option>
            <option value="requested">Requested</option>
            <option value="paid">Paid</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="text-xs text-slate-500">Search</label>
          <div class="mt-1 flex gap-2">
            <input
              v-model="q"
              type="text"
              class="flex-1 h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
              placeholder="Vendor order code / order id"
              @keyup.enter="page = 1; fetchOrders()"
            />
            <button
              class="h-10 px-3 rounded-xl border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
              @click="page = 1; fetchOrders()"
            >
              Search
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
      <div v-if="loading" class="p-6 text-sm text-slate-500">Loading vendor orders...</div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">Vendor Order</th>
              <th class="px-4 py-3 text-left">Order</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Sub Total</th>
              <th class="px-4 py-3 text-right">Commission</th>
              <th class="px-4 py-3 text-right">Net</th>
              <th class="px-4 py-3 text-center">Items</th>
              <th class="px-4 py-3 text-left">Created</th>
              <th class="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in rows"
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
                  <span
                    class="text-xs rounded-full px-2 py-0.5 w-fit"
                    :class="(r.Payout_Status || 'unpaid') === 'paid'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                      : (r.Payout_Status || 'unpaid') === 'requested'
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                  >
                    payout: {{ r.Payout_Status || "unpaid" }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-right">{{ money(r.Sub_Total) }}</td>
              <td class="px-4 py-3 text-right">{{ money(r.Commission_Amount) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-emerald-600">{{ money(calcNet(r)) }}</td>
              <td class="px-4 py-3 text-center">{{ r.Items_Count ?? 0 }}</td>
              <td class="px-4 py-3 text-xs text-slate-500">{{ fmtDate(r.created_at) }}</td>
              <td class="px-4 py-3 text-right">
                <button
                  class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs hover:bg-slate-50 dark:hover:bg-slate-900"
                  @click="openDetail(r)"
                >
                  View
                </button>
              </td>
            </tr>

            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-slate-500 text-sm">
                No vendor orders found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="text-xs text-slate-500">
          Page {{ page }} / {{ totalPages }} ({{ total }} total)
        </div>
        <div class="flex gap-2">
          <button
            class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs disabled:opacity-50"
            :disabled="page <= 1"
            @click="page--; fetchOrders()"
          >
            Previous
          </button>
          <button
            class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs disabled:opacity-50"
            :disabled="page >= totalPages"
            @click="page++; fetchOrders()"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Details Modal -->
    <div
      v-if="detailOpen"
      class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm p-4 flex items-center justify-center"
      @click.self="closeDetail"
    >
      <div class="w-full max-w-5xl max-h-[90vh] overflow-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-2xl">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 class="text-sm font-semibold">Vendor Order Details</h3>
          <button
            class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs"
            @click="closeDetail"
          >
            Close
          </button>
        </div>

        <div v-if="detailLoading" class="p-6 text-sm text-slate-500">Loading details...</div>

        <div v-else-if="detail" class="p-4 space-y-4">
          <!-- Summary -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3">
              <p class="text-xs text-slate-500">Vendor Order</p>
              <p class="font-semibold">{{ detail.vendor_order?.Vendor_Order_Code || ("#" + selectedId) }}</p>
              <p class="text-xs text-slate-500 mt-1">Main Order: #{{ detail.vendor_order?.Orders_Placed_Id }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3">
              <p class="text-xs text-slate-500">Finance</p>
              <p class="text-xs mt-1">Subtotal: <span class="font-medium">{{ money(detail.finance?.sub_total) }}</span></p>
              <p class="text-xs">Commission: <span class="font-medium">{{ money(detail.finance?.commission_amount) }}</span></p>
              <p class="text-xs">Net: <span class="font-semibold text-emerald-600">{{ money(detail.finance?.net_earnings) }}</span></p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3">
              <p class="text-xs text-slate-500">Payout</p>
              <p class="text-xs mt-1">Status: <span class="font-medium">{{ detail.finance?.payout_status || 'unpaid' }}</span></p>
              <p class="text-xs">Amount: <span class="font-medium">{{ money(detail.finance?.payout_amount) }}</span></p>
              <p class="text-xs">Date: <span class="font-medium">{{ fmtDate(detail.finance?.payout_at) }}</span></p>
            </div>
          </div>

          <!-- Items -->
          <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-800 text-sm font-medium">
              Order Items
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500">
                  <tr>
                    <th class="px-3 py-2 text-left">Product</th>
                    <th class="px-3 py-2 text-left">Code</th>
                    <th class="px-3 py-2 text-right">Qty</th>
                    <th class="px-3 py-2 text-right">Price</th>
                    <th class="px-3 py-2 text-right">Subtotal</th>
                    <th class="px-3 py-2 text-right">VAT</th>
                    <th class="px-3 py-2 text-left">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in detail.items"
                    :key="item.id"
                    class="border-t border-slate-100 dark:border-slate-800"
                  >
                    <td class="px-3 py-2">{{ item.Product_Name || item.Product_Name_Ar || ("Product #" + item.Products_Id) }}</td>
                    <td class="px-3 py-2 text-xs">{{ item.Product_Code || item.Inhouse_Barcode_Source || "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ item.Quantity }}</td>
                    <td class="px-3 py-2 text-right">{{ money(item.Price) }}</td>
                    <td class="px-3 py-2 text-right">{{ money(item.Subtotal) }}</td>
                    <td class="px-3 py-2 text-right">{{ money(item.Vat) }}</td>
                    <td class="px-3 py-2">{{ item.Status || "-" }}</td>
                  </tr>
                  <tr v-if="!detail.items?.length">
                    <td colspan="7" class="px-3 py-6 text-center text-slate-500">No items found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div v-else class="p-6 text-sm text-slate-500">No details to show.</div>
      </div>
    </div>
  </VendorLayout>
</template>