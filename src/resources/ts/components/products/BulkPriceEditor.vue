<script setup lang="ts">
import { computed } from "vue"
import { emptyTier, validateTiers, type BulkTier } from "@/services/bulkPricing"

/**
 * Reusable quantity-tier bulk price editor.
 * Rows of Min qty / Max qty (blank = "and above") / Unit price with live
 * validation mirroring the backend BulkPriceRules (overlaps, bounds, floor).
 */
const props = defineProps<{
  tiers: BulkTier[]
  /** Product Minimum_Selling_Price when known; tiers may not price below it. */
  floor?: number | null
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: "update:tiers", value: BulkTier[]): void
}>()

const errors = computed(() => validateTiers(props.tiers, props.floor ?? null))

const addTier = () => {
  emit("update:tiers", [...props.tiers, emptyTier()])
}

const removeTier = (index: number) => {
  const next = [...props.tiers]
  next.splice(index, 1)
  emit("update:tiers", next)
}

const updateField = (index: number, field: keyof BulkTier, raw: string) => {
  const next = props.tiers.map((t) => ({ ...t }))
  const value = raw === "" ? null : Number(raw)
  next[index][field] = value !== null && Number.isFinite(value) ? value : null
  emit("update:tiers", next)
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <div>
        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Bulk prices (optional)</p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">
          Quantity ranges with a special unit price. Leave "Max qty" empty for "and above". Gaps pay the normal price.
        </p>
      </div>
      <button
        type="button"
        class="px-3 py-2 rounded-xl text-xs font-semibold border border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300 hover:shadow-sm transition disabled:opacity-60"
        :disabled="disabled"
        @click="addTier"
      >
        + Add tier
      </button>
    </div>

    <div v-if="tiers.length === 0" class="text-xs text-slate-500 dark:text-slate-400">
      No bulk price tiers. All quantities pay the normal price.
    </div>

    <div
      v-for="(tier, index) in tiers"
      :key="index"
      class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto] gap-2 items-end rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 p-3"
    >
      <div>
        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Min qty</label>
        <input
          :value="tier.min_qty ?? ''"
          type="number"
          min="1"
          step="1"
          placeholder="e.g. 5"
          class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
          :disabled="disabled"
          @input="updateField(index, 'min_qty', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <div>
        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Max qty (empty = and above)</label>
        <input
          :value="tier.max_qty ?? ''"
          type="number"
          min="1"
          step="1"
          placeholder="and above"
          class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
          :disabled="disabled"
          @input="updateField(index, 'max_qty', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <div>
        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Unit price</label>
        <input
          :value="tier.unit_price ?? ''"
          type="number"
          min="0"
          step="0.001"
          placeholder="e.g. 6.000"
          class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
          :disabled="disabled"
          @input="updateField(index, 'unit_price', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <button
        type="button"
        class="px-3 py-2 rounded-xl text-xs font-semibold border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300 hover:shadow-sm transition disabled:opacity-60"
        :disabled="disabled"
        @click="removeTier(index)"
      >
        Remove
      </button>
    </div>

    <ul v-if="errors.length" class="rounded-xl border border-rose-200/70 bg-rose-50 px-3 py-2 space-y-1">
      <li v-for="(err, i) in errors" :key="i" class="text-xs text-rose-700">{{ err }}</li>
    </ul>
    <p v-if="floor !== null && floor !== undefined && tiers.length" class="text-[11px] text-slate-500 dark:text-slate-400">
      Minimum selling price for this product: {{ Number(floor).toFixed(3) }} — tiers cannot price below it.
    </p>
  </div>
</template>
