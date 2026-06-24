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
        'Request_Type',
        'Status',
        'Comment',
        'Requested_Changes_Json',
        'Action_By_User_Id',
        'Action_By_Role',
        'Action_At',
    ];

    protected $casts = [
        'Products_Temporary_Id' => 'integer',
        'Products_Id' => 'integer',
        'Vendor_Id' => 'integer',
        'Requested_Changes_Json' => 'array',
        'Action_At' => 'datetime',
    ];

 
}
