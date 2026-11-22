<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\BillingAndExitWorkflowService;
use App\Services\OrderWorkflowService;
use App\Support\OrderPresenter;
use App\Support\PkdiDemoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GatePreviewController extends Controller
{
    public function dashboard(Request $request): View
    {
        $orders = OrderPresenter::allOrders();

        return view('gate.dashboard', [
            'waitingIn' => array_values(array_filter($orders, static fn (array $o): bool => in_array($o['order_status'], ['WAITING_GATE_IN', 'GATE_IN_HELD'], true))),
            'inFactory' => array_values(array_filter($orders, static fn (array $o): bool => $o['gate_status'] === 'CHECKED_IN_APPROVED')),
            'waitingOut' => array_values(array_filter($orders, static fn (array $o): bool => in_array($o['order_status'], ['WAITING_GATE_OUT', 'GATE_OUT_HELD', 'WAITING_EXIT_DOCUMENT'], true))),
        ]);
    }

    public function checkInIndex(Request $request): View
    {
        $orders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static fn (array $o): bool => in_array($o['order_status'], ['WAITING_GATE_IN', 'GATE_IN_HELD'], true)
        ));

        return view('gate.checkin.index', ['orders' => $orders]);
    }

    public function checkInShow(Request $request, string $code): View
    {
        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        if ($dbOrder) {
            Gate::authorize('view', $dbOrder);

            return view('gate.checkin.show', ['order' => OrderPresenter::toArray($dbOrder)]);
        }

        return view('gate.checkin.show', ['order' => PkdiDemoData::findOrderOrNull($code) ?? abort(404)]);
    }

    public function checkInStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $satpam */
        $satpam = $request->user();

        $order = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $order, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('inspectGate', $order);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:APPROVED,HELD'],
            'actual_license_plate' => ['nullable', 'string', 'max:25'],
            'actual_vehicle_type' => ['nullable', 'string', 'max:80'],
            'actual_driver_name' => ['nullable', 'string', 'max:120'],
            'is_plate_matched' => ['nullable'],
            'is_vehicle_matched' => ['nullable'],
            'hold_or_note_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $log = $workflow->inspectGateIn(
            satpam: $satpam,
            order: $order,
            validated: $validated,
            request: $request,
        );

        $statusMessage = $log->decision === 'APPROVED'
            ? "Kendaraan {$log->actual_license_plate} disetujui masuk area pabrik PKDI dan diteruskan ke Kepala Gudang."
            : "Kendaraan pada pesanan {$order->order_number} ditahan di Pos Gerbang Masuk.";

        return redirect()
            ->route('gate.checkin.show', $order->order_slug)
            ->with('status', $statusMessage);
    }

    public function checkOutIndex(Request $request): View
    {
        $orders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static fn (array $o): bool => in_array($o['order_status'], ['WAITING_GATE_OUT', 'GATE_OUT_HELD', 'WAITING_EXIT_DOCUMENT', 'PAYMENT_PARTIAL'], true)
        ));

        return view('gate.checkout.index', ['orders' => $orders]);
    }

    public function checkOutShow(Request $request, string $code): View
    {
        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        if ($dbOrder) {
            Gate::authorize('view', $dbOrder);

            return view('gate.checkout.show', ['order' => OrderPresenter::toArray($dbOrder)]);
        }

        return view('gate.checkout.show', ['order' => PkdiDemoData::findOrderOrNull($code) ?? abort(404)]);
    }

    public function checkOutStore(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $satpam */
        $satpam = $request->user();

        $order = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $order, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('inspectGate', $order);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:APPROVED,HELD'],
            'delivery_order_number' => ['nullable', 'string', 'max:40'],
            'actual_license_plate' => ['nullable', 'string', 'max:25'],
            'actual_vehicle_type' => ['nullable', 'string', 'max:80'],
            'actual_driver_name' => ['nullable', 'string', 'max:120'],
            'is_cargo_matched' => ['nullable'],
            'exit_notes' => ['nullable', 'string', 'max:2000'],
            'hold_out_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $log = $billing->inspectGateOut(
            satpam: $satpam,
            order: $order,
            validated: $validated,
            request: $request,
        );

        $msg = $log->decision === 'APPROVED'
            ? "Kendaraan {$log->actual_license_plate} telah diverifikasi keluar dan transaksi pesanan {$order->order_number} selesai."
            : "Kendaraan pada pesanan {$order->order_number} ditahan di Pos Gerbang Keluar.";

        return redirect()
            ->route('gate.checkout.show', $order->order_slug)
            ->with('status', $msg);
    }

    public function history(Request $request): View
    {
        return view('gate.history', ['logs' => OrderPresenter::gateLogs()]);
    }
}
