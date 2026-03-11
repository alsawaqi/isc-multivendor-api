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
        ]);
    
        try {
            $tempProduct = null;
    
            DB::transaction(function () use ($request, &$tempProduct) {
                $user = Auth::user();
    
                // IMPORTANT: adjust these to your vendor user table structure
                // Example: if your vendor user row has Vendor_Id column:
                $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;
                if (!$vendorId) {
                    abort(403, 'Vendor ID not found for this user.');
                }
    
                $tempCode = CodeGenerator::createCode('TPROD', 'Products_Temporary_T', 'Temp_Product_Code');
    
                // Dimensions + CBM (same idea as your admin)
                $rawL = (float) ($request->input('Length_Cm') ?? 0);
                $rawW = (float) ($request->input('Width_Cm') ?? 0);
                $rawH = (float) ($request->input('Height_Cm') ?? 0);
    
                $unit = strtolower($request->input('volume_type', 'cm'));
                $toMeters = [
                    'mm' => 0.001,
                    'cm' => 0.01,
                    'm'  => 1.0,
                    'in' => 0.0254,
                    'ft' => 0.3048,
                ];
                $k = $toMeters[$unit] ?? 0.01;
    
                $L_m = round($rawL * $k, 4);
                $W_m = round($rawW * $k, 4);
                $H_m = round($rawH * $k, 4);
                $cbm = round($L_m * $W_m * $H_m, 3);
    
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
                    'Length_Cm' => $L_m,
                    'Width_Cm'  => $W_m,
                    'Height_Cm' => $H_m,
                    'Volume_Cbm' => $cbm,
    
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



                // ================================
                // ✅ Save Specifications (TEMP)
                // ================================
                $specs = $request->input('specs', []);

                if (!empty($specs)) {
                    $subSubDeptId = (int) $request->input('product_sub_sub_department_id');

                    // 1) Pull allowed descriptions for this Sub-Sub Dept
                    $allowedDescIds = ProductSpecificationDescription::query()
                        ->where('product_sub_sub_department_id', $subSubDeptId)
                        ->where('is_active', 1)
                        ->pluck('id')
                        ->all();

                    $allowedDescSet = array_flip($allowedDescIds);

                    // 2) Validate each spec row strictly
                    foreach ($specs as $i => $spec) {
                        $descId  = (int) ($spec['description_id'] ?? 0);
                        $valueId = (int) ($spec['value_id'] ?? 0);

                        if (!$descId || !$valueId) {
                            abort(422, "Invalid spec payload at index {$i}.");
                        }

                        // Description must belong to selected Sub-Sub Dept
                        if (!isset($allowedDescSet[$descId])) {
                            abort(422, "Specification description {$descId} does not belong to this category.");
                        }

                        // Value must belong to that description
                        $valueOk = ProductSpecificationValue::query()
                            ->where('id', $valueId)
                            ->where('product_specification_description_id', $descId)
                            ->exists();

                        if (!$valueOk) {
                            abort(422, "Specification value {$valueId} is not valid for description {$descId}.");
                        }

                        ProductSpecification::create([
                            'Product_Temporary_Id' => $tempProduct->id,
                            'Product_Specification_Description_Id' => $descId,
                            'product_specification_value_id' => $valueId,
                            'Created_By' => $user->id,
                        ]);
                    }
                }

    
                /*
                 * 🔹 NEW: create first timeline entry in Products_Vendor_Requests_T
                 * This records that the vendor submitted this product and it is now "pending".
                 */
                ProductVendorRequest::create([
                    'Products_Temporary_Id' => $tempProduct->id, // PK of Products_Temporary_T
                    'Products_Id'           => null,             // not in master table yet
                    'Vendor_Id'             => $vendorId,
    
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
    
}
