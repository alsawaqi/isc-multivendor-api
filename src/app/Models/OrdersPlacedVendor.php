<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdersPlacedVendor extends Model
{
    protected $table = 'Orders_Placed_Vendors_T';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'Orders_Placed_Id',
        'Vendor_Id',
        'Vendor_Order_Code',
        'Sub_Total',
        'VAT',
        'Shipping',
        'Total',
        'Status',
        'Commission_Type',
        'Commission_Value',
        'Commission_Amount',
        'Payout_Status',
        'Payout_Amount',
        'Payout_At',
        'Payout_Reference',
        'Returned_Quantity',
        'Refunded_Amount',
        'Net_Sub_Total',
        'Adjusted_Commission_Amount',
        'Net_Payout_Amount',
        'Payout_Adjustment_Amount',
    ];

    protected $casts = [
        'Sub_Total'          => 'decimal:3',
        'VAT'                => 'decimal:3',
        'Shipping'           => 'decimal:3',
        'Total'              => 'decimal:3',
        'Commission_Value'   => 'decimal:3',
        'Commission_Amount'  => 'decimal:3',
        'Payout_Amount'      => 'decimal:3',
        'Returned_Quantity'  => 'integer',
        'Refunded_Amount'    => 'decimal:3',
        'Net_Sub_Total'      => 'decimal:3',
        'Adjusted_Commission_Amount' => 'decimal:3',
        'Net_Payout_Amount'  => 'decimal:3',
        'Payout_Adjustment_Amount' => 'decimal:3',
        'Payout_At'          => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'Vendor_Id', 'id');
    }
}
