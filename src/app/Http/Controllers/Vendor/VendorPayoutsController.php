<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OrdersPlacedVendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorPayoutsController extends Controller
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
     * Step 2: Vendor payouts / payments list (read-only)
     *
     * GET /vendor/api/payouts
     *
     * Query params:
     * - page
     * - per_page
     * - payout_status=all|unpaid|requested|paid
     * - q (search by vendor order code / order id / payout ref)
     * - date_from (filters Payout_At)
     * - date_to   (filters Payout_At)
     */
    public function index(Request $request)
    {
        $vendorId = $this->vendorId();

        $validated = $request->validate([
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
            'payout_status' => ['nullable', 'string'],
            'q'             => ['nullable', 'string', 'max:100'],
            'date_from'     => ['nullable', 'date'],
            'date_to'       => ['nullable', 'date'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $payoutStatus = strtolower(trim((string) ($validated['payout_status'] ?? 'all')));
        $search = trim((string) ($validated['q'] ?? ''));

        // Payout page should show commission-approved rows only (same as admin payout queue/history)
        $q = OrdersPlacedVendor::query()
            ->where('Vendor_Id', $vendorId)
            ->where('Status', 'commission_set')
            ->with(['vendor']) // keep simple; can limit columns later
            ->select('Orders_Placed_Vendors_T.*')
            ->addSelect([
                'Items_Count' => DB::table('Orders_Placed_Details_T as d')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('d.Orders_Placed_Vendor_Id', 'Orders_Placed_Vendors_T.id'),
            ]);

        // payout_status filter
        if ($payoutStatus !== '' && $payoutStatus !== 'all') {
            if ($payoutStatus === 'unpaid') {
                $q->where(function ($w) {
                    $w->whereNull('Payout_Status')
                      ->orWhere('Payout_Status', '')
                      ->orWhere('Payout_Status', 'unpaid');
                });
            } else {
                $q->where('Payout_Status', $payoutStatus);
            }
        }

        // Date range filter (on payout date only)
        if (!empty($validated['date_from'])) {
            $from = Carbon::parse($validated['date_from'])->startOfDay()->format('Y-m-d H:i:s');
            $q->whereNotNull('Payout_At')
              ->where('Payout_At', '>=', $from);
        }

        if (!empty($validated['date_to'])) {
            $to = Carbon::parse($validated['date_to'])->endOfDay()->format('Y-m-d H:i:s');
            $q->whereNotNull('Payout_At')
              ->where('Payout_At', '<=', $to);
        }

        // Search
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('Vendor_Order_Code', 'like', "%{$search}%")
                  ->orWhere('Payout_Reference', 'like', "%{$search}%")
                  ->orWhere('Status', 'like', "%{$search}%")
                  ->orWhere('Payout_Status', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $num = (int) $search;
                    $w->orWhere('id', $num)
                      ->orWhere('Orders_Placed_Id', $num);
                }
            });
        }

        // Sort: paid rows by Payout_At desc first, unpaid rows after (but still by latest id)
        $paginator = $q
            ->orderByRaw("CASE WHEN Payout_At IS NULL THEN 1 ELSE 0 END ASC")
            ->orderByDesc('Payout_At')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        // Transform rows for clean UI consumption
        $items = collect($paginator->items())->map(function ($row) {
            $subTotal = (float) ($row->Sub_Total ?? 0);
            $commission = (float) ($row->Commission_Amount ?? 0);

            $calculatedNet = round(max($subTotal - $commission, 0), 3);
            $storedPayout = is_null($row->Payout_Amount) ? null : (float) $row->Payout_Amount;

            return [
                'id' => $row->id,
                'Orders_Placed_Id' => $row->Orders_Placed_Id,
                'Vendor_Id' => $row->Vendor_Id,
                'Vendor_Order_Code' => $row->Vendor_Order_Code,

                'Status' => $row->Status,
                'Payout_Status' => $row->Payout_Status ?: 'unpaid',

                'Sub_Total' => $subTotal,
                'Commission_Type' => $row->Commission_Type,
                'Commission_Value' => is_null($row->Commission_Value) ? null : (float) $row->Commission_Value,
                'Commission_Amount' => $commission,

                // if payout amount not stored yet, return computed net
                'Payout_Amount' => $storedPayout ?? $calculatedNet,
                'Expected_Payout_Amount' => $calculatedNet,

                'Payout_At' => $row->Payout_At,
                'Payout_Reference' => $row->Payout_Reference,

                'Items_Count' => (int) ($row->Items_Count ?? 0),

                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}