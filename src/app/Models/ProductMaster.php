<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductMaster extends Model
{
    // Soft deletes: Products_Master_T.deleted_at is added by the isc-admin-api
    // migration (add product cost / min selling / Is_Active / deleted_at).
    // That migration MUST run before this code goes live, or every query
    // on this model will fail with an invalid-column error.
    use SoftDeletes;


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
        'Product_Price' => 'decimal:3',
        'Product_Cost'  => 'decimal:3',
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

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'Products_Id', 'id');
    }

    public function questions()
    {
        return $this->hasMany(ProductQuestion::class, 'Products_Id', 'id');
    }

    /**
     * Quantity-tier bulk prices for this product, ordered by Min_Qty.
     * Table lives in the isc-admin-api bulk-pricing migration — guard
     * eager loads with Schema::hasTable('Products_Bulk_Prices_T').
     */
    public function bulkPrices()
    {
        return $this->hasMany(ProductBulkPrice::class, 'Products_Id', 'id')
            ->orderBy('Min_Qty');
    }
}
