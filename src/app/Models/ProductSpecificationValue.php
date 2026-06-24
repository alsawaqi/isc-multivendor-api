<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecificationValue extends Model
{
    protected $table = 'Product_Specification_Value_T';

    protected $fillable = [
        // 'Product_Specification_Description_Id',
        // 'Value',
        // 'Value_Ar',
        // 'Created_By',
        // ...
    ];

    public function description()
    {
        return $this->belongsTo(
            ProductSpecificationDescription::class,
            'Product_Specification_Description_Id'
        );
    }
}
