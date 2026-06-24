<?php

namespace Tests\Feature\Vendor;

use App\Models\Vendor;
use App\Models\VendorUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\FeatureTestCase;

class VendorRegistrationTest extends FeatureTestCase
{
    public function test_self_registration_creates_pending_inactive_vendor_and_user(): void
    {
        $email = 'reg_' . uniqid() . '@example.com';

        $res = $this->postJson('/vendor/auth/register', [
            'company_name'          => 'Reg Test Co',
            'email'                 => $email,
            'phone'                 => '+96850000000',
            'cr_number'             => 'CR-1',
            'vat_number'            => 'VAT-1',
            'business_type'         => 'Retailer',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertStatus(201)->assertJsonPath('success', true);

        $user = VendorUser::where('email', $email)->first();
        $this->assertNotNull($user);

        $vendor = Vendor::find($user->Vendor_Id);
        $this->assertSame('pending', $vendor->Approval_Status);
        $this->assertSame(0, (int) $vendor->Is_Active);
    }

    public function test_registration_stores_uploaded_documents(): void
    {
        Storage::fake('r2');

        $email = 'regdoc_' . uniqid() . '@example.com';

        $res = $this->post('/vendor/auth/register', [
            'company_name'          => 'Doc Co',
            'email'                 => $email,
            'phone'                 => '+96850000000',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cr_document'           => UploadedFile::fake()->create('cr.pdf', 50, 'application/pdf'),
            'rent_contract_document' => UploadedFile::fake()->create('rent.pdf', 30, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201);

        $vendor = Vendor::where('Email_1', $email)->first();
        $this->assertNotNull($vendor);

        $this->assertDatabaseHas('Vendor_Documents_T', [
            'Vendor_Id'     => $vendor->id,
            'Document_Type' => 'commercial_registration',
            'Status'        => 'pending',
        ]);
        $this->assertDatabaseHas('Vendor_Documents_T', [
            'Vendor_Id'     => $vendor->id,
            'Document_Type' => 'rent_contract',
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $vendor = $this->makeVendor();
        $existing = $this->makeVendorUser($vendor);

        $this->postJson('/vendor/auth/register', [
            'company_name'          => 'Dup Co',
            'email'                 => $existing->email,
            'phone'                 => '+96850000000',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_password_confirmation_is_required(): void
    {
        $this->postJson('/vendor/auth/register', [
            'company_name'          => 'Mismatch Co',
            'email'                 => 'mm_' . uniqid() . '@example.com',
            'phone'                 => '+96850000000',
            'password'              => 'password123',
            'password_confirmation' => 'different456',
        ])->assertStatus(422);
    }
}
