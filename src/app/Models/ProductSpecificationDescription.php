<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecificationDescription extends Model
{
    protected $table = 'Product_Specification_Description_T';

    // Add any fillable columns you want to mass-assign
    protected $fillable = [
        // 'Product_Sub_Sub_Department_Id',
        // 'Specification_Name',
        // 'Specification_Name_Ar',
        // 'Input_Type',
        // 'Created_By',
        // ...
    ];

    public function values()
    {
        return $this->hasMany(
            ProductSpecificationValue::class,
            'product_specification_description_id'
        );
    }
}
