<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"

type Opt = { id: number; name: string; name_ar?: string }
type AnyObj = Record<string, any>
type SpecValue = { id: number; value: string }
type SpecRow = {
  descriptionId: number
  name: string
  values: SpecValue[]
  selectedValueId: number | null
}

const route = useRoute()
const router = useRouter()
const productId = computed(() => Number(route.params.id))
const R2 = import.meta.env.VITE_R2_URL || ""
const isApprovedUpdate = computed(() => route.name === "vendor.product.approved.edit")

const step = ref<1 | 2 | 3 | 4 | 5>(1)
const loading = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const initialized = ref(false)

const departments = ref<Opt[]>([])
const subDepartments = ref<Opt[]>([])
const subSubDepartments = ref<Opt[]>([])
const types = ref<Opt[]>([])
const brands = ref<Opt[]>([])
const manufactures = ref<Opt[]>([])

const specRows = ref<SpecRow[]>([])
const specsLoading = ref(false)
const specsError = ref<string | null>(null)

const existingImages = ref<AnyObj[]>([])
const removeImageIds = ref<number[]>([])
const defaultImageId = ref<number | null>(null)
const uploadedImages = ref<File[]>([])
const previewUrls = ref<string[]>([])
const note = ref("")

const form = reactive({
  product_department_id: 0,
  product_sub_department_id: 0,
  product_sub_sub_department_id: 0,
  product_type_id: 0,
  product_brand_id: 0,
  product_manufacture_id: 0,
  name: "",
  name_ar: "",
  description: "",
  description_ar: "",
  price: 0,
  stock: 0,
  volume_type: "m" as "mm" | "cm" | "m" | "in" | "ft",
  Weight_Kg: 0,
  Length_Cm: 0,
  Width_Cm: 0,
  Height_Cm: 0,
})

const volumeUnitOptions = [
  { value: "mm", label: "Millimeter (mm)" },
  { value: "cm", label: "Centimeter (cm)" },
  { value: "m", label: "Meter (m)" },
  { value: "in", label: "Inch (in)" },
  { value: "ft", label: "Foot (ft)" },
]

const stepTitle = computed(() => {
  if (step.value === 1) return "Product Details"
  if (step.value === 2) return "Specifications"
  if (step.value === 3) return "Images"
  if (step.value === 4) return "Note to Admin"
  return "Edit Summary"
})

const pageTitle = computed(() => isApprovedUpdate.value ? "Request Product Update" : "Edit & Resubmit Product")
const pageDescription = computed(() =>
  isApprovedUpdate.value
    ? "Update live product information, specifications, and images. Your changes will be sent to admin for approval."
    : "Update product details, specifications, and images before sending it back to admin review."
)
const submitLabel = computed(() => isApprovedUpdate.value ? "Submit Update Request" : "Resubmit for Review")
const backPath = computed(() => isApprovedUpdate.value ? `/products/${productId.value}` : `/products/temp/${productId.value}`)
const summaryDescription = computed(() =>
  isApprovedUpdate.value
    ? "Product update request will be sent to admin approval."
    : "Product will return to pending review."
)
const dimensionUnitLabel = computed(() => volumeUnitOptions.find((u) => u.value === form.volume_type)?.label || "Meter")

const step1Valid = computed(() => {
  return (
    form.product_department_id > 0 &&
    form.product_sub_department_id > 0 &&
    form.product_sub_sub_department_id > 0 &&
    form.product_type_id > 0 &&
    form.name.trim().length > 1 &&
    form.name_ar.trim().length > 1 &&
    form.description.trim().length > 3 &&
    Number(form.price) > 0 &&
    Number(form.stock) >= 0
  )
})

const activeExistingImages = computed(() =>
  existingImages.value.filter((img) => !removeImageIds.value.includes(Number(img.id)))
)

const selectedSpecsCount = computed(() => specRows.value.filter((row) => row.selectedValueId).length)

const imageSrc = (img: AnyObj) => {
  const p = img?.Image_Path || img?.image_path || ""
  if (!p) return ""
  if (String(p).startsWith("http://") || String(p).startsWith("https://")) return String(p)
  return R2 ? `${R2}/${String(p).replace(/^\/+/, "")}` : String(p)
}

const currentSpecMap = (rows: AnyObj[]) => {
  const map = new Map<number, number>()
  for (const spec of rows || []) {
    const descId = Number(spec.Product_Specification_Description_Id ?? spec.product_specification_description_id ?? spec.description?.id ?? 0)
    const valueId = Number(spec.product_specification_value_id ?? spec.value?.id ?? 0)
    if (descId && valueId) map.set(descId, valueId)
  }
  return map
}

function resetMessages() {
  error.value = null
  success.value = null
}

async function loadCatalog() {
  const [deptRes, typeRes, brandRes, manuRes] = await Promise.all([
    api.get("/vendor/api/catalog/departments"),
    api.get("/vendor/api/catalog/types"),
    api.get("/vendor/api/catalog/brands"),
    api.get("/vendor/api/catalog/manufactures"),
  ])

  departments.value = deptRes.data.map((x: any) => ({ id: x.id, name: x.Product_Department_Name, name_ar: x.Product_Department_Name_Ar }))
  types.value = typeRes.data.map((x: any) => ({ id: x.id, name: x.Product_Types_Name, name_ar: x.Product_Types_Name_Ar }))
  brands.value = brandRes.data.map((x: any) => ({ id: x.id, name: x.Products_Brands_Name, name_ar: x.Products_Brands_Name_Ar }))
  manufactures.value = manuRes.data.map((x: any) => ({ id: x.id, name: x.Products_Manufacture_Name, name_ar: x.Products_Manufacture_Name_Ar }))
}

async function loadSubDepartments(reset = true) {
  subDepartments.value = []
  subSubDepartments.value = []
  if (reset) {
    form.product_sub_department_id = 0
    form.product_sub_sub_department_id = 0
    specRows.value = []
  }
  if (!form.product_department_id) return

  const res = await api.get(`/vendor/api/catalog/sub-departments/${form.product_department_id}`)
  subDepartments.value = res.data.map((x: any) => ({ id: x.id, name: x.Sub_Department_Name, name_ar: x.Sub_Department_Name_Ar }))
}

async function loadSubSubDepartments(reset = true) {
  subSubDepartments.value = []
  if (reset) {
    form.product_sub_sub_department_id = 0
    specRows.value = []
  }
  if (!form.product_sub_department_id) return

  const res = await api.get(`/vendor/api/catalog/sub-sub-departments/${form.product_sub_department_id}`)
  subSubDepartments.value = res.data.map((x: any) => ({ id: x.id, name: x.Product_Sub_Sub_Department_Name, name_ar: x.Product_Sub_Sub_Department_Name_Ar }))
}

async function loadSpecifications(selected = new Map<number, number>()) {
  specsError.value = null
  specRows.value = []
  if (!form.product_sub_sub_department_id) return

  specsLoading.value = true
  try {
    const res = await api.get(`/vendor/api/specifications/sub-sub-department/${form.product_sub_sub_department_id}`)
    specRows.value = (res.data || []).map((d: any) => ({
      descriptionId: Number(d.id),
      name: d.Product_Specification_Description_Name || d.name || `Spec #${d.id}`,
      values: (d.values || []).map((v: any) => ({
        id: Number(v.id),
        value: v.value || v.Value || `Value #${v.id}`,
      })),
      selectedValueId: selected.get(Number(d.id)) ?? null,
    }))
  } catch (e: any) {
    specsError.value = e?.response?.data?.message || "Failed to load specifications."
  } finally {
    specsLoading.value = false
  }
}

async function loadProduct() {
  loading.value = true
  error.value = null
  initialized.value = false

  try {
    await loadCatalog()
    const { data } = await api.get(`/vendor/api/products/${isApprovedUpdate.value ? "approved" : "pending"}/${productId.value}`)
    const detail = data?.data || {}
    const p = detail.product || {}

    form.product_department_id = Number(p.Product_Department_Id || 0)
    await loadSubDepartments(false)
    form.product_sub_department_id = Number(p.Product_Sub_Department_Id || 0)
    await loadSubSubDepartments(false)
    form.product_sub_sub_department_id = Number(p.Product_Sub_Sub_Department_Id || 0)

    form.product_type_id = Number(p.Product_Type_Id || 0)
    form.product_brand_id = Number(p.Product_Brand_Id || 0)
    form.product_manufacture_id = Number(p.Product_Manufacture_Id || 0)
    form.name = p.Product_Name || ""
    form.name_ar = p.Product_Name_Ar || ""
    form.description = p.Description || p.Product_Description || ""
    form.description_ar = p.Description_Ar || p.Product_Description_Ar || ""
    form.price = Number(p.Product_Price || 0)
    form.stock = Number(p.Product_Stock || 0)
    form.volume_type = "m"
    form.Weight_Kg = Number(p.Weight_Kg || 0)
    form.Length_Cm = Number(p.Length_Cm || 0)
    form.Width_Cm = Number(p.Width_Cm || 0)
    form.Height_Cm = Number(p.Height_Cm || 0)

    existingImages.value = detail.images || []
    defaultImageId.value = isApprovedUpdate.value
      ? null
      : (Number(existingImages.value.find((img) => Number(img.Is_Default) === 1)?.id || existingImages.value[0]?.id || 0) || null)
    await loadSpecifications(currentSpecMap(detail.specs || []))
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load product for editing."
  } finally {
    initialized.value = true
    loading.value = false
  }
}

function goNext() {
  resetMessages()
  if (step.value === 1 && !step1Valid.value) return
  if (step.value < 5) step.value = (step.value + 1) as any
}

function goPrev() {
  resetMessages()
  if (step.value > 1) step.value = (step.value - 1) as any
}

function handleFileChange(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files) return

  for (const f of Array.from(files)) {
    uploadedImages.value.push(f)
    previewUrls.value.push(URL.createObjectURL(f))
  }
  ;(e.target as HTMLInputElement).value = ""
}

function removeNewImage(index: number) {
  uploadedImages.value.splice(index, 1)
  URL.revokeObjectURL(previewUrls.value[index])
  previewUrls.value.splice(index, 1)
}

function toggleExistingImageRemoval(id: number) {
  if (removeImageIds.value.includes(id)) {
    removeImageIds.value = removeImageIds.value.filter((x) => x !== id)
    if (!defaultImageId.value) defaultImageId.value = id
    return
  }

  removeImageIds.value.push(id)
  if (defaultImageId.value === id) {
    defaultImageId.value = activeExistingImages.value.find((img) => Number(img.id) !== id)?.id || null
  }
}

const reviewRows = computed(() => [
  { k: "Category", v: departments.value.find((d) => d.id === form.product_department_id)?.name ?? "-" },
  { k: "Sub Category", v: subDepartments.value.find((d) => d.id === form.product_sub_department_id)?.name ?? "-" },
  { k: "Sub-Sub Category", v: subSubDepartments.value.find((d) => d.id === form.product_sub_sub_department_id)?.name ?? "-" },
  { k: "Type", v: types.value.find((t) => t.id === form.product_type_id)?.name ?? "-" },
  { k: "Brand", v: brands.value.find((b) => b.id === form.product_brand_id)?.name ?? "-" },
  { k: "Manufacture", v: manufactures.value.find((m) => m.id === form.product_manufacture_id)?.name ?? "-" },
  { k: "Name", v: form.name || "-" },
  { k: "Price", v: Number(form.price || 0).toFixed(3) },
  { k: "Stock", v: String(form.stock) },
  { k: "Weight (kg)", v: String(form.Weight_Kg) },
  { k: `Dimensions (${dimensionUnitLabel.value})`, v: `${form.Length_Cm} x ${form.Width_Cm} x ${form.Height_Cm}` },
])

async function submit() {
  resetMessages()
  busy.value = true

  try {
    const fd = new FormData()
    fd.append("product_department_id", String(form.product_department_id))
    fd.append("product_sub_department_id", String(form.product_sub_department_id))
    fd.append("product_sub_sub_department_id", String(form.product_sub_sub_department_id))
    fd.append("product_type_id", String(form.product_type_id))
    if (form.product_brand_id) fd.append("product_brand_id", String(form.product_brand_id))
    if (form.product_manufacture_id) fd.append("product_manufacture_id", String(form.product_manufacture_id))
    fd.append("name", form.name)
    fd.append("name_ar", form.name_ar)
    fd.append("description", form.description)
    fd.append("description_ar", form.description_ar || "")
    fd.append("price", String(form.price))
    fd.append("stock", String(form.stock))
    fd.append("volume_type", form.volume_type)
    fd.append("Weight_Kg", String(form.Weight_Kg))
    fd.append("Length_Cm", String(form.Length_Cm))
    fd.append("Width_Cm", String(form.Width_Cm))
    fd.append("Height_Cm", String(form.Height_Cm))
    fd.append("comment", note.value.trim() || "")

    specRows.value
      .filter((row) => row.selectedValueId && row.selectedValueId > 0)
      .forEach((row, index) => {
        fd.append(`specs[${index}][description_id]`, String(row.descriptionId))
        fd.append(`specs[${index}][value_id]`, String(row.selectedValueId))
      })

    removeImageIds.value.forEach((id, index) => {
      fd.append(`remove_image_ids[${index}]`, String(id))
    })
    if (!isApprovedUpdate.value && defaultImageId.value && !removeImageIds.value.includes(defaultImageId.value)) {
      fd.append("default_image_id", String(defaultImageId.value))
    }
    uploadedImages.value.forEach((file) => fd.append("file[]", file))

    if (isApprovedUpdate.value) {
      await api.post(`/vendor/api/products/approved/${productId.value}/request-update`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      })
      success.value = "Product update request submitted for admin approval."
      await router.push(`/products/${productId.value}`)
    } else {
      await api.post(`/vendor/api/products-temp/${productId.value}/resubmit`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      })
      success.value = "Product changes submitted for admin review."
      await router.push(`/products/temp/${productId.value}`)
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to submit product changes."
  } finally {
    busy.value = false
  }
}

watch(
  () => form.product_department_id,
  async () => {
    if (!initialized.value) return
    await loadSubDepartments(true)
  }
)

watch(
  () => form.product_sub_department_id,
  async () => {
    if (!initialized.value) return
    await loadSubSubDepartments(true)
  }
)

watch(
  () => form.product_sub_sub_department_id,
  async () => {
    if (!initialized.value) return
    await loadSpecifications()
  }
)

onMounted(loadProduct)
onBeforeUnmount(() => {
  previewUrls.value.forEach((url) => URL.revokeObjectURL(url))
})
</script>

<template>
  <VendorLayout>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 py-6 sm:py-8">
      <VendorPageHeader
        :title="pageTitle"
        :description="pageDescription"
        icon="fa-solid fa-pen-to-square"
      >
        <template #actions>
          <button
            class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:shadow-sm transition"
            @click="router.push(backPath)"
          >
            Back
          </button>
        </template>
      </VendorPageHeader>

      <div class="mt-5 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-primary-600 text-white flex items-center justify-center font-semibold">
              {{ step }}
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ stepTitle }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">Step {{ step }} of 5</p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-50"
              :disabled="step === 1 || loading"
              @click="goPrev"
            >
              Back
            </button>
            <button
              v-if="step < 5"
              class="px-3 py-2 rounded-xl text-xs font-semibold text-white bg-primary-600 disabled:opacity-50"
              :disabled="loading || (step === 1 && !step1Valid)"
              @click="goNext"
            >
              Continue
            </button>
            <button
              v-else
              class="px-3 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 disabled:opacity-50"
              :disabled="busy || loading"
              @click="submit"
            >
              {{ busy ? "Submitting..." : submitLabel }}
            </button>
          </div>
        </div>
        <div class="h-[3px] bg-slate-100 dark:bg-slate-900">
          <div class="h-full bg-primary-600 transition-all duration-300" :style="{ width: `${(step / 5) * 100}%` }" />
        </div>
      </div>

      <div v-if="error" class="mt-4 rounded-2xl border border-rose-200/70 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </div>
      <div v-if="success" class="mt-4 rounded-2xl border border-emerald-200/70 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ success }}
      </div>

      <div v-if="loading" class="mt-5 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 p-10 text-center">
        <div class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-primary-600"></div>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Loading product...</p>
      </div>

      <div v-else class="mt-5 space-y-4">
        <main>
          <transition name="fade-slide" mode="out-in">
            <section
              v-if="step === 1"
              key="details"
              class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden"
            >
              <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Product Details</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Category, pricing, stock, and dimensions.</p>
              </div>

              <div class="p-5 grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Category</label>
                  <select v-model.number="form.product_department_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option :value="0" disabled>Select category</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Sub Category</label>
                  <select v-model.number="form.product_sub_department_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option :value="0" disabled>Select sub category</option>
                    <option v-for="d in subDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Sub-Sub Category</label>
                  <select v-model.number="form.product_sub_sub_department_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option :value="0" disabled>Select sub-sub category</option>
                    <option v-for="d in subSubDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Type</label>
                  <select v-model.number="form.product_type_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option :value="0" disabled>Select type</option>
                    <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Brand</label>
                  <select v-model.number="form.product_brand_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option :value="0">No brand</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Manufacture</label>
                  <select v-model.number="form.product_manufacture_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option :value="0">No manufacture</option>
                    <option v-for="m in manufactures" :key="m.id" :value="m.id">{{ m.name }}</option>
                  </select>
                </div>
                <div class="sm:col-span-2">
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product Name</label>
                  <input v-model="form.name" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product Name (Arabic)</label>
                  <input v-model="form.name_ar" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Description</label>
                  <textarea v-model="form.description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Arabic Description</label>
                  <textarea v-model="form.description_ar" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Price</label>
                  <input v-model.number="form.price" type="number" min="0" step="0.001" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Stock</label>
                  <input v-model.number="form.stock" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Weight (kg)</label>
                  <input v-model.number="form.Weight_Kg" type="number" min="0" step="0.001" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Dimension Unit</label>
                  <select v-model="form.volume_type" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option v-for="u in volumeUnitOptions" :key="u.value" :value="u.value">{{ u.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Length</label>
                  <input v-model.number="form.Length_Cm" type="number" min="0" step="0.001" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Width</label>
                  <input v-model.number="form.Width_Cm" type="number" min="0" step="0.001" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Height</label>
                  <input v-model.number="form.Height_Cm" type="number" min="0" step="0.001" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                </div>
              </div>
            </section>

            <section
              v-else-if="step === 2"
              key="specs"
              class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden"
            >
              <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Specifications</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Review and update the specification values.</p>
              </div>
              <div class="p-5">
                <div v-if="specsLoading" class="text-xs text-slate-500 dark:text-slate-400">Loading specifications...</div>
                <div v-else-if="specRows.length === 0" class="text-xs text-slate-500 dark:text-slate-400">No specifications configured for this category.</div>
                <div v-else class="grid md:grid-cols-2 gap-3">
                  <div v-for="row in specRows" :key="row.descriptionId" class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-3">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ row.name }}</label>
                    <select
                      v-model.number="row.selectedValueId"
                      class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 py-2 text-sm"
                      :disabled="row.values.length === 0"
                    >
                      <option :value="null">Select value</option>
                      <option v-for="opt in row.values" :key="opt.id" :value="opt.id">{{ opt.value }}</option>
                    </select>
                  </div>
                </div>
                <p v-if="specsError" class="mt-3 text-xs text-rose-600">{{ specsError }}</p>
              </div>
            </section>

            <section
              v-else-if="step === 3"
              key="images"
              class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden"
            >
              <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Images</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Keep, remove, or upload product photos.</p>
              </div>
              <div class="p-5 space-y-5">
                <div>
                  <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Current Images</p>
                  <div v-if="existingImages.length === 0" class="text-xs text-slate-500 dark:text-slate-400">No existing images.</div>
                  <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                      v-for="img in existingImages"
                      :key="img.id"
                      class="rounded-2xl border bg-white dark:bg-slate-950 overflow-hidden"
                      :class="removeImageIds.includes(Number(img.id)) ? 'border-rose-200 opacity-60' : 'border-slate-200/70 dark:border-slate-800'"
                    >
                      <div class="aspect-square bg-slate-100 dark:bg-slate-900">
                        <img v-if="imageSrc(img)" :src="imageSrc(img)" class="w-full h-full object-cover" />
                      </div>
                      <div class="p-3 space-y-2">
                        <label v-if="!isApprovedUpdate" class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                          <input
                            v-model.number="defaultImageId"
                            type="radio"
                            :value="Number(img.id)"
                            :disabled="removeImageIds.includes(Number(img.id))"
                          />
                          Default image
                        </label>
                        <button
                          type="button"
                          class="w-full px-3 py-2 rounded-xl text-xs font-semibold border"
                          :class="removeImageIds.includes(Number(img.id)) ? 'border-slate-200 text-slate-600' : 'border-rose-200 text-rose-700 bg-rose-50'"
                          @click="toggleExistingImageRemoval(Number(img.id))"
                        >
                          {{ removeImageIds.includes(Number(img.id)) ? "Undo Remove" : "Remove Image" }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div>
                  <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Upload New Images</p>
                  <label class="block rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-5 text-center cursor-pointer">
                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Click to upload images</span>
                    <input class="hidden" type="file" multiple accept="image/*" @change="handleFileChange" />
                  </label>
                  <div v-if="previewUrls.length" class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="(url, index) in previewUrls" :key="url" class="relative rounded-2xl overflow-hidden border border-slate-200/70 dark:border-slate-800">
                      <img :src="url" class="h-40 w-full object-cover" />
                      <button type="button" class="absolute top-2 right-2 h-8 w-8 rounded-full bg-black/60 text-white" @click="removeNewImage(index)">x</button>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section
              v-else-if="step === 4"
              key="note"
              class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden"
            >
              <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Note to Admin</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Write the message admin should see before final review.</p>
              </div>
              <div class="p-5">
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Note to admin</label>
                <textarea v-model="note" rows="6" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="Explain what you changed..." />
              </div>
            </section>

            <section
              v-else
              key="summary"
              class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-sm overflow-hidden"
            >
              <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Edit Summary</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ summaryDescription }}</p>
              </div>
              <div class="p-5 space-y-4">
                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
                  <div v-for="row in reviewRows" :key="row.k" class="rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ row.k }}</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mt-1 truncate">{{ row.v }}</p>
                  </div>
                </div>
                <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4 text-xs text-slate-600 dark:text-slate-300 space-y-1">
                  <p>Specifications selected: <b>{{ selectedSpecsCount }}</b> / {{ specRows.length }}</p>
                  <p>Existing images kept: <b>{{ activeExistingImages.length }}</b></p>
                  <p>New images uploaded: <b>{{ uploadedImages.length }}</b></p>
                  <p>Images removed: <b>{{ removeImageIds.length }}</b></p>
                </div>
                <div v-if="note.trim()" class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Note to Admin</p>
                  <p class="mt-2 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ note }}</p>
                </div>
              </div>
            </section>
          </transition>
        </main>
      </div>

      <div class="sticky bottom-3 z-30 mt-6">
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/90 dark:bg-slate-950/80 backdrop-blur-xl shadow-sm px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
          <div class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
            Step <span class="font-semibold text-slate-800 dark:text-slate-100">{{ step }}</span> of 5
          </div>
          <div class="flex items-center gap-2 ml-auto">
            <button class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-50" :disabled="step === 1" @click="goPrev">
              Back
            </button>
            <button v-if="step < 5" class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-primary-600 disabled:opacity-50" :disabled="step === 1 && !step1Valid" @click="goNext">
              Continue
            </button>
            <button v-else class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 disabled:opacity-50" :disabled="busy" @click="submit">
              {{ busy ? "Submitting..." : submitLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </VendorLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 180ms ease;
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
