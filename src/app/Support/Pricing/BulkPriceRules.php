<?php

namespace App\Support\Pricing;

/**
 * Pure quantity-tier bulk-pricing rules (no DB, no framework state).
 *
 * A tier is ['min_qty' => int >= 1, 'max_qty' => int|null, 'unit_price' => float > 0].
 * A null max_qty means the tier is open-ended ("and above"). Tier ranges may
 * not overlap (null max = infinity, so at most one open-ended tier), gaps are
 * allowed, and when a product has a non-null Minimum_Selling_Price no tier
 * may price below it.
 *
 * This class is intentionally duplicated per backend repo (house pattern for
 * shared pure logic across the ISC backends).
 */
class BulkPriceRules
{
    /**
     * Validate a whole replace-set of tiers.
     *
     * @param array      $tiers list of tier arrays (min_qty / max_qty / unit_price,
     *                          Min_Qty / Max_Qty / Unit_Price also accepted)
     * @param float|null $floor product Minimum_Selling_Price (null = no floor)
     *
     * @return string[] human-readable error strings; empty array = valid set
     */
    public static function validateSet(array $tiers, ?float $floor = null): array
    {
        $errors = [];
        $accepted = [];

        foreach (array_values($tiers) as $i => $tier) {
            $label = 'Tier ' . ($i + 1);

            if (!is_array($tier)) {
                $errors[] = "{$label}: invalid tier payload.";
                continue;
            }

            $min = self::toInt($tier['min_qty'] ?? $tier['Min_Qty'] ?? null);
            $maxRaw = $tier['max_qty'] ?? $tier['Max_Qty'] ?? null;
            $maxIsOpen = ($maxRaw === null || $maxRaw === '');
            $max = $maxIsOpen ? null : self::toInt($maxRaw);
            $price = self::toFloat($tier['unit_price'] ?? $tier['Unit_Price'] ?? null);

            if ($min === null || $min < 1) {
                $errors[] = "{$label}: minimum quantity must be an integer of at least 1.";
                continue;
            }

            if (!$maxIsOpen && $max === null) {
                $errors[] = "{$label}: maximum quantity must be an integer, or empty for 'and above'.";
                continue;
            }

            if ($max !== null && $max < $min) {
                $errors[] = "{$label}: maximum quantity ({$max}) must be greater than or equal to minimum quantity ({$min}).";
                continue;
            }

            if ($price === null || $price <= 0) {
                $errors[] = "{$label}: unit price must be greater than 0.";
                continue;
            }

            if ($floor !== null && $price < $floor) {
                $errors[] = "{$label}: unit price " . number_format($price, 3, '.', '')
                    . ' is below the minimum selling price ' . number_format($floor, 3, '.', '') . '.';
                continue;
            }

            foreach ($accepted as $other) {
                if (self::rangesOverlap($min, $max, $other['min'], $other['max'])) {
                    $errors[] = "{$label}: range " . self::describeRange($min, $max)
                        . ' overlaps existing range ' . self::describeRange($other['min'], $other['max']) . '.';
                    continue 2;
                }
            }

            $accepted[] = ['min' => $min, 'max' => $max];
        }

        return $errors;
    }

    /**
     * Canonicalize a (pre-validated) tier set: snake_case keys, int/float types,
     * sorted by min_qty ascending. Safe to persist or embed in change JSON.
     *
     * @return array<int, array{min_qty:int, max_qty:int|null, unit_price:float}>
     */
    public static function normalizeSet(array $tiers): array
    {
        $normalized = [];

        foreach ($tiers as $tier) {
            if (!is_array($tier)) {
                continue;
            }

            $maxRaw = $tier['max_qty'] ?? $tier['Max_Qty'] ?? null;

            $normalized[] = [
                'min_qty' => (int) ($tier['min_qty'] ?? $tier['Min_Qty'] ?? 0),
                'max_qty' => ($maxRaw === null || $maxRaw === '') ? null : (int) $maxRaw,
                'unit_price' => round((float) ($tier['unit_price'] ?? $tier['Unit_Price'] ?? 0), 3),
            ];
        }

        usort($normalized, fn (array $a, array $b) => $a['min_qty'] <=> $b['min_qty']);

        return $normalized;
    }

    /**
     * Resolve the applicable tier unit price for a quantity, or null when no
     * tier covers it. Validated sets guarantee at most one match.
     */
    public static function resolveUnitPrice(array $tiers, int $qty): ?float
    {
        foreach (self::normalizeSet($tiers) as $tier) {
            $max = $tier['max_qty'];

            if ($qty >= $tier['min_qty'] && ($max === null || $qty <= $max)) {
                return $tier['unit_price'];
            }
        }

        return null;
    }

    /** "5-10" for bounded ranges, "51+" for open-ended ones. */
    public static function describeRange(int $min, ?int $max): string
    {
        return $max === null ? "{$min}+" : "{$min}-{$max}";
    }

    /** Overlap test treating a null max as infinity. */
    private static function rangesOverlap(int $minA, ?int $maxA, int $minB, ?int $maxB): bool
    {
        $endA = $maxA ?? PHP_INT_MAX;
        $endB = $maxB ?? PHP_INT_MAX;

        return $minA <= $endB && $minB <= $endA;
    }

    private static function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            return (int) trim($value);
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }

    private static function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }
}
