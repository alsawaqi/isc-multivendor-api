<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'Secx_Vendors_Users_Master_T';
    protected $primaryKey = 'id';

    // if your default DB connection is not sqlsrv:
    // protected $connection = 'sqlsrv';

    protected $fillable = [
        'Vendor_Id',
        'User_Id',
        'User_Name',
        'email',
        'password',
        'Status',
        'Is_Active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'Login_Password', // if present
    ];

    protected $casts = [
        'Status' => 'integer',
        'Is_Active' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'Vendor_Id', 'id');
    }
}
