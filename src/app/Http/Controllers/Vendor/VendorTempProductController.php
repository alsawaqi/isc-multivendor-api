<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductTemporary;
use App\Models\ProductTemporaryImage;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Sentry\State\HubInterface;
use App\Helpers\CodeGenerator;
use App\Models\ProductVendorRequest;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationDescription;
use App\Models\ProductSpecificationValue;

class VendorTempProductController extends Controller
{
    private function vendorUser()
    {
        $user = Auth::guard('vendor')->user();

        if (!$user) {
            abort(401, 'Vendor user is not authenticated.');
        }

        return $user;
    }

    private function normalizeDimensions(Request $request, ?ProductTemporary $product = null): array
    {
        $rawL = (float) ($request->input('Length_Cm', $product?->Length_Cm ?? 0));
        $rawW = (float) ($request->input('Width_Cm', $product?->Width_Cm ?? 0));
        $rawH = (float) ($request->input('Height_Cm', $product?->Height_Cm ?? 0));

        $unit = strtolower($request->input('volume_type', $product ? 'm' : 'cm'));
        $toMeters = [
            'mm' => 0.001,
            'cm' => 0.01,
            'm'  => 1.0,
            'in' => 0.0254,
            'ft' => 0.3048,
        ];
        $k = $toMeters[$unit] ?? 1.0;

        $L_m = round($rawL * $k, 4);
        $W_m = round($rawW * $k, 4);
        $H_m = round($rawH * $k, 4);

        return [
            'Length_Cm' => $L_m,
            'Width_Cm' => $W_m,
            'Height_Cm' => $H_m,
            'Volume_Cbm' => round($L_m * $W_m * $H_m, 6),
        ];
    }

    private function validateSpecificationPayload(array $specs, int $subSubDeptId): array
    {
        if (empty($specs)) {
            return [];
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
                abort(422, "Specification description {$descId} does not belong to this category.");
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

    private function syncTemporarySpecifications(ProductTemporary $product, array $specs, int $userId): void
    {
        ProductSpecification::where('Product_Temporary_Id', $product->id)->delete();

        foreach ($specs as $spec) {
            ProductSpecification::create([
                'Product_Temporary_Id' => $product->id,
                'Product_Specification_Description_Id' => $spec['description_id'],
                'product_specification_value_id' => $spec['value_id'],
                'Created_By' => $userId,
            ]);
        }
    }

    public function nextId()
    {
        $max = (int) ProductTemporary::max('id');
        return response()->json($max + 1);
    }

    public function store(Request $request)
    {
        // 1) Validate (keep it strict because Submission_Status, Submitted_By, etc. are NOT NULL in SQL)
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
            'cost'  => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
    
            'Weight_Kg' => ['nullable', 'numeric', 'min:0'],
            'Length_Cm' => ['nullable', 'numeric', 'min:0'],
            'Width_Cm'  => ['nullable', 'numeric', 'min:0'],
            'Height_Cm' => ['nullable', 'numeric', 'min:0'],
            'volume_type' => ['nullable', 'in:mm,cm,m,in,ft'],
    
            // images
            'file' => ['nullable'],
            'file.*' => ['image', 'max:10240'], // 10MB each (edit as you want)

            'specs' => ['nullable', 'array'],
            'specs.*.description_id' => ['nullable', 'integer'],
            'specs.*.value_id' => ['nullable', 'integer'],
        ]);
    
        try {
            $tempProduct = null;
    
            DB::transaction(function () use ($request, &$tempProduct) {
                $user = $this->vendorUser();
    
                // IMPORTANT: adjust these to your vendor user table structure
                // Example: if your vendor user row has Vendor_Id column:
                $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;
                if (!$vendorId) {
                    abort(403, 'Vendor ID not found for this user.');
                }
    
                $tempCode = CodeGenerator::createCode('TPROD', 'Products_Temporary_T', 'Temp_Product_Code');
    
                $dimensions = $this->normalizeDimensions($request);
    
                $tempProduct = ProductTemporary::create([
                    'Vendor_Id' => $vendorId,
                    'Temp_Product_Code' => $tempCode,
    
                    'Product_Name' => $request->input('name'),
                    'Product_Name_Ar' => $request->input('name_ar'),
                    'Description' => $request->input('description'),
                    'Description_Ar' => $request->input('description_ar'),
    
                    'Product_Department_Id' => (int) $request->input('product_department_id'),
                    'Product_Sub_Department_Id' => (int) $request->input('product_sub_department_id'),
                    'Product_Sub_Sub_Department_Id' => (int) $request->input('product_sub_sub_department_id'),
    
                    'Product_Type_Id' => (int) $request->input('product_type_id'),
                    'Product_Brand_Id' => $request->filled('product_brand_id') ? (int) $request->input('product_brand_id') : null,
                    'Product_Manufacture_Id' => $request->filled('product_manufacture_id') ? (int) $request->input('product_manufacture_id') : null,
    
                    'Product_Price' => $request->input('price'),
                    'Product_Cost'  => $request->input('cost'),
                    'Product_Stock' => (int) $request->input('stock'),
    
                    'Weight_Kg' => $request->input('Weight_Kg', 0),
    
                    // You are storing meters into columns named _Cm in admin code too.
                    // Keep same approach for consistency across systems.
                    'Length_Cm' => $dimensions['Length_Cm'],
                    'Width_Cm'  => $dimensions['Width_Cm'],
                    'Height_Cm' => $dimensions['Height_Cm'],
                    'Volume_Cbm' => $dimensions['Volume_Cbm'],
    
                    // REQUIRED (NOT NULL)
                    'Submission_Status' => 'pending',
                    'Submitted_By' => $user->id,
                    'Submitted_At' => now(),
    
                    'Created_By' => $user->id,
                    'Updated_By' => null,
                ]);
    
                // Images
                if ($request->hasFile('file')) {
                    $isFirst = true;
    
                    foreach ($request->file('file') as $file) {
                        // keep same disk logic you use in admin (r2)
                        $path = Storage::disk('r2')->putFile('ProductsTemporary', $file); // no 'public'
    
                        if (!$path) {
                            throw new \RuntimeException('R2 upload failed: putFile returned false');
                        }
    
                        ProductTemporaryImage::create([
                            'Products_Temporary_Id' => $tempProduct->id,
                            'Image_Path' => $path,
                            'Image_Size' => $file->getSize(),
                            'Image_Extension' => $file->getClientOriginalExtension(),
                            'Image_Type' => $file->getMimeType(),
                            'Is_Default' => $isFirst ? 1 : 0,
                            'Created_By' => $user->id,
                        ]);
    
                        $isFirst = false;
                    }
                }



                $specs = $this->validateSpecificationPayload(
                    (array) $request->input('specs', []),
                    (int) $request->input('product_sub_sub_department_id')
                );
                $this->syncTemporarySpecifications($tempProduct, $specs, $user->id);

    
                /*
                 * 🔹 NEW: create first timeline entry in Products_Vendor_Requests_T
                 * This records that the vendor submitted this product and it is now "pending".
                 */
                ProductVendorRequest::create([
                    'Products_Temporary_Id' => $tempProduct->id, // PK of Products_Temporary_T
                    'Products_Id'           => null,             // not in master table yet
                    'Vendor_Id'             => $vendorId,
    
                    'Request_Type' => 'new_product',
                    'Status'    => 'pending',
                    'Comment'   => null,
    
                    'Action_By_User_Id' => $user->id,  // the vendor user
                    'Action_By_Role'    => 'vendor',   // or 'Vendor' if you prefer
                    'Action_At'         => now(),
                ]);
            });
    
            return response()->json([
                'data' => $tempProduct->load('images', 'defaultImage'),
            ], 201);
        } catch (\Throwable $e) {
            // app(HubInterface::class)->captureException($e);
            return response()->json([
                'message' => 'Error creating temporary product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'product_department_id' => ['nullable', 'integer'],
            'product_sub_department_id' => ['nullable', 'integer'],
            'product_sub_sub_department_id' => ['nullable', 'integer'],
            'product_type_id' => ['nullable', 'integer'],
            'product_brand_id' => ['nullable', 'integer'],
            'product_manufacture_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'description_ar' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost'  => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'Weight_Kg' => ['nullable', 'numeric', 'min:0'],
            'Length_Cm' => ['nullable', 'numeric', 'min:0'],
            'Width_Cm'  => ['nullable', 'numeric', 'min:0'],
            'Height_Cm' => ['nullable', 'numeric', 'min:0'],
            'volume_type' => ['nullable', 'in:mm,cm,m,in,ft'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'specs' => ['nullable', 'array'],
            'specs.*.description_id' => ['nullable', 'integer'],
            'specs.*.value_id' => ['nullable', 'integer'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
            'default_image_id' => ['nullable', 'integer'],
            'file' => ['nullable'],
            'file.*' => ['image', 'max:10240'],
        ]);

        $user = $this->vendorUser();
        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        if (!$vendorId) {
            abort(403, 'Vendor ID not found for this user.');
        }

        $product = ProductTemporary::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId)
            ->whereIn('Submission_Status', ['pending', 'needs_changes', 'rejected'])
            ->firstOrFail();

        $dimensions = $this->normalizeDimensions($request, $product);
        $departmentId = (int) ($validated['product_department_id'] ?? $product->Product_Department_Id);
        $subDepartmentId = (int) ($validated['product_sub_department_id'] ?? $product->Product_Sub_Department_Id);
        $subSubDepartmentId = (int) ($validated['product_sub_sub_department_id'] ?? $product->Product_Sub_Sub_Department_Id);
        $typeId = (int) ($validated['product_type_id'] ?? $product->Product_Type_Id);
        $brandId = $request->has('product_brand_id')
            ? ($request->filled('product_brand_id') ? (int) $validated['product_brand_id'] : null)
            : $product->Product_Brand_Id;
        $manufactureId = $request->has('product_manufacture_id')
            ? ($request->filled('product_manufacture_id') ? (int) $validated['product_manufacture_id'] : null)
            : $product->Product_Manufacture_Id;

        $specs = $request->has('specs')
            ? $this->validateSpecificationPayload(
                (array) $request->input('specs', []),
                $subSubDepartmentId
            )
            : null;

        DB::transaction(function () use ($request, $product, $validated, $dimensions, $departmentId, $subDepartmentId, $subSubDepartmentId, $typeId, $brandId, $manufactureId, $specs, $user, $vendorId) {
            $productData = [
                'Product_Department_Id' => $departmentId,
                'Product_Sub_Department_Id' => $subDepartmentId,
                'Product_Sub_Sub_Department_Id' => $subSubDepartmentId,
                'Product_Type_Id' => $typeId,
                'Product_Brand_Id' => $brandId,
                'Product_Manufacture_Id' => $manufactureId,
                'Product_Name' => $validated['name'],
                'Product_Name_Ar' => $validated['name_ar'],
                'Description' => $validated['description'],
                'Description_Ar' => $validated['description_ar'] ?? null,
                'Product_Price' => $validated['price'],
                'Product_Stock' => (int) $validated['stock'],
                'Weight_Kg' => $validated['Weight_Kg'] ?? 0,
                'Length_Cm' => $dimensions['Length_Cm'],
                'Width_Cm' => $dimensions['Width_Cm'],
                'Height_Cm' => $dimensions['Height_Cm'],
                'Volume_Cbm' => $dimensions['Volume_Cbm'],
                'Submission_Status' => 'pending',
                'Submitted_At' => now(),
                'Reviewed_By' => null,
                'Reviewed_At' => null,
                'Rejection_Reason' => null,
                'Updated_By' => $user->id,
            ];

            if ($request->has('cost')) {
                $productData['Product_Cost'] = $validated['cost'] ?? null;
            }

            $product->update($productData);

            if ($specs !== null) {
                $this->syncTemporarySpecifications($product, $specs, $user->id);
            }

            $removeImageIds = collect($request->input('remove_image_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($removeImageIds->isNotEmpty()) {
                $imagesToRemove = ProductTemporaryImage::query()
                    ->where('Products_Temporary_Id', $product->id)
                    ->whereIn('id', $removeImageIds)
                    ->get();

                foreach ($imagesToRemove as $image) {
                    if ($image->Image_Path) {
                        Storage::disk('r2')->delete($image->Image_Path);
                    }
                    $image->delete();
                }
            }

            if ($request->filled('default_image_id')) {
                $defaultImageId = (int) $request->input('default_image_id');
                $belongsToProduct = ProductTemporaryImage::query()
                    ->where('Products_Temporary_Id', $product->id)
                    ->where('id', $defaultImageId)
                    ->exists();

                if ($belongsToProduct) {
                    ProductTemporaryImage::query()
                        ->where('Products_Temporary_Id', $product->id)
                        ->update(['Is_Default' => 0]);

                    ProductTemporaryImage::query()
                        ->where('Products_Temporary_Id', $product->id)
                        ->where('id', $defaultImageId)
                        ->update(['Is_Default' => 1]);
                }
            }

            $hasDefaultImage = ProductTemporaryImage::query()
                ->where('Products_Temporary_Id', $product->id)
                ->where('Is_Default', 1)
                ->exists();

            if ($request->hasFile('file')) {
                foreach ($request->file('file') as $file) {
                    $path = Storage::disk('r2')->putFile('ProductsTemporary', $file);

                    if (!$path) {
                        throw new \RuntimeException('R2 upload failed: putFile returned false');
                    }

                    ProductTemporaryImage::create([
                        'Products_Temporary_Id' => $product->id,
                        'Image_Path' => $path,
                        'Image_Size' => $file->getSize(),
                        'Image_Extension' => $file->getClientOriginalExtension(),
                        'Image_Type' => $file->getMimeType(),
                        'Is_Default' => $hasDefaultImage ? 0 : 1,
                        'Created_By' => $user->id,
                    ]);

                    $hasDefaultImage = true;
                }
            }

            $activeImages = ProductTemporaryImage::query()
                ->where('Products_Temporary_Id', $product->id)
                ->orderByDesc('Is_Default')
                ->orderBy('id')
                ->get();

            if ($activeImages->isNotEmpty() && !$activeImages->contains(fn ($img) => (bool) $img->Is_Default)) {
                ProductTemporaryImage::query()
                    ->where('id', $activeImages->first()->id)
                    ->update(['Is_Default' => 1]);
            }

            ProductVendorRequest::create([
                'Products_Temporary_Id' => $product->id,
                'Products_Id' => $product->Approved_Product_Id,
                'Vendor_Id' => $vendorId,
                'Request_Type' => 'new_product',
                'Status' => 'resubmitted',
                'Comment' => $validated['comment'] ?? null,
                'Action_By_User_Id' => $user->id,
                'Action_By_Role' => 'vendor',
                'Action_At' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Product changes submitted for admin review.',
            'data' => $product->fresh(['images', 'defaultImage']),
        ]);
    }
    
}
