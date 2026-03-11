<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type AnyObj = Record<string, any>

const route = useRoute()
const router = useRouter()

const R2 = import.meta.env.VITE_R2_URL || ""

const loading = ref(false)
const error = ref<string | null>(null)
const busy = ref(false)

const detail = ref<{
  mode: "pending" | "approved"
  product: AnyObj
  images: AnyObj[]
  specs: AnyObj[]
  request_history: AnyObj[]
  can_request_update: boolean
  has_open_update_request: boolean
} | null>(null)

const requestModalOpen = ref(false)
const requestComment = ref("")
const requestError = ref<string | null>(null)

const source = computed<"pending" | "approved">(() => {
  return route.name === "vendor.product.pending.show" ? "pending" : "approved"
})

const productId = computed(() => Number(route.params.id))

const fetchDetail = async () => {
  if (!productId.value) return
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get(`/vendor/api/products/${source.value}/${productId.value}`)
    detail.value = data?.data || null
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load product details."
  } finally {
    loading.value = false
  }
}

const openRequestModal = () => {
  requestComment.value = ""
  requestError.value = null
  requestModalOpen.value = true
}

const closeRequestModal = () => {
  requestModalOpen.value = false
  requestComment.value = ""
  requestError.value = null
}

const submitRequest = async () => {
  if (!detail.value || detail.value.mode !== "approved") return
  requestError.value = null

  const comment = requestComment.value.trim()
  if (!comment) {
    requestError.value = "Please write a reason for the update request."
    return
  }

  busy.value = true
  try {
    await api.post(`/vendor/api/products/approved/${productId.value}/request-update`, {
      comment,
    })
    closeRequestModal()
    await fetchDetail()
  } catch (e: any) {
    requestError.value = e?.response?.data?.message || "Failed to submit update request."
  } finally {
    busy.value = false
  }
}

const money = (v: any) => {
  const n = Number(v ?? 0)
  return Number.isFinite(n) ? n.toFixed(3) : "0.000"
}

const textOf = (obj: AnyObj | null | undefined, keys: string[]) => {
  if (!obj) return "-"
  for (const k of keys) {
    const v = obj[k]
    if (v !== null && v !== undefined && String(v).trim() !== "") return String(v)
  }
  return "-"
}

const specHeader = (s: AnyObj) =>
  textOf(s?.description, [
    "Specification_Name",
    "Product_Specification_Name",
    "Name",
    "name",
    "Specification",
  ])

const specHeaderAr = (s: AnyObj) =>
  textOf(s?.description, [
    "Specification_Name_Ar",
    "Product_Specification_Name_Ar",
    "Name_Ar",
    "name_ar",
  ])

const specValue = (s: AnyObj) =>
  textOf(s?.value, [
    "Value",
    "Specification_Value",
    "Name",
    "name",
    "Value_Text",
  ])

const specValueAr = (s: AnyObj) =>
  textOf(s?.value, [
    "Value_Ar",
    "Specification_Value_Ar",
    "Name_Ar",
    "name_ar",
  ])

const statusClass = (status?: string) => {
  const s = String(status || "").toLowerCase()
  if (s === "approved" || s === "paid") {
    return "bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20"
  }
  if (s === "pending" || s === "requested" || s === "under_review") {
    return "bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20"
  }
  if (s === "rejected") {
    return "bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20"
  }
  if (s === "needs_changes") {
    return "bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/20"
  }
  return "bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700"
}

const goBack = () => {
  router.push({ name: "vendor.viewproducts" })
}

const imageSrc = (img: AnyObj) => {
  const p = img?.Image_Path || img?.image_path || ""
  if (!p) return ""
  if (String(p).startsWith("http://") || String(p).startsWith("https://")) return String(p)
  return R2 ? `${R2}/${String(p).replace(/^\/+/, "")}` : String(p)
}

const formatDate = (v?: string | null) => {
  if (!v) return "-"
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return String(v)
  return d.toLocaleString()
}

const productStatus = computed(() => {
  if (!detail.value) return "-"
  return (
    detail.value.product?.Submission_Status ||
    detail.value.product?.Status ||
    detail.value.mode ||
    "-"
  )
})

const productCode = computed(() => {
  const p = detail.value?.product
  return (
    p?.Product_Code ||
    p?.Temp_Product_Code ||
    p?.Inhouse_Barcode_Source ||
    "-"
  )
})

const categoryBlocks = computed(() => {
  const p = detail.value?.product
  if (!p) return []
  return [
    {
      label: "Department",
      value: textOf(p?.department, ["Product_Department_Name", "name"]),
    },
    {
      label: "Sub Department",
      value: textOf(p?.sub_department || p?.subDepartment, ["Sub_Department_Name", "name"]),
    },
    {
      label: "Sub-Sub Department",
      value: textOf(p?.sub_sub_department || p?.subSubDepartment, ["Product_Sub_Sub_Department_Name", "name"]),
    },
    {
      label: "Brand",
      value: textOf(p?.brand, ["Products_Brands_Name", "name"]),
    },
    {
      label: "Manufacture",
      value: textOf(p?.manufacture, ["Products_Manufacture_Name", "name"]),
    },
    {
      label: "Type",
      value: textOf(p?.type, ["Product_Types_Name", "name"]),
    },
  ]
})

onMounted(fetchDetail)
watch(() => route.fullPath, fetchDetail)
</script>

<template>
  <VendorLayout>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 py-6 sm:py-8">
      <VendorPageHeader
        title="Product Details"
        :description="`View full product information, specs, images, and request timeline (${source}).`"
        icon="fa-solid fa-box-open"
      >
        <template #actions>
          <button
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:shadow-sm transition"
            @click="goBack"
          >
            ← Back
          </button>

          <button
            v-if="detail?.mode === 'approved'"
            class="px-3 py-2 rounded-xl text-xs font-semibold border transition"
            :class="detail?.has_open_update_request
              ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300 cursor-not-allowed'
              : 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300 hover:shadow-sm'"
            :disabled="detail?.has_open_update_request"
            @click="openRequestModal"
          >
            {{ detail?.has_open_update_request ? "Update Request Pending" : "Request Update" }}
          </button>
        </template>
      </VendorPageHeader>

      <!-- Error -->
      <div v-if="error" class="mb-4 rounded-2xl border border-rose-200/70 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </div>

      <!-- Loading -->
      <div
        v-if="loading"
        class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 p-10 text-center shadow-sm"
      >
        <div class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-800 dark:border-slate-700 dark:border-t-slate-100"></div>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Loading product details...</p>
      </div>

      <template v-else-if="detail">
        <!-- Top section -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
          <!-- Main identity card -->
          <div class="xl:col-span-2 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800">
              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold border capitalize" :class="statusClass(productStatus)">
                      {{ productStatus }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900">
                      {{ source }} product
                    </span>
                  </div>

                  <h2 class="text-lg sm:text-xl font-semibold text-slate-900 dark:text-slate-100 truncate">
                    {{ detail.product?.Product_Name || "-" }}
                  </h2>
                  <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                    {{ detail.product?.Product_Name_Ar || "-" }}
                  </p>
                </div>

                <div class="sm:text-right">
                  <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Product Code</p>
                  <p class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-100 break-all">
                    {{ productCode }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Category/meta grid -->
            <div class="p-4 sm:p-5">
              <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                  v-for="block in categoryBlocks"
                  :key="block.label"
                  class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-3"
                >
                  <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">
                    {{ block.label }}
                  </p>
                  <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 break-words">
                    {{ block.value }}
                  </p>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 p-3">
                  <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                    Description
                  </p>
                  <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">
                    {{ detail.product?.Description || "-" }}
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 p-3">
                  <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                    Description (Arabic)
                  </p>
                  <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">
                    {{ detail.product?.Description_Ar || "-" }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Commercial & stock sidebar -->
          <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800">
              <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Commercial & Stock</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pricing, inventory, and dimensions</p>
            </div>

            <div class="p-4 sm:p-5 space-y-2">
              <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-sm text-slate-500 dark:text-slate-400">Price</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ money(detail.product?.Product_Price) }}</span>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-sm text-slate-500 dark:text-slate-400">Cost</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ money(detail.product?.Product_Cost) }}</span>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-sm text-slate-500 dark:text-slate-400">Stock</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ detail.product?.Product_Stock ?? 0 }}</span>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-sm text-slate-500 dark:text-slate-400">Weight (kg)</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ detail.product?.Weight_Kg ?? "-" }}</span>
              </div>
              <div class="py-2 border-b border-slate-100 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Dimensions (cm)</p>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 break-words">
                  {{ detail.product?.Length_Cm ?? "-" }} × {{ detail.product?.Width_Cm ?? "-" }} × {{ detail.product?.Height_Cm ?? "-" }}
                </p>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-500 dark:text-slate-400">Volume (cbm)</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ detail.product?.Volume_Cbm ?? "-" }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Images -->
        <div class="mt-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm">
          <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
            <div>
              <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Images</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Product gallery and default image</p>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ detail.images?.length || 0 }} image(s)</span>
          </div>

          <div class="p-4 sm:p-5">
            <div v-if="!detail.images?.length" class="text-sm text-slate-500 dark:text-slate-400">
              No images found.
            </div>

            <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
              <div
                v-for="img in detail.images"
                :key="img.id"
                class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-2"
              >
                <div class="aspect-square rounded-lg overflow-hidden bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                  <img
                    v-if="imageSrc(img)"
                    :src="imageSrc(img)"
                    alt="product image"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full grid place-items-center text-xs text-slate-400">
                    No Image
                  </div>
                </div>

                <div class="mt-2 flex items-center justify-between gap-2">
                  <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">#{{ img.id }}</span>
                  <span
                    v-if="Number(img.Is_Default) === 1"
                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold border border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300"
                  >
                    Default
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Specifications -->
        <div class="mt-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm">
          <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
            <div>
              <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Specifications</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Mapped spec names and values</p>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ detail.specs?.length || 0 }} row(s)</span>
          </div>

          <div class="p-4 sm:p-5">
            <div v-if="!detail.specs?.length" class="text-sm text-slate-500 dark:text-slate-400">
              No specifications found.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b border-slate-200 dark:border-slate-800">
                    <th class="text-left py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Specification</th>
                    <th class="text-left py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Value</th>
                    <th class="text-left py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">IDs</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="s in detail.specs"
                    :key="s.id"
                    class="border-b last:border-0 border-slate-100 dark:border-slate-800"
                  >
                    <td class="py-3 pr-3 align-top">
                      <p class="font-medium text-slate-800 dark:text-slate-100">{{ specHeader(s) }}</p>
                      <p class="text-xs text-slate-500 dark:text-slate-400">{{ specHeaderAr(s) }}</p>
                    </td>
                    <td class="py-3 pr-3 align-top">
                      <p class="font-medium text-slate-800 dark:text-slate-100">{{ specValue(s) }}</p>
                      <p class="text-xs text-slate-500 dark:text-slate-400">{{ specValueAr(s) }}</p>
                    </td>
                    <td class="py-3 align-top">
                      <p class="text-xs font-mono text-slate-500 dark:text-slate-400">
                        D:{{ s.Product_Specification_Description_Id ?? "-" }}
                      </p>
                      <p class="text-xs font-mono text-slate-500 dark:text-slate-400">
                        V:{{ s.product_specification_value_id ?? "-" }}
                      </p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Request Timeline -->
        <div class="mt-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm">
          <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
            <div>
              <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Request Timeline</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">History of product review and update requests</p>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ detail.request_history?.length || 0 }} event(s)</span>
          </div>

          <div class="p-4 sm:p-5">
            <div v-if="!detail.request_history?.length" class="text-sm text-slate-500 dark:text-slate-400">
              No request history yet.
            </div>

            <div v-else class="relative">
              <div class="absolute left-[7px] top-1 bottom-1 w-px bg-slate-200 dark:bg-slate-700"></div>

              <div
                v-for="item in detail.request_history"
                :key="item.id"
                class="relative pl-8 pb-4 last:pb-0"
              >
                <div class="absolute left-0 top-1.5 h-4 w-4 rounded-full border-2 border-white dark:border-slate-950 bg-primary-500"></div>

                <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-3">
                  <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border capitalize" :class="statusClass(item.Status)">
                      {{ item.Status || "-" }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(item.Action_At) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">by {{ item.Action_By_Role || "-" }}</span>
                  </div>

                  <p v-if="item.Comment" class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">
                    {{ item.Comment }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Request Update Modal -->
    <div
      v-if="requestModalOpen"
      class="fixed inset-0 z-[60] bg-slate-900/50 backdrop-blur-[2px] p-4 grid place-items-center"
      @click.self="closeRequestModal"
    >
      <div class="w-full max-w-xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Request Product Update</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Tell admin what should be changed</p>
          </div>
          <button
            class="h-8 w-8 grid place-items-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-900"
            @click="closeRequestModal"
          >
            ✕
          </button>
        </div>

        <div class="p-4">
          <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
            Reason / Comment
          </label>
          <textarea
            v-model="requestComment"
            rows="5"
            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-primary-500/30"
            placeholder="Example: Please update price, stock, and product description..."
          ></textarea>

          <div
            v-if="requestError"
            class="mt-3 rounded-xl border border-rose-200/70 bg-rose-50 px-3 py-2 text-xs text-rose-700"
          >
            {{ requestError }}
          </div>
        </div>

        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
          <button
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200"
            :disabled="busy"
            @click="closeRequestModal"
          >
            Cancel
          </button>
          <button
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300 disabled:opacity-60"
            :disabled="busy"
            @click="submitRequest"
          >
            <span v-if="busy" class="inline-block h-3.5 w-3.5 mr-2 align-middle animate-spin rounded-full border-2 border-current border-t-transparent"></span>
            Submit Request
          </button>
        </div>
      </div>
    </div>
  </VendorLayout>
</template>