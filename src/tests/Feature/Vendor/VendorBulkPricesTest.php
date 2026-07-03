<?php

namespace Tests\Feature\Vendor;

use App\Models\ProductVendorRequest;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

/**
 * Vendor quantity-tier bulk pricing:
 *  - optional bulk_prices on temp product store/resubmit (replace-set into
 *    Products_Temporary_Bulk_Prices_T, smart validation 422s),
 *  - bulk_prices key inside approved-update Requested_Changes_Json
 *    (floor = Minimum_Selling_Price enforced at submission),
 *  - tier exposure on the pending/approved detail endpoints.
 */
class VendorBulkPricesTest extends FeatureTestCase
{
    /** Same minimal-but-valid master product row pattern as VendorStockOrdersTest. */
    private function makeProduct(Vendor $vendor, array $overrides = []): int
    {
        return (int) DB::table('Products_Master_T')->insertGetId(array_merge([
            'Product_Code'                  => 'PC_' . uniqid(),
            'Product_Department_Id'         => 1,
            'Product_Sub_Department_Id'     => 1,
            'Product_Sub_Sub_Department_Id' => 1,
            'Product_Name'                  => 'Bulk Price Product',
            'Product_Name_Ar'               => 'منتج',
            'Product_Description'           => 'Test description',
            'Product_Price'                 => 10.50,
            'Product_Stock'                 => 5,
            'Status'                        => 'available',
            'Created_By'                    => 1,
            'Vendor_Id'                     => $vendor->id,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ], $overrides));
    }

    private function tempProductPayload(array $overrides = []): array
    {
        return array_merge([
            'product_department_id' => 1,
            'product_sub_department_id' => 1,
            'product_sub_sub_department_id' => 1,
            'product_type_id' => 1,
            'name' => 'Bulk Temp Product',
            'name_ar' => 'منتج مؤقت',
            'description' => 'A temp product used by bulk pricing tests.',
            'price' => 10,
            'stock' => 100,
        ], $overrides);
    }

    // ---- Temp product store -------------------------------------------------

    public function test_store_temp_product_with_valid_tiers_persists_replace_set(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $res = $this->postJson('/vendor/api/products-temp', $this->tempProductPayload([
            'bulk_prices' => [
                ['min_qty' => 20, 'max_qty' => 50, 'unit_price' => 5.5],
                ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6],
                ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5],
            ],
        ]));

        $res->assertStatus(201);
        $tempId = (int) $res->json('data.id');

        $rows = DB::table('Products_Temporary_Bulk_Prices_T')
            ->where('Products_Temporary_Id', $tempId)
            ->orderBy('Min_Qty')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame(5, (int) $rows[0]->Min_Qty);
        $this->assertSame(10, (int) $rows[0]->Max_Qty);
        $this->assertSame(20, (int) $rows[1]->Min_Qty);
        $this->assertSame(51, (int) $rows[2]->Min_Qty);
        $this->assertNull($rows[2]->Max_Qty);

        // Response exposes the ordered tiers (relation key snake_cased).
        $tiers = $res->json('data.bulk_prices');
        $this->assertCount(3, $tiers);
        $this->assertSame(5, (int) $tiers[0]['Min_Qty']);
    }

    public function test_store_temp_product_without_bulk_prices_creates_no_tier_rows(): void
    {
        $this->actingAsVendor($this->makeVendor());

        $res = $this->postJson('/vendor/api/products-temp', $this->tempProductPayload());

        $res->assertStatus(201);
        $tempId = (int) $res->json('data.id');

        $this->assertSame(0, DB::table('Products_Temporary_Bulk_Prices_T')
            ->where('Products_Temporary_Id', $tempId)
            ->count());
    }

    public function test_store_temp_product_with_overlapping_tiers_is_422(): void
    {
        $this->actingAsVendor($this->makeVendor());

        $res = $this->postJson('/vendor/api/products-temp', $this->tempProductPayload([
            'bulk_prices' => [
                ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6],
                ['min_qty' => 8, 'max_qty' => 20, 'unit_price' => 5],
            ],
        ]));

        $res->assertStatus(422);
        $this->assertStringContainsString(
            'range 8-20 overlaps existing range 5-10',
            implode(' ', $res->json('errors.bulk_prices'))
        );
    }

    public function test_store_temp_product_with_non_positive_price_is_422(): void
    {
        $this->actingAsVendor($this->makeVendor());

        $this->postJson('/vendor/api/products-temp', $this->tempProductPayload([
            'bulk_prices' => [
                ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 0],
            ],
        ]))->assertStatus(422);
    }

    // ---- Temp product resubmit (replace-set + clear) ------------------------

    public function test_resubmit_replaces_tier_set_and_empty_array_clears_it(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $created = $this->postJson('/vendor/api/products-temp', $this->tempProductPayload([
            'bulk_prices' => [['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6]],
        ]));
        $created->assertStatus(201);
        $tempId = (int) $created->json('data.id');

        // Replace with a different set.
        $this->patchJson("/vendor/api/products-temp/{$tempId}", $this->tempProductPayload([
            'bulk_prices' => [['min_qty' => 3, 'max_qty' => null, 'unit_price' => 7]],
        ]))->assertOk();

        $rows = DB::table('Products_Temporary_Bulk_Prices_T')
            ->where('Products_Temporary_Id', $tempId)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]->Min_Qty);
        $this->assertNull($rows[0]->Max_Qty);

        // Explicit empty array clears every tier.
        $this->patchJson("/vendor/api/products-temp/{$tempId}", $this->tempProductPayload([
            'bulk_prices' => [],
        ]))->assertOk();

        $this->assertSame(0, DB::table('Products_Temporary_Bulk_Prices_T')
            ->where('Products_Temporary_Id', $tempId)
            ->count());
    }

    public function test_resubmit_without_bulk_prices_key_keeps_existing_tiers(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $created = $this->postJson('/vendor/api/products-temp', $this->tempProductPayload([
            'bulk_prices' => [['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6]],
        ]));
        $created->assertStatus(201);
        $tempId = (int) $created->json('data.id');

        $this->patchJson("/vendor/api/products-temp/{$tempId}", $this->tempProductPayload())
            ->assertOk();

        $this->assertSame(1, DB::table('Products_Temporary_Bulk_Prices_T')
            ->where('Products_Temporary_Id', $tempId)
            ->count());
    }

    // ---- Detail endpoints ---------------------------------------------------

    public function test_pending_detail_exposes_ordered_bulk_prices(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $created = $this->postJson('/vendor/api/products-temp', $this->tempProductPayload([
            'bulk_prices' => [
                ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5],
                ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6],
            ],
        ]));
        $created->assertStatus(201);
        $tempId = (int) $created->json('data.id');

        $res = $this->getJson("/vendor/api/products/pending/{$tempId}");

        $res->assertOk();
        $tiers = $res->json('data.bulk_prices');
        $this->assertCount(2, $tiers);
        $this->assertSame(5, $tiers[0]['min_qty']);
        $this->assertSame(10, $tiers[0]['max_qty']);
        $this->assertSame(51, $tiers[1]['min_qty']);
        $this->assertNull($tiers[1]['max_qty']);
    }

    public function test_approved_detail_exposes_bulk_prices(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor);

        // One row per insert: sqlsrv batch inserts reuse one bind type per
        // column position across rows, which trips on mixed int/decimal.
        DB::table('Products_Bulk_Prices_T')->insert(
            ['Products_Id' => $productId, 'Min_Qty' => 20, 'Max_Qty' => 50, 'Unit_Price' => 5.5, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('Products_Bulk_Prices_T')->insert(
            ['Products_Id' => $productId, 'Min_Qty' => 5, 'Max_Qty' => 10, 'Unit_Price' => 6, 'created_at' => now(), 'updated_at' => now()]
        );

        $res = $this->getJson("/vendor/api/products/approved/{$productId}");

        $res->assertOk();
        $tiers = $res->json('data.bulk_prices');
        $this->assertCount(2, $tiers);
        $this->assertSame(5, $tiers[0]['min_qty']);
        $this->assertSame(20, $tiers[1]['min_qty']);
    }

    // ---- Approved-update request: bulk_prices key ---------------------------

    public function test_update_request_with_bulk_prices_stores_normalized_key_in_changes_json(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor);

        $res = $this->postJson("/vendor/api/products/approved/{$productId}/request-update", [
            'comment' => 'Please add bulk pricing.',
            'changes' => [
                'bulk_prices' => [
                    ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5],
                    ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6],
                ],
            ],
        ]);

        $res->assertStatus(201)->assertJson(['success' => true]);

        $request = ProductVendorRequest::query()
            ->where('Products_Id', $productId)
            ->where('Request_Type', 'approved_update')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($request);
        $changes = $request->Requested_Changes_Json;
        $this->assertArrayHasKey('bulk_prices', $changes);
        // Normalized: sorted by min_qty, canonical snake_case keys.
        // assertEquals (loose): whole floats round-trip through JSON as ints.
        $this->assertEquals(
            [
                ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6.0],
                ['min_qty' => 51, 'max_qty' => null, 'unit_price' => 5.0],
            ],
            $changes['bulk_prices']
        );

        // Timeline exposes a readable display for the requested tiers.
        $detail = $this->getJson("/vendor/api/products/approved/{$productId}");
        $detail->assertOk();
        $display = $detail->json('data.request_history.0.Requested_Bulk_Prices_Display');
        $this->assertSame('5-10', $display[0]['range']);
        $this->assertSame('6.000', $display[0]['unit_price']);
        $this->assertSame('51+', $display[1]['range']);
    }

    public function test_update_request_with_overlapping_bulk_prices_is_422(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor);

        $res = $this->postJson("/vendor/api/products/approved/{$productId}/request-update", [
            'comment' => 'Bad tiers.',
            'changes' => [
                'bulk_prices' => [
                    ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 6],
                    ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 5],
                ],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'range 5-10 overlaps existing range 5-10',
            implode(' ', $res->json('errors.bulk_prices'))
        );

        $this->assertSame(0, ProductVendorRequest::query()
            ->where('Products_Id', $productId)
            ->where('Request_Type', 'approved_update')
            ->count());
    }

    public function test_update_request_tier_below_minimum_selling_price_is_422(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor, ['Minimum_Selling_Price' => 5.000]);

        $res = $this->postJson("/vendor/api/products/approved/{$productId}/request-update", [
            'comment' => 'Below floor.',
            'changes' => [
                'bulk_prices' => [
                    ['min_qty' => 5, 'max_qty' => 10, 'unit_price' => 4.999],
                ],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'below the minimum selling price 5.000',
            implode(' ', $res->json('errors.bulk_prices'))
        );
    }

    public function test_update_request_without_bulk_prices_key_omits_it_from_changes_json(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor);

        $this->postJson("/vendor/api/products/approved/{$productId}/request-update", [
            'comment' => 'Price only.',
            'changes' => ['Product_Price' => 12.5],
        ])->assertStatus(201);

        $request = ProductVendorRequest::query()
            ->where('Products_Id', $productId)
            ->where('Request_Type', 'approved_update')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($request);
        $this->assertArrayNotHasKey('bulk_prices', $request->Requested_Changes_Json);
    }
}
