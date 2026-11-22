<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\BillingAndExitWorkflowService;
use App\Services\OrderWorkflowService;
use App\Services\ReportExportService;
use App\Support\OrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WarehousePreviewController extends Controller
{
    public function dashboard(Request $request): View
    {
        $orders = OrderPresenter::allOrders();

        $needsReview = array_values(array_filter(
            $orders,
            static fn (array $o): bool => in_array($o['order_status'], ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW', 'WAITING_GATE_IN'], true)
        ));

        $waitingReceiptOrSort = array_values(array_filter(
            $orders,
            static fn (array $o): bool => in_array($o['order_status'], ['APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'RECEIVED', 'SORTING'], true)
        ));

        $activeWork = array_values(array_filter(
            $orders,
            static fn (array $o): bool => in_array($o['order_status'], ['PREPARING', 'IN_PROGRESS'], true)
        ));

        $readyOrCompleted = array_values(array_filter(
            $orders,
            static fn (array $o): bool => in_array($o['order_status'], ['READY', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'], true)
        ));

        $stats = [
            'needs_review' => count($needsReview),
            'waiting_receipt' => count($waitingReceiptOrSort),
            'active_work' => count($activeWork),
            'ready_or_completed' => count($readyOrCompleted),
        ];

        return view('warehouse.dashboard', [
            'stats' => $stats,
            'needsReview' => $needsReview,
            'waitingReceiptOrSort' => $waitingReceiptOrSort,
            'activeWork' => $activeWork,
            'readyOrCompleted' => $readyOrCompleted,
        ]);
    }

    public function inspectionsIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $transactionType = strtoupper(trim((string) $request->query('transaction_type', '')));
        $status = strtoupper(trim((string) $request->query('status', '')));

        $orders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static function (array $o) use ($search, $transactionType, $status): bool {
                $inQueue = in_array($o['order_status'], ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'RECEIVED', 'SORTING', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW', 'WAITING_GATE_IN'], true);
                if (! $inQueue) {
                    return false;
                }
                if ($transactionType !== '') {
                    $normalizedFilter = $transactionType === 'DRUM_SALE' ? 'WASTE_TRANSPORT' : $transactionType;
                    $normalizedOrder = $o['transaction_type'] === 'DRUM_SALE' ? 'WASTE_TRANSPORT' : $o['transaction_type'];
                    if ($normalizedOrder !== $normalizedFilter) {
                        return false;
                    }
                }
                if ($status !== '' && $o['order_status'] !== $status) {
                    return false;
                }
                if ($search !== '') {
                    $needle = mb_strtolower($search);
                    $haystack = mb_strtolower($o['order_number'].' '.$o['client_company'].' '.$o['product_name'].' '.$o['service_type'].' '.($o['po_number'] ?? ''));
                    if (! str_contains($haystack, $needle)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        return view('warehouse.inspections.index', [
            'orders' => $orders,
            'filters' => [
                'q' => $search,
                'transaction_type' => $transactionType,
                'status' => $status,
            ],
        ]);
    }

    public function processesIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $transactionType = strtoupper(trim((string) $request->query('transaction_type', '')));
        $serviceId = trim((string) $request->query('reconditioning_service_id', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));

        $orders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static function (array $o) use ($search, $transactionType, $serviceId, $status): bool {
                $inScope = in_array($o['order_status'], ['PREPARING', 'IN_PROGRESS', 'READY', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID'], true)
                    || in_array($o['warehouse_status'], ['PREPARING', 'IN_PROGRESS', 'COMPLETED'], true);
                if (! $inScope) {
                    return false;
                }
                if ($transactionType !== '') {
                    $normalizedFilter = $transactionType === 'DRUM_SALE' ? 'WASTE_TRANSPORT' : $transactionType;
                    $normalizedOrder = $o['transaction_type'] === 'DRUM_SALE' ? 'WASTE_TRANSPORT' : $o['transaction_type'];
                    if ($normalizedOrder !== $normalizedFilter) {
                        return false;
                    }
                }
                if ($serviceId !== '' && (string) ($o['reconditioning_service_id'] ?? '') !== $serviceId) {
                    return false;
                }
                if ($status !== '' && $o['order_status'] !== $status) {
                    return false;
                }
                if ($search !== '') {
                    $needle = mb_strtolower($search);
                    $haystack = mb_strtolower($o['order_number'].' '.$o['client_company'].' '.$o['product_name'].' '.$o['service_type']);
                    if (! str_contains($haystack, $needle)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        return view('warehouse.processes.index', [
            'orders' => $orders,
            'reconditioningServices' => ReconditioningService::active()->get(),
            'filters' => [
                'q' => $search,
                'transaction_type' => $transactionType,
                'reconditioning_service_id' => $serviceId,
                'status' => $status,
            ],
        ]);
    }

    public function orderShow(Request $request, string $code): View
    {
        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $dbOrder, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('view', $dbOrder);

        $order = OrderPresenter::toArray($dbOrder);
        $presetKey = $order['is_waste_transport'] ? 'Pengangkutan Limbah' : ($order['reconditioning_service_name'] ?? 'Laundry');
        $stagePresets = config("pkdi.reconditioning_stage_presets.{$presetKey}", config('pkdi.reconditioning_stage_presets.Laundry', []));

        return view('warehouse.orders.show', [
            'order' => $order,
            'reconditioningServices' => ReconditioningService::active()->get(),
            'stagePresets' => $stagePresets,
        ]);
    }

    public function transportStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        if (! $request->filled('transport_status') && $request->filled('transport_action')) {
            $request->merge([
                'transport_status' => strtoupper((string) $request->input('transport_action')) === 'MARK_IN_TRANSIT'
                    ? 'IN_TRANSIT'
                    : 'TRANSPORT_SCHEDULED',
            ]);
        }

        $validated = $request->validate([
            'transport_status' => ['required', 'string', 'in:TRANSPORT_SCHEDULED,IN_TRANSIT'],
            'scheduled_pickup_date' => ['nullable', 'date'],
            'transporter_name' => ['nullable', 'string', 'max:150'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'license_plate' => ['nullable', 'string', 'max:25'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'driver_phone' => ['nullable', 'string', 'max:40'],
            'transport_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $workflow->scheduleOrUpdateTransport(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            request: $request,
        );

        $msg = $updated->order_status === 'IN_TRANSIT'
            ? "Status pengangkutan {$updated->order_number} diperbarui menjadi Dalam Perjalanan menuju PKDI."
            : "Jadwal pengangkutan {$updated->order_number} berhasil disimpan.";

        return redirect()
            ->route('warehouse.orders.show', $updated->order_slug)
            ->with('status', $msg);
    }

    public function reviewStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:ACCEPT,NEEDS_CLARIFICATION,REJECT'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $workflow->reviewOrder(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            request: $request,
        );

        $msg = match ($updated->order_status) {
            'PREPARING' => "Pesanan penjualan {$updated->order_number} diterima dan masuk tahap persiapan produk.",
            'WAITING_RECEIPT' => "Pesanan rekondisi {$updated->order_number} diterima. Menunggu pencatatan penerimaan fisik drum.",
            'NEEDS_CLARIFICATION' => "Permintaan klarifikasi untuk pesanan {$updated->order_number} telah dikirim ke Client.",
            default => "Pesanan {$updated->order_number} telah ditolak dengan alasan tercatat.",
        };

        return redirect()
            ->route('warehouse.orders.show', $updated->order_slug)
            ->with('status', $msg);
    }

    public function receiptStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        $validated = $request->validate([
            'actual_received_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'received_at' => ['nullable', 'date'],
            'initial_condition' => ['required', 'string', 'min:5', 'max:2000'],
            'discrepancy_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'actual_received_quantity.required' => 'Jumlah fisik drum yang diterima wajib diisi.',
            'actual_received_quantity.min' => 'Jumlah fisik diterima minimal 1.',
            'initial_condition.required' => 'Kondisi awal fisik drum saat diterima wajib diisi.',
            'initial_condition.min' => 'Deskripsi kondisi awal minimal 5 karakter.',
            'photo.mimes' => 'Foto penerimaan harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto penerimaan maksimal 5 MB.',
        ]);

        $receipt = $workflow->recordReceipt(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            photo: $request->file('photo'),
            request: $request,
        );

        return redirect()
            ->route('warehouse.orders.show', $order->order_slug)
            ->with('status', "Penerimaan fisik {$receipt->receipt_number} ({$receipt->actual_received_quantity} {$receipt->unit}) berhasil dicatat. Lanjutkan ke pemeriksaan dan penyortiran.");
    }

    public function sortingStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        $validated = $request->validate([
            'actual_cargo_type' => ['nullable', 'string', 'max:150'],
            'actual_quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'qty_passed' => ['required', 'integer', 'min:0', 'max:100000'],
            'qty_special_repair' => ['required', 'integer', 'min:0', 'max:100000'],
            'qty_rejected' => ['required', 'integer', 'min:0', 'max:100000'],
            'cargo_condition' => ['required', 'string', 'min:5', 'max:2000'],
            'sorting_notes' => ['nullable', 'string', 'max:2000'],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'qty_passed.required' => 'Jumlah drum layak proses wajib diisi.',
            'qty_special_repair.required' => 'Jumlah drum penanganan khusus wajib diisi (isi 0 bila tidak ada).',
            'qty_rejected.required' => 'Jumlah drum tidak layak wajib diisi (isi 0 bila tidak ada).',
            'cargo_condition.required' => 'Ringkasan kondisi fisik hasil pemeriksaan wajib diisi.',
            'photo.mimes' => 'Foto penyortiran harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto penyortiran maksimal 5 MB.',
        ]);

        $res = $workflow->recordSortingAndInspection(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            photo: $request->file('photo'),
            request: $request,
        );

        $processable = $res['sorting']->processableQuantity();

        return redirect()
            ->route('warehouse.orders.show', $order->order_slug)
            ->with('status', "Pemeriksaan & penyortiran selesai ({$processable} {$res['sorting']->unit} layak diproses). Pesanan kini berstatus Sedang Diproses.");
    }

    public function inspectStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:APPROVED,NEEDS_CLARIFICATION,REJECTED'],
            'actual_cargo_type' => ['required_if:decision,APPROVED', 'nullable', 'string', 'max:150'],
            'actual_quantity' => ['required_if:decision,APPROVED', 'nullable', 'integer', 'min:1', 'max:100000'],
            'actual_unit' => ['required_if:decision,APPROVED', 'nullable', 'string', 'max:30'],
            'cargo_condition' => ['required_if:decision,APPROVED', 'nullable', 'string', 'max:2000'],
            'discrepancy_notes' => ['nullable', 'string', 'max:2000'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'actual_cargo_type.required_if' => 'Jenis muatan aktual wajib diisi.',
            'actual_quantity.required_if' => 'Jumlah aktual wajib diisi.',
            'actual_quantity.min' => 'Jumlah aktual minimal 1.',
            'actual_unit.required_if' => 'Satuan muatan wajib diisi.',
            'cargo_condition.required_if' => 'Kondisi fisik drum saat diterima wajib diisi.',
        ]);

        $inspection = $workflow->inspectWarehouse(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            photo: $request->file('photo'),
            request: $request,
        );

        $msg = match ($inspection->decision) {
            'APPROVED' => "Pemeriksaan muatan pesanan {$order->order_number} disetujui. Status berubah menjadi Sedang Diproses Gudang.",
            'NEEDS_CLARIFICATION' => "Permintaan klarifikasi muatan untuk pesanan {$order->order_number} telah disimpan.",
            default => "Pekerjaan untuk pesanan {$order->order_number} telah ditolak dengan alasan tercatat.",
        };

        return redirect()
            ->route('warehouse.orders.show', $order->order_slug)
            ->with('status', $msg);
    }

    public function processStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        $validated = $request->validate([
            'process_stage' => ['required', 'string', 'min:3', 'max:100'],
            'progress_notes' => ['required', 'string', 'min:3', 'max:2000'],
            'processed_qty' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_visible_to_client' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'process_stage.required' => 'Tahap pengerjaan wajib diisi.',
            'progress_notes.required' => 'Catatan progres pengerjaan wajib diisi.',
            'photo.mimes' => 'Foto progres harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto progres maksimal 5 MB.',
        ]);

        $process = $workflow->recordProcess(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            photo: $request->file('photo'),
            request: $request,
        );

        return redirect()
            ->route('warehouse.orders.show', $order->order_slug)
            ->with('status', "Progres pengerjaan ({$process->process_stage}) berhasil dicatat.");
    }

    public function resultStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        $validated = $request->validate([
            'reconditioning_service_id' => ['nullable', 'integer', 'exists:reconditioning_services,id'],
            'actual_service_type' => ['nullable', 'string', 'min:3', 'max:150'],
            'completed_qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'rejected_qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'result_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'completed_qty.required' => 'Kuantitas selesai wajib diisi.',
            'photo.mimes' => 'Foto hasil akhir harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto hasil akhir maksimal 5 MB.',
        ]);

        $result = $workflow->completeService(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            photo: $request->file('photo'),
            request: $request,
        );

        return redirect()
            ->route('warehouse.orders.show', $order->order_slug)
            ->with('status', "Hasil pekerjaan selesai ({$result->completed_quantity} {$result->unit}) dan transaksi otomatis masuk antrean penagihan Keuangan.");
    }

    public function deliveryStore(Request $request, string $code, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);
        Gate::authorize('manageWarehouse', $order);

        if (! $request->filled('delivery_method')) {
            $request->merge(['delivery_method' => 'DIANTAR_PKDI']);
        }

        $validated = $request->validate([
            'delivery_action' => ['required', 'string', 'in:START_DELIVERY,MARK_COMPLETED'],
            'delivery_method' => ['required', 'string', 'in:DIANTAR_PKDI,DIAMBIL_CLIENT'],
            'delivered_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'license_plate' => ['nullable', 'string', 'max:25'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'delivered_quantity.required' => 'Jumlah drum dikirim wajib diisi.',
            'delivered_quantity.min' => 'Jumlah drum dikirim minimal 1.',
        ]);

        $updated = $workflow->recordDelivery(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            request: $request,
        );

        $msg = $updated->order_status === 'IN_DELIVERY'
            ? "Status pesanan {$updated->order_number} diperbarui menjadi Dalam Pengiriman."
            : "Serah terima pesanan {$updated->order_number} selesai dicatat. Status operasional kini Selesai.";

        return redirect()
            ->route('warehouse.orders.show', $updated->order_slug)
            ->with('status', $msg);
    }

    public function reportsIndex(Request $request, ReportExportService $exporter): View
    {
        $filters = [
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
            'transaction_type' => $request->query('transaction_type', ''),
            'reconditioning_service_id' => $request->query('reconditioning_service_id', ''),
            'status' => $request->query('status', ''),
            'q' => $request->query('q', ''),
        ];

        $dataset = $exporter->buildWarehouseReportDataset($filters);

        return view('warehouse.reports.index', [
            'dataset' => $dataset,
            'filters' => $filters,
            'reconditioningServices' => ReconditioningService::active()->get(),
        ]);
    }

    public function reportsExport(Request $request, ReportExportService $exporter): Response
    {
        $format = strtolower((string) $request->query('format', 'pdf'));
        $dataset = $exporter->buildWarehouseReportDataset([
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
            'transaction_type' => $request->query('transaction_type', ''),
            'reconditioning_service_id' => $request->query('reconditioning_service_id', ''),
            'status' => $request->query('status', ''),
            'q' => $request->query('q', ''),
        ]);

        if ($format === 'xlsx' || $format === 'excel') {
            return $exporter->exportWarehouseReportExcel($dataset);
        }

        return $exporter->exportWarehouseReportPdf($dataset);
    }

    public function deliveryOrdersIndex(Request $request): View
    {
        $orders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static fn (array $o): bool => in_array($o['order_status'], ['READY', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'], true)
        ));

        return view('warehouse.delivery-orders.index', ['orders' => $orders]);
    }

    public function deliveryOrderShow(Request $request, string $code): View
    {
        $dbOrder = $this->findOrderOrFail($code);
        Gate::authorize('view', $dbOrder);

        return view('warehouse.delivery-orders.show', ['order' => OrderPresenter::toArray($dbOrder)]);
    }

    public function deliveryOrderStore(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $gudang */
        $gudang = $request->user();
        $order = $this->findOrderOrFail($code);

        $validated = $request->validate([
            'cargo_summary' => ['nullable', 'string', 'max:2000'],
            'vehicle_plate' => ['nullable', 'string', 'max:25'],
            'license_plate' => ['nullable', 'string', 'max:25'],
            'vehicle_type' => ['nullable', 'string', 'max:80'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $do = $billing->issueDeliveryOrder(
            gudang: $gudang,
            order: $order,
            validated: $validated,
            request: $request,
        );

        return redirect()
            ->route('warehouse.delivery-orders.show', $order->order_slug)
            ->with('status', "Surat Jalan {$do->delivery_order_number} berhasil diterbitkan.");
    }

    private function findOrderOrFail(string $code): ServiceOrder
    {
        $order = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $order, 404, 'Pesanan tidak ditemukan.');

        return $order;
    }
}
