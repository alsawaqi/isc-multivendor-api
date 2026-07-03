/**
 * Quantity-tier bulk pricing helpers for the vendor SPA.
 *
 * Mirrors the backend App\Support\Pricing\BulkPriceRules exactly so vendors
 * see the same messages client-side before submitting:
 *  - min_qty integer >= 1
 *  - max_qty empty/null = open-ended ("and above"), otherwise integer >= min_qty
 *  - unit_price > 0 (and >= floor when the product has a Minimum_Selling_Price)
 *  - no overlapping ranges across the set (null max = infinity), gaps allowed
 */

export type BulkTier = {
  min_qty: number | null
  max_qty: number | null
  unit_price: number | null
}

export const emptyTier = (): BulkTier => ({ min_qty: null, max_qty: null, unit_price: null })

export const describeRange = (min: number, max: number | null): string =>
  max === null ? `${min}+` : `${min}-${max}`

const isInt = (v: unknown): v is number =>
  typeof v === "number" && Number.isFinite(v) && Math.floor(v) === v

/** Human-readable errors for a tier replace-set; empty array = valid. */
export function validateTiers(tiers: BulkTier[], floor?: number | null): string[] {
  const errors: string[] = []
  const accepted: { min: number; max: number | null }[] = []

  tiers.forEach((tier, i) => {
    const label = `Tier ${i + 1}`
    const min = tier.min_qty
    const max = tier.max_qty === undefined ? null : tier.max_qty
    const price = tier.unit_price

    if (!isInt(min as unknown) || (min as number) < 1) {
      errors.push(`${label}: minimum quantity must be an integer of at least 1.`)
      return
    }

    if (max !== null && (!isInt(max as unknown) || (max as number) < 0)) {
      errors.push(`${label}: maximum quantity must be an integer, or empty for 'and above'.`)
      return
    }

    if (max !== null && (max as number) < (min as number)) {
      errors.push(`${label}: maximum quantity (${max}) must be greater than or equal to minimum quantity (${min}).`)
      return
    }

    if (price === null || !Number.isFinite(price) || price <= 0) {
      errors.push(`${label}: unit price must be greater than 0.`)
      return
    }

    if (floor !== null && floor !== undefined && price < floor) {
      errors.push(`${label}: unit price ${price.toFixed(3)} is below the minimum selling price ${floor.toFixed(3)}.`)
      return
    }

    for (const other of accepted) {
      const endA = max === null ? Number.POSITIVE_INFINITY : (max as number)
      const endB = other.max === null ? Number.POSITIVE_INFINITY : other.max

      if ((min as number) <= endB && other.min <= endA) {
        errors.push(
          `${label}: range ${describeRange(min as number, max)} overlaps existing range ${describeRange(other.min, other.max)}.`
        )
        return
      }
    }

    accepted.push({ min: min as number, max })
  })

  return errors
}

/** Tier unit price for a quantity, or null when no tier covers it. */
export function resolveUnitPrice(tiers: BulkTier[], qty: number): number | null {
  for (const tier of tiers) {
    if (tier.min_qty === null || tier.unit_price === null) continue
    const end = tier.max_qty === null ? Number.POSITIVE_INFINITY : tier.max_qty
    if (qty >= tier.min_qty && qty <= end) return tier.unit_price
  }
  return null
}

/** Map API rows ({min_qty,max_qty,unit_price} or {Min_Qty,...}) to editor rows. */
export function tiersFromApi(rows: any[] | null | undefined): BulkTier[] {
  return (rows || []).map((row: any) => {
    const maxRaw = row?.max_qty ?? row?.Max_Qty ?? null
    return {
      min_qty: Number(row?.min_qty ?? row?.Min_Qty ?? 0) || null,
      max_qty: maxRaw === null || maxRaw === "" ? null : Number(maxRaw),
      unit_price: Number(row?.unit_price ?? row?.Unit_Price ?? 0) || null,
    }
  })
}

/** Canonical payload rows for the API (sorted by min_qty). */
export function tiersToPayload(tiers: BulkTier[]): { min_qty: number; max_qty: number | null; unit_price: number }[] {
  return tiers
    .map((t) => ({
      min_qty: Number(t.min_qty ?? 0),
      max_qty: t.max_qty === null || t.max_qty === undefined ? null : Number(t.max_qty),
      unit_price: Number(t.unit_price ?? 0),
    }))
    .sort((a, b) => a.min_qty - b.min_qty)
}

/** True when two tier sets describe the same pricing (order-insensitive). */
export function tiersEqual(a: BulkTier[], b: BulkTier[]): boolean {
  return JSON.stringify(tiersToPayload(a)) === JSON.stringify(tiersToPayload(b))
}
