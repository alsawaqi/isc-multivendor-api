<?php

namespace Tests\Feature\Vendor;

use Tests\FeatureTestCase;

class VendorLoginTest extends FeatureTestCase
{
    public function test_pending_vendor_cannot_log_in_and_sees_awaiting_message(): void
    {
        $vendor = $this->makeVendor(['Approval_Status' => 'pending', 'Status' => 'pending', 'Is_Active' => 0]);
        $user = $this->makeVendorUser($vendor, 'password123');

        $res = $this->postJson('/vendor/auth/login', [
            'login'    => $user->email,
            'password' => 'password123',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('approval', json_encode($res->json()));
    }

    public function test_rejected_vendor_sees_not_approved_message(): void
    {
        $vendor = $this->makeVendor(['Approval_Status' => 'rejected', 'Status' => 'blocked', 'Is_Active' => 0]);
        $user = $this->makeVendorUser($vendor, 'password123');

        $res = $this->postJson('/vendor/auth/login', [
            'login'    => $user->email,
            'password' => 'password123',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('not approved', json_encode($res->json()));
    }

    public function test_approved_active_vendor_can_log_in(): void
    {
        $vendor = $this->makeVendor(); // approved + active by default
        $user = $this->makeVendorUser($vendor, 'password123');

        $res = $this->postJson('/vendor/auth/login', [
            'login'    => $user->email,
            'password' => 'password123',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $vendor = $this->makeVendor();
        $user = $this->makeVendorUser($vendor, 'password123');

        $this->postJson('/vendor/auth/login', [
            'login'    => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }
}
