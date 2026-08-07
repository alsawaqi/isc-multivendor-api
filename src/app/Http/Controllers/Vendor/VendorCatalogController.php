<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductBrand;
use App\Models\Catalog\ProductManufacture;
use App\Models\Catalog\ProductsDepartment;
use App\Models\Catalog\ProductsSubDepartment;
use App\Models\Catalog\ProductsSubSubDepartment;
use App\Models\Catalog\ProductType;
use Illuminate\Support\Facades\Schema;

class VendorCatalogController extends Controller
{
    public function departments()
    {
        return $this->applyHierarchyOrder(
            ProductsDepartment::query(),
            'Products_Departments_T',
            'Source_Main_Sequence',
            'Product_Department_Name',
        )
            ->get(['id', 'Product_Department_Name', 'Product_Department_Name_Ar']);
    }

    public function subDepartments($departmentId)
    {
        return $this->applyHierarchyOrder(
            ProductsSubDepartment::query()
                ->where('Products_Departments_Id', $departmentId),
            'Products_Sub_Department_T',
            'Source_Sub_Sequence',
            'Sub_Department_Name',
        )
            ->get(['id', 'Products_Departments_Id', 'Sub_Department_Name', 'Sub_Department_Name_Ar']);
    }

    public function subSubDepartments($subDepartmentId)
    {
        return $this->applyHierarchyOrder(
            ProductsSubSubDepartment::query()
                ->where('Product_Sub_Department_Id', $subDepartmentId),
            'Products_Sub_Sub_Department_T',
            'Source_Sub_Sub_Sequence',
            'Product_Sub_Sub_Department_Name',
        )
            ->get(['id', 'Product_Sub_Department_Id', 'Product_Sub_Sub_Department_Name', 'Product_Sub_Sub_Department_Name_Ar']);
    }

    public function types()
    {
        return ProductType::query()
            ->orderBy('Product_Types_Name')
            ->get(['id', 'Product_Types_Name', 'Product_Types_Name_Ar']);
    }

    public function brands()
    {
        return ProductBrand::query()
            ->orderBy('Products_Brands_Name')
            ->get(['id', 'Products_Brands_Name', 'Products_Brands_Name_Ar']);
    }

    public function manufactures()
    {
        return ProductManufacture::query()
            ->orderBy('Products_Manufacture_Name')
            ->get(['id', 'Products_Manufacture_Name', 'Products_Manufacture_Name_Ar']);
    }

    private function applyHierarchyOrder(
        $query,
        string $table,
        string $sourceSequence,
        string $englishName,
    ) {
        if (Schema::hasColumn($table, 'Display_Order')) {
            $query
                ->orderByRaw('CASE WHEN Display_Order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('Display_Order');
        }

        if (Schema::hasColumn($table, $sourceSequence)) {
            $query
                ->orderByRaw("CASE WHEN {$sourceSequence} IS NULL THEN 1 ELSE 0 END")
                ->orderBy($sourceSequence);
        }

        return $query->orderBy($englishName)->orderBy('id');
    }
}
