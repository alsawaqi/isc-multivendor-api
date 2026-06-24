<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTemporary extends Model
{

    use SoftDeletes;


    protected $table = 'Products_Temporary_T';

    protected $primaryKey = 'id';
    public $incrementing = true;

    // Table has created_at/updated_at
    public $timestamps = true;

    protected $fillable = [
        'Vendor_Id',
        'Temp_Product_Code',

        'Product_Name',
        'Product_Name_Ar',
        'Description',
        'Description_Ar',

        'Product_Department_Id',
        'Product_Sub_Department_Id',
        'Product_Sub_Sub_Department_Id',

        'Product_Brand_Id',
        'Product_Manufacture_Id',
        'Product_Type_Id',

        'Product_Price',
        'Product_Cost',
        'Product_Stock',

        'Weight_Kg',
        'Length_Cm',
        'Width_Cm',
        'Height_Cm',
        'Volume_Cbm',

        'Submission_Status',
        'Submitted_By',
        'Submitted_At',

        'Reviewed_By',
        'Reviewed_At',
        'Rejection_Reason',

        'Approved_Product_Id',

        'Created_By',
        'Updated_By',
    ];

    protected $casts = [
        'Product_Price' => 'decimal:2',
        'Product_Cost'  => 'decimal:2',
        'Product_Stock' => 'integer',

        'Weight_Kg'  => 'float',
        'Length_Cm'  => 'float',
        'Width_Cm'   => 'float',
        'Height_Cm'  => 'float',
        'Volume_Cbm' => 'float',

        'Submitted_At' => 'datetime',
        'Reviewed_At'  => 'datetime',
        'deleted_at'   => 'datetime',
    ];



    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'Vendor_Id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(ProductTemporaryImage::class, 'Products_Temporary_Id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function defaultImage()
    {
        return $this->hasOne(ProductTemporaryImage::class, 'Products_Temporary_Id', 'id')
            ->where('Is_Default', 1);
    }


      // ✅ NEW: catalog relations (adjust model class names if yours differ)
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
  
      // ✅ NEW: specs relation (Temp specs table)
      public function specs()
      {
          return $this->hasMany(ProductSpecificationTemp::class, 'Product_Temporary_Id');
      }
}
