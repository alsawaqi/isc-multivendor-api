<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductQuestion extends Model
{
    use SoftDeletes;

    protected $table = 'Product_Questions_T';
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(ProductMaster::class, 'Products_Id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'Customers_Id');
    }

    public function answers()
    {
        return $this->hasMany(ProductQuestionAnswer::class, 'Product_Question_Id');
    }
}
