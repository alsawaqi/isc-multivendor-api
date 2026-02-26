<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecification extends Model
{
    //

    protected $table = 'Product_Specification_Product_Temp_T';


    protected $fillable = [
           'Product_Temporary_Id',
           'Product_Specification_Description_Id',
           'product_specification_value_id',
           'Created_By',
           
    ];
}
