<?php

namespace Tests\Feature\Vendor;

use Tests\FeatureTestCase;

class VendorSubmitForReviewTest extends FeatureTestCase
{
    private array $completeProfile = [
        'Approval_Status'   => 'accepted',
        'Is_Active'         => 1,
        'Trade_Name'        => 'Trade',
        'CR_Number'         => 'CR-1',
        'VAT_Number'        => 'VAT-1',
        'Address_Line1'     => 'Street 1',
        'Country_Id'        => 1,
        'Region_Id'         => 1,
        'City_Id'           => 1,
        'Bank_Name'         => 'Bank',
        'Bank_Account_Name' => 'Account',
        'Bank_IBAN'         => 'IBAN123',
        'Payout_Method'     => 'bank_transfer',
    ];

    public function test_accepted_vendor_with_complete_profile_can_submit_for_review(): void
    {
        $vendor = $this->makeVendor($this->completeProfile);
        $this->actingAsVendor($vendor);

        $this->postJson('/vendor/api/profile/submit')->assertOk();

        $this->assertSame('under_review', $vendor->fresh()->Approval_Status);
    }

    public function test_incomplete_profile_cannot_submit(): void
    {
        // bare accepted vendor (no trade/address/bank) — submission blocked
        $vendor = $this->makeVendor(['Approval_Status' => 'accepted', 'Is_Active' => 1]);
        $this->actingAsVendor($vendor);

        $this->postJson('/vendor/api/profile/submit')->assertStatus(422);
        $this->assertSame('accepted', $vendor->fresh()->Approval_Status);
    }
}
