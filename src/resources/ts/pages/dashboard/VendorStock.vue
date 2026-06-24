<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

const R2 = import.meta.env.VITE_R2_URL

type RelationName = {
  Product_Department_Name?: string | null
  Sub_Department_Name?: string | null
  Product_Sub_Sub_Department_Name?: string | null
}

type DefaultImage = {
  Image_Path?: string | null
  image_path?: string | null
}

type ProductRow = {
  id: number
  Product_Code?: string | null
  Product_Sku?: string | null
  Product_Name: string
  Product_Stock?: number | null
  Status?: string | null
  defaultImage?: DefaultImage | null
  default_image?: DefaultImage | null
  department?: RelationName | null
  subDepartment?: RelationName | null
  sub_department?: RelationName | null
  subSubDepartment?: RelationName | null
  sub_sub_department?: RelationName | null
}

type StockMovement = {
  id: number
  Movement_Type: "increase" | "decrease" | "set"
  Quantity_Delta: number
  Previous_Stock: number
  New_Stock: number
  Actor_Name?: string | null
  Notes?: string | null
  created_at?: string | null
}

type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
}

const products = ref<ProductRow[]>([])
const movements = ref<StockMovement[]>([])
const loading = ref(false)
const saving = ref(false)
const movementsLoading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const selectedProduct = ref<ProductRow | null>(null)

const filters = reactive({
  page: 1,
  perPage: 12,
  search: "",
  sortBy: "Product_Stock",
  sortDir: "asc",
})

const meta = ref<Paginated<ProductRow> | null>(null)

const adjustment = reactive({
  movementType: "increase" as "increase" | "decrease" | "set",
  quantity: 1,
  newStock: 0,
  notes: "",
})

const totalStock = computed(() => products.value.reduce((sum, product) => sum + Number(product.Product_Stock || 0), 0))
const emptyStock = computed(() => products.value.filter((product) => Number(product.Product_Stock || 0) <= 0).length)

function imgUrl(product: ProductRow) {
  const path = product.defaultImage?.Image_Path
    || product.defaultImage?.image_path
    || product.default_image?.Image_Path
    || product.default_image?.image_path
    || ""

  if (!path || !R2) return ""
  return `${R2}/${String(path).replace(/^\/+/, "")}`
}

function categoryPath(product: ProductRow) {
  const department = product.department?.Product_Department_Name || "Unassigned"
  const subDepartment = product.subDepartment?.Sub_Department_Name || product.sub_department?.Sub_Department_Name || "Unassigned"
  const subSubDepartment = product.subSubDepartment?.Product_Sub_Sub_Department_Name
    || product.sub_sub_department?.Product_Sub_Sub_Department_Name
    || "Unassigned"

  return `${department} / ${subDepartment} / ${subSubDepartment}`
}

function statusClass(status?: string | null) {
  const normalized = String(status || "").toLowerCase()
  if (normalized === "available") return "bg-emerald-100 text-emerald-700 border-emerald-200"
  if (normalized === "out_of_stock") return "bg-rose-100 text-rose-700 border-rose-200"
  return "bg-slate-100 text-slate-600 border-slate-200"
}

function stockClass(stock?: number | null) {
  const value = Number(stock || 0)
  if (value <= 0) return "bg-rose-50 text-rose-700 border-rose-200"
  if (value <= 5) return "bg-amber-50 text-amber-700 border-amber-200"
  return "bg-emerald-50 text-emerald-700 border-emerald-200"
}

function formatDate(value?: string | null) {
  if (!value) return "-"
  return new Date(value).toLocaleString()
}

function extractError(e: any, fallback: string) {
  const errors = e?.response?.data?.errors
  if (errors && typeof errors === "object") {
    const first = Object.values(errors).flat()[0]
    if (first) return String(first)
  }

  return e?.response?.data?.message || fallback
}

function showSuccess(message: string) {
  success.value = message
  window.setTimeout(() => {
    success.value = null
  }, 3000)
}

async function fetchProducts() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get("/vendor/api/stock/products", {
      params: {
        page: filters.page,
        per_page: filters.perPage,
        search: filters.search,
        sort_by: filters.sortBy,
        sort_dir: filters.sortDir,
      },
    })

    meta.value = res.data
    products.value = res.data?.data || []
  } catch (e: any) {
    error.value = extractError(e, "Failed to load product stock.")
  } finally {
    loading.value = false
  }
}

async function fetchMovements(productId: number) {
  movementsLoading.value = true
  try {
    const res = await api.get(`/vendor/api/stock/products/${productId}/movements`, {
      params: { per_page: 8 },
    })
    movements.value = res.data?.data || []
  } catch (e: any) {
    error.value = extractError(e, "Failed to load stock history.")
  } finally {
    movementsLoading.value = false
  }
}

async function openAdjust(product: ProductRow) {
  selectedProduct.value = product
  adjustment.movementType = "increase"
  adjustment.quantity = 1
  adjustment.newStock = Number(product.Product_Stock || 0)
  adjustment.notes = ""
  await fetchMovements(product.id)
}

function closeAdjust() {
  selectedProduct.value = null
  movements.value = []
}

async function saveAdjustment() {
  if (!selectedProduct.value) return

  saving.value = true
  error.value = null

  try {
    await api.post(`/vendor/api/stock/products/${selectedProduct.value.id}/adjust`, {
      movement_type: adjustment.movementType,
      quantity: adjustment.movementType === "set" ? undefined : Number(adjustment.quantity || 0),
      new_stock: adjustment.movementType === "set" ? Number(adjustment.newStock || 0) : undefined,
      notes: adjustment.notes || undefined,
    })

    showSuccess("Stock updated successfully.")
    await fetchProducts()

    const refreshed = products.value.find((product) => product.id === selectedProduct.value?.id)
    if (refreshed) selectedProduct.value = refreshed
    await fetchMovements(selectedProduct.value.id)
  } catch (e: any) {
    error.value = extractError(e, "Stock update failed.")
  } finally {
    saving.value = false
  }
}

function nextPage() {
  if (meta.value && filters.page < meta.value.last_page) filters.page++
}

function prevPage() {
  if (filters.page > 1) filters.page--
}

function resetFilters() {
  filters.search = ""
  filters.sortBy = "Product_Stock"
  filters.sortDir = "asc"
  filters.page = 1
}

watch(
  () => [filters.page, filters.perPage, filters.search, filters.sortBy, filters.sortDir],
  fetchProducts,
)

onMounted(fetchProducts)
</script>

<template>
  <VendorLayout>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 py-6 sm:py-8">
      <VendorPageHeader
        title="Stock"
        description="Update available quantities for approved live products."
        icon="fa-solid fa-boxes-stacked"
      />

      <div v-if="success" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ success }}
      </div>

      <div v-if="error" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <p class="text-xs text-slate-500">Live products</p>
          <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ meta?.total || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <p class="text-xs text-slate-500">Stock in current view</p>
          <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ totalStock }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <p class="text-xs text-slate-500">Out of stock</p>
          <p class="mt-1 text-2xl font-semibold text-rose-600">{{ emptyStock }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800">
          <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
            <div class="grid grid-cols-1 sm:grid-cols-[minmax(220px,1fr)_auto] gap-3 lg:min-w-[520px]">
              <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Search</label>
                <input
                  v-model="filters.search"
                  type="text"
                  class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-200"
                  placeholder="SKU, product code, product name"
                />
              </div>
              <div class="flex items-end">
                <button
                  type="button"
                  class="w-full sm:w-auto rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900"
                  @click="resetFilters"
                >
                  Reset
                </button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <select v-model="filters.sortBy" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                <option value="Product_Stock">Stock</option>
                <option value="Product_Name">Name</option>
                <option value="Product_Code">Code</option>
                <option value="Product_Sku">SKU</option>
                <option value="Status">Status</option>
              </select>
              <select v-model="filters.sortDir" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                <option value="asc">Asc</option>
                <option value="desc">Desc</option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="loading" class="py-16 text-center text-sm text-slate-500">
          Loading products...
        </div>

        <div v-else-if="!products.length" class="py-16 text-center text-sm text-slate-500">
          No approved products found.
        </div>

        <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
          <div
            v-for="product in products"
            :key="product.id"
            class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center gap-4"
          >
            <div class="flex items-start gap-3 min-w-0 flex-1">
              <div class="h-16 w-16 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 overflow-hidden shrink-0 grid place-items-center">
                <img v-if="imgUrl(product)" :src="imgUrl(product)" alt="" class="h-full w-full object-cover" />
                <span v-else class="text-xs font-semibold text-slate-400">ISC</span>
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50 truncate">{{ product.Product_Name }}</h3>
                  <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize', statusClass(product.Status)]">
                    {{ String(product.Status || "unknown").replaceAll("_", " ") }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ categoryPath(product) }}</p>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500">
                  <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-1">{{ product.Product_Code || "-" }}</span>
                  <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-1">SKU: {{ product.Product_Sku || "-" }}</span>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between lg:justify-end gap-3">
              <span :class="['inline-flex min-w-[92px] justify-center rounded-full border px-3 py-2 text-sm font-semibold', stockClass(product.Product_Stock)]">
                {{ Number(product.Product_Stock || 0) }} in stock
              </span>
              <button
                type="button"
                class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                @click="openAdjust(product)"
              >
                Adjust
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-200 dark:border-slate-800">
          <p class="text-xs text-slate-500">
            Showing {{ meta?.from || 0 }} to {{ meta?.to || 0 }} of {{ meta?.total || 0 }}
          </p>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm disabled:opacity-50"
              :disabled="filters.page <= 1"
              @click="prevPage"
            >
              Previous
            </button>
            <span class="text-sm text-slate-500">Page {{ filters.page }} / {{ meta?.last_page || 1 }}</span>
            <button
              type="button"
              class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm disabled:opacity-50"
              :disabled="!meta || filters.page >= meta.last_page"
              @click="nextPage"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <div v-if="selectedProduct" class="fixed inset-0 z-50 flex justify-end bg-slate-950/50" @click.self="closeAdjust">
        <aside class="h-full w-full max-w-xl overflow-y-auto bg-white dark:bg-slate-950 p-5 shadow-2xl">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs text-slate-500">Stock adjustment</p>
              <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-50">{{ selectedProduct.Product_Name }}</h2>
              <p class="mt-1 text-xs text-slate-500">{{ selectedProduct.Product_Code || "-" }} / {{ selectedProduct.Product_Sku || "-" }}</p>
            </div>
            <button type="button" class="rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2" @click="closeAdjust">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>

          <div class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 flex items-center justify-between">
            <span class="text-sm font-semibold text-sky-700">Current stock</span>
            <strong class="text-3xl text-sky-950">{{ Number(selectedProduct.Product_Stock || 0) }}</strong>
          </div>

          <form class="mt-5 space-y-4" @submit.prevent="saveAdjustment">
            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Movement</label>
              <select v-model="adjustment.movementType" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                <option value="increase">Increase stock</option>
                <option value="decrease">Decrease stock</option>
                <option value="set">Set exact stock</option>
              </select>
            </div>

            <div v-if="adjustment.movementType !== 'set'">
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Quantity</label>
              <input v-model.number="adjustment.quantity" type="number" min="1" step="1" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
            </div>

            <div v-else>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">New Stock</label>
              <input v-model.number="adjustment.newStock" type="number" min="0" step="1" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
            </div>

            <div>
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Notes</label>
              <textarea v-model="adjustment.notes" rows="3" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="Optional note"></textarea>
            </div>

            <button
              type="submit"
              class="w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="saving"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Save Stock</span>
            </button>
          </form>

          <div class="mt-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Recent Movements</h3>
              <span class="text-xs text-slate-500">{{ movements.length }} rows</span>
            </div>

            <div v-if="movementsLoading" class="mt-3 text-sm text-slate-500">Loading history...</div>
            <div v-else-if="!movements.length" class="mt-3 text-sm text-slate-500">No stock history yet.</div>
            <div v-else class="mt-3 space-y-2">
              <div
                v-for="movement in movements"
                :key="movement.id"
                class="rounded-2xl border border-slate-200 dark:border-slate-800 p-3"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold capitalize text-slate-900 dark:text-slate-50">
                      {{ movement.Movement_Type }} ({{ movement.Quantity_Delta > 0 ? "+" : "" }}{{ movement.Quantity_Delta }})
                    </p>
                    <p class="text-xs text-slate-500">
                      {{ movement.Previous_Stock }} to {{ movement.New_Stock }} by {{ movement.Actor_Name || "system" }}
                    </p>
                  </div>
                  <span class="text-xs text-slate-500 text-right">{{ formatDate(movement.created_at) }}</span>
                </div>
                <p v-if="movement.Notes" class="mt-2 text-xs text-slate-500">{{ movement.Notes }}</p>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </VendorLayout>
</template>
