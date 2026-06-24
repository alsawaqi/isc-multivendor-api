<?php

namespace Tests\Feature\Vendor;

use Illuminate\Support\Facades\Hash;
use Tests\FeatureTestCase;

class VendorChangePasswordTest extends FeatureTestCase
{
    public function test_vendor_can_change_password(): void
    {
        $user = $this->actingAsVendor(password: 'password123');

        $res = $this->postJson('/vendor/auth/change-password', [
            'current_password'      => 'password123',
            'password'              => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('newpassword456', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->actingAsVendor(password: 'password123');

        $this->postJson('/vendor/auth/change-password', [
            'current_password'      => 'not-the-password',
            'password'              => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ])->assertStatus(422);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/vendor/auth/change-password', [
            'current_password'      => 'password123',
            'password'              => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ])->assertStatus(401);
    }
}
