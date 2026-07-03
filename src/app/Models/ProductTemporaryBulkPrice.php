<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Quantity-tier bulk price row submitted alongside a temp (pending) product.
 * Table created by the isc-admin-api bulk-pricing migration
 * (Products_Temporary_Bulk_Prices_T) — guard reads with Schema::hasTable.
 * Replace-set semantics: no soft deletes. Copied to Products_Bulk_Prices_T
 * by admin approval (isc-admin-api approveOne).
 */
class ProductTemporaryBulkPrice extends Model
{
    protected $table = 'Products_Temporary_Bulk_Prices_T';

    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'Products_Temporary_Id',
        'Min_Qty',
        'Max_Qty',
        'Unit_Price',
        'Created_By',
    ];

    protected $casts = [
        'Products_Temporary_Id' => 'integer',
        'Min_Qty' => 'integer',
        'Max_Qty' => 'integer',
        'Unit_Price' => 'decimal:3',
        'Created_By' => 'integer',
    ];

    public function productTemporary()
    {
        return $this->belongsTo(ProductTemporary::class, 'Products_Temporary_Id');
    }
}
