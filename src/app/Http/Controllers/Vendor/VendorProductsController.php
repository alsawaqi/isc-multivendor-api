<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductTemporary;
use App\Models\ProductMaster;
use App\Models\ProductVendorRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorProductsController extends Controller
{
    /**
     * Get vendor_id from authenticated vendor user.
     */
    private function vendorId(): int
    {
        $user = Auth::guard('vendor')->user(); // ✅ use vendor guard

        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        if (!$vendorId) {
            abort(403, 'Vendor ID not found for this user.');
        }

        return (int) $vendorId;
    }

    /**
     * 1) Vendor pending products (TEMP table)
     * GET /vendor/api/products/pending
     */
    public function pending(Request $request)
    {
        $vendorId = $this->vendorId();

        $perPage = (int) $request->get('per_page', 20);
        $search  = trim((string) $request->get('search', ''));

        $q = ProductTemporary::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Submission_Status', 'pending')
            ->with(['defaultImage','department','subDepartment','subSubDepartment','brand','manufacture','type','specs'])
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
     * 3) Optional combined summary endpoint
     * GET /vendor/api/products/summary
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

    /**
     * Step 5A: Pending product detail
     * GET /vendor/api/products/pending/{id}
     */
    public function showPending(int $id)
    {
        $vendorId = $this->vendorId();

        $product = ProductTemporary::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId)
            ->with(['vendor', 'department', 'subDepartment', 'subSubDepartment', 'brand', 'manufacture', 'type'])
            ->firstOrFail();

        $images = DB::table('Products_Temporary_Images_T')
            ->where('Products_Temporary_Id', $product->id)
            ->whereNull('deleted_at')
            ->orderByDesc('Is_Default')
            ->orderBy('id')
            ->get();

        $specs = $this->loadSpecsFromTable(
            table: 'Product_Specification_Product_Temp_T',
            fkColumn: 'Product_Temporary_Id',
            fkValue: (int) $product->id
        );

        $history = $this->loadRequestHistory(
            vendorId: $vendorId,
            productId: null,
            tempProductId: (int) $product->id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'pending',
                'product' => $product,
                'images' => $images,
                'specs' => $specs,
                'request_history' => $history,
                'can_request_update' => false,
                'has_open_update_request' => false,
            ],
        ]);
    }

    /**
     * Step 5B: Approved product detail
     * GET /vendor/api/products/approved/{id}
     */
    public function showApproved(int $id)
    {
        $vendorId = $this->vendorId();

        $product = ProductMaster::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId)
            ->with(['vendor', 'department', 'subDepartment', 'subSubDepartment', 'brand', 'manufacture', 'type'])
            ->firstOrFail();

        $images = DB::table('Products_Images_T')
            ->where('Products_Id', $product->id)
           
            ->orderBy('id')
            ->get();

        $specs = $this->loadSpecsFromTable(
            table: 'Product_Specification_Product_T',
            fkColumn: 'Product_Id',
            fkValue: (int) $product->id
        );

        $history = $this->loadRequestHistory(
            vendorId: $vendorId,
            productId: (int) $product->id,
            tempProductId: null
        );

        $hasOpenUpdateRequest = ProductVendorRequest::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Products_Id', $product->id)
            ->whereIn('Status', ['pending', 'requested', 'under_review', 'needs_changes'])
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'approved',
                'product' => $product,
                'images' => $images,
                'specs' => $specs,
                'request_history' => $history,
                'can_request_update' => true,
                'has_open_update_request' => $hasOpenUpdateRequest,
            ],
        ]);
    }

    /**
     * Step 5C: Vendor requests an update for an approved product
     * POST /vendor/api/products/approved/{id}/request-update
     */
    public function requestUpdate(Request $request, int $id)
    {
        $vendorId = $this->vendorId();

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $user = Auth::guard('vendor')->user();

        $product = ProductMaster::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId)
            ->firstOrFail();

        $hasOpen = ProductVendorRequest::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Products_Id', $product->id)
            ->whereIn('Status', ['pending', 'requested', 'under_review', 'needs_changes'])
            ->exists();

        if ($hasOpen) {
            return response()->json([
                'success' => false,
                'message' => 'An update request for this product is already open.',
            ], 422);
        }

        $requestRow = ProductVendorRequest::query()->create([
            'Products_Temporary_Id' => null,
            'Products_Id'           => $product->id,
            'Vendor_Id'             => $vendorId,

            'Status' => 'pending',
            'Comment' => $validated['comment'],

            'Action_By_User_Id' => $user->id,
            'Action_By_Role'    => 'vendor',
            'Action_At'         => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Update request submitted successfully.',
            'data' => $requestRow,
        ], 201);
    }

    /**
     * Helper: load specs for master or temp product
     */
    private function loadSpecsFromTable(string $table, string $fkColumn, int $fkValue): array
    {
        $rows = DB::table($table)
            ->where($fkColumn, $fkValue)
            ->orderBy('id')
            ->get();

        $descIds = collect($rows)->pluck('Product_Specification_Description_Id')->filter()->unique()->values();
        $valueIds = collect($rows)->pluck('product_specification_value_id')->filter()->unique()->values();

        $descriptions = $descIds->isEmpty()
            ? collect()
            : DB::table('Product_Specification_Description_T')
                ->whereIn('id', $descIds)
                ->get()
                ->keyBy('id');

        $values = $valueIds->isEmpty()
            ? collect()
            : DB::table('Product_Specification_Value_T')
                ->whereIn('id', $valueIds)
                ->get()
                ->keyBy('id');

        return collect($rows)->map(function ($row) use ($descriptions, $values) {
            $descId = $row->Product_Specification_Description_Id ?? null;
            $valId = $row->product_specification_value_id ?? null;

            return [
                'id' => $row->id ?? null,
                'Product_Specification_Description_Id' => $descId,
                'product_specification_value_id' => $valId,
                'description' => $descId ? ($descriptions->get($descId) ?: null) : null,
                'value' => $valId ? ($values->get($valId) ?: null) : null,
            ];
        })->values()->all();
    }

    /**
     * Helper: timeline/history from Products_Vendor_Requests_T
     */
    private function loadRequestHistory(int $vendorId, ?int $productId, ?int $tempProductId): array
    {
        $q = ProductVendorRequest::query()
            ->where('Vendor_Id', $vendorId)
            ->where(function ($w) use ($productId, $tempProductId) {
                if (!is_null($productId)) {
                    $w->where('Products_Id', $productId);
                }

                if (!is_null($tempProductId)) {
                    if (!is_null($productId)) {
                        $w->orWhere('Products_Temporary_Id', $tempProductId);
                    } else {
                        $w->where('Products_Temporary_Id', $tempProductId);
                    }
                }
            })
            ->orderByDesc('Action_At')
            ->orderByDesc('id');

        return $q->get()->map(function ($row) {
            return [
                'id' => $row->id ?? null,
                'Products_Id' => $row->Products_Id ?? null,
                'Products_Temporary_Id' => $row->Products_Temporary_Id ?? null,
                'Status' => $row->Status ?? null,
                'Comment' => $row->Comment ?? null,
                'Action_By_User_Id' => $row->Action_By_User_Id ?? null,
                'Action_By_Role' => $row->Action_By_Role ?? null,
                'Action_At' => $row->Action_At ?? null,
                'created_at' => $row->created_at ?? null,
            ];
        })->values()->all();
    }
}