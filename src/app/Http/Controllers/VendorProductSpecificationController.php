<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductSpecificationDescription;

class VendorProductSpecificationController extends Controller
{
    public function bySubSubDepartment(int $id)
    {

        $subSubDeptId = $id;
        $descriptions = ProductSpecificationDescription::with(['values' => function ($q) {
                $q->orderBy('value');
            }])
            ->where('product_sub_sub_department_id', $subSubDeptId) // ✅ correct column
            ->where('is_active', 1) // optional
            ->orderBy('sort_order') // optional
            ->get();

        $data = $descriptions->map(function ($desc) {
            return [
                'id' => $desc->id,
                'Product_Specification_Description_Name' => $desc->Product_Specification_Description_Name,
                'input_type' => $desc->input_type ?? 'select',
                'is_required' => (bool) $desc->is_required,
                'values' => $desc->values->map(function ($val) {
                    return [
                        'id' => $val->id,
                        'value' => $val->value,
                    ];
                })->values(),
            ];
        });

        return response()->json($data);
    }

}
