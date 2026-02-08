<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMaster extends Model
{
    //
    
    protected $table = 'Products_Master_T';

    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'Vendor_Id',
        'Product_Name',
        'Product_Name_Ar',
        'Product_Department_Id',
        'Product_Sub_Department_Id',
        'Product_Sub_Sub_Department_Id',
    ];

    protected $casts = [
        'Product_Price' => 'decimal:2',
        'Product_Cost'  => 'decimal:2',
        'Product_Stock' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'Vendor_Id');
    }


    public function defaultImage()
    {
        return $this->hasOne(ProductImage::class, 'Products_Id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(ProductDepartment::class, 'Product_Department_Id');
    }

    public function subDepartment()
    {
        return $this->belongsTo(ProductSubDepartment::class, 'Product_Sub_Department_Id');
    }
    
    public function subSubDepartment()
    {
        return $this->belongsTo(ProductSubSubDepartment::class, 'Product_Sub_Sub_Department_Id');
    }

    public function brand()
    {
        return $this->belongsTo(ProductBrand::class, 'Product_Brand_Id');
    }

    public function manufacture()
    {
        return $this->belongsTo(ProductManufacture::class, 'Product_Manufacture_Id');
    }

    public function type()
    {
        return $this->belongsTo(ProductType::class, 'Product_Type_Id');
    }

    public function specs()
    {
        return $this->hasMany(ProductsSpecification::class, 'Product_Id');
    }
}
