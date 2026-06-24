<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'Vendors_Master_T';
    protected $primaryKey = 'id';

    // protected $connection = 'sqlsrv';
    protected $guarded = [];

    protected $casts = [
        'Is_Active' => 'integer',
        'Onboarding_Completeness' => 'integer',
        'Approved_At' => 'datetime',
        'Submitted_For_Approval_At' => 'datetime',
    ];

    public function documents()
    {
        return $this->hasMany(VendorDocument::class, 'Vendor_Id', 'id');
    }
}
