<?php

namespace Tests\Feature\Vendor;

use Tests\FeatureTestCase;

class VendorCatalogProductsTest extends FeatureTestCase
{
    // ---- Auth gate (no authentication) -------------------------------------

    public function test_catalog_departments_requires_authentication(): void
    {
        $this->getJson('/vendor/api/catalog/departments')->assertStatus(401);
    }

    public function test_products_pending_requires_authentication(): void
    {
        $this->getJson('/vendor/api/products/pending')->assertStatus(401);
    }

    public function test_products_summary_requires_authentication(): void
    {
        $this->getJson('/vendor/api/products/summary')->assertStatus(401);
    }

    // ---- Approved + active vendor: happy paths -----------------------------

    public function test_catalog_departments_returns_ok_for_approved_vendor(): void
    {
        $this->actingAsVendor($this->makeVendor());

        // Endpoint returns a JSON collection (possibly empty) -> 200.
        $res = $this->getJson('/vendor/api/catalog/departments');

        $res->assertOk();
        $this->assertIsArray($res->json());
    }

    public function test_products_pending_returns_paginated_ok(): void
    {
        $this->actingAsVendor($this->makeVendor());

        $res = $this->getJson('/vendor/api/products/pending');

        $res->assertOk();
        // Laravel paginator top-level keys.
        $res->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_products_approved_returns_paginated_ok(): void
    {
        $this->actingAsVendor($this->makeVendor());

        $res = $this->getJson('/vendor/api/products/approved');

        $res->assertOk();
        $res->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_products_summary_returns_pending_and_approved_keys(): void
    {
        $this->actingAsVendor($this->makeVendor());

        $res = $this->getJson('/vendor/api/products/summary');

        $res->assertOk();
        $res->assertJsonStructure([
            'pending'  => ['data', 'current_page', 'total'],
            'approved' => ['data', 'current_page', 'total'],
        ]);
    }

    // ---- Approval gate: active-but-pending vendor gets 403 -----------------

    public function test_pending_vendor_is_blocked_by_approval_gate(): void
    {
        // Active (passes vendor.active) but NOT approved (fails vendor.approved).
        $vendor = $this->makeVendor(['Approval_Status' => 'pending', 'Is_Active' => 1]);
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/api/catalog/departments');

        $res->assertStatus(403);
        $res->assertJson(['message' => 'Vendor approval is required before accessing this area.']);
    }
}
