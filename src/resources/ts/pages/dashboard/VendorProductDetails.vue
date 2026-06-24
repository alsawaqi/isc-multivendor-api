<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type AnyObj = Record<string, any>
type SpecEditRow = {
  descriptionId: number
  name: string
  values: { id: number; value: string }[]
  selectedValueId: number | null
}

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
const selectedRequestKeys = ref<string[]>([])
const requestValues = reactive<Record<string, any>>({})
const requestSpecRows = ref<SpecEditRow[]>([])
const requestSpecsLoading = ref(false)
const requestSpecsError = ref<string | null>(null)

const tempEditModalOpen = ref(false)
const tempEditError = ref<string | null>(null)
const tempEditComment = ref("")
const tempEditSpecRows = ref<SpecEditRow[]>([])
const tempEditSpecsLoading = ref(false)
const tempEditSpecsError = ref<string | null>(null)
const tempEditSpecsLoaded = ref(false)
const tempEdit = reactive({
  name: "",
  name_ar: "",
  description: "",
  description_ar: "",
  price: 0,
  cost: null as number | null,
  stock: 0,
  Weight_Kg: 0,
  Length_Cm: 0,
  Width_Cm: 0,
  Height_Cm: 0,
})

const requestFields = [
  { key: "Product_Name", label: "Name", type: "text" },
  { key: "Product_Name_Ar", label: "Arabic Name", type: "text" },
  { key: "Product_Description", label: "Description", type: "textarea" },
  { key: "Product_Price", label: "Price", type: "number" },
  { key: "Product_Cost", label: "Cost", type: "number" },
  { key: "Product_Stock", label: "Stock", type: "number" },
  { key: "Weight_Kg", label: "Weight (kg)", type: "number" },
  { key: "Length_Cm", label: "Length (m)", type: "number" },
  { key: "Width_Cm", label: "Width (m)", type: "number" },
  { key: "Height_Cm", label: "Height (m)", type: "number" },
  { key: "specifications", label: "Specifications", type: "specifications" },
] as const

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

const productSubSubDepartmentId = () =>
  Number(detail.value?.product?.Product_Sub_Sub_Department_Id ?? detail.value?.product?.product_sub_sub_department_id ?? 0)

const currentSpecMap = () => {
  const map = new Map<number, number>()

  for (const spec of detail.value?.specs || []) {
    const descId = Number(spec.Product_Specification_Description_Id ?? spec.product_specification_description_id ?? spec.description?.id ?? 0)
    const valueId = Number(spec.product_specification_value_id ?? spec.Product_Specification_Value_Id ?? spec.value?.id ?? 0)

    if (descId && valueId) {
      map.set(descId, valueId)
    }
  }

  return map
}

const loadEditableSpecifications = async (target: "request" | "temp") => {
  const subSubDeptId = productSubSubDepartmentId()
  const selected = currentSpecMap()
  const rowsRef = target === "request" ? requestSpecRows : tempEditSpecRows
  const loadingRef = target === "request" ? requestSpecsLoading : tempEditSpecsLoading
  const errorRef = target === "request" ? requestSpecsError : tempEditSpecsError

  rowsRef.value = []
  errorRef.value = null
  if (target === "temp") tempEditSpecsLoaded.value = false

  if (!subSubDeptId) {
    errorRef.value = "Product category is missing, so specifications cannot be loaded."
    return
  }

  loadingRef.value = true
  try {
    const { data } = await api.get(`/vendor/api/specifications/sub-sub-department/${subSubDeptId}`)
    rowsRef.value = (data || []).map((d: AnyObj) => ({
      descriptionId: Number(d.id),
      name: d.Product_Specification_Description_Name || d.name || `Spec #${d.id}`,
      values: (d.values || []).map((v: AnyObj) => ({
        id: Number(v.id),
        value: v.value || v.Value || `Value #${v.id}`,
      })),
      selectedValueId: selected.get(Number(d.id)) ?? null,
    }))
    if (target === "temp") tempEditSpecsLoaded.value = true
  } catch (e: any) {
    errorRef.value = e?.response?.data?.message || "Failed to load specification values."
  } finally {
    loadingRef.value = false
  }
}

const selectedSpecPayload = (rows: SpecEditRow[]) =>
  rows
    .filter((row) => row.selectedValueId && row.selectedValueId > 0)
    .map((row) => ({
      description_id: row.descriptionId,
      value_id: row.selectedValueId,
    }))

const openRequestModal = async () => {
  const p = detail.value?.product || {}
  selectedRequestKeys.value = []
  for (const field of requestFields) {
    requestValues[field.key] = p[field.key] ?? ""
  }
  requestComment.value = ""
  requestError.value = null
  requestModalOpen.value = true
  await loadEditableSpecifications("request")
}

const closeRequestModal = () => {
  requestModalOpen.value = false
  requestComment.value = ""
  requestError.value = null
  selectedRequestKeys.value = []
  requestSpecRows.value = []
  requestSpecsError.value = null
}

const submitRequest = async () => {
  if (!detail.value || detail.value.mode !== "approved") return
  requestError.value = null

  if (selectedRequestKeys.value.length === 0) {
    requestError.value = "Please choose at least one field to update."
    return
  }

  const changes: Record<string, any> = {}
  for (const key of selectedRequestKeys.value) {
    if (key === "specifications") {
      const specs = selectedSpecPayload(requestSpecRows.value)
      if (specs.length === 0) {
        requestError.value = "Please select at least one specification value."
        return
      }
      changes.specifications = specs
      continue
    }

    changes[key] = requestValues[key]
  }

  if (["Length_Cm", "Width_Cm", "Height_Cm"].some((key) => selectedRequestKeys.value.includes(key))) {
    const l = Number(changes.Length_Cm ?? detail.value.product?.Length_Cm ?? 0)
    const w = Number(changes.Width_Cm ?? detail.value.product?.Width_Cm ?? 0)
    const h = Number(changes.Height_Cm ?? detail.value.product?.Height_Cm ?? 0)
    changes.Volume_Cbm = Number.isFinite(l * w * h) ? Number((l * w * h).toFixed(6)) : 0
  }

  busy.value = true
  try {
    await api.post(`/vendor/api/products/approved/${productId.value}/request-update`, {
      comment: requestComment.value.trim() || null,
      changes,
    })
    closeRequestModal()
    await fetchDetail()
  } catch (e: any) {
    requestError.value = e?.response?.data?.message || "Failed to submit update request."
  } finally {
    busy.value = false
  }
}

const canResubmitTemp = computed(() => {
  if (!detail.value || detail.value.mode !== "pending") return false
  const status = String(detail.value.product?.Submission_Status || "").toLowerCase()
  return ["pending", "needs_changes", "rejected"].includes(status)
})

const openTempEditModal = async () => {
  const p = detail.value?.product || {}
  tempEdit.name = p.Product_Name || ""
  tempEdit.name_ar = p.Product_Name_Ar || ""
  tempEdit.description = p.Description || ""
  tempEdit.description_ar = p.Description_Ar || ""
  tempEdit.price = Number(p.Product_Price || 0)
  tempEdit.cost = p.Product_Cost == null ? null : Number(p.Product_Cost)
  tempEdit.stock = Number(p.Product_Stock || 0)
  tempEdit.Weight_Kg = Number(p.Weight_Kg || 0)
  tempEdit.Length_Cm = Number(p.Length_Cm || 0)
  tempEdit.Width_Cm = Number(p.Width_Cm || 0)
  tempEdit.Height_Cm = Number(p.Height_Cm || 0)
  tempEditComment.value = ""
  tempEditError.value = null
  tempEditModalOpen.value = true
  await loadEditableSpecifications("temp")
}

const closeTempEditModal = () => {
  tempEditModalOpen.value = false
  tempEditError.value = null
  tempEditComment.value = ""
  tempEditSpecRows.value = []
  tempEditSpecsError.value = null
  tempEditSpecsLoaded.value = false
}

const submitTempEdit = async () => {
  if (!detail.value || detail.value.mode !== "pending") return
  tempEditError.value = null

  if (!tempEdit.name.trim() || !tempEdit.name_ar.trim() || !tempEdit.description.trim()) {
    tempEditError.value = "Name, Arabic name, and description are required."
    return
  }

  busy.value = true
  try {
    const payload: Record<string, any> = {
      ...tempEdit,
      volume_type: "m",
      comment: tempEditComment.value.trim() || null,
    }

    if (tempEditSpecsLoaded.value) {
      payload.specs = selectedSpecPayload(tempEditSpecRows.value)
    }

    await api.patch(`/vendor/api/products-temp/${productId.value}`, payload)
    closeTempEditModal()
    await fetchDetail()
  } catch (e: any) {
    tempEditError.value = e?.response?.data?.message || "Failed to resubmit product changes."
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
    "Product_Specification_Description_Name",
    "Specification_Name",
    "Product_Specification_Name",
    "Name",
    "name",
    "Specification",
  ])

const specHeaderAr = (s: AnyObj) =>
  textOf(s?.description, [
    "Product_Specification_Description_Name_Ar",
    "Specification_Name_Ar",
    "Product_Specification_Name_Ar",
    "Name_Ar",
    "name_ar",
  ])

const specValue = (s: AnyObj) =>
  textOf(s?.value, [
    "value",
    "Value",
    "Specification_Value",
    "Name",
    "name",
    "Value_Text",
  ])

const specValueAr = (s: AnyObj) =>
  textOf(s?.value, [
    "value_ar",
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

const goEditTemp = () => {
  router.push({ name: "vendor.product.pending.edit", params: { id: productId.value } })
}

const goEditApproved = () => {
  router.push({ name: "vendor.product.approved.edit", params: { id: productId.value } })
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
            @click="goEditApproved"
          >
            {{ detail?.has_open_update_request ? "Update Request Pending" : "Edit Product" }}
          </button>

          <button
            v-if="canResubmitTemp"
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300 hover:shadow-sm transition"
            @click="goEditTemp"
          >
            Edit & Resubmit
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
                    {{ detail.product?.Description || detail.product?.Product_Description || "-" }}
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200/70 dark:border-slate-800 p-3">
                  <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                    Description (Arabic)
                  </p>
                  <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">
                    {{ detail.product?.Description_Ar || detail.product?.Product_Description_Ar || "-" }}
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
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Dimensions (m)</p>
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
                  <div
                    v-if="item.Requested_Specifications_Display?.length"
                    class="mt-2 flex flex-wrap gap-2"
                  >
                    <span
                      v-for="spec in item.Requested_Specifications_Display"
                      :key="`${item.id}-${spec.description_id}-${spec.value_id}`"
                      class="px-2 py-1 rounded-lg border border-primary-200 bg-primary-50 text-[11px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300"
                    >
                      {{ spec.description }}: {{ spec.value }}
                    </span>
                  </div>
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
      <div class="w-full max-w-3xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 shadow-2xl overflow-hidden">
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
          <div class="mb-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
              Choose fields to update
            </p>
            <div class="grid sm:grid-cols-2 gap-2">
              <label
                v-for="field in requestFields"
                :key="field.key"
                class="flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 py-2 text-xs text-slate-700 dark:text-slate-200"
              >
                <input
                  v-model="selectedRequestKeys"
                  type="checkbox"
                  :value="field.key"
                  class="rounded border-slate-300"
                />
                <span>{{ field.label }}</span>
              </label>
            </div>
          </div>

          <div v-if="selectedRequestKeys.length" class="mb-4 grid sm:grid-cols-2 gap-3">
            <div
              v-for="key in selectedRequestKeys"
              :key="key"
              :class="['textarea', 'specifications'].includes(String(requestFields.find(f => f.key === key)?.type || '')) ? 'sm:col-span-2' : ''"
            >
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                {{ requestFields.find(f => f.key === key)?.label || key }}
              </label>
              <div
                v-if="requestFields.find(f => f.key === key)?.type === 'specifications'"
                class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3"
              >
                <div v-if="requestSpecsLoading" class="text-xs text-slate-500 dark:text-slate-400">
                  Loading specification values...
                </div>
                <div v-else-if="requestSpecsError" class="text-xs text-rose-600">
                  {{ requestSpecsError }}
                </div>
                <div v-else-if="requestSpecRows.length === 0" class="text-xs text-slate-500 dark:text-slate-400">
                  No specifications configured for this product category.
                </div>
                <div v-else class="grid sm:grid-cols-2 gap-3">
                  <div v-for="row in requestSpecRows" :key="row.descriptionId">
                    <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                      {{ row.name }}
                    </label>
                    <select
                      v-model.number="row.selectedValueId"
                      class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-800 dark:text-slate-100"
                      :disabled="row.values.length === 0"
                    >
                      <option :value="null">Select value</option>
                      <option v-for="opt in row.values" :key="opt.id" :value="opt.id">
                        {{ opt.value }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>
              <textarea
                v-else-if="requestFields.find(f => f.key === key)?.type === 'textarea'"
                v-model="requestValues[key]"
                rows="3"
                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-primary-500/30"
              />
              <input
                v-else
                v-model="requestValues[key]"
                :type="requestFields.find(f => f.key === key)?.type || 'text'"
                step="0.001"
                min="0"
                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-primary-500/30"
              />
            </div>
          </div>

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

    <!-- Edit Temporary Product Modal -->
    <div
      v-if="tempEditModalOpen"
      class="fixed inset-0 z-[60] bg-slate-900/50 backdrop-blur-[2px] p-4 grid place-items-center"
      @click.self="closeTempEditModal"
    >
      <div class="w-full max-w-3xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Edit & Resubmit Product</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Update the product and send it back to admin review.</p>
          </div>
          <button
            class="h-8 w-8 grid place-items-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-900"
            @click="closeTempEditModal"
          >
            x
          </button>
        </div>

        <div class="p-4 grid sm:grid-cols-2 gap-3">
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Product Name</label>
            <input v-model="tempEdit.name" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Arabic Name</label>
            <input v-model="tempEdit.name_ar" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Description</label>
            <textarea v-model="tempEdit.description" rows="3" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Arabic Description</label>
            <textarea v-model="tempEdit.description_ar" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Price</label>
            <input v-model.number="tempEdit.price" type="number" min="0" step="0.001" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Stock</label>
            <input v-model.number="tempEdit.stock" type="number" min="0" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Cost</label>
            <input v-model.number="tempEdit.cost" type="number" min="0" step="0.001" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Weight (kg)</label>
            <input v-model.number="tempEdit.Weight_Kg" type="number" min="0" step="0.001" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Length (m)</label>
            <input v-model.number="tempEdit.Length_Cm" type="number" min="0" step="0.001" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Width (m)</label>
            <input v-model.number="tempEdit.Width_Cm" type="number" min="0" step="0.001" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Height (m)</label>
            <input v-model.number="tempEdit.Height_Cm" type="number" min="0" step="0.001" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
          </div>
          <div class="sm:col-span-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 p-3">
            <div class="flex items-center justify-between gap-2 mb-3">
              <div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Specifications</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Update the selected values before resubmitting.</p>
              </div>
              <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ tempEditSpecRows.length }} row(s)</span>
            </div>

            <div v-if="tempEditSpecsLoading" class="text-xs text-slate-500 dark:text-slate-400">
              Loading specification values...
            </div>
            <div v-else-if="tempEditSpecsError" class="text-xs text-rose-600">
              {{ tempEditSpecsError }}
            </div>
            <div v-else-if="tempEditSpecRows.length === 0" class="text-xs text-slate-500 dark:text-slate-400">
              No specifications configured for this product category.
            </div>
            <div v-else class="grid sm:grid-cols-2 gap-3">
              <div v-for="row in tempEditSpecRows" :key="row.descriptionId">
                <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                  {{ row.name }}
                </label>
                <select
                  v-model.number="row.selectedValueId"
                  class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-800 dark:text-slate-100"
                  :disabled="row.values.length === 0"
                >
                  <option :value="null">Select value</option>
                  <option v-for="opt in row.values" :key="opt.id" :value="opt.id">
                    {{ opt.value }}
                  </option>
                </select>
              </div>
            </div>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Note to admin</label>
            <textarea v-model="tempEditComment" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="Explain what you changed..." />
          </div>

          <div
            v-if="tempEditError"
            class="sm:col-span-2 rounded-xl border border-rose-200/70 bg-rose-50 px-3 py-2 text-xs text-rose-700"
          >
            {{ tempEditError }}
          </div>
        </div>

        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
          <button
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200"
            :disabled="busy"
            @click="closeTempEditModal"
          >
            Cancel
          </button>
          <button
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300 disabled:opacity-60"
            :disabled="busy"
            @click="submitTempEdit"
          >
            <span v-if="busy" class="inline-block h-3.5 w-3.5 mr-2 align-middle animate-spin rounded-full border-2 border-current border-t-transparent"></span>
            Resubmit for Review
          </button>
        </div>
      </div>
    </div>
  </VendorLayout>
</template>
