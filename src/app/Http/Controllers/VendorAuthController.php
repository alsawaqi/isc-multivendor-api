<?php

namespace App\Http\Controllers;

use App\Helpers\CodeGenerator;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorUser;
use App\Support\Vendors\VendorOnboardingChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorAuthController extends Controller
{
    /**
     * Registration document upload fields => Vendor_Documents_T Document_Type.
     * 'commercial_registration' aligns with VendorOnboardingChecklist's required set.
     */
    private const REGISTRATION_DOCUMENTS = [
        'cr_document'                  => 'commercial_registration',
        'chamber_of_commerce_document' => 'chamber_of_commerce',
        'activity_license_document'    => 'activity_license',
        'rent_contract_document'       => 'rent_contract',
    ];
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],   // email OR username (User_Id)
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $login = $data['login'];
        $remember = (bool)($data['remember'] ?? false);

        $credentials = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email' => $login, 'password' => $data['password']]
            : ['User_Id' => $login, 'password' => $data['password']];

        // enforce active user at login time
        $credentials['No_Login'] =  Null;

        if (!Auth::guard('vendor')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('vendor')->user()->load('vendor');

        if (!$user->vendor || (int) $user->vendor->Is_Active !== 1) {
            $approval = strtolower((string) ($user->vendor->Approval_Status ?? ''));

            Auth::guard('vendor')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = match ($approval) {
                'pending', 'under_review' => 'Your vendor account is awaiting admin approval. You will be able to sign in once it has been approved.',
                'rejected' => 'Your vendor registration was not approved. Please contact ISC support for more information.',
                default => 'Vendor account is inactive.',
            };

            throw ValidationException::withMessages([
                'login' => [$message],
            ]);
        }

        $user->setAttribute('profile_complete', $this->profileComplete($user->vendor));

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * Whether the vendor has filled the essential profile fields (business, trade,
     * address, bank/payout). Excludes the document-approval checklist item — that is
     * reviewed by admins separately. Used to gate the dashboard after approval.
     */
    private function profileComplete(?Vendor $vendor): bool
    {
        if (! $vendor) {
            return false;
        }

        $checklist = VendorOnboardingChecklist::evaluate($vendor->getAttributes(), []);

        foreach ($checklist['items'] as $item) {
            if (($item['key'] ?? null) === 'documents') {
                continue;
            }
            if (empty($item['complete'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Public vendor self-registration.
     *
     * Creates a vendor in a "pending" / inactive state plus the login user, then
     * notifies admins. The vendor cannot sign in until an admin approves them
     * (admin approval flips Is_Active=1 + Approval_Status='approved').
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'company_name'  => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('Secx_Vendors_Users_Master_T', 'email')],
            'phone'         => ['required', 'string', 'max:50'],
            'cr_number'     => ['nullable', 'string', 'max:100'],
            'vat_number'    => ['nullable', 'string', 'max:100'],
            'business_type' => ['nullable', 'string', 'max:80'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],

            // Registration documents (all optional; CR/Chamber/Activity/Rent)
            'cr_document'                  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'chamber_of_commerce_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'activity_license_document'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'rent_contract_document'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = null;

        $vendor = DB::transaction(function () use ($data, &$user) {
            $vendorCode = CodeGenerator::createCode('VENDOR', 'Vendors_Master_T', 'Vendor_Code');

            // Build the vendor payload, then keep only columns that actually exist
            // (the shared schema is managed out-of-band, so we stay defensive).
            $vendorPayload = collect([
                'Vendor_Code'               => $vendorCode,
                'Vendor_Name'               => $data['company_name'],
                'Email_1'                   => $data['email'],
                'Phone_No'                  => $data['phone'],
                'CR_Number'                 => $data['cr_number'] ?? null,
                'VAT_Number'                => $data['vat_number'] ?? null,
                'Business_Type'             => $data['business_type'] ?? null,
                'Contact_Person_Email'      => $data['email'],
                'Status'                    => 'pending',
                'Approval_Status'           => 'pending',
                'Onboarding_Status'         => 'incomplete',
                'Onboarding_Completeness'   => 0,
                'Is_Active'                 => 0, // gate: blocks login until an admin approves
                'Submitted_For_Approval_At' => now(),
            ])->filter(fn ($value, string $key) => Schema::hasColumn('Vendors_Master_T', $key))->all();

            $vendor = Vendor::create($vendorPayload);

            $userId = CodeGenerator::createCode('VENDUSR', 'Secx_Vendors_Users_Master_T', 'User_Id');

            // Note: login is gated on the VENDOR's Is_Active (set to 0 above), not the
            // user's. Secx_Vendors_Users_Master_T has no Is_Active column, so we don't set it.
            $user = VendorUser::create([
                'Vendor_Id' => $vendor->id,
                'User_Id'   => $userId,
                'User_Name' => $data['company_name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']), // VendorUser has no auto-hash cast
            ]);

            $this->notifyAdminsOfRegistration($vendor);

            return $vendor;
        });

        // Store uploaded documents AFTER the vendor/user are committed (so a storage
        // hiccup never rolls back a successful registration; vendor can re-upload later).
        $this->storeRegistrationDocuments($request, $vendor, $user);

        return response()->json([
            'success' => true,
            'message' => 'Your registration has been submitted and is pending admin approval. You will be able to sign in once it has been approved.',
            'vendor'  => [
                'Vendor_Code'     => $vendor->Vendor_Code,
                'Vendor_Name'     => $vendor->Vendor_Name,
                'Approval_Status' => $vendor->Approval_Status,
            ],
        ], 201);
    }

    /**
     * Store any uploaded registration documents (CR, Chamber of Commerce, Activity
     * License, Rent Contract) to R2 and record them in Vendor_Documents_T as pending.
     */
    private function storeRegistrationDocuments(Request $request, Vendor $vendor, ?VendorUser $user): void
    {
        if (! Schema::hasTable('Vendor_Documents_T')) {
            return;
        }

        foreach (self::REGISTRATION_DOCUMENTS as $field => $type) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);

            try {
                $path = Storage::disk('r2')->putFile("VendorDocuments/{$vendor->id}", $file);

                VendorDocument::create([
                    'Vendor_Id'                  => $vendor->id,
                    'Document_Type'              => $type,
                    'Document_Name'              => $file->getClientOriginalName(),
                    'File_Path'                  => $path,
                    'File_Mime'                  => $file->getClientMimeType(),
                    'File_Size'                  => $file->getSize(),
                    'Status'                     => 'pending',
                    'Uploaded_By_Vendor_User_Id' => $user?->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Insert one admin notification row per admin into the shared Conx_Notifications_T.
     * The admin notification bell (NotificationController@index) reads rows where
     * notifiable_type='App\Models\Admin' and notifiable_id = the admin's id.
     */
    private function notifyAdminsOfRegistration(Vendor $vendor): void
    {
        if (! Schema::hasTable('Conx_Notifications_T') || ! Schema::hasTable('Secx_Admin_User_Master_T')) {
            return;
        }

        $adminQuery = DB::table('Secx_Admin_User_Master_T')->select('id');
        if (Schema::hasColumn('Secx_Admin_User_Master_T', 'No_Login')) {
            $adminQuery->whereNull('No_Login'); // only admins that can log in
        }
        $adminIds = $adminQuery->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $now = now();
        $data = json_encode([
            'title'       => 'New Vendor Registration',
            'message'     => "{$vendor->Vendor_Name} has registered and is awaiting approval.",
            'url'         => '/admin/vendor/registration-requests',
            'vendor_id'   => $vendor->id,
            'vendor_code' => $vendor->Vendor_Code,
        ]);

        $rows = $adminIds->map(fn ($id) => [
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\VendorRegistration',
            'notifiable_type' => 'App\\Models\\Admin',
            'notifiable_id'   => $id,
            'data'            => $data,
            'read_at'         => null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->all();

        DB::table('Conx_Notifications_T')->insert($rows);
    }

    public function me(Request $request)
    {
        $user = Auth::guard('vendor')->user();

        if (!$user) {
            return response()->json(['user' => null], 401);
        }

        $user->load('vendor');
        $user->setAttribute('profile_complete', $this->profileComplete($user->vendor));

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Change the signed-in vendor user's password.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::guard('vendor')->user();

        if (! $user || ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($data['password']);
        if (Schema::hasColumn('Secx_Vendors_Users_Master_T', 'Password_Changed_Date')) {
            $user->Password_Changed_Date = now();
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been updated.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('vendor')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }
}
