<script setup lang="ts">
  import { computed, onMounted, reactive, ref, watch } from "vue"
  import { useRouter } from "vue-router"
  import VendorLayout from "@/components/layout/VendorLayout.vue"
  import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
  import api from "@/services/api"

  type Opt = { id: number; name: string; name_ar?: string }

  const router = useRouter()

  const step = ref<1 | 2 | 3 | 4>(1)
  const busy = ref(false)
  const error = ref<string | null>(null)
  const success = ref<string | null>(null)

  const nextId = ref<number | null>(null)
  const randomDigits = ref<number>(Math.floor(10000 + Math.random() * 90000))

  const departments = ref<Opt[]>([])
  const subDepartments = ref<Opt[]>([])
  const subSubDepartments = ref<Opt[]>([])
  const types = ref<Opt[]>([])
  const brands = ref<Opt[]>([])
  const manufactures = ref<Opt[]>([])

  const uploadedImages = ref<File[]>([])
  const previewUrls = ref<string[]>([])


  type SpecValue = { id: number; value: string }

type SpecRow = {
  descriptionId: number
  name: string          // Product_Specification_Description_Name
  values: SpecValue[]
  selectedValueId: number | null
}


const specRows = ref<SpecRow[]>([])
const specsLoading = ref(false)
const specsError = ref<string | null>(null)


  const form = reactive({
    product_department_id: 0,
    product_sub_department_id: 0,
    product_sub_sub_department_id: 0,
    product_type_id: 0,
    product_brand_id: 0,
    product_manufacture_id: 0,

    product_sku: "",
    name: "",
    name_ar: "",
    description: "",

    price: 0,
    stock: 0,

    volume_type: "cm" as "mm" | "cm" | "m" | "in" | "ft",
    Weight_Kg: 0,
    Length_Cm: 0,
    Width_Cm: 0,
    Height_Cm: 0,
  })

  const inhouseBarcode = computed(() => {
    // server will finally store: {id}-{suffix}
    const idPart = nextId.value ?? "NEW"
    return `${idPart}-${randomDigits.value}`
  })

  const volumeUnitOptions = [
    { value: "mm", label: "Millimeter (mm)" },
    { value: "cm", label: "Centimeter (cm)" },
    { value: "m",  label: "Meter (m)" },
    { value: "in", label: "Inch (in)" },
    { value: "ft", label: "Foot (ft)" },
  ]

  const step1Valid = computed(() => {
    return (
      form.product_department_id > 0 &&
      form.product_sub_department_id > 0 &&
      form.product_sub_sub_department_id > 0 &&
      form.product_type_id > 0 &&
      form.name.trim().length > 1 &&
      form.name_ar.trim().length > 1 &&
      form.description.trim().length > 3 &&
      form.price > 0 &&
      form.stock >= 0
    )
  })

  function toastReset() {
    error.value = null
    success.value = null
  }

  function handleFileChange(e: Event) {
    const files = (e.target as HTMLInputElement).files
    if (!files) return

    for (const f of Array.from(files)) {
      uploadedImages.value.push(f)
      previewUrls.value.push(URL.createObjectURL(f))
    }
  }

  function removeImage(i: number) {
    uploadedImages.value.splice(i, 1)
    URL.revokeObjectURL(previewUrls.value[i])
    previewUrls.value.splice(i, 1)
  }

  function goNext() {
  toastReset()
  if (step.value === 1 && !step1Valid.value) return
  if (step.value < 4) step.value = (step.value + 1) as any
}

function goPrev() {
  toastReset()
  if (step.value > 1) step.value = (step.value - 1) as any
}


  async function loadCatalog() {
    const [deptRes, typeRes, brandRes, manuRes, nextIdRes] = await Promise.all([
      api.get("/vendor/api/catalog/departments"),
      api.get("/vendor/api/catalog/types"),
      api.get("/vendor/api/catalog/brands"),
      api.get("/vendor/api/catalog/manufactures"),
      api.get("/vendor/api/products-temp/next-id"),
    ])

    departments.value = deptRes.data.map((x: any) => ({ id: x.id, name: x.Product_Department_Name, name_ar: x.Product_Department_Name_Ar }))
    types.value = typeRes.data.map((x: any) => ({ id: x.id, name: x.Product_Types_Name, name_ar: x.Product_Types_Name_Ar }))
    brands.value = brandRes.data.map((x: any) => ({ id: x.id, name: x.Products_Brands_Name, name_ar: x.Products_Brands_Name_Ar }))
    manufactures.value = manuRes.data.map((x: any) => ({ id: x.id, name: x.Products_Manufacture_Name, name_ar: x.Products_Manufacture_Name_Ar }))

    nextId.value = Number(nextIdRes.data)
  }

  async function loadSubDepartments() {
    subDepartments.value = []
    subSubDepartments.value = []
    form.product_sub_department_id = 0
    form.product_sub_sub_department_id = 0

    if (!form.product_department_id) return

    const res = await api.get(`/vendor/api/catalog/sub-departments/${form.product_department_id}`)
    subDepartments.value = res.data.map((x: any) => ({ id: x.id, name: x.Sub_Department_Name, name_ar: x.Sub_Department_Name_Ar }))
  }

  async function loadSubSubDepartments() {
    subSubDepartments.value = []
    form.product_sub_sub_department_id = 0

    if (!form.product_sub_department_id) return

    const res = await api.get(`/vendor/api/catalog/sub-sub-departments/${form.product_sub_department_id}`)
    subSubDepartments.value = res.data.map((x: any) => ({ id: x.id, name: x.Product_Sub_Sub_Department_Name, name_ar: x.Product_Sub_Sub_Department_Name_Ar }))
  }


  async function loadSpecifications() {
  specsError.value = null
  specRows.value = []

  if (!form.product_sub_sub_department_id) return

  try {
    specsLoading.value = true

    // You can reuse the same controller as admin, or a vendor-specific route.
    // If path is different in your API, just change the URL.
    const res = await api.get(`vendor/api/specifications/sub-sub-department/${form.product_sub_sub_department_id}`)


    // Expecting: [{ id, Product_Specification_Description_Name, values: [{id, value}, ...] }]
    const raw = res.data

    specRows.value = (raw || []).map((d: any) => ({
      descriptionId: d.id,
      name: d.Product_Specification_Description_Name,
      values: (d.values || []).map((v: any) => ({
        id: v.id,
        value: v.value,
      })),
      selectedValueId: null, // no default
    }))
  } catch (e: any) {
    specsError.value =
      e?.response?.data?.message || "Failed to load specifications for this category."
  } finally {
    specsLoading.value = false
  }
}



  watch(() => form.product_department_id, loadSubDepartments)
  watch(() => form.product_sub_department_id, loadSubSubDepartments)
  watch(
  () => form.product_sub_sub_department_id,
  (val) => {
    if (val) {
      loadSpecifications()
    } else {
      specRows.value = []
      specsError.value = null
    }
  }
)

  const reviewRows = computed(() => ([
    { k: "Category", v: departments.value.find(d => d.id === form.product_department_id)?.name ?? "-" },
    { k: "Sub Category", v: subDepartments.value.find(d => d.id === form.product_sub_department_id)?.name ?? "-" },
    { k: "Sub-Sub Category", v: subSubDepartments.value.find(d => d.id === form.product_sub_sub_department_id)?.name ?? "-" },
    { k: "Type", v: types.value.find(t => t.id === form.product_type_id)?.name ?? "-" },
    { k: "Brand", v: brands.value.find(b => b.id === form.product_brand_id)?.name ?? "-" },
    { k: "Manufacture", v: manufactures.value.find(m => m.id === form.product_manufacture_id)?.name ?? "-" },
    { k: "SKU", v: form.product_sku || "-" },
    { k: "Inhouse Barcode", v: inhouseBarcode.value },
    { k: "Name", v: form.name },
    { k: "Arabic Name", v: form.name_ar },
    { k: "Price", v: String(form.price) },
    { k: "Stock", v: String(form.stock) },
    { k: "Weight (Kg)", v: String(form.Weight_Kg) },
    { k: "Dimensions", v: `${form.Length_Cm} × ${form.Width_Cm} × ${form.Height_Cm} (${form.volume_type})` },
  ]))

  async function submit() {
    toastReset()
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

      // send only the suffix; backend will prefix with temp id
      fd.append("inhouse_barcode", String(randomDigits.value))

      fd.append("price", String(form.price))
      fd.append("stock", String(form.stock))
      fd.append("product_sku", form.product_sku)

      fd.append("volume_type", form.volume_type)
      fd.append("Weight_Kg", String(form.Weight_Kg))
      fd.append("Length_Cm", String(form.Length_Cm))
      fd.append("Width_Cm", String(form.Width_Cm))
      fd.append("Height_Cm", String(form.Height_Cm))


        // 🔹 Append specs as nested array: specs[0][description_id], specs[0][value_id], ...
        const specsPayload = specRows.value
        .filter((r) => r.selectedValueId && r.selectedValueId > 0)
        .map((r) => ({
          description_id: r.descriptionId,
          value_id: r.selectedValueId,
        }))

      specsPayload.forEach((spec, index) => {
        fd.append(`specs[${index}][description_id]`, String(spec.description_id))
        fd.append(`specs[${index}][value_id]`, String(spec.value_id))
      })

      uploadedImages.value.forEach((f) => fd.append("file[]", f))

      const res = await api.post("/vendor/api/products-temp", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      })

      success.value = "✅ Product submitted successfully and is now Pending approval."
      // reset for next product
      uploadedImages.value = []
      previewUrls.value.forEach((u) => URL.revokeObjectURL(u))
      previewUrls.value = []
      randomDigits.value = Math.floor(10000 + Math.random() * 90000)
      nextId.value = (nextId.value ?? 1) + 1

        form.product_department_id = 0
        form.product_sub_department_id = 0
        form.product_sub_sub_department_id = 0
        form.product_type_id = 0
        form.product_brand_id = 0
        form.product_manufacture_id = 0

        form.product_sku = ""
        form.name = ""
        form.name_ar = ""
        form.description = ""

        form.price = 0
        form.stock = 0

        form.volume_type = "cm"

      step.value = 1

      // optional: go to products list
      // await router.push("/products")
    } catch (e: any) {
      error.value =
        e?.response?.data?.message ||
        e?.response?.data?.error ||
        "Failed to submit product. Please check required fields."
    } finally {
      busy.value = false
    }
  }

  onMounted(loadCatalog)
  </script>

  <template>
    <VendorLayout>
      <div class="max-w-6xl mx-auto px-3 sm:px-6 py-6 sm:py-8">
        <VendorPageHeader
          title="Add New Product (Pending Approval)"
          subtitle="Create a product in the temporary table. Admin will review and approve it."
        />

        <!-- Progress -->
        <div class="mt-5 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden">
          <div class="px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-sky-500 to-primary-600 text-white flex items-center justify-center font-semibold">
                {{ step }}
              </div>
              <div class="leading-tight">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{
                      step === 1
                        ? "Product Details"
                        : step === 2
                          ? "Specifications"
                          : step === 3
                            ? "Images"
                            : "Confirm & Submit"
}}

                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Step {{ step }} of 4
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button
                class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-sm transition"
                :disabled="step === 1"
                @click="goPrev"
              >
                Back
              </button>

              <button
                v-if="step < 4"
                class="px-3 py-2 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-primary-600 to-sky-500 hover:brightness-105 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
                :disabled="step === 1 && !step1Valid"
                @click="goNext"
              >
                Continue
              </button>

              <button
                v-else
                class="px-3 py-2 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:brightness-105 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
                :disabled="busy"
                @click="submit"
              >
                <span v-if="busy" class="inline-flex items-center gap-2">
                  <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                  Submitting...
                </span>
                <span v-else>Submit for Approval</span>
              </button>
            </div>
          </div>

          <div class="h-[3px] bg-slate-100 dark:bg-slate-900">
            <div
              class="h-full bg-gradient-to-r from-primary-600 to-sky-500 transition-all duration-500"
              :style="{ width: `${(step / 4) * 100}%` }"
            />
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="error" class="mt-4 rounded-2xl border border-rose-200/70 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {{ error }}
        </div>
        <div v-if="success" class="mt-4 rounded-2xl border border-emerald-200/70 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {{ success }}
        </div>

         <!-- Body -->
<div class="mt-5 grid lg:grid-cols-12 gap-4 items-start">
  <!-- STEPS (make it much wider) -->
  <div class="lg:col-span-9 order-1">
    <transition name="fade-slide" mode="out-in">
      <!-- STEP 1 -->
      <div
        v-if="step === 1"
        key="s1"
        class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden"
      >
        <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800">
          <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Product Details</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">Category, type, and core product info</p>
        </div>

        <div class="p-5 grid sm:grid-cols-2 gap-4">
                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Category</label>
                    <select v-model.number="form.product_department_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option value="0" disabled>Select category</option>
                      <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Sub Category</label>
                    <select v-model.number="form.product_sub_department_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option value="0" disabled>Select sub category</option>
                      <option v-for="d in subDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Sub-Sub Category</label>
                    <select v-model.number="form.product_sub_sub_department_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option value="0" disabled>Select sub-sub category</option>
                      <option v-for="d in subSubDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Type</label>
                    <select v-model.number="form.product_type_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option value="0" disabled>Select type</option>
                      <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Brand (optional)</label>
                    <select v-model.number="form.product_brand_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option value="0">No brand</option>
                      <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Manufacture (optional)</label>
                    <select v-model.number="form.product_manufacture_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option value="0">No manufacture</option>
                      <option v-for="m in manufactures" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                  </div>

                  <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product SKU</label>
                    <input v-model="form.product_sku" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="SKU (optional)" />
                  </div>

                  <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product Name</label>
                    <input v-model="form.name" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="Enter product name" />
                  </div>

                  <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product Name (Arabic)</label>
                    <input v-model="form.name_ar" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="Enter Arabic name" />
                  </div>

                  <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Description</label>
                    <textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" placeholder="Describe the product..." />
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Price</label>
                    <input v-model.number="form.price" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Stock</label>
                    <input v-model.number="form.stock" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Weight (Kg)</label>
                    <input v-model.number="form.Weight_Kg" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Units</label>
                    <select v-model="form.volume_type" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                      <option v-for="u in volumeUnitOptions" :key="u.value" :value="u.value">{{ u.label }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Length</label>
                    <input v-model.number="form.Length_Cm" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Width</label>
                    <input v-model.number="form.Width_Cm" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                  </div>

                  <div>
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Height</label>
                    <input v-model.number="form.Height_Cm" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm" />
                  </div>

                  <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Inhouse Barcode (auto)</label>
                    <input :value="inhouseBarcode" disabled readonly class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm opacity-80" />
                  </div>

                  <div class="sm:col-span-2">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                      Required fields must be filled to continue.
                    </div>
                  </div>
                </div>
      </div>


      

     

     
<!-- STEP 2: Specifications -->
<div
  v-else-if="step === 2"
  key="s2"
  class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden"
>
  <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800">
    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Specifications</p>
    <p class="text-xs text-slate-500 dark:text-slate-400">
      Choose specification values for the selected Sub-Sub Category.
    </p>
  </div>

  <div class="p-5">
    <div v-if="specsLoading" class="text-xs text-slate-500 dark:text-slate-400">
      Loading specifications...
    </div>

    <div v-else-if="specRows.length === 0" class="text-xs text-slate-500 dark:text-slate-400">
      No specifications configured for this Sub-Sub Category yet.
    </div>

    <div v-else class="space-y-3">
      <!-- 2x2 rows: label left, select right -->
      <div
        v-for="row in specRows"
        :key="row.descriptionId"
        class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-3"
      >
        <!-- LEFT: description -->
        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">
          {{ row.name }}
          <span v-if="row.values.length === 0" class="ml-2 text-[10px] font-normal text-slate-400">
            (No values)
          </span>
        </div>

        <!-- RIGHT: value -->
        <div>
          <select
            v-model.number="row.selectedValueId"
            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs"
            :disabled="row.values.length === 0"
          >
            <option :value="null">Select value</option>
            <option v-for="opt in row.values" :key="opt.id" :value="opt.id">
              {{ opt.value }}
            </option>
          </select>
        </div>
      </div>

      <p v-if="specsError" class="mt-2 text-xs text-rose-600">
        {{ specsError }}
      </p>
    </div>
  </div>
</div>



<!-- STEP 3: Images -->
<div
  v-else-if="step === 3"
  key="s3"
  class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden"
>
  <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800">
    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Images</p>
    <p class="text-xs text-slate-500 dark:text-slate-400">
      Upload clear product photos to speed up approval.
    </p>
  </div>

  <div class="p-5">
    <label class="block">
      <div class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-5 text-center hover:shadow-sm transition">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Click to upload images</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">PNG/JPG, up to 5MB each</p>
      </div>
      <input class="hidden" type="file" multiple accept="image/*" @change="handleFileChange" />
    </label>

    <div v-if="previewUrls.length" class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
      <div
        v-for="(u, i) in previewUrls"
        :key="u"
        class="relative rounded-2xl overflow-hidden border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-950"
      >
        <img :src="u" class="h-36 w-full object-cover" />
        <button
          type="button"
          @click="removeImage(i)"
          class="absolute top-2 right-2 h-8 w-8 rounded-full bg-black/50 text-white text-sm hover:bg-black/70 transition"
          aria-label="Remove"
        >
          ✕
        </button>
      </div>
    </div>

    <div v-else class="mt-4 text-xs text-slate-500 dark:text-slate-400">
      No images uploaded yet.
    </div>
  </div>
</div>



<!-- STEP 4: Confirm & Submit -->
<div
  v-else
  key="s4"
  class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden"
>
  <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800">
    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Confirm & Submit</p>
    <p class="text-xs text-slate-500 dark:text-slate-400">Final check before sending to admin approval.</p>
  </div>

  <div class="p-5 space-y-4">
    <div class="grid sm:grid-cols-2 gap-3">
      <div
        v-for="r in reviewRows"
        :key="r.k"
        class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4"
      >
        <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ r.k }}</p>
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mt-1 truncate">{{ r.v }}</p>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4">
      <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">Summary</p>
      <div class="mt-2 text-xs text-slate-600 dark:text-slate-300 space-y-1">
        <p>• Specifications selected: <b>{{ specRows.filter(r => r.selectedValueId).length }}</b> / {{ specRows.length }}</p>
        <p>• Images uploaded: <b>{{ uploadedImages.length }}</b></p>
        <p class="text-slate-500 dark:text-slate-400">Status will be saved as <b>pending</b> after submission.</p>
      </div>
    </div>
  </div>
</div>


      

       
    </transition>
  </div>

  <!-- QUICK SUMMARY (smaller + not squeezing the form) -->
  <aside class="lg:col-span-3 order-2 lg:order-2 lg:sticky lg:top-20">
    <div
      class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-950/60 backdrop-blur-xl shadow-sm overflow-hidden"
    >
      <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Quick Summary</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">Live preview while you type</p>
      </div>

      <div class="p-5 space-y-3">
        <div class="grid sm:grid-cols-2 gap-3">
                    <div
                      v-for="r in reviewRows"
                      :key="r.k"
                      class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-4"
                    >
                      <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ r.k }}</p>
                      <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mt-1 truncate">{{ r.v }}</p>
                    </div>
                  </div>

                  <div class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    When you submit, the product is saved into <b>Products_Temp_T</b> with status <b>pending</b>.
                  </div>
      </div>
    </div>
  </aside>
</div>

<!-- Sticky bottom actions -->
<div class="sticky bottom-3 z-30 mt-6">
  <div
    class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white/85 dark:bg-slate-950/70 backdrop-blur-xl shadow-sm px-4 sm:px-6 py-3 flex items-center justify-between gap-3"
  >
    <div class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
      Step <span class="font-semibold text-slate-800 dark:text-slate-100">{{ step }}</span> of 3
    </div>

    <div class="flex items-center gap-2 ml-auto">
      <button
        class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
        :disabled="step === 1"
        @click="goPrev"
      >
        Back
      </button>

      <button
        v-if="step < 4"
        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-primary-600 to-sky-500 hover:brightness-105 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
        :disabled="step === 1 && !step1Valid"
        @click="goNext"
      >
        Continue
      </button>

      <button
        v-else
        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:brightness-105 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
        :disabled="busy"
        @click="submit"
      >
        <span v-if="busy" class="inline-flex items-center gap-2">
          <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
          Submitting...
        </span>
        <span v-else>Submit for Approval</span>
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
    transition: all 220ms ease;
  }
  .fade-slide-enter-from {
    opacity: 0;
    transform: translateY(10px);
  }
  .fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
  }
  </style>
