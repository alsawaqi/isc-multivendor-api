<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTemporaryImage extends Model
{

    use SoftDeletes;


    protected $table = 'Products_Temporary_Images_T';

    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'Products_Temporary_Id',
        'Image_Path',
        'Image_Size',
        'Image_Extension',
        'Image_Type',
        'Is_Default',
        'Created_By',
    ];

    protected $casts = [
        'Image_Size' => 'integer',
        'Is_Default' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(ProductTemporary::class, 'Products_Temporary_Id', 'id');
    }
}