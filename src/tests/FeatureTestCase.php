<?php

namespace Tests;

use App\Models\Vendor;
use App\Models\VendorUser;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

/**
 * Base class for portal HTTP feature tests.
 *
 * Runs against the shared sqlsrv `isc` connection and rolls back each test
 * (DatabaseTransactions). CSRF is disabled (the SPA normally supplies the token).
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function makeVendor(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'Vendor_Code'     => 'TEST_' . uniqid(),
            'Vendor_Name'     => 'Test Vendor',
            'Email_1'         => 'v' . uniqid() . '@example.com',
            'Phone_No'        => '+96850000000',
            'Approval_Status' => 'approved',
            'Status'          => 'active',
            'Is_Active'       => 1,
        ], $overrides));
    }

    protected function makeVendorUser(Vendor $vendor, string $password = 'password123', array $overrides = []): VendorUser
    {
        return VendorUser::create(array_merge([
            'Vendor_Id' => $vendor->id,
            'User_Id'   => 'TU_' . uniqid(),
            'User_Name' => 'Test User',
            'email'     => 'u' . uniqid() . '@example.com',
            'password'  => Hash::make($password),
        ], $overrides));
    }

    /** Authenticate as a vendor user on the 'vendor' session guard. */
    protected function actingAsVendor(?Vendor $vendor = null, string $password = 'password123'): VendorUser
    {
        $vendor = $vendor ?: $this->makeVendor();
        $user = $this->makeVendorUser($vendor, $password);
        $this->actingAs($user, 'vendor');

        return $user;
    }
}
