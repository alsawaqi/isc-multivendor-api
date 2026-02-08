<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductTemporary;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorProductsController extends Controller
{
        /**
     * Get vendor_id from the authenticated vendor user.
     * Adjust this if your user table uses different column naming.
     */
    private function vendorId(): int
    {
        $user = Auth::user();

        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        if (!$vendorId) {
            abort(403, 'Vendor ID not found for this user.');
        }

        return (int) $vendorId;
    }

    /**
     * 1) Vendor pending products (TEMP table)
     * GET /vendor/api/products/pending
     * Query: ?search=&per_page=20
     */
    public function pending(Request $request)
    {
        $vendorId = $this->vendorId();

        $perPage = (int) $request->get('per_page', 20);
        $search  = trim((string) $request->get('search', ''));

        $q = ProductTemporary::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Submission_Status', 'pending')
            ->with(['defaultImage','department','subDepartment','subSubDepartment','brand','manufacture','type','specs']) // optional
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('Product_Name', 'like', "%{$search}%")
                      ->orWhere('Temp_Product_Code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('Submitted_At');

        return response()->json($q->paginate($perPage));
    }

    /**
     * 2) Vendor approved products (MASTER table)
     * GET /vendor/api/products/approved
     * Query: ?search=&per_page=20
     */
    public function approved(Request $request)
    {
        $vendorId = $this->vendorId();

        $perPage = (int) $request->get('per_page', 20);
        $search  = trim((string) $request->get('search', ''));

        $q = ProductMaster::query()
            ->where('Vendor_Id', $vendorId)
            ->with(['defaultImage','department','subDepartment','subSubDepartment','brand','manufacture','type','specs'])
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('Product_Name', 'like', "%{$search}%")
                      ->orWhere('Product_Code', 'like', "%{$search}%")
                      ->orWhere('Inhouse_Barcode_Source', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        return response()->json($q->paginate($perPage));
    }

    /**
     * 3) (Optional) One endpoint returns both lists together
     * GET /vendor/api/products/summary?pending_per_page=10&approved_per_page=10&search=
     */
    public function summary(Request $request)
    {
        $vendorId = $this->vendorId();
        $search   = trim((string) $request->get('search', ''));

        $pendingPerPage  = (int) $request->get('pending_per_page', 10);
        $approvedPerPage = (int) $request->get('approved_per_page', 10);

        $pending = ProductTemporary::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Submission_Status', 'pending')
            ->with(['defaultImage'])
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('Product_Name', 'like', "%{$search}%")
                      ->orWhere('Temp_Product_Code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('Submitted_At')
            ->paginate($pendingPerPage, ['*'], 'pending_page');

        $approved = ProductMaster::query()
            ->where('Vendor_Id', $vendorId)
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('Product_Name', 'like', "%{$search}%")
                      ->orWhere('Product_Code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($approvedPerPage, ['*'], 'approved_page');

        return response()->json([
            'pending'  => $pending,
            'approved' => $approved,
        ]);
    }
}
