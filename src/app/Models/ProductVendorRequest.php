<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVendorRequest extends Model
{
    protected $table = 'Products_Vendor_Requests_T';
  

    protected $fillable = [
        'Products_Temporary_Id',
        'Products_Id',
        'Vendor_Id',
        'Status',
        'Comment',
        'Action_By_User_Id',
        'Action_By_Role',
        'Action_At',
    ];

 
}
