<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\OrderPresenter;
use App\Support\PkdiDemoData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StatusHeartbeatController extends Controller
{
    /**
     * Endpoint ringan untuk fallback polling terukur (setiap 15 detik).
     * Hanya mengambil ringkasan status terindeks sesuai hak akses pengguna.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $baseQuery = ServiceOrder::query()->visibleTo($user);

        $latestUpdatedAt = (clone $baseQuery)->max('updated_at');
        $statusCounts = (clone $baseQuery)
            ->selectRaw('order_status, count(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status')
            ->all();

        $orderPayload = null;
        $orderCode = trim((string) $request->query('order', ''));
        if ($orderCode !== '') {
            $order = ServiceOrder::findByCodeOrSlug($orderCode);
            if ($order && Gate::forUser($user)->allows('view', $order)) {
                $presented = OrderPresenter::toArray($order);
                $statusMeta = PkdiDemoData::statusMeta($order->order_status);
                $orderPayload = [
                    'order_number' => $order->order_number,
                    'order_slug' => $order->order_slug,
                    'order_status' => $order->order_status,
                    'order_status_label' => $statusMeta['label'],
                    'order_status_tone' => $statusMeta['tone'],
                    'warehouse_status' => $order->warehouse_status,
                    'billing_status' => $order->billing_status ?? 'NOT_READY',
                    'payment_status' => $order->payment_status,
                    'latest_timeline' => $presented['timeline'][0] ?? null,
                    'updated_at' => $order->updated_at?->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'mode' => 'fallback_polling',
            'interval_seconds' => 15,
            'role' => $user->role,
            'server_time_wib' => now('Asia/Jakarta')->format('d M Y, H.i.s').' WIB',
            'latest_updated_at' => $latestUpdatedAt ? (string) $latestUpdatedAt : null,
            'status_counts' => $statusCounts,
            'order' => $orderPayload,
        ]);
    }
}
