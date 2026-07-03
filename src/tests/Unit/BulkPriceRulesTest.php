<?php

use App\Support\Pricing\BulkPriceRules;

// ---------------------------------------------------------------------------
// validateSet: happy paths
// ---------------------------------------------------------------------------

test('empty tier set is valid', function () {
    expect(BulkPriceRules::validateSet([]))->toBe([]);
});

test('valid tier set with gap and open-ended tail passes', function () {
    $tiers = [
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
        ['min_qty' => 20, 'max_qty' => 50, 'unit_price' => 5.5],
        ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5.0],
    ];

    expect(BulkPriceRules::validateSet($tiers))->toBe([]);
});

test('adjacent non-overlapping ranges pass (5-10 then 11-20)', function () {
    $tiers = [
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
        ['min_qty' => 11, 'max_qty' => 20, 'unit_price' => 5.0],
    ];

    expect(BulkPriceRules::validateSet($tiers))->toBe([]);
});

test('single-quantity range (min == max) is valid', function () {
    expect(BulkPriceRules::validateSet([
        ['min_qty' => 5, 'max_qty' => 5, 'unit_price' => 6.0],
    ]))->toBe([]);
});

test('string numeric payloads (multipart form data) are accepted', function () {
    $tiers = [
        ['min_qty' => '5', 'max_qty' => '10', 'unit_price' => '6.000'],
        ['min_qty' => '11', 'max_qty' => '', 'unit_price' => '5.5'],
    ];

    expect(BulkPriceRules::validateSet($tiers))->toBe([]);
});

// ---------------------------------------------------------------------------
// validateSet: per-tier rule violations
// ---------------------------------------------------------------------------

test('min_qty below 1 is rejected', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 0, 'max_qty' => 10, 'unit_price' => 6.0],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('minimum quantity');
});

test('missing min_qty is rejected', function () {
    $errors = BulkPriceRules::validateSet([
        ['max_qty' => 10, 'unit_price' => 6.0],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('minimum quantity');
});

test('max_qty below min_qty is rejected', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 10, 'max_qty' => 5, 'unit_price' => 6.0],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('maximum quantity (5)');
});

test('zero and negative unit prices are rejected', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 1, 'max_qty' => 5, 'unit_price' => 0],
        ['min_qty' => 6, 'max_qty' => 10, 'unit_price' => -2],
    ]);

    expect($errors)->toHaveCount(2)
        ->and($errors[0])->toContain('unit price must be greater than 0')
        ->and($errors[1])->toContain('unit price must be greater than 0');
});

// ---------------------------------------------------------------------------
// validateSet: overlaps
// ---------------------------------------------------------------------------

test('duplicate ranges overlap and name the clash', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 5.0],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('range 5-10 overlaps existing range 5-10');
});

test('partially intersecting ranges overlap', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
        ['min_qty' => 10, 'max_qty' => 20, 'unit_price' => 5.0],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('range 10-20 overlaps existing range 5-10');
});

test('open-ended tier overlaps a later bounded range', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 20, 'max_qty' => null, 'unit_price' => 5.0],
        ['min_qty' => 30, 'max_qty' => 40, 'unit_price' => 4.5],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('range 30-40 overlaps existing range 20+');
});

test('a second open-ended tier always overlaps', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 20, 'max_qty' => null, 'unit_price' => 5.0],
        ['min_qty' => 100, 'max_qty' => null, 'unit_price' => 4.0],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('range 100+ overlaps existing range 20+');
});

// ---------------------------------------------------------------------------
// validateSet: floor (Minimum_Selling_Price)
// ---------------------------------------------------------------------------

test('tier priced below the floor is rejected', function () {
    $errors = BulkPriceRules::validateSet([
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 4.999],
    ], 5.0);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('below the minimum selling price 5.000');
});

test('tier priced exactly at the floor passes', function () {
    expect(BulkPriceRules::validateSet([
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 5.0],
    ], 5.0))->toBe([]);
});

test('null floor skips the floor check', function () {
    expect(BulkPriceRules::validateSet([
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 0.001],
    ], null))->toBe([]);
});

// ---------------------------------------------------------------------------
// resolveUnitPrice
// ---------------------------------------------------------------------------

test('resolveUnitPrice matches boundaries, gaps, and open-ended tiers', function () {
    $tiers = [
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
        ['min_qty' => 20, 'max_qty' => 50, 'unit_price' => 5.5],
        ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5.0],
    ];

    expect(BulkPriceRules::resolveUnitPrice($tiers, 4))->toBeNull()      // below every tier
        ->and(BulkPriceRules::resolveUnitPrice($tiers, 5))->toBe(6.0)    // qty == min boundary
        ->and(BulkPriceRules::resolveUnitPrice($tiers, 10))->toBe(6.0)   // qty == max boundary
        ->and(BulkPriceRules::resolveUnitPrice($tiers, 15))->toBeNull()  // gap pays normal price
        ->and(BulkPriceRules::resolveUnitPrice($tiers, 20))->toBe(5.5)
        ->and(BulkPriceRules::resolveUnitPrice($tiers, 51))->toBe(5.0)   // open-ended start
        ->and(BulkPriceRules::resolveUnitPrice($tiers, 100000))->toBe(5.0);
});

test('resolveUnitPrice returns null for an empty set', function () {
    expect(BulkPriceRules::resolveUnitPrice([], 10))->toBeNull();
});

test('resolveUnitPrice accepts DB-style column keys', function () {
    $tiers = [
        ['Min_Qty' => 5, 'Max_Qty' => 10, 'Unit_Price' => '6.000'],
    ];

    expect(BulkPriceRules::resolveUnitPrice($tiers, 7))->toBe(6.0);
});

// ---------------------------------------------------------------------------
// normalizeSet / describeRange
// ---------------------------------------------------------------------------

test('normalizeSet sorts by min_qty and canonicalizes keys and types', function () {
    $normalized = BulkPriceRules::normalizeSet([
        ['Min_Qty' => '51', 'Max_Qty' => '', 'Unit_Price' => '5'],
        ['min_qty' => '5', 'max_qty' => '10', 'unit_price' => '6.0004'],
    ]);

    expect($normalized)->toBe([
        ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
        ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5.0],
    ]);
});

test('describeRange renders bounded and open-ended ranges', function () {
    expect(BulkPriceRules::describeRange(5, 10))->toBe('5-10')
        ->and(BulkPriceRules::describeRange(51, null))->toBe('51+');
});
