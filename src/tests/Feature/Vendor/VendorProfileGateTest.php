<?php

namespace Tests\Feature\Vendor;

use Tests\FeatureTestCase;

class VendorProfileGateTest extends FeatureTestCase
{
    public function test_me_reports_incomplete_profile_for_bare_vendor(): void
    {
        // Bare approved vendor — only name/email/phone, no trade/address/bank.
        $vendor = $this->makeVendor();
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/auth/me');

        $res->assertOk();
        $this->assertFalse($res->json('user.profile_complete'));
    }

    public function test_me_reports_complete_profile_when_required_fields_filled(): void
    {
        $vendor = $this->makeVendor([
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
        ]);
        $this->actingAsVendor($vendor);

        $res = $this->getJson('/vendor/auth/me');

        $res->assertOk();
        $this->assertTrue($res->json('user.profile_complete'));
    }
}
