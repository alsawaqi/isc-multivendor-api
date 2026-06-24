<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Support\Vendors\VendorOnboardingChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorProfileController extends Controller
{
    public function show()
    {
        $vendor = $this->vendor();

        return response()->json([
            'success' => true,
            'data' => $this->decorate($vendor),
        ]);
    }

    public function update(Request $request)
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'Vendor_Name' => ['nullable', 'string', 'max:255'],
            'Trade_Name' => ['nullable', 'string', 'max:255'],
            'CR_Number' => ['nullable', 'string', 'max:100'],
            'VAT_Number' => ['nullable', 'string', 'max:100'],
            'Email_1' => ['nullable', 'email', 'max:255'],
            'Phone_No' => ['nullable', 'string', 'max:50'],
            'Business_Type' => ['nullable', 'string', 'max:80'],
            'Contact_Person_Name' => ['nullable', 'string', 'max:150'],
            'Contact_Person_Title' => ['nullable', 'string', 'max:100'],
            'Contact_Person_Email' => ['nullable', 'email', 'max:255'],
            'Contact_Person_Phone' => ['nullable', 'string', 'max:50'],
            'Address_Line1' => ['nullable', 'string', 'max:255'],
            'Address_Line2' => ['nullable', 'string', 'max:255'],
            'Postal_Code' => ['nullable', 'string', 'max:30'],
            'PO_Box' => ['nullable', 'string', 'max:30'],
            'Country_Id' => ['nullable', 'integer'],
            'Region_Id' => ['nullable', 'integer'],
            'District_Id' => ['nullable', 'integer'],
            'City_Id' => ['nullable', 'integer'],
            'Bank_Name' => ['nullable', 'string', 'max:150'],
            'Bank_Account_Name' => ['nullable', 'string', 'max:150'],
            'Bank_Account_Number' => ['nullable', 'string', 'max:80'],
            'Bank_IBAN' => ['nullable', 'string', 'max:80'],
            'Bank_Swift_Code' => ['nullable', 'string', 'max:40'],
            'Payout_Method' => ['nullable', 'in:bank_transfer,manual,cheque'],
        ]);

        $vendor->update($this->filterVendorPayload($data));
        $this->syncOnboardingSnapshot($vendor->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Vendor profile updated.',
            'data' => $this->decorate($vendor->fresh()),
        ]);
    }

    public function upsertDocuments(Request $request)
    {
        if (! $this->documentsReady()) {
            return response()->json(['message' => 'Vendor document table is not migrated yet.'], 409);
        }

        $vendor = $this->vendor();
        $user = Auth::guard('vendor')->user();

        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.Document_Type' => ['required', 'string', 'max:60'],
            'documents.*.Document_Name' => ['nullable', 'string', 'max:150'],
            'documents.*.File_Path' => ['nullable', 'string', 'max:2000'],
            'documents.*.File_Mime' => ['nullable', 'string', 'max:80'],
            'documents.*.File_Size' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['documents'] as $document) {
            VendorDocument::updateOrCreate(
                [
                    'Vendor_Id' => $vendor->id,
                    'Document_Type' => $document['Document_Type'],
                ],
                [
                    'Document_Name' => $document['Document_Name'] ?? null,
                    'File_Path' => $document['File_Path'] ?? null,
                    'File_Mime' => $document['File_Mime'] ?? null,
                    'File_Size' => $document['File_Size'] ?? null,
                    'Status' => 'pending',
                    'Review_Note' => null,
                    'Uploaded_By_Vendor_User_Id' => $user?->id,
                ]
            );
        }

        $this->syncOnboardingSnapshot($vendor->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Vendor documents submitted for review.',
            'data' => $this->decorate($vendor->fresh()),
        ]);
    }

    public function submitForReview()
    {
        $vendor = $this->vendor();
        $decorated = $this->decorate($vendor);
        $checklist = $decorated->getAttribute('onboarding_checklist');

        // Require the PROFILE items (business / trade / address / bank) only —
        // documents are reviewed separately by admins and don't block submission.
        $items = collect($checklist['items'] ?? []);
        $profileItems = $items->reject(fn ($i) => ($i['key'] ?? null) === 'documents');
        $profileComplete = $profileItems->isNotEmpty()
            && $profileItems->every(fn ($i) => ! empty($i['complete']));

        if (! $profileComplete) {
            return response()->json([
                'success' => false,
                'message' => 'Complete your business, address and bank details before submitting for approval.',
                'missing' => $profileItems->reject(fn ($i) => ! empty($i['complete']))->pluck('label')->values(),
                'onboarding_checklist' => $checklist,
            ], 422);
        }

        $vendor->update($this->filterVendorPayload([
            'Approval_Status' => 'under_review',
            'Submitted_For_Approval_At' => now(),
            'Onboarding_Status' => 'ready_for_review',
            'Onboarding_Completeness' => 100,
        ]));

        $this->notifyAdminsOfSubmission($vendor->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Profile submitted. An admin will review and approve your account.',
            'data' => $this->decorate($vendor->fresh()),
        ]);
    }

    /**
     * Notify admins that a vendor has submitted their completed profile (gate 2).
     */
    private function notifyAdminsOfSubmission(Vendor $vendor): void
    {
        if (! Schema::hasTable('Conx_Notifications_T') || ! Schema::hasTable('Secx_Admin_User_Master_T')) {
            return;
        }

        $adminQuery = DB::table('Secx_Admin_User_Master_T')->select('id');
        if (Schema::hasColumn('Secx_Admin_User_Master_T', 'No_Login')) {
            $adminQuery->whereNull('No_Login');
        }
        $adminIds = $adminQuery->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $now = now();
        $data = json_encode([
            'title'     => 'Vendor profile submitted',
            'message'   => "{$vendor->Vendor_Name} completed their profile and is awaiting approval.",
            'url'       => '/admin/vendor/registration-requests',
            'vendor_id' => $vendor->id,
        ]);

        $rows = $adminIds->map(fn ($id) => [
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\VendorProfileSubmitted',
            'notifiable_type' => 'App\\Models\\Admin',
            'notifiable_id'   => $id,
            'data'            => $data,
            'read_at'         => null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->all();

        DB::table('Conx_Notifications_T')->insert($rows);
    }

    private function vendor(): Vendor
    {
        $user = Auth::guard('vendor')->user();
        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        if (!$vendorId) {
            abort(403, 'Vendor ID not found for this user.');
        }

        return Vendor::query()
            ->when($this->documentsReady(), fn ($query) => $query->with('documents'))
            ->findOrFail((int) $vendorId);
    }

    private function documentsReady(): bool
    {
        return Schema::hasTable('Vendor_Documents_T');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterVendorPayload(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, string $key) => Schema::hasColumn('Vendors_Master_T', $key))
            ->all();
    }

    private function decorate(Vendor $vendor): Vendor
    {
        if ($this->documentsReady() && !$vendor->relationLoaded('documents')) {
            $vendor->load('documents');
        }

        $vendor->setAttribute('onboarding_checklist', $this->onboardingChecklist($vendor));
        $vendor->syncOriginalAttribute('onboarding_checklist');

        return $vendor;
    }

    private function onboardingChecklist(Vendor $vendor): array
    {
        $documents = $this->documentsReady() ? $vendor->documents->all() : [];

        return VendorOnboardingChecklist::evaluate($vendor->getAttributes(), $documents);
    }

    private function syncOnboardingSnapshot(?Vendor $vendor): void
    {
        if (!$vendor || !Schema::hasColumn('Vendors_Master_T', 'Onboarding_Completeness')) {
            return;
        }

        $checklist = $this->onboardingChecklist($vendor);
        $snapshot = $this->filterVendorPayload([
            'Onboarding_Completeness' => $checklist['completeness_percent'],
            'Onboarding_Status' => $checklist['readiness'],
            'Payout_Status' => ($checklist['items'][3]['complete'] ?? false) ? 'pending_review' : 'not_configured',
        ]);

        if ($snapshot !== []) {
            $vendor->forceFill($snapshot)->save();
        }
    }
}
