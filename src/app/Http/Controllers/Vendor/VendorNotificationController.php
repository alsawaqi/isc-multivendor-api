<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorNotificationController extends Controller
{
    private const NOTIFIABLE_TYPE = 'App\\Models\\Vendor';

    /**
     * List the signed-in vendor's recent notifications + unread count.
     * Notifications live in the shared Conx_Notifications_T, scoped to the
     * vendor via notifiable_type='App\Models\Vendor' + notifiable_id = Vendor_Id.
     */
    public function index(Request $request)
    {
        $vendorId = $this->vendorId();

        if (! $vendorId || ! Schema::hasTable('Conx_Notifications_T')) {
            return response()->json(['data' => [], 'unread_count' => 0]);
        }

        $rows = DB::table('Conx_Notifications_T')
            ->where('notifiable_type', self::NOTIFIABLE_TYPE)
            ->where('notifiable_id', $vendorId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $notifications = $rows->map(function ($n) {
            $data = is_array($n->data) ? $n->data : json_decode($n->data ?? '{}', true);

            return [
                'id'         => $n->id,
                'title'      => $data['title']   ?? 'Notification',
                'message'    => $data['message'] ?? null,
                'url'        => $data['url']     ?? null,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        $unread = DB::table('Conx_Notifications_T')
            ->where('notifiable_type', self::NOTIFIABLE_TYPE)
            ->where('notifiable_id', $vendorId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unread,
        ]);
    }

    /**
     * Mark all (or one) of the vendor's notifications as read.
     */
    public function markAllRead(Request $request)
    {
        $vendorId = $this->vendorId();

        if (! $vendorId || ! Schema::hasTable('Conx_Notifications_T')) {
            return response()->json(['status' => 'ok']);
        }

        $query = DB::table('Conx_Notifications_T')
            ->where('notifiable_type', self::NOTIFIABLE_TYPE)
            ->where('notifiable_id', $vendorId)
            ->whereNull('read_at');

        if ($id = $request->input('id')) {
            $query->where('id', $id);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    private function vendorId(): ?int
    {
        $user = Auth::guard('vendor')->user();
        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        return $vendorId ? (int) $vendorId : null;
    }
}
