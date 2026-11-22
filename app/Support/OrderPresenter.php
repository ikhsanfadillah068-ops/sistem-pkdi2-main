<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\BillableTransaction;
use App\Models\CashbookEntry;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\OperationalExpense;
use App\Models\Payment;
use App\Models\ServiceOrder;
use App\Models\ServiceResult;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Models\VehicleGateLog;
use App\Models\WarehouseInspection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class OrderPresenter
{
    /**
     * Mengubah model ServiceOrder beserta relasinya menjadi struktur array
     * yang dikonsumsi oleh seluruh komponen dan view Blade lintas peran.
     *
     * @return array<string, mixed>
     */
    public static function toArray(ServiceOrder $order): array
    {
        $order->loadMissing([
            'clientCompany',
            'drumProduct',
            'reconditioningService',
            'gateLogs.checkedBy',
            'latestReceipt.receivedBy',
            'latestSorting.sortedBy',
            'latestInspection.inspectedBy',
            'processes.recordedBy',
            'result.completedBy',
            'billableTransaction.invoice.items.billableTransaction.serviceOrder',
            'billableTransaction.invoice.payments',
            'billableTransaction.invoice.billableTransactions.serviceOrder',
            'latestInvoice.items.billableTransaction.serviceOrder',
            'latestInvoice.payments',
            'latestInvoice.billableTransactions.serviceOrder',
            'latestDeliveryOrder.issuedBy',
            'statusHistories.changedBy',
        ]);

        $gateInLog = $order->gateLogs->where('check_type', 'GATE_IN')->sortByDesc('id')->first();
        $gateOutLog = $order->gateLogs->where('check_type', 'GATE_OUT')->sortByDesc('id')->first();
        $receipt = $order->latestReceipt;
        $sorting = $order->latestSorting;
        $inspection = $order->latestInspection;
        $result = $order->result;
        $billableTransaction = $order->billableTransaction;
        $invoice = $billableTransaction?->invoice ?? $order->latestInvoice;
        $deliveryOrder = $order->latestDeliveryOrder;

        $viewerIsClient = auth()->user()?->isClient() ?? false;

        $timeline = [];
        foreach ($order->statusHistories->sortByDesc('id') as $history) {
            if ($viewerIsClient && ! $history->is_visible_to_client) {
                continue;
            }
            $meta = PkdiDemoData::statusMeta($history->to_status);
            $tone = match ($meta['tone'] ?? 'neutral') {
                'danger' => 'danger',
                'success' => 'success',
                'info', 'soft' => 'primary',
                default => 'default',
            };

            $timeline[] = [
                'title' => $meta['label'],
                'time' => $history->created_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'datetime' => $history->created_at?->toIso8601String(),
                'description' => trim($history->notes ?: 'Status diperbarui.'),
                'tone' => $tone,
                'is_visible_to_client' => (bool) $history->is_visible_to_client,
            ];
        }

        if ($timeline === []) {
            $timeline[] = [
                'title' => 'Pesanan Diajukan',
                'time' => $order->submitted_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'datetime' => $order->submitted_at?->toIso8601String(),
                'description' => "{$order->origin_company_name} mengajukan layanan {$order->service_type} ({$order->requested_quantity} {$order->requested_unit}).",
                'tone' => 'primary',
            ];
        }

        $clientProgress = self::buildClientProgress(
            order: $order,
            gateInLog: $gateInLog,
            gateOutLog: $gateOutLog,
            inspection: $inspection,
            result: $result,
            invoice: $invoice,
            deliveryOrder: $deliveryOrder,
        );

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_slug' => $order->order_slug,
            'transaction_type' => $order->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
            'transaction_type_label' => $order->transactionTypeLabel(),
            'is_waste_transport' => $order->isWasteTransport(),
            'is_drum_sale' => $order->isWasteTransport(),
            'is_reconditioning' => $order->isReconditioning(),
            'drum_product_id' => $order->drum_product_id,
            'product_name' => $order->drumProduct?->name ?? $order->service_category ?? '-',
            'reconditioning_service_id' => $order->reconditioning_service_id,
            'reconditioning_service_name' => $order->reconditioningService?->name ?? ($order->isReconditioning() ? ($order->service_type ?? '-') : null),
            'client_company' => $order->clientCompany?->company_name ?? $order->origin_company_name,
            'origin_company' => $order->origin_company_name,
            'service_category' => $order->drumProduct?->name ?? $order->service_category ?? '-',
            'cargo_type' => $order->cargo_type ?? '-',
            'service_type' => $order->isWasteTransport()
                ? 'Pengangkutan Limbah'
                : ($order->reconditioningService?->name ?? $order->service_type ?? '-'),
            'requested_qty' => $order->requested_quantity ?? 0,
            'unit' => $order->requested_unit,
            'po_number' => $order->po_number,
            'po_attachment_path' => $order->po_attachment_path,
            'needed_date' => $order->needed_date?->format('d M Y'),
            'needed_date_raw' => $order->needed_date?->toDateString(),
            'scheduled_pickup_date' => $order->scheduled_pickup_date?->format('d M Y'),
            'scheduled_pickup_date_raw' => $order->scheduled_pickup_date?->toDateString(),
            'specification_notes' => $order->specification_notes,
            'transport_notes' => $order->transport_notes,
            'transporter_name' => $order->transporter_name,
            'vehicle_type' => $order->vehicle_type,
            'license_plate' => $order->license_plate,
            'driver_name' => $order->driver_name,
            'driver_phone' => $order->driver_phone,
            'in_transit_at' => $order->in_transit_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            'submitted_at' => $order->submitted_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            'submitted_at_raw' => $order->submitted_at?->timezone('Asia/Jakarta')->toDateString(),
            'order_status' => $order->order_status,
            'gate_status' => $order->gate_status,
            'warehouse_status' => $order->warehouse_status,
            'billing_status' => $order->billing_status ?? 'NOT_READY',
            'billing_status_meta' => config('pkdi.billing_statuses.'.($order->billing_status ?? 'NOT_READY'), ['label' => 'Belum Ditagihkan', 'tone' => 'neutral']),
            'payment_status' => $order->payment_status,
            'payment_status_meta' => config('pkdi.payment_statuses.'.($order->payment_status ?? 'UNPAID'), ['label' => 'Menunggu Bayar', 'tone' => 'warning']),
            'delivery_method' => $order->delivery_method,
            'delivery_method_label' => $order->delivery_method
                ? config("pkdi.delivery_methods.{$order->delivery_method}", $order->delivery_method)
                : null,
            'delivered_quantity' => $order->delivered_quantity,
            'delivered_at' => $order->delivered_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            'delivery_notes' => $order->delivery_notes,
            'client_notes' => $order->client_notes,
            'attachment_path' => $order->attachment_path,
            'progress' => $clientProgress,
            'warehouse_tracker' => self::buildWarehouseTracker($order),
            'receipt' => $receipt ? [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'received_at' => $receipt->received_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'actual_received_quantity' => $receipt->actual_received_quantity,
                'unit' => $receipt->unit,
                'initial_condition' => $receipt->initial_condition,
                'discrepancy_notes' => $receipt->discrepancy_notes,
                'photo_path' => $receipt->photo_path,
                'officer' => ($receipt->receivedBy?->name ?? 'Kepala Gudang').' (Kepala Gudang)',
            ] : null,
            'sorting' => $sorting ? [
                'id' => $sorting->id,
                'qty_passed' => $sorting->qty_passed,
                'qty_special_repair' => $sorting->qty_special_repair,
                'qty_rejected' => $sorting->qty_rejected,
                'total_sorted' => $sorting->totalSorted(),
                'processable_qty' => $sorting->processableQuantity(),
                'unit' => $sorting->unit,
                'sorting_notes' => $sorting->sorting_notes,
                'rejected_reason' => $sorting->rejected_reason,
                'photo_path' => $sorting->photo_path,
                'officer' => ($sorting->sortedBy?->name ?? 'Kepala Gudang').' (Kepala Gudang)',
                'sorted_at' => $sorting->sorted_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            ] : null,
            'billable_transaction' => $billableTransaction ? [
                'id' => $billableTransaction->id,
                'transaction_code' => $billableTransaction->transaction_code,
                'transaction_type' => $billableTransaction->transaction_type,
                'product_name' => $billableTransaction->product_name_snapshot,
                'service_name' => $billableTransaction->service_name_snapshot,
                'billable_quantity' => $billableTransaction->billable_quantity,
                'rejected_quantity' => $billableTransaction->rejected_quantity,
                'unit' => $billableTransaction->unit,
                'billing_status' => $billableTransaction->billing_status,
                'ready_at' => $billableTransaction->ready_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            ] : null,
            'gate_in' => $gateInLog ? [
                'actual_plate' => $gateInLog->actual_license_plate,
                'actual_vehicle' => $gateInLog->actual_vehicle_type,
                'actual_driver' => $gateInLog->actual_driver_name,
                'is_plate_matched' => $gateInLog->is_plate_matched,
                'is_vehicle_matched' => $gateInLog->is_vehicle_matched,
                'decision' => $gateInLog->decision,
                'reason' => $gateInLog->hold_or_note_reason ?: 'Sesuai.',
                'officer' => ($gateInLog->checkedBy?->name ?? 'Petugas Satpam').' (Satpam)',
                'time' => $gateInLog->checked_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            ] : null,
            'inspection' => $inspection ? [
                'actual_cargo_type' => $inspection->actual_cargo_type,
                'actual_quantity' => $inspection->actual_quantity,
                'actual_unit' => $inspection->actual_unit,
                'cargo_condition' => $inspection->cargo_condition,
                'discrepancy_notes' => $inspection->discrepancy_notes ?: 'Tidak ada selisih.',
                'decision' => $inspection->decision,
                'decision_reason' => $inspection->decision_reason,
                'photo_path' => $inspection->photo_path,
                'officer' => ($inspection->inspectedBy?->name ?? 'Kepala Gudang').' (Kepala Gudang)',
                'time' => $inspection->inspected_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            ] : null,
            'processes' => $order->processes
                ->filter(static fn ($proc): bool => ! $viewerIsClient || (bool) $proc->is_visible_to_client)
                ->map(static fn ($proc): array => [
                    'id' => $proc->id,
                    'stage' => $proc->process_stage,
                    'qty' => $proc->processed_qty ?? ($sorting?->processableQuantity() ?? $inspection?->actual_quantity ?? $order->requested_quantity ?? 0),
                    'note' => $proc->progress_notes,
                    'photo_path' => $proc->photo_path,
                    'is_visible_to_client' => (bool) $proc->is_visible_to_client,
                    'time' => $proc->recorded_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                    'officer' => $proc->recordedBy?->name ?? 'Kepala Gudang',
                ])->values()->all(),
            'result' => $result ? [
                'reconditioning_service_id' => $result->reconditioning_service_id,
                'actual_service_type' => $result->actual_service_type,
                'completed_qty' => $result->completed_quantity,
                'rejected_qty' => $result->unprocessed_or_rejected_quantity,
                'unit' => $result->unit,
                'notes' => $result->result_notes ?: '-',
                'photo_path' => $result->photo_path,
                'officer' => ($result->completedBy?->name ?? 'Kepala Gudang').' (Kepala Gudang)',
                'completed_at' => $result->completed_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            ] : null,
            'invoice' => $invoice ? self::invoiceToArray($invoice) : null,
            'delivery_order' => $deliveryOrder ? [
                'do_number' => $deliveryOrder->delivery_order_number,
                'status' => $deliveryOrder->status,
                'cargo_summary' => $deliveryOrder->cargo_summary,
                'quantity_released' => $deliveryOrder->quantity_released,
                'unit' => $deliveryOrder->unit,
                'vehicle_type' => $deliveryOrder->vehicle_type,
                'license_plate' => $deliveryOrder->license_plate,
                'driver_name' => $deliveryOrder->driver_name,
                'issued_by' => ($deliveryOrder->issuedBy?->name ?? 'Kepala Gudang').' (Kepala Gudang)',
                'issued_at' => $deliveryOrder->issued_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            ] : null,
            'timeline' => $timeline,
        ];
    }

    /**
     * @param  Collection<int, ServiceOrder>  $orders
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $orders): array
    {
        return $orders->map(static fn (ServiceOrder $order): array => self::toArray($order))->values()->all();
    }

    /**
     * Mengambil seluruh daftar pesanan dari database (bila sudah ada) atau fallback demo data.
     *
     * @return list<array<string, mixed>>
     */
    public static function allOrders(?User $viewer = null): array
    {
        $query = ServiceOrder::query()
            ->with([
                'clientCompany',
                'drumProduct',
                'reconditioningService',
                'gateLogs.checkedBy',
                'latestReceipt.receivedBy',
                'latestSorting.sortedBy',
                'latestInspection.inspectedBy',
                'processes.recordedBy',
                'result.completedBy',
                'billableTransaction.invoice.items.billableTransaction.serviceOrder',
                'billableTransaction.invoice.payments',
                'billableTransaction.invoice.billableTransactions.serviceOrder',
                'latestInvoice.items.billableTransaction.serviceOrder',
                'latestInvoice.payments',
                'latestInvoice.billableTransactions.serviceOrder',
                'latestDeliveryOrder.issuedBy',
                'statusHistories.changedBy',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        if ($viewer) {
            $query->visibleTo($viewer);
        }

        $dbOrders = $query->get();

        return self::collection($dbOrders);
    }

    /**
     * Memetakan model Invoice (satuan maupun gabungan multi-transaksi) ke array siap-tampil.
     *
     * @return array<string, mixed>
     */
    public static function invoiceToArray(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'clientCompany',
            'serviceOrder',
            'items.billableTransaction.serviceOrder',
            'billableTransactions.serviceOrder',
            'payments',
        ]);

        $orderNumbers = $invoice->billableTransactions
            ->map(static fn (BillableTransaction $tx): ?string => $tx->serviceOrder?->order_number)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($orderNumbers === [] && $invoice->serviceOrder) {
            $orderNumbers = [$invoice->serviceOrder->order_number];
        }

        $txCount = max(1, count($orderNumbers), $invoice->items->count());

        $hasPending = $invoice->payments->contains(static fn ($p): bool => $p->verification_status === 'PENDING_VERIFICATION');
        $hasRejected = $invoice->payments->contains(static fn ($p): bool => $p->verification_status === 'REJECTED');
        $latestRejectedNote = $invoice->payments
            ->where('verification_status', 'REJECTED')
            ->sortByDesc('id')
            ->first()?->verification_notes;

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_slug' => $invoice->invoice_slug,
            'revision_number' => $invoice->revision_number,
            'status' => $invoice->status,
            'client_company_id' => $invoice->client_company_id,
            'client_company' => $invoice->clientCompany?->company_name ?? ($invoice->serviceOrder?->origin_company_name ?? '-'),
            'client_npwp' => $invoice->clientCompany?->npwp,
            'client_nib' => $invoice->clientCompany?->nib_number,
            'primary_order_number' => $orderNumbers[0] ?? ($invoice->serviceOrder?->order_number ?? '-'),
            'primary_order_slug' => $invoice->serviceOrder?->order_slug ?? (isset($orderNumbers[0]) ? PkdiDemoData::orderSlug($orderNumbers[0]) : ''),
            'order_numbers' => $orderNumbers,
            'transaction_count' => $txCount,
            'is_combined' => count($orderNumbers) > 1 || $invoice->items->count() > 1,
            'issue_date' => $invoice->issue_date?->format('d M Y') ?? '-',
            'issue_date_raw' => $invoice->issue_date?->toDateString() ?? '',
            'due_date' => $invoice->due_date?->format('d M Y') ?? '-',
            'subtotal' => (float) $invoice->subtotal_amount,
            'adjustment' => (float) $invoice->adjustment_amount,
            'tax' => (float) $invoice->tax_amount,
            'total' => (float) $invoice->total_amount,
            'paid' => (float) $invoice->paid_amount,
            'balance' => (float) $invoice->balance_due,
            'payment_instructions' => $invoice->payment_instructions ?: 'Transfer ke Rekening Resmi PT Pulo Kencana Drum Industri (BCA / Mandiri) dengan mencantumkan nomor invoice.',
            'notes' => $invoice->notes,
            'has_pending_verification' => $hasPending,
            'has_rejected_payment' => $hasRejected,
            'latest_rejected_note' => $latestRejectedNote,
            'items' => $invoice->items->map(static fn ($item): array => [
                'id' => $item->id,
                'billable_transaction_id' => $item->billable_transaction_id,
                'transaction_code' => $item->billableTransaction?->transaction_code,
                'order_number' => $item->billableTransaction?->serviceOrder?->order_number ?? $invoice->serviceOrder?->order_number,
                'transaction_type' => $item->transaction_type ?? $item->billableTransaction?->transaction_type,
                'transaction_type_label' => config('pkdi.transaction_types.'.($item->transaction_type ?? $item->billableTransaction?->transaction_type ?? ''), ''),
                'description' => $item->description,
                'qty' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->line_total,
            ])->values()->all(),
            'payments' => $invoice->payments->map(static function ($pay): array {
                $path = (string) ($pay->proof_file_path ?? '');
                $hasStoredFile = $path !== '' && Storage::disk('local')->exists($path);

                return [
                    'id' => $pay->id,
                    'number' => $pay->payment_number,
                    'date' => $pay->payment_date?->format('d M Y') ?? '-',
                    'method' => $pay->payment_method,
                    'reference' => $pay->reference_number ?? '-',
                    'amount' => (float) $pay->amount,
                    'status' => $pay->verification_status,
                    'proof' => $path !== '' ? basename($path) : '-',
                    'has_file' => $hasStoredFile,
                    'notes' => $pay->verification_notes,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Mengambil seluruh daftar Invoice unik (mendukung satuan maupun gabungan multi-transaksi).
     *
     * @return list<array<string, mixed>>
     */
    public static function allInvoices(?User $viewer = null, bool $excludeDraft = false): array
    {
        $query = Invoice::query()
            ->with([
                'clientCompany',
                'serviceOrder',
                'items.billableTransaction.serviceOrder',
                'billableTransactions.serviceOrder',
                'payments',
            ])
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($viewer) {
            $query->visibleTo($viewer);
        }
        if ($excludeDraft) {
            $query->where('status', '!=', 'DRAFT');
        }

        return $query->get()
            ->map(static fn (Invoice $inv): array => self::invoiceToArray($inv))
            ->values()
            ->all();
    }

    /**
     * Mengambil daftar antrean BillableTransaction untuk modul Keuangan dengan filter PRD 2.0.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public static function billableTransactions(array $filters = []): array
    {
        $query = BillableTransaction::query()
            ->with(['serviceOrder', 'clientCompany', 'drumProduct', 'reconditioningService', 'invoice'])
            ->orderByDesc('ready_at')
            ->orderByDesc('id');

        $status = strtoupper(trim((string) ($filters['billing_status'] ?? 'READY_TO_BILL')));
        if ($status !== '' && $status !== 'ALL') {
            $query->where('billing_status', $status);
        }

        if (! empty($filters['client_company_id'])) {
            $query->where('client_company_id', (int) $filters['client_company_id']);
        }
        if (! empty($filters['transaction_type'])) {
            $typeFilter = strtoupper(trim((string) $filters['transaction_type']));
            $normalizedType = $typeFilter === 'DRUM_SALE' ? ServiceOrder::TYPE_WASTE_TRANSPORT : $typeFilter;
            $query->whereIn('transaction_type', $normalizedType === ServiceOrder::TYPE_WASTE_TRANSPORT ? ['WASTE_TRANSPORT', 'DRUM_SALE'] : [$normalizedType]);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('ready_at', '>=', (string) $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('ready_at', '<=', (string) $filters['to']);
        }
        if (! empty($filters['q'])) {
            $search = trim((string) $filters['q']);
            $query->where(function ($q) use ($search): void {
                $q->where('transaction_code', 'like', "%{$search}%")
                    ->orWhere('product_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('service_name_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('serviceOrder', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('clientCompany', fn ($cq) => $cq->where('company_name', 'like', "%{$search}%"));
            });
        }

        $tariffs = ServiceTariff::query()->where('is_active', true)->get();

        return $query->get()->map(static function (BillableTransaction $tx) use ($tariffs): array {
            $matchedTariff = $tariffs->first(static function (ServiceTariff $t) use ($tx): bool {
                $svcMatch = mb_strtolower($t->service_type) === mb_strtolower((string) $tx->service_name_snapshot);
                $catMatch = $t->service_category === null || mb_strtolower($t->service_category) === mb_strtolower($tx->product_name_snapshot);

                return $svcMatch && $catMatch;
            }) ?? $tariffs->first(static fn (ServiceTariff $t): bool => mb_strtolower($t->service_type) === mb_strtolower((string) $tx->service_name_snapshot));

            $billingMeta = config("pkdi.billing_statuses.{$tx->billing_status}", ['label' => $tx->billing_status, 'tone' => 'neutral']);

            return [
                'id' => $tx->id,
                'transaction_code' => $tx->transaction_code,
                'service_order_id' => $tx->service_order_id,
                'order_number' => $tx->serviceOrder?->order_number ?? '-',
                'order_slug' => $tx->serviceOrder?->order_slug ?? '',
                'client_company_id' => $tx->client_company_id,
                'client_company' => $tx->clientCompany?->company_name ?? '-',
                'transaction_type' => $tx->transaction_type,
                'transaction_type_label' => config("pkdi.transaction_types.{$tx->transaction_type}", $tx->transaction_type),
                'product_name' => $tx->product_name_snapshot,
                'service_name' => $tx->service_name_snapshot ?? '-',
                'billable_quantity' => $tx->billable_quantity,
                'rejected_quantity' => $tx->rejected_quantity,
                'unit' => $tx->unit,
                'billing_status' => $tx->billing_status,
                'billing_status_meta' => $billingMeta,
                'invoice_id' => $tx->invoice_id,
                'invoice_number' => $tx->invoice?->invoice_number,
                'invoice_slug' => $tx->invoice?->invoice_slug,
                'ready_at' => $tx->ready_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'ready_at_raw' => $tx->ready_at?->timezone('Asia/Jakarta')->toDateString() ?? '',
                'notes' => $tx->notes,
                'suggested_tariff_id' => $matchedTariff?->id,
                'suggested_unit_price' => $matchedTariff ? (float) $matchedTariff->unit_price : null,
            ];
        })->values()->all();
    }

    /**
     * Ringkasan operasional lintas jenis transaksi & Gudang untuk Dashboard Owner dan Keuangan.
     *
     * @return array{
     *   total_orders: int,
     *   reconditioning_orders: int,
     *   drum_sale_orders: int,
     *   warehouse_active: int,
     *   warehouse_completed: int,
     *   ready_to_bill_count: int,
     *   pending_payment_verification: int
     * }
     */
    public static function operationalSummary(): array
    {
        $orders = ServiceOrder::query()->get(['transaction_type', 'order_status', 'warehouse_status', 'billing_status']);
        $readyToBill = BillableTransaction::query()->where('billing_status', BillableTransaction::STATUS_READY_TO_BILL)->whereNull('invoice_id')->count();
        $pendingPayments = Payment::query()->where('verification_status', 'PENDING_VERIFICATION')->count();

        return [
            'total_orders' => $orders->count(),
            'reconditioning_orders' => $orders->where('transaction_type', ServiceOrder::TYPE_RECONDITIONING)->count(),
            'waste_transport_orders' => $orders->whereIn('transaction_type', [ServiceOrder::TYPE_WASTE_TRANSPORT, 'DRUM_SALE'])->count(),
            'drum_sale_orders' => $orders->whereIn('transaction_type', [ServiceOrder::TYPE_WASTE_TRANSPORT, 'DRUM_SALE'])->count(),
            'warehouse_active' => $orders->whereIn('order_status', ['UNDER_REVIEW', 'APPROVED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'RECEIVED', 'SORTING', 'PREPARING', 'PROCESSING', 'IN_PROGRESS', 'NEEDS_CLARIFICATION'])->count(),
            'warehouse_completed' => $orders->where('warehouse_status', 'COMPLETED')->count(),
            'ready_to_bill_count' => $readyToBill,
            'pending_payment_verification' => $pendingPayments,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function gateLogs(): array
    {
        $logs = VehicleGateLog::query()
            ->with(['serviceOrder.clientCompany', 'checkedBy'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get();

        return $logs->map(static fn (VehicleGateLog $log): array => [
            'time' => $log->checked_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
            'type' => $log->check_type === 'GATE_OUT' ? 'Keluar' : 'Masuk',
            'order_number' => $log->serviceOrder?->order_number ?? '-',
            'company' => $log->serviceOrder?->clientCompany?->company_name ?? ($log->serviceOrder?->origin_company_name ?? '-'),
            'plate' => $log->actual_license_plate,
            'driver' => $log->actual_driver_name ?? '-',
            'decision' => $log->decision === 'APPROVED' ? 'Disetujui' : 'Ditahan',
            'tone' => $log->decision === 'APPROVED' ? 'success' : 'danger',
            'officer' => $log->checkedBy?->name ?? 'Satpam',
            'note' => $log->hold_or_note_reason ?: 'Sesuai pesanan',
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function auditLogs(): array
    {
        $logs = AuditLog::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $logs->map(static function (AuditLog $log): array {
            $roleLabel = config("pkdi.roles.{$log->user_role}.label", ucfirst($log->user_role));
            [$icon, $tile] = match ($log->user_role) {
                User::ROLE_SATPAM => str_contains($log->action, 'HELD')
                    ? ['alert-triangle', 'bg-danger-soft text-danger']
                    : ['shield', 'bg-success-soft text-success'],
                User::ROLE_KEPALA_GUDANG => str_contains($log->action, 'REJECT')
                    ? ['alert-triangle', 'bg-danger-soft text-danger']
                    : ['clipboard-check', 'bg-primary-soft text-primary'],
                User::ROLE_KEUANGAN => ['wallet', 'bg-success-soft text-success'],
                User::ROLE_CLIENT => ['package', 'bg-info-soft text-info'],
                default => ['history', 'bg-muted text-foreground'],
            };

            $newVals = is_array($log->new_values) ? $log->new_values : [];
            $entity = (string) ($newVals['order_number'] ?? $newVals['invoice_number'] ?? ($log->auditable_type.'#'.$log->auditable_id));
            $detail = (string) ($newVals['summary'] ?? $log->reason ?? "Aksi {$log->action} pada {$entity}");

            return [
                'time' => $log->created_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'user' => $log->user?->name ?? 'Sistem',
                'role' => $roleLabel,
                'icon' => $icon,
                'tile' => $tile,
                'action' => $log->action,
                'entity' => $entity,
                'detail' => $detail,
                'reason' => $log->reason ?: '-',
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function tariffs(): array
    {
        $tariffs = ServiceTariff::query()->orderBy('id')->get();

        return $tariffs->map(static fn (ServiceTariff $t): array => [
            'id' => $t->id,
            'code' => $t->tariff_code,
            'name' => $t->name,
            'service_category' => $t->service_category ?? '-',
            'raw_service_category' => $t->service_category ?? '',
            'cargo_type' => $t->cargo_type ?? '-',
            'service_type' => $t->service_type,
            'unit' => $t->unit,
            'price' => (float) $t->unit_price,
            'effective_from' => $t->effective_from?->format('d M Y') ?? '-',
            'active' => $t->is_active,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function expenses(): array
    {
        $expenses = OperationalExpense::query()->orderByDesc('expense_date')->get();

        return $expenses->map(static fn (OperationalExpense $e): array => [
            'number' => $e->expense_number,
            'date' => $e->expense_date?->format('d M Y') ?? '-',
            'category' => $e->expense_category,
            'description' => $e->description,
            'vendor' => $e->recipient_or_vendor ?? '-',
            'method' => $e->payment_method,
            'amount' => (float) $e->amount,
            'status' => $e->status === 'RECORDED' ? 'Tercatat' : 'Dibatalkan',
            'tone' => $e->status === 'RECORDED' ? 'success' : 'danger',
            'proof' => $e->receipt_file_path ?? '-',
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function cashbookEntries(): array
    {
        $entries = CashbookEntry::query()
            ->with(['payment', 'operationalExpense'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return $entries->map(static fn (CashbookEntry $c): array => [
            'entry_number' => $c->entry_number,
            'date' => $c->transaction_date?->format('d M Y') ?? '-',
            'raw_date' => $c->transaction_date?->toDateString() ?? '',
            'type' => $c->entry_type,
            'source' => $c->description,
            'reference' => $c->payment?->payment_number ?? ($c->operationalExpense?->expense_number ?? '-'),
            'amount' => (float) $c->amount,
        ])->values()->all();
    }

    /**
     * Menghitung ringkasan keuangan dari transaksi tersimpan di database (bukan hardcode).
     *
     * @return array{
     *   total_invoiced: float,
     *   total_paid: float,
     *   total_receivable: float,
     *   total_expenses: float,
     *   cash_in: float,
     *   cash_out: float,
     *   net_cash: float,
     *   invoice_count: int,
     *   unpaid_invoice_count: int,
     *   expense_count: int
     * }
     */
    public static function financialSummary(?string $from = null, ?string $to = null): array
    {
        $invQuery = Invoice::query()->whereNotIn('status', ['DRAFT', 'VOIDED']);
        $expQuery = OperationalExpense::query()->where('status', 'RECORDED');
        $cbInQuery = CashbookEntry::query()->where('status', 'ACTIVE')->where('entry_type', 'IN');
        $cbOutQuery = CashbookEntry::query()->where('status', 'ACTIVE')->where('entry_type', 'OUT');

        if ($from) {
            $invQuery->whereDate('issue_date', '>=', $from);
            $expQuery->whereDate('expense_date', '>=', $from);
            $cbInQuery->whereDate('transaction_date', '>=', $from);
            $cbOutQuery->whereDate('transaction_date', '>=', $from);
        }
        if ($to) {
            $invQuery->whereDate('issue_date', '<=', $to);
            $expQuery->whereDate('expense_date', '<=', $to);
            $cbInQuery->whereDate('transaction_date', '<=', $to);
            $cbOutQuery->whereDate('transaction_date', '<=', $to);
        }

        $totalInvoiced = (float) (clone $invQuery)->sum('total_amount');
        $totalPaid = (float) (clone $invQuery)->sum('paid_amount');
        $totalReceivable = (float) (clone $invQuery)->sum('balance_due');
        $invoiceCount = (int) (clone $invQuery)->count();
        $unpaidCount = (int) (clone $invQuery)->where('balance_due', '>', 0)->count();

        $totalExpenses = (float) (clone $expQuery)->sum('amount');
        $expenseCount = (int) (clone $expQuery)->count();

        $cashIn = (float) $cbInQuery->sum('amount');
        $cashOut = (float) $cbOutQuery->sum('amount');

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_receivable' => $totalReceivable,
            'total_expenses' => $totalExpenses,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash' => $cashIn - $cashOut,
            'invoice_count' => $invoiceCount,
            'unpaid_invoice_count' => $unpaidCount,
            'expense_count' => $expenseCount,
        ];
    }

    /**
     * Menghitung seri grafik bulanan (dalam satuan juta Rupiah) dari transaksi kas tersimpan.
     *
     * @return array{labels: list<string>, income: list<float>, expense: list<float>}
     */
    public static function monthlyTrend(int $months = 6): array
    {
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $entries = CashbookEntry::query()
            ->where('status', 'ACTIVE')
            ->get(['transaction_date', 'entry_type', 'amount']);

        $labels = [];
        $income = [];
        $expense = [];
        $now = now()->startOfMonth();

        for ($i = $months - 1; $i >= 0; $i--) {
            $cursor = $now->copy()->subMonths($i);
            $ym = $cursor->format('Y-m');
            $labels[] = $monthNames[(int) $cursor->format('n')] ?? $cursor->format('M');

            $monthIn = (float) $entries
                ->filter(static fn (CashbookEntry $c): bool => $c->entry_type === 'IN' && $c->transaction_date?->format('Y-m') === $ym)
                ->sum('amount');

            $monthOut = (float) $entries
                ->filter(static fn (CashbookEntry $c): bool => $c->entry_type === 'OUT' && $c->transaction_date?->format('Y-m') === $ym)
                ->sum('amount');

            $income[] = round($monthIn / 1000000, 2);
            $expense[] = round($monthOut / 1000000, 2);
        }

        return [
            'labels' => $labels,
            'income' => $income,
            'expense' => $expense,
        ];
    }

    /**
     * Menyusun pelacakan pesanan tingkat tinggi untuk Portal Client yang DIBEDAKAN
     * antara alur Pengangkutan Limbah (4 tahap) dan Pengangkutan & Rekondisi Drum (5 tahap).
     *
     * @return array<string, mixed>
     */
    private static function buildClientProgress(
        ServiceOrder $order,
        ?VehicleGateLog $gateInLog,
        ?VehicleGateLog $gateOutLog,
        ?WarehouseInspection $inspection,
        ?ServiceResult $result,
        ?Invoice $invoice,
        ?DeliveryOrder $deliveryOrder,
    ): array {
        $isWasteTransport = $order->isWasteTransport();
        $isCancelledOrRejected = in_array($order->order_status, ['CANCELLED', 'REJECTED'], true) || $order->warehouse_status === 'REJECTED';

        $stateLabelMap = [
            'done' => 'Selesai',
            'current' => 'Sedang berlangsung',
            'warning' => 'Perlu klarifikasi',
            'danger' => 'Dibatalkan atau ditolak',
            'todo' => 'Belum dimulai',
        ];

        $submittedState = match (true) {
            $isCancelledOrRejected => 'danger',
            $order->order_status === 'NEEDS_CLARIFICATION' => 'warning',
            in_array($order->order_status, ['SUBMITTED', 'UNDER_REVIEW', 'WAITING_GATE_IN', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW'], true) => 'current',
            default => 'done',
        };

        $receivedDoneStatuses = ['RECEIVED', 'SORTING', 'PROCESSING', 'IN_PROGRESS', 'PROCESS_COMPLETED', 'READY', 'READY_FOR_HANDOVER', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'];
        $transitCurrentStatuses = ['APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING'];

        $transitState = match (true) {
            in_array($order->order_status, $receivedDoneStatuses, true) || $order->latestReceipt !== null => 'done',
            in_array($order->order_status, $transitCurrentStatuses, true) => 'current',
            default => 'todo',
        };

        $finalDoneStatuses = ['READY', 'READY_FOR_HANDOVER', 'PROCESS_COMPLETED', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'];

        if ($isWasteTransport) {
            // ALUR 1: PENGANGKUTAN LIMBAH (4 TAHAP TINGKAT TINGGI)
            $receivedState = match (true) {
                $order->warehouse_status === 'COMPLETED' || in_array($order->order_status, $finalDoneStatuses, true) => 'done',
                in_array($order->order_status, ['RECEIVED', 'SORTING', 'IN_PROGRESS', 'PROCESSING'], true) || $order->latestReceipt !== null => 'current',
                default => 'todo',
            };

            $completedState = match (true) {
                $order->warehouse_status === 'COMPLETED' || in_array($order->order_status, $finalDoneStatuses, true) => 'done',
                default => 'todo',
            };

            $steps = [
                [
                    'key' => 'submitted',
                    'number' => 1,
                    'label' => 'Diajukan & Ditinjau',
                    'short_label' => 'Diajukan',
                    'icon' => 'package',
                    'state' => $submittedState,
                    'state_text' => $stateLabelMap[$submittedState],
                    'time' => $order->submitted_at?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
                [
                    'key' => 'transit',
                    'number' => 2,
                    'label' => 'Disetujui & Dalam Perjalanan',
                    'short_label' => 'Perjalanan',
                    'icon' => 'truck',
                    'state' => $transitState,
                    'state_text' => $stateLabelMap[$transitState],
                    'time' => ($order->in_transit_at ?? $order->scheduled_pickup_date)?->format('d M'),
                ],
                [
                    'key' => 'received',
                    'number' => 3,
                    'label' => 'Diterima di PKDI',
                    'short_label' => 'Diterima PKDI',
                    'icon' => 'warehouse',
                    'state' => $receivedState,
                    'state_text' => $stateLabelMap[$receivedState],
                    'time' => ($order->latestReceipt?->received_at ?? $inspection?->inspected_at)?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
                [
                    'key' => 'completed',
                    'number' => 4,
                    'label' => 'Layanan Selesai',
                    'short_label' => 'Selesai',
                    'icon' => 'check-circle',
                    'state' => $completedState,
                    'state_text' => $stateLabelMap[$completedState],
                    'time' => ($result?->completed_at ?? $order->completed_at)?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
            ];
        } else {
            // ALUR 2: REKONDISI DRUM (5 TAHAP - TANPA TAHAP PENGANGKUTAN LIMBAH)
            $recondStartStatuses = ['PROCESSING', 'IN_PROGRESS', 'PROCESS_COMPLETED', 'READY', 'READY_FOR_HANDOVER', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'];

            $receivedState = match (true) {
                in_array($order->order_status, $receivedDoneStatuses, true) || $order->latestReceipt !== null || $inspection !== null => 'done',
                in_array($order->order_status, $transitCurrentStatuses, true) => 'current',
                default => 'todo',
            };

            $sortingState = match (true) {
                in_array($order->order_status, $recondStartStatuses, true) || $order->latestSorting !== null || $inspection !== null => 'done',
                in_array($order->order_status, ['RECEIVED', 'SORTING'], true) || $order->latestReceipt !== null => 'current',
                default => 'todo',
            };

            $recondState = match (true) {
                $order->warehouse_status === 'COMPLETED' || in_array($order->order_status, $finalDoneStatuses, true) => 'done',
                in_array($order->order_status, ['PROCESSING', 'IN_PROGRESS'], true) => 'current',
                default => 'todo',
            };

            $completedState = match (true) {
                in_array($order->order_status, ['DELIVERED', 'COMPLETED', 'PAID'], true) => 'done',
                in_array($order->order_status, ['READY', 'READY_FOR_HANDOVER', 'PROCESS_COMPLETED', 'SERVICE_COMPLETED', 'IN_DELIVERY', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL'], true) => 'current',
                default => 'todo',
            };

            $steps = [
                [
                    'key' => 'submitted',
                    'number' => 1,
                    'label' => 'Diajukan & Ditinjau',
                    'short_label' => 'Diajukan',
                    'icon' => 'package',
                    'state' => $submittedState,
                    'state_text' => $stateLabelMap[$submittedState],
                    'time' => $order->submitted_at?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
                [
                    'key' => 'received',
                    'number' => 2,
                    'label' => 'Diterima di Gudang',
                    'short_label' => 'Diterima',
                    'icon' => 'warehouse',
                    'state' => $receivedState,
                    'state_text' => $stateLabelMap[$receivedState],
                    'time' => ($order->latestReceipt?->received_at ?? $inspection?->inspected_at)?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
                [
                    'key' => 'sorting',
                    'number' => 3,
                    'label' => 'Pemeriksaan & Sortir',
                    'short_label' => 'Sortir',
                    'icon' => 'clipboard-check',
                    'state' => $sortingState,
                    'state_text' => $stateLabelMap[$sortingState],
                    'time' => ($order->latestSorting?->sorted_at ?? $inspection?->inspected_at)?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
                [
                    'key' => 'reconditioning',
                    'number' => 4,
                    'label' => 'Proses Rekondisi',
                    'short_label' => 'Rekondisi',
                    'icon' => 'sliders',
                    'state' => $recondState,
                    'state_text' => $stateLabelMap[$recondState],
                    'time' => $result?->completed_at?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
                [
                    'key' => 'completed',
                    'number' => 5,
                    'label' => 'Rekondisi Selesai & Siap Serah',
                    'short_label' => 'Selesai',
                    'icon' => 'check-circle',
                    'state' => $completedState,
                    'state_text' => $stateLabelMap[$completedState],
                    'time' => ($order->completed_at ?? $order->delivered_at ?? $result?->completed_at)?->timezone('Asia/Jakarta')->format('d M, H.i'),
                ],
            ];
        }

        $activeStep = 1;
        foreach ($steps as $step) {
            if ($step['state'] !== 'todo') {
                $activeStep = $step['number'];
            }
        }

        $latestHistoryAt = $order->statusHistories->max('created_at') ?? $order->updated_at ?? $order->submitted_at;

        [$headline, $summary, $issueAlert] = match ($order->order_status) {
            'SUBMITTED', 'UNDER_REVIEW', 'WAITING_GATE_IN', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW' => [
                'Permintaan Sedang Ditinjau Kepala Gudang',
                "Permintaan {$order->transactionTypeLabel()} sedang diperiksa kelayakan operasionalnya oleh Kepala Gudang.",
                null,
            ],
            'NEEDS_CLARIFICATION' => [
                'Menunggu Klarifikasi Permintaan',
                $inspection?->decision_reason ?: 'Kepala Gudang meminta konfirmasi atas spesifikasi atau kuantitas permintaan.',
                [
                    'variant' => 'warning',
                    'title' => 'Klarifikasi Diperlukan',
                    'text' => $inspection?->decision_reason ?: 'Mohon konfirmasi kesesuaian permintaan dengan pihak operasional PKDI.',
                ],
            ],
            'APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'PREPARING' => [
                $order->order_status === 'TRANSPORT_SCHEDULED' ? 'Pengangkutan Telah Dijadwalkan' : 'Permintaan Disetujui untuk Ditangani',
                $order->scheduled_pickup_date
                    ? 'Jadwal pengangkutan ditetapkan pada '.$order->scheduled_pickup_date->format('d M Y').($order->license_plate ? " (Armada {$order->license_plate})." : '.')
                    : 'Permintaan telah disetujui Kepala Gudang dan menunggu jadwal pengangkutan atau kedatangan fisik di PKDI.',
                null,
            ],
            'IN_TRANSIT' => [
                'Armada Pengangkutan Dalam Perjalanan',
                'Limbah / kemasan sedang dalam perjalanan menuju fasilitas Gudang PKDI'.($order->license_plate ? " dengan armada {$order->license_plate}." : '.'),
                null,
            ],
            'RECEIVED', 'SORTING' => [
                'Barang / Limbah Telah Diterima di PKDI',
                $isWasteTransport
                    ? 'Kepala Gudang telah mencatat penerimaan fisik limbah/kemasan di fasilitas PKDI.'
                    : 'Kepala Gudang telah menerima fisik drum/kemasan dan melakukan pemeriksaan awal sebelum rekondisi.',
                null,
            ],
            'PROCESSING', 'IN_PROGRESS' => [
                'Dalam Penanganan Rekondisi Drum',
                "Drum sedang ditangani pada paket layanan {$order->service_type} di fasilitas PKDI.",
                null,
            ],
            'PROCESS_COMPLETED', 'READY', 'READY_FOR_HANDOVER', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT' => [
                $isWasteTransport ? 'Layanan Pengangkutan & Penerimaan Selesai' : 'Rekondisi Selesai dan Siap Diserahkan',
                $result
                    ? "Hasil tercatat: {$result->completed_quantity} {$result->unit} selesai ditangani."
                    : 'Tahap operasional layanan di Gudang PKDI telah selesai.',
                null,
            ],
            'IN_DELIVERY' => [
                'Dalam Pengiriman Kembali ke Client',
                'Drum hasil rekondisi sedang dalam perjalanan menuju lokasi Client.',
                null,
            ],
            'DELIVERED', 'COMPLETED' => [
                'Layanan Selesai Sepenuhnya',
                'Seluruh rangkaian operasional permintaan layanan telah selesai.',
                null,
            ],
            'REJECTED', 'CANCELLED' => [
                'Permintaan Ditolak / Dibatalkan',
                $order->cancellation_reason ?: 'Permintaan tidak dapat ditangani berdasarkan hasil peninjauan operasional.',
                [
                    'variant' => 'danger',
                    'title' => 'Permintaan Dibatalkan',
                    'text' => $order->cancellation_reason ?: 'Permintaan tidak dapat diproses lebih lanjut.',
                ],
            ],
            default => [
                PkdiDemoData::statusMeta($order->order_status)['label'],
                'Status permintaan diperbarui oleh sistem operasional PKDI.',
                null,
            ],
        };

        return [
            'track_variant' => $isWasteTransport ? 'WASTE_TRANSPORT' : 'RECONDITIONING',
            'track_title' => $isWasteTransport
                ? 'Pengangkutan Limbah'
                : 'Rekondisi Drum',
            'steps' => $steps,
            'active_step' => $activeStep,
            'total_steps' => count($steps),
            'headline' => $headline,
            'summary' => $summary,
            'issue_alert' => $issueAlert,
            'updated_at' => $latestHistoryAt?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
        ];
    }

    /**
     * Menyusun tracker tahapan operasional internal Kepala Gudang yang dibedakan
     * antara alur Pengangkutan Limbah (4 tahap) dan Rekondisi Drum (6 tahap).
     *
     * @return array{variant: string, title: string, steps: list<array{number: int, label: string, state: string}>}
     */
    private static function buildWarehouseTracker(ServiceOrder $order): array
    {
        $isWasteTransport = $order->isWasteTransport();
        $st = $order->order_status;
        $hasReceipt = $order->latestReceipt !== null || in_array($st, ['RECEIVED', 'SORTING', 'PROCESSING', 'IN_PROGRESS', 'PROCESS_COMPLETED', 'READY', 'READY_FOR_HANDOVER', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID'], true);
        $hasSorting = $order->latestSorting !== null || $order->latestInspection !== null || in_array($st, ['PROCESSING', 'IN_PROGRESS', 'PROCESS_COMPLETED', 'READY', 'READY_FOR_HANDOVER', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID'], true);
        $hasResult = $order->result !== null || $order->warehouse_status === 'COMPLETED' || in_array($st, ['PROCESS_COMPLETED', 'READY', 'READY_FOR_HANDOVER', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID'], true);
        $isReviewDone = ! in_array($st, ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW', 'WAITING_GATE_IN', 'CANCELLED', 'REJECTED'], true);

        if ($isWasteTransport) {
            return [
                'variant' => 'WASTE_TRANSPORT',
                'title' => 'Pengangkutan Limbah',
                'steps' => [
                    [
                        'number' => 1,
                        'label' => 'Tinjau Permintaan',
                        'state' => $isReviewDone ? 'done' : 'current',
                    ],
                    [
                        'number' => 2,
                        'label' => 'Jadwal & Perjalanan',
                        'state' => $hasReceipt ? 'done' : ($isReviewDone ? 'current' : 'todo'),
                    ],
                    [
                        'number' => 3,
                        'label' => 'Penerimaan Fisik Limbah',
                        'state' => $hasResult ? 'done' : ($hasReceipt ? 'current' : 'todo'),
                    ],
                    [
                        'number' => 4,
                        'label' => 'Konfirmasi Hasil & Siap Tagih',
                        'state' => in_array($st, ['COMPLETED', 'DELIVERED'], true) || $hasResult ? 'done' : 'todo',
                    ],
                ],
            ];
        }

        return [
            'variant' => 'RECONDITIONING',
            'title' => 'Rekondisi Drum',
            'steps' => [
                [
                    'number' => 1,
                    'label' => 'Tinjau Permintaan',
                    'state' => $isReviewDone ? 'done' : 'current',
                ],
                [
                    'number' => 2,
                    'label' => 'Penerimaan Fisik Drum',
                    'state' => $hasReceipt ? 'done' : ($isReviewDone ? 'current' : 'todo'),
                ],
                [
                    'number' => 3,
                    'label' => 'Penyortiran Drum',
                    'state' => $hasSorting ? 'done' : ($hasReceipt ? 'current' : 'todo'),
                ],
                [
                    'number' => 4,
                    'label' => 'Proses Rekondisi',
                    'state' => $hasResult ? 'done' : (in_array($st, ['IN_PROGRESS', 'PROCESSING'], true) ? 'current' : 'todo'),
                ],
                [
                    'number' => 5,
                    'label' => 'Hasil Akhir & Serah Terima',
                    'state' => in_array($st, ['COMPLETED', 'DELIVERED'], true) ? 'done' : ($hasResult ? 'current' : 'todo'),
                ],
            ],
        ];
    }
}
