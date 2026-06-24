<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue"
import { useRouter } from "vue-router"
import VendorLayout from "@/components/layout/VendorLayout.vue"
import VendorPageHeader from "@/components/layout/VendorPageHeader.vue"
import api from "@/services/api"
import { useVendorAuth } from "@/composables/useVendorAuth"

type ChecklistItem = {
  key: string
  label: string
  complete: boolean
  missing_fields?: string[]
}

type VendorProfile = {
  Vendor_Name?: string | null
  Trade_Name?: string | null
  CR_Number?: string | null
  VAT_Number?: string | null
  Email_1?: string | null
  Phone_No?: string | null
  Business_Type?: string | null
  Contact_Person_Name?: string | null
  Contact_Person_Email?: string | null
  Contact_Person_Phone?: string | null
  Address_Line1?: string | null
  Postal_Code?: string | null
  PO_Box?: string | null
  Country_Id?: number | null
  Region_Id?: number | null
  District_Id?: number | null
  City_Id?: number | null
  Bank_Name?: string | null
  Bank_Account_Name?: string | null
  Bank_Account_Number?: string | null
  Bank_IBAN?: string | null
  Bank_Swift_Code?: string | null
  Payout_Method?: string | null
  Payout_Status?: string | null
  Approval_Status?: string | null
  onboarding_checklist?: {
    completeness_percent: number
    readiness: string
    missing_required: string[]
    items: ChecklistItem[]
  }
  documents?: Array<{ Document_Type: string; Document_Name?: string | null; Status?: string | null; Review_Note?: string | null }>
}

const router = useRouter()
const auth = useVendorAuth()

const loading = ref(false)
const saving = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const profile = ref<VendorProfile | null>(null)

const form = reactive({
  Vendor_Name: "",
  Trade_Name: "",
  CR_Number: "",
  VAT_Number: "",
  Email_1: "",
  Phone_No: "",
  Business_Type: "",
  Contact_Person_Name: "",
  Contact_Person_Email: "",
  Contact_Person_Phone: "",
  Address_Line1: "",
  Postal_Code: "",
  PO_Box: "",
  Bank_Name: "",
  Bank_Account_Name: "",
  Bank_Account_Number: "",
  Bank_IBAN: "",
  Bank_Swift_Code: "",
  Payout_Method: "bank_transfer",
})

// Geography (cascading)
const geo = reactive<{ country: number | ""; region: number | ""; district: number | ""; city: number | "" }>({
  country: "", region: "", district: "", city: "",
})
const countries = ref<any[]>([])
const regions = ref<any[]>([])
const districts = ref<any[]>([])
const cities = ref<any[]>([])

const documents = reactive({
  commercial_registration: "",
  vat_certificate: "",
  bank_letter: "",
})

const checklist = computed(() => profile.value?.onboarding_checklist)
const completeness = computed(() => Number(checklist.value?.completeness_percent || 0))
const status = computed(() => String(profile.value?.Approval_Status || "").toLowerCase())
const approved = computed(() => status.value === "approved")
const underReview = computed(() => status.value === "under_review")
// Profile (non-document) items complete — documents are reviewed separately by admins.
const profileComplete = computed(() => {
  const items = checklist.value?.items || []
  const prof = items.filter((i) => i.key !== "documents")
  return prof.length > 0 && prof.every((i) => i.complete)
})

async function fetchCountries() {
  try { const { data } = await api.get("/vendor/api/geo/countries"); countries.value = data } catch { countries.value = [] }
}
async function loadRegions(id: number | "") {
  regions.value = []
  if (!id) return
  try { const { data } = await api.get(`/vendor/api/geo/regions/${id}`); regions.value = data } catch { /* ignore */ }
}
async function loadDistricts(id: number | "") {
  districts.value = []
  if (!id) return
  try { const { data } = await api.get(`/vendor/api/geo/districts/${id}`); districts.value = data } catch { /* ignore */ }
}
async function loadCities(id: number | "") {
  cities.value = []
  if (!id) return
  try { const { data } = await api.get(`/vendor/api/geo/cities/${id}`); cities.value = data } catch { /* ignore */ }
}

async function onCountryChange() {
  geo.region = ""; geo.district = ""; geo.city = ""
  regions.value = []; districts.value = []; cities.value = []
  await loadRegions(geo.country)
}
async function onRegionChange() {
  geo.district = ""; geo.city = ""
  districts.value = []; cities.value = []
  await loadDistricts(geo.region)
}
async function onDistrictChange() {
  geo.city = ""; cities.value = []
  await loadCities(geo.district)
}

async function hydrate(row: VendorProfile) {
  profile.value = row
  for (const key of Object.keys(form) as Array<keyof typeof form>) {
    form[key] = String((row as any)[key] || (key === "Payout_Method" ? "bank_transfer" : ""))
  }

  // Geo cascade hydration
  geo.country = (row.Country_Id as any) || ""
  geo.region = ""; geo.district = ""; geo.city = ""
  if (geo.country) { await loadRegions(geo.country); geo.region = (row.Region_Id as any) || "" }
  if (geo.region) { await loadDistricts(geo.region); geo.district = (row.District_Id as any) || "" }
  if (geo.district) { await loadCities(geo.district); geo.city = (row.City_Id as any) || "" }
}

async function fetchProfile() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get("/vendor/api/profile")
    await hydrate(data.data || {})
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to load vendor profile."
  } finally {
    loading.value = false
  }
}

async function saveProfile(): Promise<boolean> {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const payload = {
      ...form,
      Country_Id: geo.country || null,
      Region_Id: geo.region || null,
      District_Id: geo.district || null,
      City_Id: geo.city || null,
    }
    const { data } = await api.put("/vendor/api/profile", payload)
    await hydrate(data.data || {})
    message.value = data.message || "Profile updated."
    return true
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to save vendor profile."
    return false
  } finally {
    saving.value = false
  }
}

function goToDashboard() {
  router.push("/dashboard")
}

async function saveDocuments() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const payload = {
      documents: Object.entries(documents)
        .filter(([, value]) => value.trim())
        .map(([type, name]) => ({ Document_Type: type, Document_Name: name.trim() })),
    }
    if (!payload.documents.length) {
      message.value = "Add at least one document reference before submitting."
      return
    }
    const { data } = await api.post("/vendor/api/profile/documents", payload)
    await hydrate(data.data || {})
    message.value = data.message || "Documents submitted."
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Failed to submit documents."
  } finally {
    saving.value = false
  }
}

async function submitForReview() {
  submitting.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post("/vendor/api/profile/submit")
    await hydrate(data.data || {})
    await auth.refresh()
    message.value = data.message || "Submitted for approval."
  } catch (e: any) {
    error.value = e?.response?.data?.message || "Complete all required onboarding items first."
  } finally {
    submitting.value = false
  }
}

const badgeClass = (status?: string | null) => {
  if (status === "approved") return "bg-emerald-100 text-emerald-700 border-emerald-200"
  if (status === "rejected") return "bg-rose-100 text-rose-700 border-rose-200"
  if (status === "under_review" || status === "pending_review" || status === "pending") return "bg-amber-100 text-amber-700 border-amber-200"
  return "bg-slate-100 text-slate-700 border-slate-200"
}

onMounted(async () => {
  await fetchCountries()
  await fetchProfile()
})
</script>

<template>
  <VendorLayout>
    <VendorPageHeader
      title="Complete your profile"
      description="Add your business, address, bank and payout details to unlock your dashboard and start adding products."
      icon="fa-solid fa-clipboard-check"
    />

    <div v-if="underReview" class="rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm mb-4">
      Your profile has been submitted and is awaiting admin approval. You'll get access to your dashboard and products once it's approved.
    </div>
    <div v-else-if="approved" class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm mb-4">
      Your account is approved. You can edit your details here any time, or head to your dashboard.
    </div>
    <div v-else class="rounded-lg border border-sky-200 bg-sky-50 text-sky-800 px-4 py-3 text-sm mb-4">
      Welcome! Complete your business, address and bank details below, then submit for approval to unlock your dashboard and products.
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm mb-4">{{ error }}</div>
    <div v-if="message" class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm mb-4">{{ message }}</div>

    <div v-if="loading" class="text-sm text-slate-500">Loading profile...</div>
    <div v-else class="grid grid-cols-1 xl:grid-cols-[340px_minmax(0,1fr)] gap-4">
      <aside class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 h-fit">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs text-slate-500">Profile completeness</p>
            <p class="text-2xl font-semibold">{{ completeness }}%</p>
          </div>
          <span class="px-2 py-1 rounded-full border text-xs font-semibold" :class="badgeClass(profile?.Approval_Status)">
            {{ profile?.Approval_Status || "pending" }}
          </span>
        </div>

        <div class="mt-4 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full bg-emerald-500" :style="{ width: `${completeness}%` }"></div>
        </div>

        <div class="mt-4 space-y-2">
          <div
            v-for="item in checklist?.items || []"
            :key="item.key"
            class="rounded-lg border px-3 py-2 text-sm"
            :class="item.complete ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700'"
          >
            <div class="flex items-center justify-between gap-3">
              <span class="font-medium">{{ item.label }}</span>
              <span>{{ item.complete ? "Complete" : "Needed" }}</span>
            </div>
            <div v-if="!item.complete" class="text-xs mt-1 opacity-80">
              {{ item.missing_fields?.join(", ") || "Missing details" }}
            </div>
          </div>
        </div>

        <!-- Approved: full access -->
        <button
          v-if="approved"
          class="mt-4 w-full rounded-lg bg-primary-600 text-white text-sm font-semibold py-2"
          @click="goToDashboard"
        >
          Go to dashboard
        </button>

        <!-- Submitted: waiting on admin (gate 2) -->
        <button
          v-else-if="underReview"
          class="mt-4 w-full rounded-lg bg-amber-100 text-amber-700 text-sm font-semibold py-2 cursor-default"
          disabled
        >
          Awaiting admin approval
        </button>

        <!-- Accepted: complete the profile then submit for approval -->
        <template v-else>
          <button
            class="mt-4 w-full rounded-lg bg-primary-600 text-white text-sm font-semibold py-2 disabled:opacity-60"
            :disabled="submitting || !profileComplete"
            @click="submitForReview"
          >
            {{ submitting ? "Submitting..." : "Submit for approval" }}
          </button>
          <p v-if="!profileComplete" class="mt-2 text-[11px] text-slate-500">
            Complete every required item above (marked “Needed”) to enable submission.
          </p>
        </template>
      </aside>

      <main class="space-y-4">
        <section class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <h3 class="text-sm font-semibold mb-3">Business Details</h3>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <input v-model="form.Vendor_Name" class="form-field" placeholder="Vendor name" />
            <input v-model="form.Trade_Name" class="form-field" placeholder="Trade name" />
            <input v-model="form.Business_Type" class="form-field" placeholder="Business type" />
            <input v-model="form.CR_Number" class="form-field" placeholder="CR number" />
            <input v-model="form.VAT_Number" class="form-field" placeholder="VAT number" />
            <input v-model="form.Email_1" class="form-field" placeholder="Business email" />
            <input v-model="form.Phone_No" class="form-field" placeholder="Business phone" />
            <input v-model="form.Contact_Person_Name" class="form-field" placeholder="Contact person" />
            <input v-model="form.Contact_Person_Email" class="form-field" placeholder="Contact email" />
            <input v-model="form.Contact_Person_Phone" class="form-field" placeholder="Contact phone" />
          </div>
        </section>

        <section class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <h3 class="text-sm font-semibold mb-3">Address</h3>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <select v-model="geo.country" class="form-field" @change="onCountryChange">
              <option value="">Country</option>
              <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.Country_Name }}</option>
            </select>
            <select v-model="geo.region" class="form-field" :disabled="!geo.country" @change="onRegionChange">
              <option value="">Region</option>
              <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.Region_Name }}</option>
            </select>
            <select v-model="geo.district" class="form-field" :disabled="!geo.region" @change="onDistrictChange">
              <option value="">District</option>
              <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.District_Name }}</option>
            </select>
            <select v-model="geo.city" class="form-field" :disabled="!geo.district">
              <option value="">City</option>
              <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.City_Name }}</option>
            </select>
            <input v-model="form.Address_Line1" class="form-field sm:col-span-2" placeholder="Registered address" />
            <input v-model="form.Postal_Code" class="form-field" placeholder="Postal code" />
            <input v-model="form.PO_Box" class="form-field" placeholder="PO box" />
          </div>
        </section>

        <section class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <h3 class="text-sm font-semibold mb-3">Bank & Payout</h3>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <input v-model="form.Bank_Name" class="form-field" placeholder="Bank name" />
            <input v-model="form.Bank_Account_Name" class="form-field" placeholder="Account holder" />
            <input v-model="form.Bank_Account_Number" class="form-field" placeholder="Account number" />
            <input v-model="form.Bank_IBAN" class="form-field" placeholder="IBAN" />
            <input v-model="form.Bank_Swift_Code" class="form-field" placeholder="SWIFT code" />
            <select v-model="form.Payout_Method" class="form-field">
              <option value="bank_transfer">Bank transfer</option>
              <option value="manual">Manual</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
        </section>

        <section class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 class="text-sm font-semibold">Documents</h3>
            <span class="px-2 py-1 rounded-full border text-xs font-semibold" :class="badgeClass(profile?.Payout_Status)">
              Payout: {{ profile?.Payout_Status || "not_configured" }}
            </span>
          </div>

          <div v-if="profile?.documents?.length" class="grid sm:grid-cols-3 gap-2">
            <div v-for="doc in profile.documents" :key="doc.Document_Type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <div class="font-medium">{{ doc.Document_Type.replaceAll("_", " ") }}</div>
              <span class="inline-flex mt-2 px-2 py-1 rounded-full border text-xs" :class="badgeClass(doc.Status)">
                {{ doc.Status || "pending" }}
              </span>
              <div v-if="doc.Review_Note" class="text-xs text-slate-500 mt-1">{{ doc.Review_Note }}</div>
            </div>
          </div>
          <p v-else class="text-xs text-slate-500">No documents uploaded yet.</p>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
          <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold disabled:opacity-60" :disabled="saving" @click="saveProfile">
            {{ saving ? "Saving..." : "Save profile" }}
          </button>
          <button
            v-if="!approved && !underReview"
            class="rounded-lg bg-primary-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-60"
            :disabled="submitting || !profileComplete"
            @click="submitForReview"
          >
            {{ submitting ? "Submitting..." : "Submit for approval" }}
          </button>
          <button
            v-else-if="approved"
            class="rounded-lg bg-primary-600 text-white px-4 py-2 text-sm font-semibold"
            @click="goToDashboard"
          >
            Go to dashboard
          </button>
        </div>
      </main>
    </div>
  </VendorLayout>
</template>

<style scoped>
.form-field {
  min-height: 42px;
  border-radius: 8px;
  border: 1px solid rgb(203 213 225);
  background: white;
  padding: 0.55rem 0.75rem;
  font-size: 0.875rem;
}
</style>
