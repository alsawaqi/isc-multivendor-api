<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import BulkPriceEditor from '@/components/products/BulkPriceEditor.vue'
import { tiersFromApi, tiersToPayload, validateTiers, type BulkTier } from '@/services/bulkPricing'
const props = defineProps<{ productId: number }>()
const product = ref<any>(null)
const price = ref(0)
const stock = ref(0)
const status = ref('available')
const tiers = ref<BulkTier[]>([])
const comment = ref('')
const busy = ref(false)
const pending = ref(false)
const error = ref('')
const success = ref('')
onMounted(async () => {
  busy.value = true
  try {
    const { data } = await api.get(`/vendor/api/products/approved/${props.productId}`)
    product.value = data.data.product
    price.value = Number(product.value.Product_Price)
    stock.value = Number(product.value.Product_Stock)
    status.value = product.value.Status === 'discontinued' ? 'discontinued' : 'available'
    tiers.value = tiersFromApi(data.data.bulk_prices)
    pending.value = data.data.has_open_update_request
  } catch (e: any) { error.value = e?.response?.data?.message || 'Could not load your offer.' }
  finally { busy.value = false }
})
async function submit() {
  error.value = ''; success.value = ''
  const errors = validateTiers(tiers.value, product.value?.Minimum_Selling_Price == null ? null : Number(product.value.Minimum_Selling_Price))
  if (errors.length) { error.value = errors.join(' '); return }
  busy.value = true
  try {
    await api.post(`/vendor/api/products/approved/${props.productId}/request-update`, {
      comment: comment.value,
      changes: { Product_Price: price.value, Product_Stock: stock.value, Status: status.value, bulk_prices: tiersToPayload(tiers.value) },
    })
    pending.value = true
    success.value = 'Your offer changes have been submitted for admin approval.'
  } catch (e: any) { error.value = e?.response?.data?.message || 'Could not submit your offer changes.' }
  finally { busy.value = false }
}
</script>

<template>
  <form class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 dark:bg-slate-950" @submit.prevent="submit">
    <h2 class="text-lg font-semibold">Your offer — {{ product?.Product_Name }}</h2>
    <p class="my-3 text-sm text-slate-500">Set your selling price, stock and bulk prices. Product descriptions, images and specifications are shared across sellers and managed by the administrator.</p>
    <p v-if="error" role="alert" class="my-3 text-red-700">{{ error }}</p>
    <p v-if="success" role="status" class="my-3 text-emerald-700">{{ success }}</p>
    <p v-else-if="pending" class="my-3 text-amber-700">An update request is already awaiting review.</p>
    <fieldset :disabled="busy || pending || !product" class="space-y-4">
      <div class="grid gap-4 sm:grid-cols-3">
        <label class="block text-sm">Selling price (OMR)<input v-model.number="price" type="number" step="0.001" min="0" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
        <label class="block text-sm">Your stock<input v-model.number="stock" type="number" step="1" min="0" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
        <label class="block text-sm">Availability<select v-model="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="available">Available for sale</option><option value="discontinued">Discontinued</option></select></label>
      </div>
      <BulkPriceEditor v-model:tiers="tiers" :floor="product?.Minimum_Selling_Price == null ? null : Number(product.Minimum_Selling_Price)" :disabled="busy || pending" />
      <label class="block text-sm">Note for the administrator<textarea v-model="comment" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
      <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white disabled:opacity-50">{{ busy ? 'Submitting…' : 'Submit offer changes' }}</button>
    </fieldset>
  </form>
</template>
