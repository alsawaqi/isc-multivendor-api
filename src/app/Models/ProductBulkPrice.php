<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Quantity-tier bulk price row for an approved (master) product.
 * Table created by the isc-admin-api bulk-pricing migration
 * (Products_Bulk_Prices_T) — guard reads with Schema::hasTable.
 * Replace-set semantics: no soft deletes.
 */
class ProductBulkPrice extends Model
{
    protected $table = 'Products_Bulk_Prices_T';

    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'Products_Id',
        'Min_Qty',
        'Max_Qty',
        'Unit_Price',
        'Created_By',
    ];

    protected $casts = [
        'Products_Id' => 'integer',
        'Min_Qty' => 'integer',
        'Max_Qty' => 'integer',
        'Unit_Price' => 'decimal:3',
        'Created_By' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(ProductMaster::class, 'Products_Id');
    }
}
