<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'Vendors_Master_T';
    protected $primaryKey = 'id';

    // protected $connection = 'sqlsrv';

    protected $casts = [
        'Status' => 'integer',
        'Is_Active' => 'integer',
    ];
}
