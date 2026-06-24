<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductsDepartment;
use App\Models\Catalog\ProductsSubDepartment;
use App\Models\Catalog\ProductsSubSubDepartment;
use App\Models\Catalog\ProductType;
use App\Models\Catalog\ProductBrand;
use App\Models\Catalog\ProductManufacture;

class VendorCatalogController extends Controller
{
    public function departments()
    {
        return ProductsDepartment::query()
             ->orderBy('Product_Department_Name')
            ->get(['id', 'Product_Department_Name', 'Product_Department_Name_Ar']);
    }

    public function subDepartments($departmentId)
    {
        return ProductsSubDepartment::query()
             ->where('Products_Departments_Id', $departmentId)
            ->orderBy('Sub_Department_Name')
            ->get(['id', 'Products_Departments_Id', 'Sub_Department_Name', 'Sub_Department_Name_Ar']);
    }

    public function subSubDepartments($subDepartmentId)
    {
        return ProductsSubSubDepartment::query()
             ->where('Product_Sub_Department_Id', $subDepartmentId)
            ->orderBy('Product_Sub_Sub_Department_Name')
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
}
