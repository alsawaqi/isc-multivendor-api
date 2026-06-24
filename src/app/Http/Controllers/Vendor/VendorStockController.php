<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ProductStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorStockController extends Controller
{
    private function vendorId(): int
    {
        $user = Auth::guard('vendor')->user();
        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        if (!$vendorId) {
            abort(403, 'Vendor ID not found for this user.');
        }

        return (int) $vendorId;
    }

    public function index(Request $request)
    {
        $vendorId = $this->vendorId();
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);
        $sortBy = (string) $request->query('sort_by', 'Product_Stock');
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc'));

        if (!in_array($sortBy, ['id', 'Product_Name', 'Product_Code', 'Product_Sku', 'Product_Stock', 'Status', 'created_at'], true)) {
            $sortBy = 'Product_Stock';
        }

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $products = ProductMaster::query()
            ->where('Vendor_Id', $vendorId)
            ->with(['defaultImage', 'department', 'subDepartment', 'subSubDepartment'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($where) use ($search) {
                    $where->where('Product_Name', 'like', "%{$search}%")
                        ->orWhere('Product_Code', 'like', "%{$search}%")
                        ->orWhere('Product_Sku', 'like', "%{$search}%")
                        ->orWhere('Inhouse_Barcode_Source', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        return response()->json($products);
    }

    public function adjust(Request $request, int $id)
    {
        $vendorId = $this->vendorId();

        $validated = $request->validate([
            'movement_type' => ['required', 'in:increase,decrease,set'],
            'quantity' => ['required_unless:movement_type,set', 'nullable', 'integer', 'min:1'],
            'new_stock' => ['required_if:movement_type,set', 'nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = DB::transaction(function () use ($validated, $id, $vendorId) {
            $product = ProductMaster::query()
                ->where('id', $id)
                ->where('Vendor_Id', $vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = (int) ($product->Product_Stock ?? 0);
            $movementType = $validated['movement_type'];
            $quantity = $movementType === 'set'
                ? abs((int) $validated['new_stock'] - $previousStock)
                : (int) $validated['quantity'];

            if ($movementType === 'increase') {
                $newStock = $previousStock + $quantity;
                $delta = $quantity;
            } elseif ($movementType === 'decrease') {
                $newStock = $previousStock - $quantity;
                $delta = -$quantity;
            } else {
                $newStock = (int) $validated['new_stock'];
                $delta = $newStock - $previousStock;
            }

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot go below zero.'],
                ]);
            }

            $currentStatus = (string) ($product->Status ?? 'available');

            $product->forceFill([
                'Product_Stock' => $newStock,
                'Status' => $currentStatus === 'discontinued'
                    ? 'discontinued'
                    : ($newStock > 0 ? 'available' : 'out_of_stock'),
            ])->save();

            $user = Auth::guard('vendor')->user();
            $movement = ProductStockMovement::create([
                'Products_Id' => $product->id,
                'Vendor_Id' => $vendorId,
                'Movement_Type' => $movementType,
                'Quantity_Delta' => $delta,
                'Quantity' => $quantity,
                'Previous_Stock' => $previousStock,
                'New_Stock' => $newStock,
                'Actor_Type' => 'vendor',
                'Actor_Id' => $user?->id,
                'Actor_Name' => $user?->name ?? $user?->User_Name ?? $user?->email,
                'Notes' => $validated['notes'] ?? null,
            ]);

            return [
                'product' => $product->fresh(['defaultImage', 'department', 'subDepartment', 'subSubDepartment']),
                'movement' => $movement,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully.',
            'data' => $result,
        ]);
    }

    public function movements(Request $request, int $id)
    {
        $vendorId = $this->vendorId();

        ProductMaster::query()
            ->where('id', $id)
            ->where('Vendor_Id', $vendorId)
            ->firstOrFail();

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return response()->json(
            ProductStockMovement::query()
                ->where('Products_Id', $id)
                ->where('Vendor_Id', $vendorId)
                ->orderByDesc('created_at')
                ->paginate($perPage)
        );
    }
}
