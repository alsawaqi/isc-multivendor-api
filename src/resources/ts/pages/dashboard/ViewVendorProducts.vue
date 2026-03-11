<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type TabKey = "pending" | "approved"


const R2 = import.meta.env.VITE_R2_URL
const url = `${R2}`


type Rel = {
  id: number
  // keep flexible: your API may return different naming fields
  name?: string
  name_ar?: string
  Product_Department_Name?: string
  Product_Department_Name_Ar?: string
  Sub_Department_Name?: string
  Sub_Department_Name_Ar?: string
  Product_Sub_Sub_Department_Name?: string
  Product_Sub_Sub_Department_Name_Ar?: string
  Product_Types_Name?: string
  Product_Types_Name_Ar?: string
  Products_Brands_Name?: string
  Products_Brands_Name_Ar?: string
  Products_Manufacture_Name?: string
  Products_Manufacture_Name_Ar?: string
}

type DefaultImage = {
  id: number
  Image_Path?: string
  image_path?: string
  Is_Default?: number
}

type TempRow = {
  id: number
  Temp_Product_Code?: string
  Product_Name?: string
  Product_Name_Ar?: string
  Description?: string
  Product_Price?: number
  Product_Stock?: number
  Submission_Status?: "pending" | "approved" | "rejected" | "needs_changes"
  Submitted_At?: string
  Reviewed_At?: string
  Rejection_Reason?: string | null

  department?: Rel | null
  subDepartment?: Rel | null
  subSubDepartment?: Rel | null
  brand?: Rel | null
  manufacture?: Rel | null
  type?: Rel | null

  defaultImage?: DefaultImage | null
}

type MasterRow = {
  id: number
  Product_Code?: string
  Product_Name?: string
  Product_Name_Ar?: string
  Product_Price?: number
  Product_Stock?: number
  Status?: string
  created_at?: string

  department?: Rel | null
  subDepartment?: Rel | null
  subSubDepartment?: Rel | null
  brand?: Rel | null
  manufacture?: Rel | null
  type?: Rel | null

  defaultImage?: DefaultImage | null
}

type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const tab = ref<TabKey>("pending")

const loading = ref(false)
const error = ref<string | null>(null)

const search = ref("")
const debouncedSearch = ref("")
let t: any = null

watch(search, (v) => {
  clearTimeout(t)
  t = setTimeout(() => (debouncedSearch.value = v.trim()), 350)
})

const page = ref(1)
const perPage = ref(12)

watch([tab], () => {
  page.value = 1
  error.value = null
})

const pending = ref<Paginated<TempRow> | null>(null)
const approved = ref<Paginated<MasterRow> | null>(null)

function resetState() {
  error.value = null
  loading.value = false
}

function imgUrl(row: { defaultImage?: DefaultImage | null }) {
  return `${url}/${(row as any).default_image?.Image_Path || row.defaultImage?.image_path || ""}`
  
}

function displayRel(r?: Rel | null): string {
  if (!r) return "-"
  return (
    r.Product_Department_Name ||
    r.Sub_Department_Name ||
    r.Product_Sub_Sub_Department_Name ||
    r.Product_Types_Name ||
    r.Products_Brands_Name ||
    r.Products_Manufacture_Name ||
    r.name ||
    "-"
  )
}

function statusBadge(status?: string) {
  const s = (status || "").toLowerCase()
  if (s === "pending") return "bg-amber-100 text-amber-700 border-amber-200"
  if (s === "needs_changes") return "bg-sky-100 text-sky-700 border-sky-200"
  if (s === "rejected") return "bg-rose-100 text-rose-700 border-rose-200"
  if (s === "approved") return "bg-emerald-100 text-emerald-700 border-emerald-200"
  return "bg-slate-100 text-slate-700 border-slate-200"
}

async function fetchPending() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get("/vendor/api/products/pending", {
      params: {
        page: page.value,
        per_page: perPage.value,
        search: debouncedSearch.value,
        status: "all", // change to "pending" if you want pending-only
        sortBy: "Submitted_At",
        sortDir: "desc",
      },
    })
    pending.value = res.data
  } catch (e: any) {
    error.value =
      e?.response?.data?.message || "Failed to load pending products."
  } finally {
    loading.value = false
  }
}

async function fetchApproved() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get("/vendor/api/products/approved", {
      params: {
        page: page.value,
        per_page: perPage.value,
        search: debouncedSearch.value,
        sortBy: "created_at",
        sortDir: "desc",
      },
    })
    approved.value = res.data
  } catch (e: any) {
    error.value =
      e?.response?.data?.message || "Failed to load approved products."
  } finally {
    loading.value = false
  }
}

async function fetchActive() {
  resetState()
  if (tab.value === "pending") return fetchPending()
  return fetchApproved()
}

watch([tab, page, perPage, debouncedSearch], fetchActive)

onMounted(fetchActive)

const activeRows = computed(() => {
  return tab.value === "pending"
    ? pending.value?.data ?? []
    : approved.value?.data ?? []
})

const activeMeta = computed(() => {
  return tab.value === "pending" ? pending.value : approved.value
})

function nextPage() {
  if (!activeMeta.value) return
  if (page.value < activeMeta.value.last_page) page.value++
}
function prevPage() {
  if (page.value > 1) page.value--
}
</script>

<template>
  <VendorLayout>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 py-6 sm:py-8">
      <VendorPageHeader
        title="Products"
        subtitle="Track what is under review (temporary) and what is approved (live catalog)."
      />

      <!-- Tabs + Controls -->
      <div
        class="mt-5 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden"
      >
        <div class="px-4 sm:px-6 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <!-- Tabs -->
          <div class="flex items-center gap-2">
            <button
              class="px-4 py-2 rounded-xl text-xs font-semibold border transition"
              :class="tab === 'pending'
                ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 dark:border-white'
                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900/60'"
              @click="tab = 'pending'"
            >
              Pending (Temp) 
            </button>

            <button
              class="px-4 py-2 rounded-xl text-xs font-semibold border transition"
              :class="tab === 'approved'
                ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 dark:border-white'
                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900/60'"
              @click="tab = 'approved'"
            >
              Approved (Live)
            </button>
          </div>

          <!-- Search + per page -->
          <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            <div class="relative">
              <input
                v-model="search"
                class="w-full sm:w-80 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
                placeholder="Search by name / code..."
              />
              <div
                v-if="search"
                class="absolute right-2 top-2.5 text-slate-400 cursor-pointer"
                @click="search = ''"
                title="Clear"
              >
                ✕
              </div>
            </div>

            <select
              v-model.number="perPage"
              class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
            >
              <option :value="12">12 / page</option>
              <option :value="24">24 / page</option>
              <option :value="48">48 / page</option>
            </select>
          </div>
        </div>

        <div class="h-[1px] bg-slate-100 dark:bg-slate-900"></div>

        <!-- Alerts -->
        <div v-if="error" class="px-4 sm:px-6 py-4">
          <div class="rounded-2xl border border-rose-200/70 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ error }}
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="px-4 sm:px-6 py-10 text-sm text-slate-500 dark:text-slate-400">
          Loading...
        </div>

        <!-- Grid -->
        <div v-else class="px-4 sm:px-6 py-5">
          <div v-if="activeRows.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
            No products found.
          </div>

          <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="row in activeRows"
              :key="row.id"
              class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden hover:shadow-sm transition"
            >
              <!-- Image -->
              <div class="h-40 bg-slate-50 dark:bg-slate-900/50 overflow-hidden">
                <img
                  v-if="imgUrl(row as any)"
                  :src="imgUrl(row as any)"
                  class="h-40 w-full object-cover"
                />
                <div
                  v-else
                  class="h-40 flex items-center justify-center text-xs text-slate-400"
                >
                  No Image
                </div>
              </div>

              <!-- Body -->
              <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                      {{ (row as any).Product_Name || "-" }}
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                      {{
                        tab === 'pending'
                          ? ((row as any).Temp_Product_Code || `#${row.id}`)
                          : ((row as any).Product_Code || `#${row.id}`)
                      }}
                    </p>
                  </div>

                  <!-- Status -->
                  <span
                    v-if="tab === 'pending'"
                    class="shrink-0 px-2 py-1 rounded-full text-[10px] font-semibold border"
                    :class="statusBadge((row as any).Submission_Status)"
                  >
                    {{ (row as any).Submission_Status || "pending" }}
                  </span>

                  <span
                    v-else
                    class="shrink-0 px-2 py-1 rounded-full text-[10px] font-semibold border"
                    :class="statusBadge((row as any).Status || 'approved')"
                  >
                    {{ (row as any).Status || "approved" }}
                  </span>
                </div>

                <!-- Meta -->
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                  <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-2">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Department</p>
                    <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">
                      {{ displayRel((row as any).department) }}
                    </p>
                  </div>

                  <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-2">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</p>
                    <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">
                      {{ displayRel((row as any).type) }}
                    </p>
                  </div>

                  <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-2">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Brand</p>
                    <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">
                      {{ displayRel((row as any).brand) }}
                    </p>
                  </div>

                  <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-2">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Manufacturer</p>
                    <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">
                      {{ displayRel((row as any).manufacture) }}
                    </p>
                  </div>
                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                  <span class="font-semibold">
                    {{ Number((row as any).Product_Price || 0).toFixed(3) }}
                  </span>
                  <span>
                    Stock: <b>{{ (row as any).Product_Stock ?? "-" }}</b>
                  </span>
                </div>

                <!-- Optional: show review/rejection messages for pending -->
                <div
                  v-if="tab === 'pending' && (row as any).Submission_Status === 'rejected'"
                  class="mt-3 rounded-xl border border-rose-200/70 bg-rose-50 px-3 py-2 text-[11px] text-rose-700"
                >
                  Rejected: {{ (row as any).Rejection_Reason || "-" }}
                </div>

                <!-- Actions (placeholder for next blueprint steps: order tracking, etc.) -->
                <div class="mt-4 flex items-center gap-2">
                  <button
                    class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-sm transition"
                    @click="$router.push(tab === 'pending' ? `/products/temp/${row.id}` : `/products/${row.id}`)"
                  >
                    View
                  </button>

                  <button
                    v-if="tab === 'pending'"
                    class="ml-auto px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-300 cursor-not-allowed"
                    disabled
                    title="Locked until admin approves"
                  >
                    Locked
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div
            v-if="activeMeta"
            class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
          >
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Showing
              <b class="text-slate-800 dark:text-slate-100">
                {{ activeMeta?.data?.length || 0 }}
              </b>
              of
              <b class="text-slate-800 dark:text-slate-100">
                {{ activeMeta?.total || 0 }}
              </b>
              — Page
              <b class="text-slate-800 dark:text-slate-100">
                {{ activeMeta?.current_page || 1 }}
              </b>
              /
              <b class="text-slate-800 dark:text-slate-100">
                {{ activeMeta?.last_page || 1 }}
              </b>
            </p>

            <div class="flex items-center gap-2">
              <button
                class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-50"
                :disabled="page <= 1"
                @click="prevPage"
              >
                Prev
              </button>
              <button
                class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-50"
                :disabled="activeMeta && page >= activeMeta.last_page"
                @click="nextPage"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </VendorLayout>
</template>
