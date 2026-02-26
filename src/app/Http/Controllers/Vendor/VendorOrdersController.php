<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OrdersPlacedVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorOrdersController extends Controller
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
     * Step 1A: Vendor orders list (read-only)
     *
     * GET /vendor/api/orders
     * Query params:
     *  - page
     *  - per_page
     *  - status=all|pending|commission_set|...
     *  - payout_status=all|unpaid|requested|paid
     *  - q (search by vendor order code / order id / vendor order id)
     */
    public function index(Request $request)
    {
        $vendorId = $this->vendorId();

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $status = trim((string) $request->query('status', 'all'));
        $payoutStatus = trim((string) $request->query('payout_status', 'all'));
        $search = trim((string) $request->query('q', ''));

        $q = OrdersPlacedVendor::query()
            ->where('Vendor_Id', $vendorId)
            ->with(['vendor:id,Vendor_Name'])
            ->select('Orders_Placed_Vendors_T.*')
            ->addSelect([
                // helpful for UI
                'Items_Count' => DB::table('Orders_Placed_Details_T as d')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('d.Orders_Placed_Vendor_Id', 'Orders_Placed_Vendors_T.id'),
            ]);

        // Status filter
        if ($status !== '' && strtolower($status) !== 'all') {
            $q->where('Status', $status);
        }

        // Payout filter
        if ($payoutStatus !== '' && strtolower($payoutStatus) !== 'all') {
            $normalized = strtolower($payoutStatus);

            if ($normalized === 'unpaid') {
                $q->where(function ($w) {
                    $w->whereNull('Payout_Status')
                      ->orWhere('Payout_Status', '')
                      ->orWhere('Payout_Status', 'unpaid');
                });
            } else {
                $q->where('Payout_Status', $normalized);
            }
        }

        // Search
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('Vendor_Order_Code', 'like', "%{$search}%")
                  ->orWhere('Status', 'like', "%{$search}%")
                  ->orWhere('Payout_Status', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $num = (int) $search;
                    $w->orWhere('id', $num)
                      ->orWhere('Orders_Placed_Id', $num);
                }
            });
        }

        $paginator = $q->orderByDesc('id')->paginate($perPage)->appends($request->query());

        return response()->json($paginator);
    }

    /**
     * Step 1B: Vendor order details (header + items)
     *
     * GET /vendor/api/orders/{id}
     */
    public function show(int $id)
    {
        $vendorId = $this->vendorId();

        $vendorOrder = OrdersPlacedVendor::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId) // ownership check ✅
            ->with(['vendor:id,Vendor_Name'])
            ->firstOrFail();

        // Main order header (customer order)
        $order = DB::table('Orders_Placed_T')
            ->where('id', $vendorOrder->Orders_Placed_Id)
            ->first();

        // Items that belong to this vendor-order
        $items = DB::table('Orders_Placed_Details_T as d')
            ->leftJoin('Products_Master_T as p', 'p.id', '=', 'd.Products_Id')
            ->select([
                'd.id',
                'd.Orders_Placed_Vendor_Id',
                'd.Products_Id',
                'd.Quantity',
                'd.Price',
                'd.Subtotal',
                'd.Vat',
                'd.Status',

                'p.Product_Code',
                'p.Product_Name',
                'p.Product_Name_Ar',
                'p.Inhouse_Barcode_Source',
            ])
            ->where('d.Orders_Placed_Vendor_Id', $vendorOrder->id)
            ->orderBy('d.id')
            ->get();

        $subTotal = (float) ($vendorOrder->Sub_Total ?? 0);
        $commission = (float) ($vendorOrder->Commission_Amount ?? 0);
        $net = round(max($subTotal - $commission, 0), 3);

        return response()->json([
            'success' => true,
            'data' => [
                'vendor_order' => $vendorOrder,
                'order' => $order,
                'items' => $items,
                'finance' => [
                    'sub_total' => $subTotal,
                    'commission_amount' => $commission,
                    'net_earnings' => $net,
                    'payout_status' => $vendorOrder->Payout_Status ?: 'unpaid',
                    'payout_amount' => (float) ($vendorOrder->Payout_Amount ?? 0),
                    'payout_at' => $vendorOrder->Payout_At,
                    'payout_reference' => $vendorOrder->Payout_Reference,
                ],
            ],
        ]);
    }
}