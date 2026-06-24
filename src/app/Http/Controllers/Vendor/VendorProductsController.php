<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductTemporary;
use App\Models\ProductMaster;
use App\Models\ProductVendorRequest;
use App\Models\ProductSpecificationDescription;
use App\Models\ProductSpecificationValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\Vendors\VendorApprovalSla;

class VendorProductsController extends Controller
{
    private const OPEN_APPROVED_UPDATE_STATUSES = ['pending', 'requested', 'under_review', 'needs_changes'];

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

        $status = (string) $request->get('status', 'active');

        $q = ProductTemporary::query()
            ->where('Vendor_Id', $vendorId)
            ->when($status === 'active', function ($qq) {
                $qq->whereIn('Submission_Status', ['pending', 'needs_changes', 'rejected']);
            })
            ->when($status !== 'active' && $status !== 'all', function ($qq) use ($status) {
                $qq->where('Submission_Status', $status);
            })
            ->with(['defaultImage','department','subDepartment','subSubDepartment','brand','manufacture','type','specs'])
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('Product_Name', 'like', "%{$search}%")
                      ->orWhere('Temp_Product_Code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('Submitted_At');

        $page = $q->paginate($perPage);
        $page->getCollection()->transform(function (ProductTemporary $product) {
            $product->setAttribute('approval_sla', VendorApprovalSla::forProduct($product));

            return $product;
        });

        return response()->json($page);
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
            ->whereIn('Submission_Status', ['pending', 'needs_changes', 'rejected'])
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

        $product->setAttribute('approval_sla', VendorApprovalSla::forProduct($product));

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
            ->where('Request_Type', 'approved_update')
            ->whereIn('Status', self::OPEN_APPROVED_UPDATE_STATUSES)
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
        $user = Auth::guard('vendor')->user();

        $product = ProductMaster::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId)
            ->firstOrFail();

        $usesStructuredChanges = $request->has('changes');

        if ($usesStructuredChanges) {
            $validated = $request->validate([
                'comment' => ['nullable', 'string', 'max:2000', 'required_without:changes'],
                'changes' => ['nullable', 'array', 'min:1'],
                'changes.Product_Department_Id' => ['sometimes', 'integer'],
                'changes.Product_Sub_Department_Id' => ['sometimes', 'integer'],
                'changes.Product_Sub_Sub_Department_Id' => ['sometimes', 'integer'],
                'changes.Product_Type_Id' => ['sometimes', 'integer'],
                'changes.Product_Brand_Id' => ['sometimes', 'nullable', 'integer'],
                'changes.Product_Manufacture_Id' => ['sometimes', 'nullable', 'integer'],
                'changes.Product_Name' => ['sometimes', 'string', 'max:255'],
                'changes.Product_Name_Ar' => ['sometimes', 'string', 'max:255'],
                'changes.Product_Description' => ['sometimes', 'string'],
                'changes.Product_Price' => ['sometimes', 'numeric', 'min:0'],
                'changes.Product_Cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'changes.Product_Stock' => ['sometimes', 'integer', 'min:0'],
                'changes.Weight_Kg' => ['sometimes', 'numeric', 'min:0'],
                'changes.Length_Cm' => ['sometimes', 'numeric', 'min:0'],
                'changes.Width_Cm' => ['sometimes', 'numeric', 'min:0'],
                'changes.Height_Cm' => ['sometimes', 'numeric', 'min:0'],
                'changes.Volume_Cbm' => ['sometimes', 'numeric', 'min:0'],
                'changes.volume_type' => ['sometimes', 'nullable', 'in:mm,cm,m,in,ft'],
                'changes.specifications' => ['sometimes', 'array', 'min:1'],
                'changes.specifications.*.description_id' => ['nullable', 'integer'],
                'changes.specifications.*.value_id' => ['nullable', 'integer'],
                'changes.specifications.*.product_specification_description_id' => ['nullable', 'integer'],
                'changes.specifications.*.product_specification_value_id' => ['nullable', 'integer'],
            ]);
        } else {
            $validated = $request->validate([
                'product_department_id' => ['required', 'integer'],
                'product_sub_department_id' => ['required', 'integer'],
                'product_sub_sub_department_id' => ['required', 'integer'],
                'product_type_id' => ['required', 'integer'],
                'product_brand_id' => ['nullable', 'integer'],
                'product_manufacture_id' => ['nullable', 'integer'],
                'name' => ['required', 'string', 'max:255'],
                'name_ar' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'description_ar' => ['nullable', 'string'],
                'price' => ['required', 'numeric', 'min:0'],
                'cost' => ['nullable', 'numeric', 'min:0'],
                'stock' => ['required', 'integer', 'min:0'],
                'Weight_Kg' => ['nullable', 'numeric', 'min:0'],
                'Length_Cm' => ['nullable', 'numeric', 'min:0'],
                'Width_Cm' => ['nullable', 'numeric', 'min:0'],
                'Height_Cm' => ['nullable', 'numeric', 'min:0'],
                'volume_type' => ['nullable', 'in:mm,cm,m,in,ft'],
                'comment' => ['nullable', 'string', 'max:2000'],
                'specs' => ['nullable', 'array'],
                'specs.*.description_id' => ['nullable', 'integer'],
                'specs.*.value_id' => ['nullable', 'integer'],
                'remove_image_ids' => ['nullable', 'array'],
                'remove_image_ids.*' => ['integer'],
                'file' => ['nullable'],
                'file.*' => ['image', 'max:10240'],
            ]);
        }

        $hasOpen = ProductVendorRequest::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Products_Id', $product->id)
            ->where('Request_Type', 'approved_update')
            ->whereIn('Status', self::OPEN_APPROVED_UPDATE_STATUSES)
            ->exists();

        if ($hasOpen) {
            return response()->json([
                'success' => false,
                'message' => 'An update request for this product is already open.',
            ], 422);
        }

        $allowed = [
            'Product_Department_Id',
            'Product_Sub_Department_Id',
            'Product_Sub_Sub_Department_Id',
            'Product_Type_Id',
            'Product_Brand_Id',
            'Product_Manufacture_Id',
            'Product_Name',
            'Product_Name_Ar',
            'Product_Description',
            'Product_Price',
            'Product_Cost',
            'Product_Stock',
            'Weight_Kg',
            'Length_Cm',
            'Width_Cm',
            'Height_Cm',
            'Volume_Cbm',
            'volume_type',
        ];

        if ($usesStructuredChanges) {
            $rawChanges = $validated['changes'] ?? [];
        } else {
            $dimensions = $this->normalizeApprovedUpdateDimensions($request, $product);
            $rawChanges = [
                'Product_Department_Id' => (int) $validated['product_department_id'],
                'Product_Sub_Department_Id' => (int) $validated['product_sub_department_id'],
                'Product_Sub_Sub_Department_Id' => (int) $validated['product_sub_sub_department_id'],
                'Product_Type_Id' => (int) $validated['product_type_id'],
                'Product_Brand_Id' => $request->filled('product_brand_id') ? (int) $validated['product_brand_id'] : null,
                'Product_Manufacture_Id' => $request->filled('product_manufacture_id') ? (int) $validated['product_manufacture_id'] : null,
                'Product_Name' => $validated['name'],
                'Product_Name_Ar' => $validated['name_ar'],
                'Product_Description' => $validated['description'],
                'Product_Price' => $validated['price'],
                'Product_Stock' => (int) $validated['stock'],
                'Weight_Kg' => $validated['Weight_Kg'] ?? 0,
                'Length_Cm' => $dimensions['Length_Cm'],
                'Width_Cm' => $dimensions['Width_Cm'],
                'Height_Cm' => $dimensions['Height_Cm'],
                'Volume_Cbm' => $dimensions['Volume_Cbm'],
                'volume_type' => strtolower($request->input('volume_type', 'm')),
            ];

            if ($request->has('cost')) {
                $rawChanges['Product_Cost'] = $validated['cost'] ?? null;
            }
        }

        $changes = collect($rawChanges)
            ->only($allowed)
            ->all();

        if (array_key_exists('specifications', $rawChanges)) {
            $changes['specifications'] = $this->validateSpecificationPayload(
                (array) $rawChanges['specifications'],
                (int) ($changes['Product_Sub_Sub_Department_Id'] ?? $product->Product_Sub_Sub_Department_Id)
            );
        }

        if (!$usesStructuredChanges && $request->has('specs')) {
            $changes['specifications'] = $this->validateSpecificationPayload(
                (array) $request->input('specs', []),
                (int) ($changes['Product_Sub_Sub_Department_Id'] ?? $product->Product_Sub_Sub_Department_Id)
            );
        }

        if (!$usesStructuredChanges) {
            $imageUpdates = $this->captureApprovedProductImageUpdates($request, $product);
            if (!empty($imageUpdates['remove_image_ids']) || !empty($imageUpdates['new_images'])) {
                $changes['image_updates'] = $imageUpdates;
            }
        }

        if (empty($changes)) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose at least one product field or specification to update.',
            ], 422);
        }

        $requestRow = ProductVendorRequest::query()->create([
            'Products_Temporary_Id' => null,
            'Products_Id'           => $product->id,
            'Vendor_Id'             => $vendorId,

            'Request_Type' => 'approved_update',
            'Status' => 'requested',
            'Comment' => $validated['comment'] ?? null,
            'Requested_Changes_Json' => $changes,

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

    private function normalizeApprovedUpdateDimensions(Request $request, ProductMaster $product): array
    {
        $rawL = (float) ($request->input('Length_Cm', $product->Length_Cm ?? 0));
        $rawW = (float) ($request->input('Width_Cm', $product->Width_Cm ?? 0));
        $rawH = (float) ($request->input('Height_Cm', $product->Height_Cm ?? 0));

        $unit = strtolower($request->input('volume_type', 'm'));
        $toMeters = [
            'mm' => 0.001,
            'cm' => 0.01,
            'm' => 1.0,
            'in' => 0.0254,
            'ft' => 0.3048,
        ];

        $factor = $toMeters[$unit] ?? 1.0;
        $length = round($rawL * $factor, 4);
        $width = round($rawW * $factor, 4);
        $height = round($rawH * $factor, 4);

        return [
            'Length_Cm' => $length,
            'Width_Cm' => $width,
            'Height_Cm' => $height,
            'Volume_Cbm' => round($length * $width * $height, 6),
        ];
    }

    private function captureApprovedProductImageUpdates(Request $request, ProductMaster $product): array
    {
        $removeImageIds = collect($request->input('remove_image_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($removeImageIds->isNotEmpty()) {
            $validCount = DB::table('Products_Images_T')
                ->where('Products_Id', $product->id)
                ->whereIn('id', $removeImageIds)
                ->count();

            if ($validCount !== $removeImageIds->count()) {
                abort(422, 'One or more selected images do not belong to this product.');
            }
        }

        $newImages = [];

        if ($request->hasFile('file')) {
            foreach ($request->file('file') as $file) {
                $path = Storage::disk('r2')->putFile('ProductsUpdateRequests', $file);

                if (!$path) {
                    throw new \RuntimeException('R2 upload failed: putFile returned false');
                }

                $newImages[] = [
                    'Image_Path' => $path,
                    'Image_Size' => $file->getSize(),
                    'Image_Extension' => $file->getClientOriginalExtension(),
                    'Image_Type' => $file->getMimeType(),
                ];
            }
        }

        return [
            'remove_image_ids' => $removeImageIds->all(),
            'new_images' => $newImages,
        ];
    }

    /**
     * Helper: load specs for master or temp product
     */
    private function validateSpecificationPayload(array $specs, int $subSubDeptId): array
    {
        if (empty($specs)) {
            abort(422, 'Please select at least one specification value.');
        }

        $allowedDescIds = ProductSpecificationDescription::query()
            ->where('product_sub_sub_department_id', $subSubDeptId)
            ->pluck('id')
            ->all();

        $allowedDescSet = array_flip($allowedDescIds);
        $validated = [];

        foreach ($specs as $i => $spec) {
            $descId = (int) ($spec['description_id'] ?? $spec['product_specification_description_id'] ?? 0);
            $valueId = (int) ($spec['value_id'] ?? $spec['product_specification_value_id'] ?? 0);

            if (!$descId || !$valueId) {
                abort(422, "Invalid spec payload at index {$i}.");
            }

            if (!isset($allowedDescSet[$descId])) {
                abort(422, "Specification description {$descId} does not belong to this product category.");
            }

            $valueOk = ProductSpecificationValue::query()
                ->where('id', $valueId)
                ->where('product_specification_description_id', $descId)
                ->exists();

            if (!$valueOk) {
                abort(422, "Specification value {$valueId} is not valid for description {$descId}.");
            }

            $validated[$descId] = [
                'description_id' => $descId,
                'value_id' => $valueId,
            ];
        }

        return array_values($validated);
    }

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
            $changes = $row->Requested_Changes_Json ?? null;

            return [
                'id' => $row->id ?? null,
                'Products_Id' => $row->Products_Id ?? null,
                'Products_Temporary_Id' => $row->Products_Temporary_Id ?? null,
                'Status' => $row->Status ?? null,
                'Comment' => $row->Comment ?? null,
                'Request_Type' => $row->Request_Type ?? null,
                'Requested_Changes_Json' => $changes,
                'Requested_Specifications_Display' => is_array($changes) && !empty($changes['specifications'])
                    ? $this->describeSpecificationChanges((array) $changes['specifications'])
                    : [],
                'Action_By_User_Id' => $row->Action_By_User_Id ?? null,
                'Action_By_Role' => $row->Action_By_Role ?? null,
                'Action_At' => $row->Action_At ?? null,
                'created_at' => $row->created_at ?? null,
            ];
        })->values()->all();
    }

    private function describeSpecificationChanges(array $specs): array
    {
        $descIds = collect($specs)->map(fn ($s) => (int) ($s['description_id'] ?? $s['product_specification_description_id'] ?? 0))->filter()->unique()->values();
        $valueIds = collect($specs)->map(fn ($s) => (int) ($s['value_id'] ?? $s['product_specification_value_id'] ?? 0))->filter()->unique()->values();

        $descriptions = $descIds->isEmpty()
            ? collect()
            : ProductSpecificationDescription::query()
                ->whereIn('id', $descIds)
                ->pluck('Product_Specification_Description_Name', 'id');

        $values = $valueIds->isEmpty()
            ? collect()
            : ProductSpecificationValue::query()
                ->whereIn('id', $valueIds)
                ->pluck('value', 'id');

        return collect($specs)->map(function ($spec) use ($descriptions, $values) {
            $descId = (int) ($spec['description_id'] ?? $spec['product_specification_description_id'] ?? 0);
            $valueId = (int) ($spec['value_id'] ?? $spec['product_specification_value_id'] ?? 0);

            return [
                'description_id' => $descId,
                'value_id' => $valueId,
                'description' => $descriptions[$descId] ?? "Spec #{$descId}",
                'value' => $values[$valueId] ?? "Value #{$valueId}",
            ];
        })->values()->all();
    }
}
