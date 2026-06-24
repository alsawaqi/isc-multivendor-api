<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecificationProductTemp extends Model
{
    protected $table = 'Product_Specification_Product_Temp_T';

    protected $fillable = [
        'Product_Temporary_Id',
        'Product_Specification_Description_Id',
        'product_specification_value_id',
        'Created_By',
    ];

    // temp product
    public function tempProduct()
    {
        return $this->belongsTo(ProductTemporary::class, 'Product_Temporary_Id');
    }

    // spec header (Voltage, Material, etc.)
    public function description()
    {
        return $this->belongsTo(
            ProductSpecificationDescription::class,
            'Product_Specification_Description_Id'
        );
    }

    // chosen dropdown value (220V, Steel, etc.)
    public function value()
    {
        return $this->belongsTo(
            ProductSpecificationValue::class,
            'product_specification_value_id'
        );
    }
}
