<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OrdersPlacedVendor;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorEarningsController extends Controller
{
    /**
     * Resolve Vendor_Id from logged in vendor user.
     */
    private function vendorId(): int
    {
        $user = Auth::guard('vendor')->user();

        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        if (!$vendorId) {
            abort(403, 'Vendor ID not found for this user.');
        }

        return (int) $vendorId;
    }

    /**
     * Step 3: Vendor earnings summary (dashboard cards)
     *
     * GET /vendor/api/earnings/summary
     * Query params:
     * - date_from (optional, filters created_at)
     * - date_to   (optional, filters created_at)
     */
    public function summary(Request $request)
    {
        $vendorId = $this->vendorId();
    
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]);
    
        $base = OrdersPlacedVendor::query()
            ->where('Vendor_Id', $vendorId);
    
        // Keep parsed dates so we can reuse them in the top-product query
        $fromDateTime = null;
        $toDateTime = null;
    
        // Optional date filters (based on vendor order created_at)
        if (!empty($validated['date_from'])) {
            $fromDateTime = Carbon::parse($validated['date_from'])->startOfDay()->format('Y-m-d H:i:s');
            $base->where('created_at', '>=', $fromDateTime);
        }
    
        if (!empty($validated['date_to'])) {
            $toDateTime = Carbon::parse($validated['date_to'])->endOfDay()->format('Y-m-d H:i:s');
            $base->where('created_at', '<=', $toDateTime);
        }
    
        // Aggregate summary
        $agg = (clone $base)
            ->selectRaw("
                COUNT(*) AS total_orders,
    
                SUM(COALESCE(Sub_Total, 0)) AS gross_sales,
    
                SUM(CASE
                    WHEN Commission_Amount IS NOT NULL THEN COALESCE(Commission_Amount, 0)
                    ELSE 0
                END) AS commission_total,
    
                SUM(CASE
                    WHEN Commission_Amount IS NOT NULL THEN
                        CASE
                            WHEN (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0)) < 0 THEN 0
                            ELSE (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0))
                        END
                    ELSE 0
                END) AS net_earnings_total,
    
                SUM(CASE WHEN Status = 'commission_set' THEN 1 ELSE 0 END) AS commission_set_orders,
    
                SUM(CASE
                    WHEN Payout_Status = 'paid' THEN 1
                    ELSE 0
                END) AS paid_orders_count,
    
                SUM(CASE
                    WHEN Payout_Status = 'requested' THEN 1
                    ELSE 0
                END) AS requested_orders_count,
    
                SUM(CASE
                    WHEN Payout_Status IS NULL OR Payout_Status = '' OR Payout_Status = 'unpaid' THEN 1
                    ELSE 0
                END) AS unpaid_orders_count,
    
                SUM(CASE
                    WHEN Payout_Status = 'paid' THEN
                        COALESCE(
                            Payout_Amount,
                            CASE
                                WHEN (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0)) < 0 THEN 0
                                ELSE (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0))
                            END
                        )
                    ELSE 0
                END) AS paid_payout_total,
    
                SUM(CASE
                    WHEN Status = 'commission_set'
                         AND (Payout_Status IS NULL OR Payout_Status = '' OR Payout_Status = 'unpaid')
                    THEN
                        CASE
                            WHEN (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0)) < 0 THEN 0
                            ELSE (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0))
                        END
                    ELSE 0
                END) AS unpaid_payout_total,
    
                SUM(CASE
                    WHEN Status = 'commission_set'
                         AND Payout_Status = 'requested'
                    THEN
                        CASE
                            WHEN (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0)) < 0 THEN 0
                            ELSE (COALESCE(Sub_Total,0) - COALESCE(Commission_Amount,0))
                        END
                    ELSE 0
                END) AS requested_payout_total
            ")
            ->first();
    
        // Pending payout = unpaid + requested
        $pendingPayout = (float) ($agg->unpaid_payout_total ?? 0) + (float) ($agg->requested_payout_total ?? 0);
    
        // Recent orders widget
        $recentOrders = (clone $base)
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'Orders_Placed_Id',
                'Vendor_Order_Code',
                'Sub_Total',
                'Commission_Amount',
                'Payout_Amount',
                'Status',
                'Payout_Status',
                'Payout_At',
                'created_at',
            ])
            ->map(function ($row) {
                $sub = (float) ($row->Sub_Total ?? 0);
                $comm = (float) ($row->Commission_Amount ?? 0);
                $expectedNet = round(max($sub - $comm, 0), 3);
    
                return [
                    'id' => $row->id,
                    'Orders_Placed_Id' => $row->Orders_Placed_Id,
                    'Vendor_Order_Code' => $row->Vendor_Order_Code,
                    'Status' => $row->Status,
                    'Payout_Status' => $row->Payout_Status ?: 'unpaid',
                    'Sub_Total' => $sub,
                    'Commission_Amount' => $comm,
                    'Expected_Payout_Amount' => $expectedNet,
                    'Payout_Amount' => is_null($row->Payout_Amount) ? null : (float) $row->Payout_Amount,
                    'Payout_At' => $row->Payout_At,
                    'created_at' => $row->created_at,
                ];
            })
            ->values();
    
        // Most sold products (by total quantity sold)
        $topProductQuery = DB::table('Orders_Placed_Details_T as d')
            ->join('Orders_Placed_Vendors_T as ov', 'ov.id', '=', 'd.Orders_Placed_Vendor_Id')
            ->leftJoin('Products_Master_T as p', 'p.id', '=', 'd.Products_Id')
            ->where('ov.Vendor_Id', $vendorId);
    
        // Apply same date range filters (based on vendor order created_at)
        if ($fromDateTime) {
            $topProductQuery->where('ov.created_at', '>=', $fromDateTime);
        }
        if ($toDateTime) {
            $topProductQuery->where('ov.created_at', '<=', $toDateTime);
        }
    
        $topProducts = $topProductQuery
            ->selectRaw("
                d.Products_Id as product_id,
                MAX(p.Product_Name) as product_name,
                MAX(p.Product_Code) as product_code,
                SUM(COALESCE(d.Quantity, 0)) as units_sold,
                COUNT(DISTINCT d.Orders_Placed_Vendor_Id) as orders_count,
                SUM(COALESCE(d.Subtotal, 0)) as sales_total
            ")
            ->groupBy('d.Products_Id')
            ->orderByDesc('units_sold')
            ->orderByDesc('sales_total')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                return [
                    'product_id' => (int) $row->product_id,
                    'product_name' => $row->product_name,
                    'product_code' => $row->product_code,
                    'units_sold' => (float) $row->units_sold,
                    'orders_count' => (int) $row->orders_count,
                    'sales_total' => round((float) $row->sales_total, 3),
                ];
            })
            ->values();

        $topProduct = $topProducts->first();

        $trendFrom = $fromDateTime
            ? Carbon::parse($fromDateTime)->startOfDay()
            : now()->subDays(29)->startOfDay();

        $trendTo = $toDateTime
            ? Carbon::parse($toDateTime)->endOfDay()
            : now()->endOfDay();

        if ($trendFrom->gt($trendTo)) {
            [$trendFrom, $trendTo] = [$trendTo->copy()->startOfDay(), $trendFrom->copy()->endOfDay()];
        }

        $trendRows = DB::table('Orders_Placed_Vendors_T')
            ->where('Vendor_Id', $vendorId)
            ->whereBetween('created_at', [$trendFrom->format('Y-m-d H:i:s'), $trendTo->format('Y-m-d H:i:s')])
            ->selectRaw('CONVERT(date, created_at) as sales_date')
            ->selectRaw('SUM(COALESCE(Total, 0)) as sales_total')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupBy(DB::raw('CONVERT(date, created_at)'))
            ->orderBy(DB::raw('CONVERT(date, created_at)'))
            ->get()
            ->keyBy(fn ($row) => (string) $row->sales_date);

        $salesTrend = [];
        foreach (CarbonPeriod::create($trendFrom->copy()->startOfDay(), $trendTo->copy()->startOfDay()) as $date) {
            $key = $date->toDateString();
            $row = $trendRows->get($key);

            $salesTrend[] = [
                'date' => $key,
                'label' => $date->format('M d'),
                'sales_total' => round((float) ($row->sales_total ?? 0), 3),
                'orders_count' => (int) ($row->orders_count ?? 0),
            ];
        }
    
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_orders' => (int) ($agg->total_orders ?? 0),
    
                    'gross_sales' => round((float) ($agg->gross_sales ?? 0), 3),
                    'commission_total' => round((float) ($agg->commission_total ?? 0), 3),
                    'net_earnings_total' => round((float) ($agg->net_earnings_total ?? 0), 3),
    
                    'paid_payout_total' => round((float) ($agg->paid_payout_total ?? 0), 3),
                    'pending_payout_total' => round($pendingPayout, 3),
                    'unpaid_payout_total' => round((float) ($agg->unpaid_payout_total ?? 0), 3),
                    'requested_payout_total' => round((float) ($agg->requested_payout_total ?? 0), 3),
    
                    'commission_set_orders' => (int) ($agg->commission_set_orders ?? 0),
                    'paid_orders_count' => (int) ($agg->paid_orders_count ?? 0),
                    'requested_orders_count' => (int) ($agg->requested_orders_count ?? 0),
                    'unpaid_orders_count' => (int) ($agg->unpaid_orders_count ?? 0),
                ],
                'recent_orders' => $recentOrders,
    
                // ✅ New dashboard widget payload
                'top_sold_product' => $topProduct ?: null,
                'top_products' => $topProducts,
                'sales_trend' => $salesTrend,
    
                'filters' => [
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                ],
            ],
        ]);
    }
}
