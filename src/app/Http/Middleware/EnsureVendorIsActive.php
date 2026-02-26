<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureVendorIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('vendor')->user();

        if (!$user || !$user->vendor || (int)$user->vendor->Is_Active !== 1) {
            Auth::guard('vendor')->logout();
            return $request->expectsJson()
                ? response()->json(['message' => 'Vendor inactive.'], 403)
                : redirect('/vendor/login');
        }

        return $next($request);
    }
}
