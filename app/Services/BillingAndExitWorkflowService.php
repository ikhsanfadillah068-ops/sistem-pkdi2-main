<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BillableTransaction;
use App\Models\CashbookEntry;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OperationalExpense;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ServiceOrder;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Models\VehicleGateLog;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BillingAndExitWorkflowService
{
    /**
     * 1. Keuangan menambah referensi tarif jasa.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createTariff(User $keuangan, array $validated, ?Request $request = null): ServiceTariff
    {
        abort_unless($keuangan->hasRole(User::ROLE_KEUANGAN) && $keuangan->is_active, 403);

        return DB::transaction(function () use ($keuangan, $validated, $request): ServiceTariff {
            $seq = ServiceTariff::query()->lockForUpdate()->count() + 1;
            $code = isset($validated['tariff_code']) && trim((string) $validated['tariff_code']) !== ''
                ? strtoupper(trim((string) $validated['tariff_code']))
                : sprintf('TRF-PKDI-%03d', $seq);

            while (ServiceTariff::query()->where('tariff_code', $code)->exists()) {
                $seq++;
                $code = sprintf('TRF-PKDI-%03d', $seq);
            }

            $tariff = ServiceTariff::query()->create([
                'tariff_code' => $code,
                'name' => trim((string) $validated['name']),
                'service_category' => isset($validated['service_category']) && trim((string) $validated['service_category']) !== ''
                    ? trim((string) $validated['service_category'])
                    : null,
                'cargo_type' => isset($validated['cargo_type']) && trim((string) $validated['cargo_type']) !== ''
                    ? trim((string) $validated['cargo_type'])
                    : null,
                'service_type' => trim((string) $validated['service_type']),
                'unit' => trim((string) ($validated['unit'] ?? 'Drum')),
                'unit_price' => (float) $validated['unit_price'],
                'effective_from' => $validated['effective_from'] ?? now()->toDateString(),
                'is_active' => true,
                'created_by_user_id' => $keuangan->id,
            ]);

            AuditLogger::record(
                action: 'TARIFF_CREATED',
                auditable: $tariff,
                newValues: [
                    'tariff_code' => $tariff->tariff_code,
                    'name' => $tariff->name,
                    'unit_price' => (float) $tariff->unit_price,
                    'summary' => "Tarif baru {$tariff->tariff_code} ({$tariff->name}) disimpan oleh Keuangan.",
                ],
                actor: $keuangan,
                request: $request,
            );

            return $tariff;
        });
    }

    /**
     * 1b. Keuangan memperbarui referensi tarif jasa.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateTariff(
        User $keuangan,
        ServiceTariff $tariff,
        array $validated,
        ?Request $request = null,
    ): ServiceTariff {
        abort_unless($keuangan->hasRole(User::ROLE_KEUANGAN) && $keuangan->is_active, 403);

        return DB::transaction(function () use ($keuangan, $tariff, $validated, $request): ServiceTariff {
            $oldValues = [
                'name' => $tariff->name,
                'service_category' => $tariff->service_category,
                'service_type' => $tariff->service_type,
                'unit' => $tariff->unit,
                'unit_price' => (float) $tariff->unit_price,
            ];

            $tariff->update([
                'name' => trim((string) $validated['name']),
                'service_category' => isset($validated['service_category']) && trim((string) $validated['service_category']) !== ''
                    ? trim((string) $validated['service_category'])
                    : null,
                'service_type' => trim((string) $validated['service_type']),
                'unit' => trim((string) ($validated['unit'] ?? 'Drum')),
                'unit_price' => (float) $validated['unit_price'],
            ]);

            AuditLogger::record(
                action: 'TARIFF_UPDATED',
                auditable: $tariff,
                oldValues: $oldValues,
                newValues: [
                    'tariff_code' => $tariff->tariff_code,
                    'name' => $tariff->name,
                    'unit_price' => (float) $tariff->unit_price,
                    'summary' => "Tarif {$tariff->tariff_code} ({$tariff->name}) diperbarui oleh Keuangan.",
                ],
                actor: $keuangan,
                request: $request,
            );

            return $tariff;
        });
    }

    /**
     * 1c. Keuangan menghapus referensi tarif jasa.
     */
    public function deleteTariff(
        User $keuangan,
        ServiceTariff $tariff,
        ?Request $request = null,
    ): void {
        abort_unless($keuangan->hasRole(User::ROLE_KEUANGAN) && $keuangan->is_active, 403);

        DB::transaction(function () use ($keuangan, $tariff, $request): void {
            $code = $tariff->tariff_code;
            $name = $tariff->name;
            $price = (float) $tariff->unit_price;

            InvoiceItem::query()->where('service_tariff_id', $tariff->id)->update(['service_tariff_id' => null]);

            AuditLogger::record(
                action: 'TARIFF_DELETED',
                auditable: $tariff,
                oldValues: [
                    'tariff_code' => $code,
                    'name' => $name,
                    'unit_price' => $price,
                ],
                newValues: [
                    'summary' => "Tarif {$code} ({$name}) dihapus oleh Keuangan.",
                ],
                actor: $keuangan,
                request: $request,
            );

            $tariff->delete();
        });
    }

    /**
     * 2. Keuangan membuat draft atau menerbitkan Invoice dari hasil kerja Kepala Gudang yang sah.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveOrIssueInvoice(
        User $keuangan,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): Invoice {
        Gate::forUser($keuangan)->authorize('manageBilling', $order);

        return DB::transaction(function () use ($keuangan, $order, $validated, $request): Invoice {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['result', 'latestInvoice'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOrder->result
                || $lockedOrder->warehouse_status !== 'COMPLETED'
                || (! in_array($lockedOrder->order_status, ['READY', 'IN_DELIVERY', 'DELIVERED', 'COMPLETED', 'WAITING_FINANCE', 'SERVICE_COMPLETED', 'INVOICE_ISSUED'], true)
                    && $lockedOrder->billing_status !== 'READY_TO_BILL')) {
                throw ValidationException::withMessages([
                    'order_status' => 'Invoice hanya dapat dibuat dari pesanan yang pekerjaan gudangnya telah selesai dan memiliki hasil kerja aktual.',
                ]);
            }

            $action = strtoupper(trim((string) ($validated['invoice_action'] ?? 'ISSUE')));
            $isDraft = $action === 'DRAFT';

            $tariffCodeOrId = $validated['tariff_id'] ?? null;
            $tariff = null;
            if ($tariffCodeOrId) {
                $tariff = ServiceTariff::query()
                    ->where('tariff_code', (string) $tariffCodeOrId)
                    ->orWhere('id', is_numeric($tariffCodeOrId) ? (int) $tariffCodeOrId : 0)
                    ->first();
            }

            $quantity = isset($validated['quantity']) && $validated['quantity'] !== ''
                ? (int) $validated['quantity']
                : $lockedOrder->result->completed_quantity;

            $unitPrice = isset($validated['unit_price']) && $validated['unit_price'] !== ''
                ? (float) $validated['unit_price']
                : (float) ($tariff?->unit_price ?? 0);

            $adjustment = isset($validated['adjustment_amount']) && $validated['adjustment_amount'] !== ''
                ? (float) $validated['adjustment_amount']
                : 0.0;

            $tax = isset($validated['tax_amount']) && $validated['tax_amount'] !== ''
                ? (float) $validated['tax_amount']
                : 0.0;

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Kuantitas yang ditagihkan harus lebih besar dari 0.',
                ]);
            }

            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal = $lineTotal;
            $total = round($subtotal - $adjustment + $tax, 2);

            if ($total <= 0 || $total > 999999999999) {
                throw ValidationException::withMessages([
                    'unit_price' => 'Total tagihan harus berada di antara Rp 1 dan Rp 999.999.999.999.',
                ]);
            }

            $description = isset($validated['description']) && trim((string) $validated['description']) !== ''
                ? trim((string) $validated['description'])
                : "Jasa {$lockedOrder->result->actual_service_type} - {$lockedOrder->service_category}";

            $existingInvoice = $lockedOrder->latestInvoice;
            if ($existingInvoice && $existingInvoice->status === 'VOIDED') {
                $existingInvoice = null;
            }
            if ($existingInvoice && in_array($existingInvoice->status, ['PAID', 'PARTIALLY_PAID'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Invoice untuk pesanan ini sudah memiliki pembayaran terverifikasi. Gunakan fitur Revisi Tagihan bila perlu koreksi.',
                ]);
            }

            if ($lockedOrder->billableTransaction
                && $lockedOrder->billableTransaction->billing_status === BillableTransaction::STATUS_INVOICED
                && $lockedOrder->billableTransaction->invoice_id !== null
                && $lockedOrder->billableTransaction->invoice_id !== $existingInvoice?->id) {
                throw ValidationException::withMessages([
                    'order_status' => 'Transaksi pesanan ini sudah masuk ke invoice resmi lain dan tidak dapat ditagihkan ulang.',
                ]);
            }

            if ($existingInvoice) {
                $invoiceNumber = $existingInvoice->invoice_number;
                $invoiceSlug = $existingInvoice->invoice_slug;
            } else {
                [$invoiceNumber, $invoiceSlug] = $this->generateUniqueInvoiceNumber();
            }

            $status = $isDraft ? 'DRAFT' : 'ISSUED';
            $issueDate = isset($validated['issue_date']) && $validated['issue_date'] !== ''
                ? (string) $validated['issue_date']
                : now('Asia/Jakarta')->toDateString();
            $dueDate = isset($validated['due_date']) && $validated['due_date'] !== ''
                ? (string) $validated['due_date']
                : now('Asia/Jakarta')->addDays(7)->toDateString();

            $invoice = Invoice::query()->updateOrCreate(
                ['id' => $existingInvoice?->id],
                [
                    'invoice_number' => $invoiceNumber,
                    'invoice_slug' => $invoiceSlug,
                    'service_order_id' => $lockedOrder->id,
                    'client_company_id' => $lockedOrder->client_company_id,
                    'revision_number' => $existingInvoice ? $existingInvoice->revision_number : 0,
                    'subtotal_amount' => $subtotal,
                    'adjustment_amount' => $adjustment,
                    'tax_amount' => $tax,
                    'total_amount' => $total,
                    'paid_amount' => 0,
                    'balance_due' => $total,
                    'status' => $status,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'payment_instructions' => $validated['payment_instructions'] ?? 'Transfer ke Rekening Resmi PT Pulo Kencana Drum Industri (BCA / Mandiri) dengan mencantumkan nomor invoice.',
                    'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
                    'created_by_user_id' => $keuangan->id,
                    'issued_at' => $isDraft ? null : now(),
                ]
            );

            $invoice->items()->delete();
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'service_tariff_id' => $tariff?->id,
                'billable_transaction_id' => $lockedOrder->billableTransaction?->id,
                'transaction_type' => $lockedOrder->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $lockedOrder->result->unit,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            if (! $isDraft) {
                $oldBillingStatus = $lockedOrder->billing_status;
                $lockedOrder->update([
                    'billing_status' => 'INVOICED',
                    'payment_status' => 'UNPAID',
                ]);

                if ($lockedOrder->billableTransaction) {
                    $lockedOrder->billableTransaction->update([
                        'billing_status' => 'INVOICED',
                        'invoice_id' => $invoice->id,
                        'invoiced_at' => now(),
                    ]);
                }

                $formattedTotal = number_format($total, 0, ',', '.');
                $summary = "Tagihan {$invoice->invoice_number} diterbitkan sebesar Rp {$formattedTotal} untuk {$quantity} {$lockedOrder->result->unit}.";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'INVOICE',
                    'from_status' => $oldBillingStatus,
                    'to_status' => 'INVOICE_ISSUED',
                    'changed_by_user_id' => $keuangan->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'INVOICE_ISSUED',
                    auditable: $invoice,
                    newValues: [
                        'invoice_number' => $invoice->invoice_number,
                        'order_number' => $lockedOrder->order_number,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_amount' => $total,
                        'status' => 'ISSUED',
                        'summary' => $summary,
                    ],
                    actor: $keuangan,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: "Tagihan {$invoice->invoice_number} telah diterbitkan",
                    message: "Total tagihan Rp {$formattedTotal} untuk pesanan {$lockedOrder->order_number}. Silakan unduh invoice dan unggah bukti pembayaran.",
                    tone: 'info',
                    icon: 'receipt',
                    actionUrl: route('client.invoices.show', $invoice->invoice_slug, false),
                );
            } else {
                AuditLogger::record(
                    action: 'INVOICE_DRAFT_SAVED',
                    auditable: $invoice,
                    newValues: [
                        'invoice_number' => $invoice->invoice_number,
                        'order_number' => $lockedOrder->order_number,
                        'total_amount' => $total,
                        'status' => 'DRAFT',
                        'summary' => "Draft tagihan {$invoice->invoice_number} disimpan oleh Keuangan.",
                    ],
                    actor: $keuangan,
                    request: $request,
                );
            }

            return $invoice;
        });
    }

    /**
     * 2b. Keuangan menggabungkan satu atau beberapa transaksi siap-tagih (BillableTransaction)
     *     milik Client yang sama menjadi satu Invoice resmi (atau Draft).
     *     Mencegah keras penggabungan lintas Client dan penagihan ganda.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createCombinedInvoice(
        User $keuangan,
        array $validated,
        ?Request $request = null,
    ): Invoice {
        abort_unless($keuangan->hasRole(User::ROLE_KEUANGAN) && $keuangan->is_active, 403);

        return DB::transaction(function () use ($keuangan, $validated, $request): Invoice {
            $rawIds = array_values(array_unique(array_map('intval', (array) ($validated['billable_transaction_ids'] ?? []))));
            $rawIds = array_values(array_filter($rawIds, static fn (int $id): bool => $id > 0));

            if ($rawIds === []) {
                throw ValidationException::withMessages([
                    'billable_transaction_ids' => 'Pilih minimal satu transaksi siap ditagihkan untuk membuat invoice.',
                ]);
            }

            $transactions = BillableTransaction::query()
                ->with(['serviceOrder', 'clientCompany'])
                ->whereIn('id', $rawIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($transactions->count() !== count($rawIds)) {
                throw ValidationException::withMessages([
                    'billable_transaction_ids' => 'Sebagian transaksi yang dipilih tidak ditemukan.',
                ]);
            }

            $clientIds = $transactions->pluck('client_company_id')->unique()->values();
            if ($clientIds->count() > 1) {
                throw ValidationException::withMessages([
                    'billable_transaction_ids' => 'Invoice gabungan hanya dapat dibuat dari transaksi milik satu perusahaan Client yang sama.',
                ]);
            }

            foreach ($transactions as $tx) {
                if ($tx->billing_status !== BillableTransaction::STATUS_READY_TO_BILL || $tx->invoice_id !== null) {
                    throw ValidationException::withMessages([
                        'billable_transaction_ids' => "Transaksi {$tx->transaction_code} sudah pernah ditagihkan dan tidak boleh ditagihkan ulang.",
                    ]);
                }
                if ($tx->billable_quantity <= 0) {
                    throw ValidationException::withMessages([
                        'billable_transaction_ids' => "Transaksi {$tx->transaction_code} tidak memiliki kuantitas layak tagih.",
                    ]);
                }
            }

            $action = strtoupper(trim((string) ($validated['invoice_action'] ?? 'ISSUE')));
            $isDraft = $action === 'DRAFT';

            $unitPrices = (array) ($validated['unit_prices'] ?? []);
            $tariffIds = (array) ($validated['tariff_ids'] ?? []);
            $descriptions = (array) ($validated['descriptions'] ?? []);
            $adjustment = isset($validated['adjustment_amount']) && $validated['adjustment_amount'] !== ''
                ? round((float) $validated['adjustment_amount'], 2)
                : 0.0;
            $tax = isset($validated['tax_amount']) && $validated['tax_amount'] !== ''
                ? round((float) $validated['tax_amount'], 2)
                : 0.0;

            $linePayloads = [];
            $subtotal = 0.0;

            foreach ($transactions as $tx) {
                $txKey = (string) $tx->id;
                $tariff = null;
                $tariffInput = $tariffIds[$txKey] ?? $tariffIds[$tx->id] ?? null;
                if ($tariffInput) {
                    $tariff = ServiceTariff::query()
                        ->where('tariff_code', (string) $tariffInput)
                        ->orWhere('id', is_numeric($tariffInput) ? (int) $tariffInput : 0)
                        ->first();
                }

                $rawPrice = $unitPrices[$txKey] ?? $unitPrices[$tx->id] ?? null;
                $unitPrice = ($rawPrice !== null && $rawPrice !== '')
                    ? round((float) $rawPrice, 2)
                    : (float) ($tariff?->unit_price ?? 0);

                if ($unitPrice <= 0) {
                    throw ValidationException::withMessages([
                        "unit_prices.{$tx->id}" => "Harga satuan untuk transaksi {$tx->transaction_code} wajib lebih besar dari Rp 0.",
                    ]);
                }

                $qty = (int) $tx->billable_quantity;
                $lineTotal = round($qty * $unitPrice, 2);
                $subtotal = round($subtotal + $lineTotal, 2);

                $defaultDesc = in_array($tx->transaction_type, [ServiceOrder::TYPE_WASTE_TRANSPORT, 'DRUM_SALE'], true)
                    ? "Jasa Pengangkutan Limbah {$tx->product_name_snapshot} ({$tx->serviceOrder?->order_number})"
                    : "Jasa Rekondisi {$tx->service_name_snapshot} - {$tx->product_name_snapshot} ({$tx->serviceOrder?->order_number})";
                $customDesc = trim((string) ($descriptions[$txKey] ?? $descriptions[$tx->id] ?? ''));

                $linePayloads[] = [
                    'transaction' => $tx,
                    'service_tariff_id' => $tariff?->id,
                    'billable_transaction_id' => $tx->id,
                    'transaction_type' => $tx->transaction_type,
                    'description' => $customDesc !== '' ? $customDesc : $defaultDesc,
                    'quantity' => $qty,
                    'unit' => $tx->unit,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $total = round($subtotal - $adjustment + $tax, 2);
            if ($total <= 0 || $total > 999999999999) {
                throw ValidationException::withMessages([
                    'unit_prices' => 'Total tagihan gabungan harus berada di antara Rp 1 dan Rp 999.999.999.999.',
                ]);
            }

            /** @var BillableTransaction $firstTx */
            $firstTx = $transactions->first();
            [$invoiceNumber, $invoiceSlug] = $this->generateUniqueInvoiceNumber();

            $issueDate = isset($validated['issue_date']) && $validated['issue_date'] !== ''
                ? (string) $validated['issue_date']
                : now('Asia/Jakarta')->toDateString();
            $dueDate = isset($validated['due_date']) && $validated['due_date'] !== ''
                ? (string) $validated['due_date']
                : now('Asia/Jakarta')->addDays(7)->toDateString();

            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'invoice_slug' => $invoiceSlug,
                'service_order_id' => $firstTx->service_order_id,
                'client_company_id' => $firstTx->client_company_id,
                'revision_number' => 0,
                'subtotal_amount' => $subtotal,
                'adjustment_amount' => $adjustment,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'balance_due' => $total,
                'status' => $isDraft ? 'DRAFT' : 'ISSUED',
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'payment_instructions' => isset($validated['payment_instructions']) && trim((string) $validated['payment_instructions']) !== ''
                    ? trim((string) $validated['payment_instructions'])
                    : 'Transfer ke Rekening Resmi PT Pulo Kencana Drum Industri (BCA / Mandiri) dengan mencantumkan nomor invoice.',
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                    ? trim((string) $validated['notes'])
                    : null,
                'created_by_user_id' => $keuangan->id,
                'issued_at' => $isDraft ? null : now(),
            ]);

            $orderNumbers = [];
            foreach ($linePayloads as $line) {
                /** @var BillableTransaction $tx */
                $tx = $line['transaction'];

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'service_tariff_id' => $line['service_tariff_id'],
                    'billable_transaction_id' => $tx->id,
                    'transaction_type' => $line['transaction_type'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                $tx->update([
                    'invoice_id' => $invoice->id,
                    'billing_status' => $isDraft ? BillableTransaction::STATUS_READY_TO_BILL : BillableTransaction::STATUS_INVOICED,
                    'invoiced_at' => $isDraft ? null : now(),
                ]);

                if ($tx->serviceOrder) {
                    $orderNumbers[] = $tx->serviceOrder->order_number;

                    if (! $isDraft) {
                        $oldBilling = $tx->serviceOrder->billing_status;
                        $tx->serviceOrder->update([
                            'billing_status' => 'INVOICED',
                            'payment_status' => 'UNPAID',
                        ]);

                        OrderStatusHistory::query()->create([
                            'service_order_id' => $tx->serviceOrder->id,
                            'track_type' => 'INVOICE',
                            'from_status' => $oldBilling,
                            'to_status' => 'INVOICE_ISSUED',
                            'changed_by_user_id' => $keuangan->id,
                            'notes' => "Masuk ke Invoice {$invoice->invoice_number} (Rp ".number_format($line['line_total'], 0, ',', '.').').',
                            'is_visible_to_client' => true,
                        ]);
                    }
                }
            }

            $formattedTotal = number_format($total, 0, ',', '.');
            $txCount = count($linePayloads);
            $summary = $isDraft
                ? "Draft invoice {$invoice->invoice_number} ({$txCount} transaksi, Rp {$formattedTotal}) disimpan oleh Keuangan."
                : "Invoice {$invoice->invoice_number} ({$txCount} transaksi: ".implode(', ', $orderNumbers).") diterbitkan sebesar Rp {$formattedTotal}.";

            AuditLogger::record(
                action: $isDraft ? 'INVOICE_DRAFT_SAVED' : 'COMBINED_INVOICE_ISSUED',
                auditable: $invoice,
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'transaction_count' => $txCount,
                    'order_numbers' => $orderNumbers,
                    'total_amount' => $total,
                    'status' => $invoice->status,
                    'summary' => $summary,
                ],
                actor: $keuangan,
                request: $request,
            );

            if (! $isDraft && $firstTx->serviceOrder) {
                $this->notifyClient(
                    order: $firstTx->serviceOrder,
                    title: "Tagihan {$invoice->invoice_number} telah diterbitkan",
                    message: "Total tagihan Rp {$formattedTotal} mencakup {$txCount} transaksi. Unduh invoice PDF dan unggah bukti transfer melalui Portal Client.",
                    tone: 'info',
                    icon: 'receipt',
                    actionUrl: route('client.invoices.show', $invoice->invoice_slug, false),
                );
            }

            return $invoice;
        });
    }

    /**
     * 2c. Keuangan menerbitkan Draft Invoice (satuan maupun gabungan) menjadi ISSUED.
     */
    public function publishDraftInvoice(
        User $keuangan,
        Invoice $invoice,
        ?Request $request = null,
    ): Invoice {
        Gate::forUser($keuangan)->authorize('update', $invoice);

        return DB::transaction(function () use ($keuangan, $invoice, $request): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->with(['billableTransactions.serviceOrder', 'serviceOrder'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya invoice berstatus Draft yang dapat diterbitkan.',
                ]);
            }

            $lockedInvoice->update([
                'status' => 'ISSUED',
                'issued_at' => now(),
            ]);

            $linkedOrders = $this->resolveLinkedOrders($lockedInvoice);
            foreach ($lockedInvoice->billableTransactions as $tx) {
                $tx->update([
                    'billing_status' => BillableTransaction::STATUS_INVOICED,
                    'invoiced_at' => now(),
                ]);
            }

            $formattedTotal = number_format((float) $lockedInvoice->total_amount, 0, ',', '.');
            foreach ($linkedOrders as $ord) {
                $oldBilling = $ord->billing_status;
                $ord->update([
                    'billing_status' => 'INVOICED',
                    'payment_status' => 'UNPAID',
                ]);

                OrderStatusHistory::query()->create([
                    'service_order_id' => $ord->id,
                    'track_type' => 'INVOICE',
                    'from_status' => $oldBilling,
                    'to_status' => 'INVOICE_ISSUED',
                    'changed_by_user_id' => $keuangan->id,
                    'notes' => "Draft Invoice {$lockedInvoice->invoice_number} resmi diterbitkan (Total Rp {$formattedTotal}).",
                    'is_visible_to_client' => true,
                ]);
            }

            AuditLogger::record(
                action: 'INVOICE_ISSUED',
                auditable: $lockedInvoice,
                oldValues: ['status' => 'DRAFT'],
                newValues: [
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'status' => 'ISSUED',
                    'total_amount' => (float) $lockedInvoice->total_amount,
                    'summary' => "Invoice {$lockedInvoice->invoice_number} diterbitkan ke Portal Client.",
                ],
                actor: $keuangan,
                request: $request,
            );

            $primaryOrder = $linkedOrders->first();
            if ($primaryOrder) {
                $this->notifyClient(
                    order: $primaryOrder,
                    title: "Tagihan {$lockedInvoice->invoice_number} telah diterbitkan",
                    message: "Total tagihan Rp {$formattedTotal}. Silakan unduh invoice PDF dan unggah bukti transfer.",
                    tone: 'info',
                    icon: 'receipt',
                    actionUrl: route('client.invoices.show', $lockedInvoice->invoice_slug, false),
                );
            }

            return $lockedInvoice->fresh();
        });
    }

    /**
     * 2d. Keuangan membatalkan (VOIDED) invoice yang belum memiliki pembayaran terverifikasi
     *     dengan alasan wajib, lalu melepas kembali transaksi terkait ke antrean READY_TO_BILL.
     *
     * @param  array<string, mixed>  $validated
     */
    public function voidInvoice(
        User $keuangan,
        Invoice $invoice,
        array $validated,
        ?Request $request = null,
    ): Invoice {
        Gate::forUser($keuangan)->authorize('update', $invoice);

        return DB::transaction(function () use ($keuangan, $invoice, $validated, $request): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->with(['billableTransactions.serviceOrder', 'serviceOrder'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === 'VOIDED') {
                throw ValidationException::withMessages([
                    'status' => 'Invoice ini sudah berstatus Dibatalkan (VOIDED).',
                ]);
            }

            if ((float) $lockedInvoice->paid_amount > 0 || $lockedInvoice->status === 'PAID') {
                throw ValidationException::withMessages([
                    'status' => 'Invoice yang sudah memiliki pembayaran terverifikasi tidak dapat dibatalkan.',
                ]);
            }

            $reason = trim((string) ($validated['void_reason'] ?? ''));
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'void_reason' => 'Alasan pembatalan invoice wajib diisi minimal 5 karakter.',
                ]);
            }

            $oldStatus = $lockedInvoice->status;
            $lockedInvoice->update([
                'status' => 'VOIDED',
                'balance_due' => 0,
                'notes' => trim(($lockedInvoice->notes ? $lockedInvoice->notes."\n" : '')."[Dibatalkan] {$reason}"),
            ]);

            $linkedOrders = $this->resolveLinkedOrders($lockedInvoice);

            foreach ($lockedInvoice->billableTransactions as $tx) {
                $tx->update([
                    'billing_status' => BillableTransaction::STATUS_READY_TO_BILL,
                    'invoice_id' => null,
                    'invoiced_at' => null,
                ]);
            }

            foreach ($linkedOrders as $ord) {
                $ord->update([
                    'billing_status' => 'READY_TO_BILL',
                    'payment_status' => 'UNPAID',
                ]);

                OrderStatusHistory::query()->create([
                    'service_order_id' => $ord->id,
                    'track_type' => 'INVOICE',
                    'from_status' => 'INVOICED',
                    'to_status' => 'READY_TO_BILL',
                    'changed_by_user_id' => $keuangan->id,
                    'notes' => "Invoice {$lockedInvoice->invoice_number} dibatalkan (VOIDED). Transaksi kembali siap ditagihkan. Alasan: {$reason}",
                    'is_visible_to_client' => true,
                ]);
            }

            AuditLogger::record(
                action: 'INVOICE_VOIDED',
                auditable: $lockedInvoice,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'status' => 'VOIDED',
                    'summary' => "Invoice {$lockedInvoice->invoice_number} dibatalkan oleh Keuangan. Transaksi terkait dilepas kembali ke antrean penagihan.",
                ],
                reason: $reason,
                actor: $keuangan,
                request: $request,
            );

            return $lockedInvoice->fresh();
        });
    }

    /**
     * 3. Keuangan melakukan revisi / koreksi nominal tagihan dengan alasan wajib.
     *
     * @param  array<string, mixed>  $validated
     */
    public function reviseInvoice(
        User $keuangan,
        Invoice $invoice,
        array $validated,
        ?Request $request = null,
    ): Invoice {
        Gate::forUser($keuangan)->authorize('update', $invoice);

        return DB::transaction(function () use ($keuangan, $invoice, $validated, $request): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->with(['serviceOrder', 'items'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reason = trim((string) ($validated['revision_reason'] ?? ''));
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'revision_reason' => 'Alasan revisi / koreksi tagihan wajib diisi minimal 5 karakter.',
                ]);
            }

            $revisedTotal = round((float) $validated['revised_total'], 2);
            if ($revisedTotal <= 0) {
                throw ValidationException::withMessages([
                    'revised_total' => 'Nominal koreksi tagihan harus lebih besar dari Rp 0.',
                ]);
            }

            if ($revisedTotal < (float) $lockedInvoice->paid_amount) {
                throw ValidationException::withMessages([
                    'revised_total' => 'Nominal koreksi tidak boleh lebih kecil dari jumlah pembayaran yang sudah terverifikasi.',
                ]);
            }

            $oldValues = [
                'revision_number' => $lockedInvoice->revision_number,
                'subtotal_amount' => (float) $lockedInvoice->subtotal_amount,
                'adjustment_amount' => (float) $lockedInvoice->adjustment_amount,
                'total_amount' => (float) $lockedInvoice->total_amount,
                'balance_due' => (float) $lockedInvoice->balance_due,
                'status' => $lockedInvoice->status,
            ];

            $newBalance = round($revisedTotal - (float) $lockedInvoice->paid_amount, 2);
            $newRevision = $lockedInvoice->revision_number + 1;
            $newStatus = $newBalance <= 0
                ? 'PAID'
                : ((float) $lockedInvoice->paid_amount > 0 ? 'PARTIALLY_PAID' : 'ISSUED');

            $adjustmentDiff = round((float) $lockedInvoice->subtotal_amount + (float) $lockedInvoice->tax_amount - $revisedTotal, 2);

            $lockedInvoice->update([
                'revision_number' => $newRevision,
                'adjustment_amount' => max(0, $adjustmentDiff),
                'total_amount' => $revisedTotal,
                'balance_due' => $newBalance,
                'status' => $newStatus,
                'notes' => trim(($lockedInvoice->notes ? $lockedInvoice->notes."\n" : '')."[Revisi #{$newRevision}] {$reason}"),
                'paid_at' => $newStatus === 'PAID' ? ($lockedInvoice->paid_at ?? now()) : null,
            ]);

            $linkedOrders = $this->resolveLinkedOrders($lockedInvoice);
            foreach ($linkedOrders as $order) {
                if ($newStatus === 'PAID') {
                    $order->update(['payment_status' => 'PAID']);
                } elseif ($newStatus === 'PARTIALLY_PAID') {
                    $order->update(['payment_status' => 'PARTIALLY_PAID']);
                }

                OrderStatusHistory::query()->create([
                    'service_order_id' => $order->id,
                    'track_type' => 'INVOICE',
                    'from_status' => $order->order_status,
                    'to_status' => $order->order_status,
                    'changed_by_user_id' => $keuangan->id,
                    'notes' => sprintf(
                        'Invoice %s direvisi (Revisi #%d) menjadi Rp %s. Alasan: %s',
                        $lockedInvoice->invoice_number,
                        $newRevision,
                        number_format($revisedTotal, 0, ',', '.'),
                        $reason
                    ),
                    'is_visible_to_client' => true,
                ]);
            }

            AuditLogger::record(
                action: 'INVOICE_REVISED',
                auditable: $lockedInvoice,
                oldValues: $oldValues,
                newValues: [
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'revision_number' => $newRevision,
                    'total_amount' => $revisedTotal,
                    'balance_due' => $newBalance,
                    'status' => $newStatus,
                    'summary' => sprintf(
                        'Invoice %s direvisi (#%d) menjadi Rp %s.',
                        $lockedInvoice->invoice_number,
                        $newRevision,
                        number_format($revisedTotal, 0, ',', '.')
                    ),
                ],
                reason: $reason,
                actor: $keuangan,
                request: $request,
            );

            return $lockedInvoice;
        });
    }

    /**
     * 4. Client mengunggah bukti pembayaran (status PENDING_VERIFICATION, belum melunaskan tagihan).
     *
     * @param  array<string, mixed>  $validated
     */
    public function submitClientPaymentProof(
        User $client,
        Invoice $invoice,
        array $validated,
        ?UploadedFile $proofFile = null,
        ?Request $request = null,
    ): Payment {
        Gate::forUser($client)->authorize('submitPaymentProof', $invoice);

        return DB::transaction(function () use ($client, $invoice, $validated, $proofFile, $request): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->with('serviceOrder')
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === 'PAID' || (float) $lockedInvoice->balance_due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Tagihan ini sudah berstatus Lunas dan tidak memerlukan unggahan pembayaran lagi.',
                ]);
            }

            $amount = round((float) $validated['amount'], 2);
            if ($amount <= 0 || $amount > (float) $lockedInvoice->balance_due) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran harus lebih besar dari Rp 0 dan tidak melebihi sisa tagihan.',
                ]);
            }

            $paymentDate = (string) ($validated['payment_date'] ?? now('Asia/Jakarta')->toDateString());
            $paymentMethod = trim((string) $validated['payment_method']);
            $referenceNumber = isset($validated['reference_number']) && trim((string) $validated['reference_number']) !== ''
                ? trim((string) $validated['reference_number'])
                : null;

            // Cegah pencatatan pembayaran ganda berdasarkan nomor referensi atau kiriman identik berulang
            if ($referenceNumber !== null) {
                $duplicateRef = Payment::query()
                    ->where('invoice_id', $lockedInvoice->id)
                    ->where('reference_number', $referenceNumber)
                    ->where('verification_status', '!=', 'REJECTED')
                    ->exists();

                if ($duplicateRef) {
                    throw ValidationException::withMessages([
                        'reference_number' => 'Nomor referensi mutasi ini sudah pernah dikirim untuk tagihan ini.',
                    ]);
                }
            }

            $recentDuplicate = Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('submitted_by_user_id', $client->id)
                ->where('amount', $amount)
                ->where('reference_number', $referenceNumber)
                ->where('created_at', '>=', now()->subSeconds(5))
                ->first();

            if ($recentDuplicate) {
                return $recentDuplicate;
            }

            $proofPath = 'bukti-bayar-client.pdf';
            if ($proofFile) {
                $proofPath = $proofFile->store('payment-proofs', 'local');
            } elseif (isset($validated['proof_file_name']) && trim((string) $validated['proof_file_name']) !== '') {
                $proofPath = trim((string) $validated['proof_file_name']);
            }

            $paymentNumber = $this->generateUniquePaymentNumber();

            $payment = Payment::query()->create([
                'payment_number' => $paymentNumber,
                'invoice_id' => $lockedInvoice->id,
                'client_company_id' => $lockedInvoice->client_company_id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'proof_file_path' => $proofPath,
                'verification_status' => 'PENDING_VERIFICATION',
                'submitted_by_user_id' => $client->id,
            ]);

            $formattedAmount = number_format($amount, 0, ',', '.');
            $summary = "Client mengunggah bukti pembayaran {$payment->payment_number} sebesar Rp {$formattedAmount} (Menunggu verifikasi Keuangan).";

            $linkedOrders = $this->resolveLinkedOrders($lockedInvoice);
            foreach ($linkedOrders as $ord) {
                $ord->update([
                    'payment_status' => 'PROOF_SUBMITTED',
                ]);

                OrderStatusHistory::query()->create([
                    'service_order_id' => $ord->id,
                    'track_type' => 'PAYMENT',
                    'from_status' => $ord->order_status,
                    'to_status' => $ord->order_status,
                    'changed_by_user_id' => $client->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);
            }

            $primaryOrder = $linkedOrders->first();
            if ($primaryOrder) {
                $this->notifyRole(
                    role: User::ROLE_KEUANGAN,
                    order: $primaryOrder,
                    title: 'Bukti pembayaran baru menunggu verifikasi',
                    message: "{$lockedInvoice->invoice_number} · Rp {$formattedAmount} ({$paymentMethod})",
                    tone: 'warning',
                    icon: 'receipt',
                    actionUrl: route('finance.invoices.show', $lockedInvoice->invoice_slug, false),
                );
            }

            AuditLogger::record(
                action: 'UPLOAD_PAYMENT_PROOF',
                auditable: $payment,
                newValues: [
                    'payment_number' => $payment->payment_number,
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'amount' => $amount,
                    'verification_status' => 'PENDING_VERIFICATION',
                    'summary' => $summary,
                ],
                actor: $client,
                request: $request,
            );

            return $payment;
        });
    }

    /**
     * 5. Keuangan mencatat pembayaran langsung atau memverifikasi/menolak bukti pembayaran Client.
     *    Hanya Keuangan yang berwenang mengubah status tagihan menjadi PAID dan mencatat Kas Masuk.
     *
     * @param  array<string, mixed>  $validated
     */
    public function verifyOrRecordPayment(
        User $keuangan,
        Invoice $invoice,
        array $validated,
        ?Payment $payment = null,
        ?Request $request = null,
    ): Payment {
        Gate::forUser($keuangan)->authorize('verifyPayment', $invoice);

        return DB::transaction(function () use ($keuangan, $invoice, $validated, $payment, $request): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->with('serviceOrder')
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->whereKey($lockedInvoice->service_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $decision = strtoupper(trim((string) ($validated['decision'] ?? 'VERIFIED')));
            if (! in_array($decision, ['VERIFIED', 'REJECTED'], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Keputusan verifikasi pembayaran harus bernilai VERIFIED atau REJECTED.',
                ]);
            }

            if ($payment) {
                /** @var Payment $lockedPayment */
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($lockedPayment->invoice_id !== $lockedInvoice->id) {
                    abort(403, 'Pembayaran tidak sesuai dengan dokumen tagihan.');
                }

                if ($lockedPayment->verification_status === 'VERIFIED') {
                    throw ValidationException::withMessages([
                        'payment' => 'Pembayaran ini sudah diverifikasi sebelumnya (cegah pencatatan pembayaran ganda).',
                    ]);
                }
            } else {
                if ((float) $lockedInvoice->balance_due <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Tagihan ini sudah lunas penuh. Tidak dapat mencatat pembayaran tambahan.',
                    ]);
                }

                $amount = round((float) $validated['amount'], 2);
                if ($amount <= 0 || $amount > (float) $lockedInvoice->balance_due) {
                    throw ValidationException::withMessages([
                        'amount' => 'Nominal pembayaran harus lebih besar dari Rp 0 dan tidak melebihi sisa tagihan.',
                    ]);
                }
                $referenceNumber = isset($validated['reference_number']) && trim((string) $validated['reference_number']) !== ''
                    ? trim((string) $validated['reference_number'])
                    : null;

                if ($referenceNumber !== null) {
                    $duplicateRef = Payment::query()
                        ->where('invoice_id', $lockedInvoice->id)
                        ->where('reference_number', $referenceNumber)
                        ->where('verification_status', 'VERIFIED')
                        ->exists();

                    if ($duplicateRef) {
                        throw ValidationException::withMessages([
                            'reference_number' => 'Nomor referensi mutasi ini sudah pernah diverifikasi pada tagihan ini (cegah pencatatan ganda).',
                        ]);
                    }
                }

                $lockedPayment = Payment::query()->create([
                    'payment_number' => $this->generateUniquePaymentNumber(),
                    'invoice_id' => $lockedInvoice->id,
                    'client_company_id' => $lockedInvoice->client_company_id,
                    'payment_date' => $validated['payment_date'] ?? now('Asia/Jakarta')->toDateString(),
                    'amount' => $amount,
                    'payment_method' => trim((string) ($validated['payment_method'] ?? 'Transfer Bank')),
                    'reference_number' => $referenceNumber,
                    'proof_file_path' => 'mutasi-rekening-terverifikasi.pdf',
                    'verification_status' => 'PENDING_VERIFICATION',
                    'submitted_by_user_id' => $keuangan->id,
                ]);
            }

            $notesOrReason = isset($validated['verification_notes'])
                ? trim((string) $validated['verification_notes'])
                : (isset($validated['reject_reason']) ? trim((string) $validated['reject_reason']) : '');

            if ($decision === 'REJECTED') {
                if (mb_strlen($notesOrReason) < 5) {
                    throw ValidationException::withMessages([
                        'reject_reason' => 'Alasan penolakan bukti pembayaran wajib diisi minimal 5 karakter.',
                    ]);
                }

                $lockedPayment->update([
                    'verification_status' => 'REJECTED',
                    'verification_notes' => $notesOrReason,
                    'verified_by_user_id' => $keuangan->id,
                    'verified_at' => now(),
                ]);

                $summary = "Bukti pembayaran {$lockedPayment->payment_number} ditolak oleh Keuangan. Alasan: {$notesOrReason}";

                $linkedOrders = $this->resolveLinkedOrders($lockedInvoice);
                foreach ($linkedOrders as $ord) {
                    if ((float) $lockedInvoice->paid_amount <= 0) {
                        $ord->update(['payment_status' => 'PROOF_REJECTED']);
                    }

                    OrderStatusHistory::query()->create([
                        'service_order_id' => $ord->id,
                        'track_type' => 'PAYMENT',
                        'from_status' => $ord->order_status,
                        'to_status' => $ord->order_status,
                        'changed_by_user_id' => $keuangan->id,
                        'notes' => $summary,
                        'is_visible_to_client' => true,
                    ]);
                }

                $this->notifyClient(
                    order: $lockedOrder,
                    title: "Bukti pembayaran {$lockedInvoice->invoice_number} ditolak",
                    message: "Alasan: {$notesOrReason}. Silakan unggah ulang bukti transfer yang sesuai melalui Portal Client.",
                    tone: 'danger',
                    icon: 'alert-triangle',
                    actionUrl: route('client.invoices.show', $lockedInvoice->invoice_slug, false),
                );

                AuditLogger::record(
                    action: 'PAYMENT_REJECTED',
                    auditable: $lockedPayment,
                    newValues: [
                        'payment_number' => $lockedPayment->payment_number,
                        'invoice_number' => $lockedInvoice->invoice_number,
                        'verification_status' => 'REJECTED',
                        'summary' => $summary,
                    ],
                    reason: $notesOrReason,
                    actor: $keuangan,
                    request: $request,
                );

                return $lockedPayment;
            }

            // Keputusan VERIFIED
            $lockedPayment->update([
                'verification_status' => 'VERIFIED',
                'verification_notes' => $notesOrReason !== '' ? $notesOrReason : 'Pembayaran sah terverifikasi mutasi rekening.',
                'verified_by_user_id' => $keuangan->id,
                'verified_at' => now(),
            ]);

            $verifiedSum = round((float) Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('verification_status', 'VERIFIED')
                ->sum('amount'), 2);

            $balanceDue = max(0.0, round((float) $lockedInvoice->total_amount - $verifiedSum, 2));
            $isFullyPaid = $balanceDue <= 0.0;
            $newInvoiceStatus = $isFullyPaid ? 'PAID' : 'PARTIALLY_PAID';

            $oldInvoiceStatus = $lockedInvoice->status;
            $oldOrderStatus = $lockedOrder->order_status;

            $lockedInvoice->update([
                'paid_amount' => $verifiedSum,
                'balance_due' => $balanceDue,
                'status' => $newInvoiceStatus,
                'paid_at' => $isFullyPaid ? now() : null,
            ]);

            // Otomatis catat ke Buku Transaksi Keuangan (1-ke-1 dengan payment_id agar tidak pernah ganda)
            $entryNumber = $this->generateUniqueCashbookNumber();
            $statusLabel = $isFullyPaid ? 'Lunas' : 'Sebagian';
            CashbookEntry::query()->firstOrCreate(
                ['payment_id' => $lockedPayment->id],
                [
                    'entry_number' => $entryNumber,
                    'entry_type' => 'IN',
                    'transaction_date' => $lockedPayment->payment_date?->toDateString() ?? now('Asia/Jakarta')->toDateString(),
                    'source_type' => 'PAYMENT',
                    'operational_expense_id' => null,
                    'description' => "Pembayaran Invoice {$lockedInvoice->invoice_number} ({$statusLabel}) - {$lockedOrder->origin_company_name}",
                    'amount' => $lockedPayment->amount,
                    'status' => 'ACTIVE',
                    'recorded_by_user_id' => $keuangan->id,
                ]
            );

            $formattedPay = number_format((float) $lockedPayment->amount, 0, ',', '.');
            $linkedOrders = $this->resolveLinkedOrders($lockedInvoice);

            if ($isFullyPaid) {
                foreach ($linkedOrders as $ord) {
                    $ord->update([
                        'payment_status' => 'PAID',
                    ]);

                    OrderStatusHistory::query()->create([
                        'service_order_id' => $ord->id,
                        'track_type' => 'PAYMENT',
                        'from_status' => $ord->order_status,
                        'to_status' => 'PAID',
                        'changed_by_user_id' => $keuangan->id,
                        'notes' => "Pembayaran Rp {$formattedPay} diverifikasi Keuangan. Tagihan {$lockedInvoice->invoice_number} lunas penuh.",
                        'is_visible_to_client' => true,
                    ]);
                }

                $summary = "Tagihan {$lockedInvoice->invoice_number} ditandai Lunas (Rp {$formattedPay} diverifikasi) dan tercatat di Buku Kas.";

                $this->notifyRole(
                    role: User::ROLE_KEPALA_GUDANG,
                    order: $lockedOrder,
                    title: 'Tagihan pesanan telah Lunas terverifikasi',
                    message: "{$lockedInvoice->invoice_number} telah lunas terverifikasi Keuangan.",
                    tone: 'success',
                    icon: 'check-circle',
                    actionUrl: route('warehouse.orders.show', $lockedOrder->order_slug, false),
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: "Pembayaran {$lockedInvoice->invoice_number} telah Lunas",
                    message: "Pembayaran Rp {$formattedPay} telah diverifikasi oleh divisi Keuangan.",
                    tone: 'success',
                    icon: 'check-circle',
                    actionUrl: route('client.invoices.show', $lockedInvoice->invoice_slug, false),
                );
            } else {
                $formattedBalance = number_format($balanceDue, 0, ',', '.');
                $summary = "Pembayaran sebagian Rp {$formattedPay} untuk {$lockedInvoice->invoice_number} diverifikasi & masuk Buku Kas. Sisa tagihan: Rp {$formattedBalance}.";

                foreach ($linkedOrders as $ord) {
                    $ord->update([
                        'payment_status' => 'PARTIALLY_PAID',
                    ]);

                    OrderStatusHistory::query()->create([
                        'service_order_id' => $ord->id,
                        'track_type' => 'PAYMENT',
                        'from_status' => $ord->order_status,
                        'to_status' => 'PAYMENT_PARTIAL',
                        'changed_by_user_id' => $keuangan->id,
                        'notes' => $summary,
                        'is_visible_to_client' => true,
                    ]);
                }
            }

            AuditLogger::record(
                action: 'PAYMENT_VERIFIED',
                auditable: $lockedPayment,
                oldValues: ['invoice_status' => $oldInvoiceStatus, 'order_status' => $oldOrderStatus],
                newValues: [
                    'payment_number' => $lockedPayment->payment_number,
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'order_number' => $lockedOrder->order_number,
                    'amount' => (float) $lockedPayment->amount,
                    'paid_amount' => $verifiedSum,
                    'balance_due' => $balanceDue,
                    'invoice_status' => $newInvoiceStatus,
                    'order_status' => $lockedOrder->order_status,
                    'summary' => $summary,
                ],
                actor: $keuangan,
                request: $request,
            );

            return $lockedPayment;
        });
    }

    /**
     * 6. Keuangan mencatat biaya operasional (bahan, gaji karyawan, utilitas, dll.)
     *    dan otomatis membuat entri Kas Keluar (OUT) di Buku Transaksi Keuangan.
     *
     * @param  array<string, mixed>  $validated
     */
    public function recordOperationalExpense(
        User $keuangan,
        array $validated,
        ?UploadedFile $receiptFile = null,
        ?Request $request = null,
    ): OperationalExpense {
        Gate::forUser($keuangan)->authorize('create', OperationalExpense::class);

        return DB::transaction(function () use ($keuangan, $validated, $receiptFile, $request): OperationalExpense {
            $category = trim((string) $validated['expense_category']);
            $expenseDate = (string) ($validated['expense_date'] ?? now('Asia/Jakarta')->toDateString());
            $description = trim((string) $validated['description']);
            $amount = round((float) $validated['amount'], 2);
            $vendor = isset($validated['vendor']) && trim((string) $validated['vendor']) !== ''
                ? trim((string) $validated['vendor'])
                : (isset($validated['recipient_or_vendor']) && trim((string) $validated['recipient_or_vendor']) !== ''
                    ? trim((string) $validated['recipient_or_vendor'])
                    : null);
            $paymentMethod = trim((string) ($validated['payment_method'] ?? 'Tunai'));

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pengeluaran operasional harus lebih besar dari Rp 0.',
                ]);
            }

            $receiptPath = 'nota-operasional.pdf';
            if ($receiptFile) {
                $receiptPath = $receiptFile->store('expense-receipts', 'local');
            }

            $expenseNumber = $this->generateUniqueExpenseNumber();

            $expense = OperationalExpense::query()->create([
                'expense_number' => $expenseNumber,
                'expense_category' => $category,
                'expense_date' => $expenseDate,
                'description' => $description,
                'amount' => $amount,
                'recipient_or_vendor' => $vendor,
                'payment_method' => $paymentMethod,
                'receipt_file_path' => $receiptPath,
                'status' => 'RECORDED',
                'recorded_by_user_id' => $keuangan->id,
            ]);

            CashbookEntry::query()->firstOrCreate(
                ['operational_expense_id' => $expense->id],
                [
                    'entry_number' => $this->generateUniqueCashbookNumber(),
                    'entry_type' => 'OUT',
                    'transaction_date' => $expenseDate,
                    'source_type' => 'OPERATIONAL_EXPENSE',
                    'payment_id' => null,
                    'description' => "Biaya Operasional ({$category}): {$description}",
                    'amount' => $amount,
                    'status' => 'ACTIVE',
                    'recorded_by_user_id' => $keuangan->id,
                ]
            );

            $formattedAmount = number_format($amount, 0, ',', '.');
            $summary = "Biaya operasional {$expense->expense_number} ({$category}) sebesar Rp {$formattedAmount} dicatat & masuk Buku Kas Keluar.";

            AuditLogger::record(
                action: 'OPERATIONAL_EXPENSE_RECORDED',
                auditable: $expense,
                newValues: [
                    'expense_number' => $expense->expense_number,
                    'expense_category' => $category,
                    'amount' => $amount,
                    'summary' => $summary,
                ],
                actor: $keuangan,
                request: $request,
            );

            return $expense;
        });
    }

    /**
     * 7. Kepala Gudang menerbitkan Surat Jalan setelah tagihan terverifikasi Lunas (PAID).
     *
     * @param  array<string, mixed>  $validated
     */
    public function issueDeliveryOrder(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): DeliveryOrder {
        abort_unless($gudang->hasRole(User::ROLE_KEPALA_GUDANG) && $gudang->is_active, 403);

        return DB::transaction(function () use ($gudang, $order, $validated, $request): DeliveryOrder {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['result', 'latestInvoice', 'latestDeliveryOrder'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $lockedOrder->latestInvoice;

            if ($lockedOrder->payment_status !== 'PAID'
                || ! $invoice
                || $invoice->status !== 'PAID'
                || (float) $invoice->balance_due > 0
                || $lockedOrder->warehouse_status !== 'COMPLETED'
                || in_array($lockedOrder->order_status, ['CANCELLED', 'GATE_IN_HELD'], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Surat Jalan tidak dapat diterbitkan sebelum pekerjaan gudang selesai dan seluruh tagihan terkait diverifikasi Lunas (PAID) oleh Keuangan.',
                ]);
            }

            $existingDo = $lockedOrder->latestDeliveryOrder;
            if ($existingDo && $existingDo->status === 'USED_AT_GATE_OUT') {
                throw ValidationException::withMessages([
                    'delivery_order' => 'Surat Jalan untuk pesanan ini sudah pernah digunakan untuk transaksi keluar dan tidak dapat diterbitkan ulang.',
                ]);
            }

            $doNumber = $existingDo?->delivery_order_number ?? $this->generateUniqueDeliveryOrderNumber();
            $quantityReleased = $lockedOrder->result?->completed_quantity ?? ($lockedOrder->requested_quantity ?? 1);
            $unit = $lockedOrder->result?->unit ?? $lockedOrder->requested_unit;

            $cargoSummary = isset($validated['cargo_summary']) && trim((string) $validated['cargo_summary']) !== ''
                ? trim((string) $validated['cargo_summary'])
                : "{$quantityReleased} {$unit} {$lockedOrder->service_category} ({$lockedOrder->service_type})";

            $licensePlate = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($validated['vehicle_plate'] ?? ($validated['license_plate'] ?? $lockedOrder->license_plate)))) ?? '');
            $vehicleType = trim((string) ($validated['vehicle_type'] ?? $lockedOrder->vehicle_type));
            $driverName = isset($validated['driver_name']) && trim((string) $validated['driver_name']) !== ''
                ? trim((string) $validated['driver_name'])
                : $lockedOrder->driver_name;

            $oldOrderStatus = $lockedOrder->order_status;

            $deliveryOrder = DeliveryOrder::query()->updateOrCreate(
                ['id' => $existingDo?->id],
                [
                    'delivery_order_number' => $doNumber,
                    'service_order_id' => $lockedOrder->id,
                    'invoice_id' => $invoice->id,
                    'client_company_id' => $lockedOrder->client_company_id,
                    'cargo_summary' => $cargoSummary,
                    'quantity_released' => $quantityReleased,
                    'unit' => $unit,
                    'vehicle_type' => $vehicleType,
                    'license_plate' => $licensePlate,
                    'driver_name' => $driverName,
                    'status' => 'ISSUED',
                    'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
                    'issued_by_user_id' => $gudang->id,
                    'issued_at' => now(),
                ]
            );

            $nextStatus = $lockedOrder->order_status === 'COMPLETED' ? 'COMPLETED' : 'IN_DELIVERY';

            $lockedOrder->update([
                'order_status' => $nextStatus,
                'gate_status' => 'NOT_REQUIRED',
                'delivered_quantity' => $lockedOrder->delivered_quantity ?? $quantityReleased,
            ]);

            $plateNote = $licensePlate !== '' ? ", Nopol {$licensePlate}" : '';
            $summary = "Surat Jalan {$deliveryOrder->delivery_order_number} diterbitkan oleh Kepala Gudang untuk {$lockedOrder->order_number} ({$quantityReleased} {$unit}{$plateNote}).";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'DELIVERY_ORDER',
                'from_status' => $oldOrderStatus,
                'to_status' => $nextStatus,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'DELIVERY_ORDER_ISSUED',
                auditable: $deliveryOrder,
                oldValues: ['order_status' => $oldOrderStatus],
                newValues: [
                    'delivery_order_number' => $deliveryOrder->delivery_order_number,
                    'order_number' => $lockedOrder->order_number,
                    'invoice_number' => $invoice->invoice_number,
                    'quantity_released' => $quantityReleased,
                    'license_plate' => $licensePlate,
                    'order_status' => $nextStatus,
                    'summary' => $summary,
                ],
                actor: $gudang,
                request: $request,
            );

            $this->notifyClient(
                order: $lockedOrder,
                title: 'Surat Jalan telah diterbitkan',
                message: "{$deliveryOrder->delivery_order_number} untuk pesanan {$lockedOrder->order_number} ({$quantityReleased} {$unit}).",
                tone: 'success',
                icon: 'truck',
                actionUrl: route('client.orders.show', $lockedOrder->order_slug, false),
            );

            return $deliveryOrder;
        });
    }

    /**
     * Alur pemeriksaan gerbang keluar Satpam telah dikeluarkan pada PRD 2.0.
     *
     * @param  array<string, mixed>  $validated
     */
    public function inspectGateOut(
        User $satpam,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): VehicleGateLog {
        Gate::forUser($satpam)->authorize('inspectGate', $order);

        throw ValidationException::withMessages([
            'gate_status' => 'Alur pemeriksaan gerbang keluar Satpam tidak lagi aktif pada PRD PKDI v2.0. Pencatatan pengiriman dilakukan oleh Kepala Gudang.',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generateUniqueInvoiceNumber(): array
    {
        $ym = now('Asia/Jakarta')->format('Y/m');
        $seq = Invoice::query()->lockForUpdate()->count() + 15;

        while (true) {
            $num = sprintf('INV/PKDI/%s/%04d', $ym, $seq);
            $slug = str_replace('/', '-', $num);
            if (! Invoice::query()->where('invoice_number', $num)->orWhere('invoice_slug', $slug)->exists()) {
                return [$num, $slug];
            }
            $seq++;
        }
    }

    private function generateUniquePaymentNumber(): string
    {
        $ymd = now('Asia/Jakarta')->format('Ymd');
        $seq = Payment::query()->lockForUpdate()->count() + 12;

        while (true) {
            $num = sprintf('PAY-%s-%04d', $ymd, $seq);
            if (! Payment::query()->where('payment_number', $num)->exists()) {
                return $num;
            }
            $seq++;
        }
    }

    private function generateUniqueExpenseNumber(): string
    {
        $ymd = now('Asia/Jakarta')->format('Ymd');
        $seq = OperationalExpense::query()->lockForUpdate()->count() + 5;

        while (true) {
            $num = sprintf('EXP-%s-%03d', $ymd, $seq);
            if (! OperationalExpense::query()->where('expense_number', $num)->exists()) {
                return $num;
            }
            $seq++;
        }
    }

    private function generateUniqueCashbookNumber(): string
    {
        $ymd = now('Asia/Jakarta')->format('Ymd');
        $seq = CashbookEntry::query()->lockForUpdate()->count() + 8;

        while (true) {
            $num = sprintf('CB-%s-%03d', $ymd, $seq);
            if (! CashbookEntry::query()->where('entry_number', $num)->exists()) {
                return $num;
            }
            $seq++;
        }
    }

    /**
     * Mengumpulkan seluruh ServiceOrder yang terhubung ke satu Invoice (baik satuan maupun gabungan).
     *
     * @return Collection<int, ServiceOrder>
     */
    private function resolveLinkedOrders(Invoice $invoice): Collection
    {
        $orderIds = BillableTransaction::query()
            ->where('invoice_id', $invoice->id)
            ->pluck('service_order_id')
            ->all();

        if ($invoice->service_order_id) {
            $orderIds[] = $invoice->service_order_id;
        }

        $uniqueIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($uniqueIds === []) {
            return collect();
        }

        return ServiceOrder::query()->whereIn('id', $uniqueIds)->orderBy('id')->get();
    }

    private function generateUniqueDeliveryOrderNumber(): string
    {
        $ym = now('Asia/Jakarta')->format('Y/m');
        $seq = DeliveryOrder::query()->lockForUpdate()->count() + 10;

        while (true) {
            $num = sprintf('SJ/PKDI/%s/%04d', $ym, $seq);
            if (! DeliveryOrder::query()->where('delivery_order_number', $num)->exists()) {
                return $num;
            }
            $seq++;
        }
    }

    private function notifyRole(
        string $role,
        ServiceOrder $order,
        string $title,
        string $message,
        string $tone,
        string $icon,
        string $actionUrl,
    ): void {
        $recipients = User::query()->where('role', $role)->where('is_active', true)->get();
        foreach ($recipients as $recipient) {
            AppNotification::query()->create([
                'recipient_user_id' => $recipient->id,
                'service_order_id' => $order->id,
                'title' => $title,
                'message' => $message,
                'tone' => $tone,
                'icon' => $icon,
                'action_url' => $actionUrl,
            ]);
        }
    }

    private function notifyClient(
        ServiceOrder $order,
        string $title,
        string $message,
        string $tone,
        string $icon,
        string $actionUrl,
    ): void {
        $recipients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->where('client_company_id', $order->client_company_id)
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $recipient) {
            AppNotification::query()->create([
                'recipient_user_id' => $recipient->id,
                'service_order_id' => $order->id,
                'title' => $title,
                'message' => $message,
                'tone' => $tone,
                'icon' => $icon,
                'action_url' => $actionUrl,
            ]);
        }
    }
}
