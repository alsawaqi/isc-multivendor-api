<?php

namespace Tests\Feature\Vendor;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class VendorNotificationsTest extends FeatureTestCase
{
    private function seedNotification(int $vendorId, ?string $readAt = null): void
    {
        DB::table('Conx_Notifications_T')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\VendorNewSale',
            'notifiable_type' => 'App\\Models\\Vendor',
            'notifiable_id'   => $vendorId,
            'data'            => json_encode(['title' => 'New sale', 'message' => 'You have a new order.', 'url' => '/orders']),
            'read_at'         => $readAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function test_vendor_sees_their_notifications_and_unread_count(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $this->seedNotification($vendor->id);

        $res = $this->getJson('/vendor/api/notifications');

        $res->assertOk();
        $this->assertGreaterThanOrEqual(1, count($res->json('data')));
        $this->assertGreaterThanOrEqual(1, $res->json('unread_count'));
    }

    public function test_mark_all_read_zeroes_the_unread_count(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);
        $this->seedNotification($vendor->id);

        $this->postJson('/vendor/api/notifications/mark-as-read')->assertOk();

        $res = $this->getJson('/vendor/api/notifications');
        $res->assertOk();
        $this->assertSame(0, $res->json('unread_count'));
    }

    public function test_vendor_only_sees_their_own_notifications(): void
    {
        $other = $this->makeVendor();
        $this->seedNotification($other->id); // belongs to another vendor

        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/api/notifications');
        $res->assertOk();
        $this->assertSame(0, $res->json('unread_count'));
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/vendor/api/notifications')->assertStatus(401);
    }
}
