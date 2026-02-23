<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type VendorPayoutRow = {
  id: number
  Orders_Placed_Id: number
  Vendor_Order_Code?: string | null
  Status?: string | null
  Payout_Status?: "unpaid" | "requested" | "paid" | string | null
  Sub_Total?: number | string | null
  Commission_Type?: string | null
  Commission_Value?: number | string | null
  Commission_Amount?: number | string | null
  Payout_Amount?: number | string | null
  Expected_Payout_Amount?: number | string | null
  Payout_At?: string | null
  Payout_Reference?: string | null
  Items_Count?: number
  created_at?: string | null
}

const loading = ref(false)
const error = ref<string | null>(null)

const rows = ref<VendorPayoutRow[]>([])
const page = ref(1)
const perPage = ref(15)
const total = ref(0)

const payoutStatus = ref("all")
const q = ref("")
const dateFrom = ref("")
const dateTo = ref("")

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

const paidTotal = computed(() =>
  rows.value
    .filter((r) => (r.Payout_Status || "unpaid") === "paid")
    .reduce((a, b) => a + Number(b.Payout_Amount ?? 0), 0)
)

const pendingTotal = computed(() =>
  rows.value
    .filter((r) => (r.Payout_Status || "unpaid") !== "paid")
    .reduce((a, b) => a + Number(b.Expected_Payout_Amount ?? b.Payout_Amount ?? 0), 0)
)

const money = (v: any) => {
  const n = Number(v ?? 0)
  return Number.isFinite(n) ? n.toFixed(3) : "0.000"
}

const fmtDate = (v?: string | null) => {
  if (!v) return "-"
  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? v : d.toLocaleString()
}

async function fetchPayouts() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get("/vendor/api/payouts", {
      params: {
        page: page.value,
        per_page: perPage.value,
        payout_status: payoutStatus.value,
        q: q.value.trim() || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
      },
    })

    rows.value = data?.data || []
    total.value = Number(data?.meta?.total || 0)
    page.value = Number(data?.meta?.current_page || 1)
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load payouts."
  } finally {
    loading.value = false
  }
}

onMounted(fetchPayouts)
</script>

<template>
  <VendorLayout>
    <VendorPageHeader
      title="Vendor Payouts"
      description="See commission deductions, expected payouts, and paid payout history."
      icon="fa-solid fa-money-bill-transfer"
    >
      <template #actions>
        <button
          class="h-9 px-3 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-60"
          :disabled="loading"
          @click="fetchPayouts"
        >
          Refresh
        </button>
      </template>
    </VendorPageHeader>

    <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      {{ error }}
    </div>

    <!-- Summary bar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Paid in Current Page</p>
        <p class="text-xl font-semibold text-emerald-600">{{ money(paidTotal) }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Pending in Current Page</p>
        <p class="text-xl font-semibold text-amber-600">{{ money(pendingTotal) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
          <label class="text-xs text-slate-500">Payout Status</label>
          <select
            v-model="payoutStatus"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
            @change="page = 1; fetchPayouts()"
          >
            <option value="all">All</option>
            <option value="unpaid">Unpaid</option>
            <option value="requested">Requested</option>
            <option value="paid">Paid</option>
          </select>
        </div>

        <div>
          <label class="text-xs text-slate-500">Date From</label>
          <input
            v-model="dateFrom"
            type="date"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
          />
        </div>

        <div>
          <label class="text-xs text-slate-500">Date To</label>
          <input
            v-model="dateTo"
            type="date"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
          />
        </div>

        <div class="md:col-span-2">
          <label class="text-xs text-slate-500">Search</label>
          <div class="mt-1 flex gap-2">
            <input
              v-model="q"
              type="text"
              class="flex-1 h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
              placeholder="Order code / payout ref / order id"
              @keyup.enter="page = 1; fetchPayouts()"
            />
            <button
              class="h-10 px-3 rounded-xl border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
              @click="page = 1; fetchPayouts()"
            >
              Search
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
      <div v-if="loading" class="p-6 text-sm text-slate-500">Loading payouts...</div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">Vendor Order</th>
              <th class="px-4 py-3 text-left">Order</th>
              <th class="px-4 py-3 text-right">Sub Total</th>
              <th class="px-4 py-3 text-right">Commission</th>
              <th class="px-4 py-3 text-right">Expected Payout</th>
              <th class="px-4 py-3 text-right">Paid Amount</th>
              <th class="px-4 py-3 text-left">Payout Status</th>
              <th class="px-4 py-3 text-left">Paid At</th>
              <th class="px-4 py-3 text-left">Reference</th>
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
              <td class="px-4 py-3 text-right">{{ money(r.Sub_Total) }}</td>
              <td class="px-4 py-3 text-right">
                {{ money(r.Commission_Amount) }}
                <div class="text-[11px] text-slate-500" v-if="r.Commission_Type">
                  {{ r.Commission_Type }}{{ r.Commission_Value != null ? ` (${r.Commission_Value})` : "" }}
                </div>
              </td>
              <td class="px-4 py-3 text-right font-medium">{{ money(r.Expected_Payout_Amount) }}</td>
              <td class="px-4 py-3 text-right">{{ money(r.Payout_Amount) }}</td>
              <td class="px-4 py-3">
                <span
                  class="text-xs rounded-full px-2 py-0.5"
                  :class="(r.Payout_Status || 'unpaid') === 'paid'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : (r.Payout_Status || 'unpaid') === 'requested'
                      ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                      : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                >
                  {{ r.Payout_Status || "unpaid" }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500">{{ fmtDate(r.Payout_At) }}</td>
              <td class="px-4 py-3 text-xs">{{ r.Payout_Reference || "-" }}</td>
            </tr>

            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-slate-500 text-sm">
                No payouts found.
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
            @click="page--; fetchPayouts()"
          >
            Previous
          </button>
          <button
            class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs disabled:opacity-50"
            :disabled="page >= totalPages"
            @click="page++; fetchPayouts()"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </VendorLayout>
</template>