<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type Tab = "reviews" | "questions"

type EngagementResponse = {
  id: number
  Reply_Type?: string
  Answer_Type?: string
  Body: string
  created_at?: string | null
}

type EngagementRow = {
  id: number
  Products_Id: number
  Rating?: number | string | null
  Title?: string | null
  Body?: string | null
  Question?: string | null
  Status?: string | null
  Verified_Purchase?: boolean | number
  Helpful_Count?: number | string | null
  Report_Count?: number | string | null
  created_at?: string | null
  product?: {
    Product_Name?: string | null
    Product_Name_Ar?: string | null
    Slug?: string | null
  } | null
  customer?: {
    Customer_Full_Name?: string | null
    Company_Name?: string | null
  } | null
  replies?: EngagementResponse[]
  answers?: EngagementResponse[]
}

const activeTab = ref<Tab>("reviews")
const loading = ref(false)
const error = ref<string | null>(null)
const savingId = ref<number | null>(null)
const rows = ref<EngagementRow[]>([])
const drafts = reactive<Record<string, string>>({})

const table = reactive({
  page: 1,
  perPage: 15,
  status: "",
  q: "",
})

const total = ref(0)
const lastPage = ref(1)

const totalPages = computed(() => Math.max(1, Number(lastPage.value || 1)))
const endpoint = computed(() => activeTab.value === "reviews" ? "/vendor/api/reviews" : "/vendor/api/questions")
const tabLabel = computed(() => activeTab.value === "reviews" ? "review" : "question")
const pendingCount = computed(() => rows.value.filter((row) => (row.Status || "pending") === "pending").length)
const reportCount = computed(() => rows.value.reduce((sum, row) => sum + Number(row.Report_Count || 0), 0))

const productName = (row: EngagementRow) => row.product?.Product_Name || `Product #${row.Products_Id}`
const customerName = (row: EngagementRow) => row.customer?.Customer_Full_Name || row.customer?.Company_Name || "Customer"
const rowTitle = (row: EngagementRow) => activeTab.value === "reviews" ? (row.Title || "Review") : "Question"
const rowText = (row: EngagementRow) => activeTab.value === "reviews" ? (row.Body || "") : (row.Question || "")
const responses = (row: EngagementRow): EngagementResponse[] => activeTab.value === "reviews" ? (row.replies || []) : (row.answers || [])
const responseType = (reply: EngagementResponse) => reply.Reply_Type || reply.Answer_Type || "reply"

const fmtDate = (value?: string | null) => {
  if (!value) return "-"
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

const statusClass = (status?: string | null) => {
  const value = String(status || "pending").toLowerCase()

  if (value === "approved") return "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
  if (value === "reported" || value === "rejected") return "bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"
  return "bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
}

async function fetchRows() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get(endpoint.value, {
      params: {
        page: table.page,
        per_page: table.perPage,
        status: table.status || undefined,
        q: table.q.trim() || undefined,
      },
    })

    rows.value = data?.data || []
    total.value = Number(data?.total || 0)
    lastPage.value = Number(data?.last_page || 1)
    table.page = Number(data?.current_page || 1)
  } catch (e: any) {
    error.value = e?.response?.data?.message || `Failed to load product ${activeTab.value}.`
  } finally {
    loading.value = false
  }
}

async function sendResponse(row: EngagementRow) {
  const key = `${activeTab.value}-${row.id}`
  const body = drafts[key]?.trim()
  if (!body) return

  savingId.value = row.id
  error.value = null

  try {
    const url = activeTab.value === "reviews"
      ? `/vendor/api/reviews/${row.id}/reply`
      : `/vendor/api/questions/${row.id}/answer`

    await api.post(url, { body })
    drafts[key] = ""
    await fetchRows()
  } catch (e: any) {
    error.value = e?.response?.data?.message || `Failed to send ${activeTab.value === "reviews" ? "reply" : "answer"}.`
  } finally {
    savingId.value = null
  }
}

watch(activeTab, () => {
  table.page = 1
  table.status = ""
  table.q = ""
  fetchRows()
})

onMounted(fetchRows)
</script>

<template>
  <VendorLayout>
    <VendorPageHeader
      title="Reviews & Q&A"
      description="Read customer reviews, answer product questions, and keep your catalog conversation professional."
      icon="fa-solid fa-comments"
    >
      <template #actions>
        <button
          class="h-9 px-3 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-60"
          :disabled="loading"
          @click="fetchRows"
        >
          Refresh
        </button>
      </template>
    </VendorPageHeader>

    <div v-if="error" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      {{ error }}
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Visible Total</p>
        <p class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ total }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Pending on Page</p>
        <p class="text-xl font-semibold text-amber-600">{{ pendingCount }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
        <p class="text-xs text-slate-500">Reports on Page</p>
        <p class="text-xl font-semibold text-rose-600">{{ reportCount }}</p>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
      <div class="grid grid-cols-1 lg:grid-cols-[auto_180px_1fr_auto] gap-3 lg:items-end">
        <div>
          <span class="block text-xs text-slate-500 mb-1">Type</span>
          <div class="inline-flex rounded-xl border border-slate-300 dark:border-slate-700 overflow-hidden" role="tablist" aria-label="Engagement type">
            <button
              type="button"
              class="h-10 px-4 text-sm font-medium"
              :class="activeTab === 'reviews' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-200'"
              @click="activeTab = 'reviews'"
            >
              Reviews
            </button>
            <button
              type="button"
              class="h-10 px-4 text-sm font-medium border-l border-slate-300 dark:border-slate-700"
              :class="activeTab === 'questions' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-200'"
              @click="activeTab = 'questions'"
            >
              Q&A
            </button>
          </div>
        </div>

        <label class="block">
          <span class="text-xs text-slate-500">Status</span>
          <select
            v-model="table.status"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
            @change="table.page = 1; fetchRows()"
          >
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="reported">Reported</option>
            <option value="rejected">Rejected</option>
          </select>
        </label>

        <label class="block">
          <span class="text-xs text-slate-500">Search</span>
          <input
            v-model="table.q"
            type="search"
            class="mt-1 w-full h-10 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
            placeholder="Product, review, or question"
            @keyup.enter="table.page = 1; fetchRows()"
          />
        </label>

        <button
          type="button"
          class="h-10 px-4 rounded-xl border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
          @click="table.page = 1; fetchRows()"
        >
          Search
        </button>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
      <div v-if="loading" class="p-6 text-sm text-slate-500" role="status">
        Loading product {{ activeTab }}...
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-[1080px] w-full text-sm">
          <caption class="sr-only">Vendor product reviews and questions</caption>
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-left">Customer</th>
              <th v-if="activeTab === 'reviews'" class="px-4 py-3 text-left">Rating</th>
              <th class="px-4 py-3 text-left">Content</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Signals</th>
              <th class="px-4 py-3 text-left">Replies</th>
              <th class="px-4 py-3 text-left">Respond</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100 dark:border-slate-800 align-top">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900 dark:text-slate-50">{{ productName(row) }}</div>
                <div class="text-xs text-slate-500">{{ fmtDate(row.created_at) }}</div>
              </td>
              <td class="px-4 py-3">{{ customerName(row) }}</td>
              <td v-if="activeTab === 'reviews'" class="px-4 py-3">
                <div class="font-semibold text-amber-600">{{ row.Rating || 0 }}/5</div>
                <div v-if="row.Verified_Purchase" class="text-xs text-emerald-600">Verified purchase</div>
              </td>
              <td class="px-4 py-3 max-w-sm">
                <div class="font-medium text-slate-900 dark:text-slate-50">{{ rowTitle(row) }}</div>
                <p class="mt-1 text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ rowText(row) }}</p>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(row.Status)">
                  {{ row.Status || "pending" }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500">
                <div>Helpful: {{ row.Helpful_Count || 0 }}</div>
                <div>Reports: {{ row.Report_Count || 0 }}</div>
              </td>
              <td class="px-4 py-3 max-w-xs">
                <div v-if="responses(row).length" class="space-y-2">
                  <div v-for="reply in responses(row)" :key="reply.id" class="rounded-xl bg-slate-50 dark:bg-slate-900 p-3">
                    <div class="text-xs font-semibold text-slate-500 capitalize">{{ responseType(reply) }}</div>
                    <p class="mt-1 text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ reply.Body }}</p>
                  </div>
                </div>
                <span v-else class="text-xs text-slate-500">No response yet</span>
              </td>
              <td class="px-4 py-3 w-72">
                <label class="sr-only" :for="`vendor-response-${activeTab}-${row.id}`">
                  Reply to {{ tabLabel }} {{ row.id }}
                </label>
                <textarea
                  :id="`vendor-response-${activeTab}-${row.id}`"
                  v-model="drafts[`${activeTab}-${row.id}`]"
                  rows="3"
                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
                  :placeholder="activeTab === 'reviews' ? 'Write a vendor reply' : 'Answer this question'"
                ></textarea>
                <button
                  type="button"
                  class="mt-2 h-9 px-3 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-60"
                  :disabled="savingId === row.id"
                  @click="sendResponse(row)"
                >
                  {{ activeTab === "reviews" ? "Reply" : "Answer" }}
                </button>
              </td>
            </tr>

            <tr v-if="rows.length === 0">
              <td :colspan="activeTab === 'reviews' ? 8 : 7" class="px-4 py-8 text-center text-slate-500">
                No product {{ activeTab }} found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="text-xs text-slate-500">
          Page {{ table.page }} / {{ totalPages }} ({{ total }} total)
        </div>
        <div class="flex gap-2">
          <button
            class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs disabled:opacity-50"
            :disabled="table.page <= 1"
            @click="table.page--; fetchRows()"
          >
            Previous
          </button>
          <button
            class="h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs disabled:opacity-50"
            :disabled="table.page >= totalPages"
            @click="table.page++; fetchRows()"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </VendorLayout>
</template>
