<?php

namespace Tests\Feature\Vendor;

use Tests\FeatureTestCase;

class VendorGeoTest extends FeatureTestCase
{
    public function test_countries_lookup_returns_a_list(): void
    {
        $this->actingAsVendor();

        $res = $this->getJson('/vendor/api/geo/countries');

        $res->assertOk();
        $this->assertIsArray($res->json());
    }

    public function test_geo_requires_authentication(): void
    {
        $this->getJson('/vendor/api/geo/countries')->assertStatus(401);
    }
}
