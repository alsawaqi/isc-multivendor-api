<?php

namespace Tests\Feature\Vendor;

use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

class VendorStockOrdersTest extends FeatureTestCase
{
    /**
     * Insert a minimal-but-valid product row owned by $vendor.
     *
     * Products_Master_T has several NOT NULL columns without defaults
     * (the three department ids, names, description, price, stock, Created_By),
     * so we set them all explicitly and bypass the model's limited $fillable.
     */
    private function makeProduct(Vendor $vendor, array $overrides = []): int
    {
        return (int) DB::table('Products_Master_T')->insertGetId(array_merge([
            'Product_Code'                  => 'PC_' . uniqid(),
            'Product_Department_Id'         => 1,
            'Product_Sub_Department_Id'     => 1,
            'Product_Sub_Sub_Department_Id' => 1,
            'Product_Name'                  => 'Test Product',
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

    // ---------------------------------------------------------------------
    // Auth gate: every endpoint must reject unauthenticated callers (401).
    // ---------------------------------------------------------------------

    public function test_stock_products_requires_authentication(): void
    {
        $this->getJson('/vendor/api/stock/products')->assertStatus(401);
    }

    public function test_orders_requires_authentication(): void
    {
        $this->getJson('/vendor/api/orders')->assertStatus(401);
    }

    public function test_earnings_summary_requires_authentication(): void
    {
        $this->getJson('/vendor/api/earnings/summary')->assertStatus(401);
    }

    public function test_payouts_requires_authentication(): void
    {
        $this->getJson('/vendor/api/payouts')->assertStatus(401);
    }

    // ---------------------------------------------------------------------
    // Happy-path list endpoints: 200 + expected top-level JSON shape.
    // ---------------------------------------------------------------------

    public function test_stock_products_returns_paginator_for_authenticated_vendor(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $this->makeProduct($vendor);

        $res = $this->getJson('/vendor/api/stock/products');

        $res->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);

        // The vendor's own product is present in the list.
        $this->assertGreaterThanOrEqual(1, $res->json('total'));
    }

    public function test_orders_index_returns_paginator(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/api/orders');

        $res->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_earnings_summary_returns_summary_block(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/api/earnings/summary');

        $res->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_orders',
                        'gross_sales',
                        'commission_total',
                        'net_earnings_total',
                    ],
                    'recent_orders',
                    'sales_trend',
                ],
            ]);
    }

    public function test_payouts_index_returns_data_and_meta(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/api/payouts');

        $res->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    // ---------------------------------------------------------------------
    // Stock adjust: validation + a safe happy-path increase.
    // ---------------------------------------------------------------------

    public function test_stock_adjust_validates_movement_type(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor);

        // Missing movement_type (and quantity) -> 422.
        $this->postJson("/vendor/api/stock/products/{$productId}/adjust", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['movement_type']);
    }

    public function test_stock_adjust_increase_updates_stock_and_logs_movement(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $productId = $this->makeProduct($vendor, ['Product_Stock' => 5]);

        $res = $this->postJson("/vendor/api/stock/products/{$productId}/adjust", [
            'movement_type' => 'increase',
            'quantity'      => 3,
            'notes'         => 'restock',
        ]);

        $res->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('Products_Master_T', [
            'id'            => $productId,
            'Product_Stock' => 8,
        ]);

        $this->assertDatabaseHas('Product_Stock_Movements_T', [
            'Products_Id'   => $productId,
            'Vendor_Id'     => $vendor->id,
            'Movement_Type' => 'increase',
            'New_Stock'     => 8,
        ]);
    }
}
