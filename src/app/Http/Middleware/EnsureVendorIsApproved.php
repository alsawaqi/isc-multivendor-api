<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorIsApproved
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('vendor')->user();
        $vendor = $user?->vendor;

        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found.'], 403);
        }

        if (!Schema::hasColumn('Vendors_Master_T', 'Approval_Status')) {
            return $next($request);
        }

        if (strtolower((string) $vendor->Approval_Status) !== 'approved') {
            return response()->json([
                'message' => 'Vendor approval is required before accessing this area.',
                'approval_status' => $vendor->Approval_Status ?: 'pending',
            ], 403);
        }

        return $next($request);
    }
}
