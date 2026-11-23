<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BillableTransaction;
use App\Models\ClientContract;
use App\Models\ComplianceDoc;
use App\Models\DiscrepancyReport;
use App\Models\GateLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrderStatusHistory;
use App\Models\ReconditionCert;
use App\Models\ServiceOrder;
use App\Models\ServiceResult;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseSorting;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrdV21WorkflowService
{
    /**
     * FASE 1 (2.1): Gate-In Armada oleh Kepala Gudang.
     * Menghitung total_physical_qty = qty_good + qty_dent + qty_reject.
     * - Kondisi A (Sesuai): total == po_qty && qty_reject == 0 -> GATE_IN_VERIFIED
     * - Kondisi B (Selisih): total != po_qty || qty_reject > 0 -> DISCREPANCY_PENDING + BA Selisih otomatis
     *
     * @param  array<string, mixed>  $validated
     * @param  list<UploadedFile>  $evidenceFiles
     * @return array{gate_log: GateLog, discrepancy: DiscrepancyReport|null, order: ServiceOrder}
     */
    public function recordGateIn(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        array $evidenceFiles = [],
        ?Request $request = null,
    ): array {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $validated, $evidenceFiles, $request): array {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->is_locked) {
                throw ValidationException::withMessages([
                    'order_status' => 'Data kuantitas drum sudah dikunci (READ-ONLY) setelah diserahkan ke Keuangan. Perubahan hanya dapat dilakukan melalui Revisi Terotorisasi.',
                ]);
            }

            $plateNumber = strtoupper(preg_replace('/\s+/', ' ', trim((string) $validated['plate_number'])) ?? '');
            $driverName = trim((string) $validated['driver_name']);
            $qtyGood = (int) $validated['qty_good'];
            $qtyDent = (int) $validated['qty_dent'];
            $qtyReject = (int) $validated['qty_reject'];
            $totalPhysical = $qtyGood + $qtyDent + $qtyReject;
            $poQty = (int) ($lockedOrder->requested_quantity ?? 0);

            if ($totalPhysical <= 0) {
                throw ValidationException::withMessages([
                    'qty_good' => 'Total kuantitas fisik drum yang masuk minimal 1.',
                ]);
            }

            $hasDiscrepancy = ($totalPhysical !== $poQty) || ($qtyReject > 0);

            if ($hasDiscrepancy && count($evidenceFiles) === 0) {
                throw ValidationException::withMessages([
                    'evidence_photos' => 'Wajib mengunggah minimal 1 foto bukti kondisi drum karena jumlah fisik tidak sama dengan PO atau terdapat drum reject.',
                ]);
            }

            $storedPhotos = [];
            foreach ($evidenceFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $storedPhotos[] = $file->store('gate-in-evidence', 'local');
                }
            }

            $notes = isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                ? trim((string) $validated['notes'])
                : null;

            $oldValues = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
                'license_plate' => $lockedOrder->license_plate,
                'driver_name' => $lockedOrder->driver_name,
            ];

            $gateLog = GateLog::query()->create([
                'service_order_id' => $lockedOrder->id,
                'type' => GateLog::TYPE_IN,
                'plate_number' => $plateNumber,
                'driver_name' => $driverName,
                'qty_good' => $qtyGood,
                'qty_dent' => $qtyDent,
                'qty_reject' => $qtyReject,
                'total_physical_qty' => $totalPhysical,
                'evidence_photos' => $storedPhotos,
                'notes' => $notes,
                'recorded_by_user_id' => $gudang->id,
                'recorded_at' => now(),
            ]);

            // Sinkronisasi ke tabel receipt & sorting agar dasbor existing tetap konsisten (Single Source of Truth)
            $receipt = WarehouseReceipt::query()->create([
                'service_order_id' => $lockedOrder->id,
                'receipt_number' => $this->generateUniqueCode(WarehouseReceipt::class, 'receipt_number', 'RCV-'.Carbon::now('Asia/Jakarta')->format('Ymd').'-', 3),
                'received_at' => now(),
                'actual_received_quantity' => $totalPhysical,
                'unit' => $lockedOrder->requested_unit ?: 'Drum',
                'initial_condition' => sprintf('Gate-In %s (Sopir: %s) — Baik: %d, Penyok: %d, Reject: %d.', $plateNumber, $driverName, $qtyGood, $qtyDent, $qtyReject),
                'discrepancy_notes' => $hasDiscrepancy ? ($notes ?: "Selisih fisik ({$totalPhysical} vs PO {$poQty}) atau reject ({$qtyReject}).") : $notes,
                'photo_path' => $storedPhotos[0] ?? null,
                'received_by_user_id' => $gudang->id,
            ]);

            if ($lockedOrder->isReconditioning()) {
                WarehouseSorting::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'warehouse_receipt_id' => $receipt->id,
                    'qty_passed' => $qtyGood,
                    'qty_special_repair' => $qtyDent,
                    'qty_rejected' => $qtyReject,
                    'unit' => $lockedOrder->requested_unit ?: 'Drum',
                    'sorting_notes' => $notes,
                    'rejected_reason' => $qtyReject > 0 ? ($notes ?: 'Drum bocor/rusak parah pada pemeriksaan Gate-In.') : null,
                    'photo_path' => $storedPhotos[0] ?? null,
                    'sorted_by_user_id' => $gudang->id,
                    'sorted_at' => now(),
                ]);
            }

            $discrepancy = null;

            if (! $hasDiscrepancy) {
                $lockedOrder->update([
                    'license_plate' => $plateNumber,
                    'driver_name' => $driverName,
                    'order_status' => 'GATE_IN_VERIFIED',
                    'warehouse_status' => 'IN_PROGRESS',
                ]);

                $summary = "Gate-In Armada {$plateNumber} (Sopir: {$driverName}) terverifikasi sesuai PO ({$totalPhysical} {$lockedOrder->requested_unit}: {$qtyGood} baik, {$qtyDent} penyok, 0 reject). Masuk antrean produksi.";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldValues['order_status'],
                    'to_status' => 'GATE_IN_VERIFIED',
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_GATE_IN_VERIFIED',
                    auditable: $gateLog,
                    oldValues: $oldValues,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'plate_number' => $plateNumber,
                        'driver_name' => $driverName,
                        'qty_good' => $qtyGood,
                        'qty_dent' => $qtyDent,
                        'qty_reject' => $qtyReject,
                        'total_physical_qty' => $totalPhysical,
                        'order_status' => 'GATE_IN_VERIFIED',
                        'summary' => $summary,
                    ],
                    reason: $notes,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: 'Gate-In Terverifikasi & Masuk Produksi',
                    message: "{$lockedOrder->order_number}: Armada {$plateNumber} diterima ({$totalPhysical} {$lockedOrder->requested_unit}) tanpa selisih.",
                    tone: 'success',
                    icon: 'check-circle',
                );
            } else {
                $baNumber = $this->generateUniqueCode(
                    DiscrepancyReport::class,
                    'ba_number',
                    'BA-PKDI-'.Carbon::now('Asia/Jakarta')->format('Ym').'-',
                    4
                );

                $discrepancy = DiscrepancyReport::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'gate_log_id' => $gateLog->id,
                    'ba_number' => $baNumber,
                    'po_qty' => $poQty,
                    'actual_good_qty' => $qtyGood,
                    'actual_dent_qty' => $qtyDent,
                    'actual_reject_qty' => $qtyReject,
                    'total_physical_qty' => $totalPhysical,
                    'evidence_urls' => $storedPhotos,
                    'notes' => $notes ?: "Ditemukan selisih fisik (Aktual {$totalPhysical} vs PO {$poQty}) atau drum reject ({$qtyReject} unit).",
                    'status' => DiscrepancyReport::STATUS_PENDING,
                    'magic_token' => Str::random(48),
                    'created_by_user_id' => $gudang->id,
                ]);

                $lockedOrder->update([
                    'license_plate' => $plateNumber,
                    'driver_name' => $driverName,
                    'order_status' => 'DISCREPANCY_PENDING',
                    'warehouse_status' => 'DISCREPANCY_PENDING',
                ]);

                $summary = "Gate-In Armada {$plateNumber} menemukan selisih/reject (PO: {$poQty}, Aktual: {$totalPhysical} [Baik {$qtyGood}, Penyok {$qtyDent}, Reject {$qtyReject}]). Berita Acara {$baNumber} diterbitkan dan menunggu ACC Client.";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldValues['order_status'],
                    'to_status' => 'DISCREPANCY_PENDING',
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'DISCREPANCY_REPORT_CREATED',
                    auditable: $discrepancy,
                    oldValues: $oldValues,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'ba_number' => $baNumber,
                        'po_qty' => $poQty,
                        'qty_good' => $qtyGood,
                        'qty_dent' => $qtyDent,
                        'qty_reject' => $qtyReject,
                        'total_physical_qty' => $totalPhysical,
                        'order_status' => 'DISCREPANCY_PENDING',
                        'summary' => $summary,
                    ],
                    reason: $notes,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: "Berita Acara Selisih {$baNumber} Menunggu ACC",
                    message: "Ditemukan selisih/reject pada pesanan {$lockedOrder->order_number} (PO {$poQty} vs Aktual {$totalPhysical}, Reject {$qtyReject}). Klik untuk meninjau foto bukti dan melakukan ACC digital.",
                    tone: 'danger',
                    icon: 'alert-triangle',
                    actionUrl: route('client.orders.show', $lockedOrder->order_slug, false).'#ba-selisih',
                );
            }

            return [
                'gate_log' => $gateLog,
                'discrepancy' => $discrepancy,
                'order' => $lockedOrder->fresh(),
            ];
        });
    }

    /**
     * FASE 1 (2.1): Gate-Out Armada oleh Kepala Gudang.
     *
     * @param  array<string, mixed>  $validated
     */
    public function recordGateOut(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): GateLog {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $validated, $request): GateLog {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $plateNumber = strtoupper(preg_replace('/\s+/', ' ', trim((string) $validated['plate_number'])) ?? '');
            $driverName = trim((string) $validated['driver_name']);
            $qtyOut = (int) ($validated['qty_good'] ?? $lockedOrder->result?->completed_quantity ?? $lockedOrder->latestGateIn?->billableQuantity() ?? $lockedOrder->requested_quantity ?? 1);
            $notes = isset($validated['notes']) && trim((string) $validated['notes']) !== '' ? trim((string) $validated['notes']) : null;

            $gateOut = GateLog::query()->create([
                'service_order_id' => $lockedOrder->id,
                'type' => GateLog::TYPE_OUT,
                'plate_number' => $plateNumber,
                'driver_name' => $driverName,
                'qty_good' => $qtyOut,
                'qty_dent' => 0,
                'qty_reject' => 0,
                'total_physical_qty' => $qtyOut,
                'notes' => $notes,
                'recorded_by_user_id' => $gudang->id,
                'recorded_at' => now(),
            ]);

            $oldStatus = $lockedOrder->order_status;
            $lockedOrder->update([
                'delivered_quantity' => $qtyOut,
                'delivered_at' => now(),
                'license_plate' => $plateNumber,
                'driver_name' => $driverName,
            ]);

            $summary = "Gate-Out Armada {$plateNumber} (Sopir: {$driverName}) keluar membawa {$qtyOut} {$lockedOrder->requested_unit}.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'DELIVERY',
                'from_status' => $oldStatus,
                'to_status' => $lockedOrder->order_status,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'WAREHOUSE_GATE_OUT_RECORDED',
                auditable: $gateOut,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'plate_number' => $plateNumber,
                    'driver_name' => $driverName,
                    'qty_out' => $qtyOut,
                    'summary' => $summary,
                ],
                reason: $notes,
                actor: $gudang,
                request: $request,
            );

            return $gateOut;
        });
    }

    /**
     * FASE 1 (2.3): Unggah Dokumen Kepatuhan (SURAT_JALAN, MANIFEST_B3, BAST).
     *
     * @param  array<string, mixed>  $validated
     */
    public function uploadComplianceDoc(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        UploadedFile $file,
        ?Request $request = null,
    ): ComplianceDoc {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        $docType = strtoupper(trim((string) $validated['doc_type']));
        if (! in_array($docType, ComplianceDoc::TYPES, true)) {
            throw ValidationException::withMessages([
                'doc_type' => 'Jenis dokumen kepatuhan harus SURAT_JALAN, MANIFEST_B3, atau BAST.',
            ]);
        }

        $storedPath = $file->store('compliance-docs', 'local');
        $originalName = $file->getClientOriginalName();
        $docNumber = isset($validated['document_number']) && trim((string) $validated['document_number']) !== ''
            ? trim((string) $validated['document_number'])
            : null;
        $notes = isset($validated['notes']) && trim((string) $validated['notes']) !== ''
            ? trim((string) $validated['notes'])
            : null;

        return DB::transaction(function () use ($gudang, $order, $docType, $docNumber, $storedPath, $originalName, $notes, $request): ComplianceDoc {
            $doc = ComplianceDoc::query()->updateOrCreate(
                [
                    'service_order_id' => $order->id,
                    'doc_type' => $docType,
                ],
                [
                    'document_number' => $docNumber,
                    'file_path' => $storedPath,
                    'original_filename' => $originalName,
                    'is_verified' => true,
                    'uploaded_by_user_id' => $gudang->id,
                    'verified_by_user_id' => $gudang->id,
                    'verified_at' => now(),
                    'notes' => $notes,
                ]
            );

            $label = $doc->typeLabel();
            $summary = "Dokumen kepatuhan {$label}".($docNumber ? " ({$docNumber})" : '')." diunggah dan diverifikasi oleh Kepala Gudang.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $order->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $order->order_status,
                'to_status' => $order->order_status,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'COMPLIANCE_DOC_UPLOADED',
                auditable: $doc,
                newValues: [
                    'order_number' => $order->order_number,
                    'doc_type' => $docType,
                    'document_number' => $docNumber,
                    'original_filename' => $originalName,
                    'is_verified' => true,
                    'summary' => $summary,
                ],
                reason: $notes,
                actor: $gudang,
                request: $request,
            );

            return $doc;
        });
    }

    /**
     * FASE 1 (2.3): Auto-Generate Sertifikat Rekondisi PDF resmi + QR Code Verifikasi.
     */
    public function issueReconditionCert(
        User $gudang,
        ServiceOrder $order,
        ReportExportService $exporter,
        ?Request $request = null,
    ): ReconditionCert {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $exporter, $request): ReconditionCert {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['latestGateIn', 'latestSorting', 'result', 'reconditionCert', 'clientCompany'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->hasPendingDiscrepancy() && ! $lockedOrder->hasManagerOverride()) {
                throw ValidationException::withMessages([
                    'discrepancy' => 'Sertifikat Rekondisi tidak dapat diterbitkan selama Berita Acara Selisih belum di-ACC oleh Client.',
                ]);
            }

            $qcPassedQty = $lockedOrder->latestGateIn
                ? $lockedOrder->latestGateIn->billableQuantity()
                : ($lockedOrder->result?->completed_quantity
                    ?? $lockedOrder->latestSorting?->processableQuantity()
                    ?? (int) ($lockedOrder->requested_quantity ?? 0));

            if ($qcPassedQty <= 0) {
                throw ValidationException::withMessages([
                    'qc_passed_qty' => 'Tidak terdapat kuantitas drum lolos QC untuk diterbitkan Sertifikat Rekondisi.',
                ]);
            }

            $existing = $lockedOrder->reconditionCert;
            $certNumber = $existing?->cert_number ?? $this->generateUniqueCode(
                ReconditionCert::class,
                'cert_number',
                'CERT-PKDI-'.Carbon::now('Asia/Jakarta')->format('Ym').'-',
                4
            );
            $qrHash = $existing?->qr_hash ?? hash('sha256', $certNumber.'|'.$lockedOrder->order_number.'|'.Str::random(24));

            $cert = ReconditionCert::query()->updateOrCreate(
                ['service_order_id' => $lockedOrder->id],
                [
                    'cert_number' => $certNumber,
                    'qr_hash' => $qrHash,
                    'qc_passed_qty' => $qcPassedQty,
                    'completion_date' => now('Asia/Jakarta')->toDateString(),
                    'issued_by_user_id' => $gudang->id,
                    'issued_at' => now(),
                ]
            );

            $pdfBinary = $exporter->buildReconditionCertPdfBinary($cert);
            $pdfPath = 'recondition-certs/'.$certNumber.'.pdf';
            Storage::disk('local')->put($pdfPath, $pdfBinary);
            $cert->update(['pdf_path' => $pdfPath]);

            $summary = "Sertifikat Rekondisi {$certNumber} diterbitkan untuk {$qcPassedQty} {$lockedOrder->requested_unit} lolos QC lengkap dengan QR Code Verifikasi.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $lockedOrder->order_status,
                'to_status' => $lockedOrder->order_status,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'RECONDITION_CERT_ISSUED',
                auditable: $cert,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'cert_number' => $certNumber,
                    'qr_hash' => $qrHash,
                    'qc_passed_qty' => $qcPassedQty,
                    'summary' => $summary,
                ],
                actor: $gudang,
                request: $request,
            );

            return $cert->fresh();
        });
    }

    /**
     * FASE 1 (2.3) & System Invariant #2 & #3:
     * Tombol "Serah ke Keuangan" (READY_FOR_BILLING) — mengunci data kuantitas Gudang menjadi READ-ONLY.
     */
    public function handoverToFinance(
        User $gudang,
        ServiceOrder $order,
        ?Request $request = null,
    ): ServiceOrder {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $request): ServiceOrder {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['latestGateIn', 'latestSorting', 'result', 'billableTransaction', 'drumProduct', 'reconditioningService', 'complianceDocs', 'reconditionCert', 'discrepancyReports'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->is_locked) {
                throw ValidationException::withMessages([
                    'order_status' => 'Pesanan ini sudah diserahkan ke Keuangan dan data kuantitas sudah dikunci.',
                ]);
            }

            $checklist = $lockedOrder->complianceChecklist();

            if ($checklist['has_pending_discrepancy'] && ! $checklist['has_manager_override']) {
                throw ValidationException::withMessages([
                    'discrepancy' => 'Pesanan tidak dapat diserahkan ke Keuangan karena Berita Acara Selisih masih menunggu persetujuan (ACC) Client.',
                ]);
            }

            if (! $checklist['docs_valid'] && ! $checklist['has_manager_override']) {
                $missing = collect($checklist['items'])
                    ->filter(static fn (array $i): bool => $i['required'] && ! $i['valid'])
                    ->pluck('label')
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'compliance_docs' => "Tidak dapat menyerahkan ke Keuangan sebelum dokumen wajib lengkap: {$missing}.",
                ]);
            }

            $gateIn = $lockedOrder->latestGateIn;
            $billableQty = $gateIn
                ? $gateIn->billableQuantity()
                : ($lockedOrder->result?->completed_quantity
                    ?? $lockedOrder->latestSorting?->processableQuantity()
                    ?? (int) ($lockedOrder->requested_quantity ?? 0));
            $rejectQty = $gateIn
                ? (int) $gateIn->qty_reject
                : (int) ($lockedOrder->result?->unprocessed_or_rejected_quantity ?? $lockedOrder->latestSorting?->qty_rejected ?? 0);

            if ($billableQty <= 0) {
                throw ValidationException::withMessages([
                    'billable_qty' => 'Kuantitas drum layak tagih (Good + Dent) harus lebih dari 0.',
                ]);
            }

            $unit = $lockedOrder->requested_unit ?: 'Drum';
            $productSnapshot = $lockedOrder->drumProduct?->name ?? $lockedOrder->service_category ?? 'Drum';
            $serviceSnapshot = $lockedOrder->isWasteTransport()
                ? 'Pengangkutan Limbah'
                : ($lockedOrder->reconditioningService?->name ?? $lockedOrder->service_type ?? 'Laundry');

            $oldValues = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
                'billing_status' => $lockedOrder->billing_status,
                'is_locked' => (bool) $lockedOrder->is_locked,
            ];

            ServiceResult::query()->updateOrCreate(
                ['service_order_id' => $lockedOrder->id],
                [
                    'reconditioning_service_id' => $lockedOrder->reconditioning_service_id,
                    'actual_service_type' => $serviceSnapshot,
                    'completed_quantity' => $billableQty,
                    'unprocessed_or_rejected_quantity' => $rejectQty,
                    'unit' => $unit,
                    'result_notes' => $lockedOrder->result?->result_notes ?: "Diserahkan ke Keuangan melalui Gatekeeping Dokumen PRD v2.1 ({$billableQty} {$unit} layak tagih, {$rejectQty} {$unit} reject).",
                    'completed_by_user_id' => $gudang->id,
                    'completed_at' => now(),
                ]
            );

            $existingBillable = $lockedOrder->billableTransaction;
            $billable = BillableTransaction::query()->updateOrCreate(
                ['service_order_id' => $lockedOrder->id],
                [
                    'transaction_code' => $existingBillable?->transaction_code ?? $this->generateUniqueCode(
                        BillableTransaction::class,
                        'transaction_code',
                        'BTX-'.Carbon::now('Asia/Jakarta')->format('Ymd').'-',
                        3
                    ),
                    'client_company_id' => $lockedOrder->client_company_id,
                    'transaction_type' => $lockedOrder->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
                    'drum_product_id' => $lockedOrder->drum_product_id,
                    'reconditioning_service_id' => $lockedOrder->reconditioning_service_id,
                    'product_name_snapshot' => $productSnapshot,
                    'service_name_snapshot' => $serviceSnapshot,
                    'billable_quantity' => $billableQty,
                    'rejected_quantity' => $rejectQty,
                    'unit' => $unit,
                    'billing_status' => BillableTransaction::STATUS_READY_TO_BILL,
                    'ready_at' => now(),
                    'created_by_user_id' => $gudang->id,
                    'notes' => "Tervalidasi dokumen kepatuhan & Gate-In (Good+Dent = {$billableQty} {$unit}).",
                ]
            );

            $lockedOrder->update([
                'order_status' => 'READY_FOR_BILLING',
                'warehouse_status' => 'COMPLETED',
                'billing_status' => 'READY_TO_BILL',
                'is_locked' => true,
                'handed_over_to_finance_at' => now(),
            ]);

            $summary = "Pesanan {$lockedOrder->order_number} diserahkan ke Keuangan (READY_FOR_BILLING). Data kuantitas ({$billableQty} {$unit} layak tagih) kini terkunci (READ-ONLY) untuk Gudang.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $oldValues['order_status'],
                'to_status' => 'READY_FOR_BILLING',
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'ORDER_HANDED_OVER_TO_FINANCE',
                auditable: $lockedOrder,
                oldValues: $oldValues,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'order_status' => 'READY_FOR_BILLING',
                    'billing_status' => 'READY_TO_BILL',
                    'is_locked' => true,
                    'billable_quantity' => $billableQty,
                    'rejected_quantity' => $rejectQty,
                    'billable_transaction_code' => $billable->transaction_code,
                    'summary' => $summary,
                ],
                actor: $gudang,
                request: $request,
            );

            $this->notifyRole(
                role: User::ROLE_KEUANGAN,
                order: $lockedOrder,
                title: 'Pesanan Siap Ditagihkan (Dokumen Kepatuhan Valid)',
                message: "{$lockedOrder->order_number} ({$billable->transaction_code}) · {$billableQty} {$unit} {$productSnapshot} siap diterbitkan Invoice.",
                tone: 'success',
                icon: 'receipt',
                actionUrl: route('finance.billable.show', $lockedOrder->order_slug, false),
            );

            return $lockedOrder->fresh();
        });
    }

    /**
     * FASE 3 (4.2): Persetujuan Digital Berita Acara Selisih (ACC) oleh Client.
     * Mencatat IP Address, timestamp, dan nama akun penyetuju, lalu membuka kunci transaksi.
     */
    public function approveDiscrepancyReport(
        User $client,
        DiscrepancyReport $report,
        ?Request $request = null,
    ): DiscrepancyReport {
        return DB::transaction(function () use ($client, $report, $request): DiscrepancyReport {
            /** @var DiscrepancyReport $lockedReport */
            $lockedReport = DiscrepancyReport::query()
                ->with('serviceOrder')
                ->whereKey($report->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = $lockedReport->serviceOrder;
            abort_if(! $order, 404);

            if (! $client->isClient() || (int) $client->client_company_id !== (int) $order->client_company_id) {
                abort(403, 'Anda tidak berwenang menyetujui Berita Acara Selisih perusahaan lain.');
            }

            if ($lockedReport->isApproved()) {
                return $lockedReport;
            }

            $oldValues = [
                'status' => $lockedReport->status,
                'order_status' => $order->order_status,
            ];

            $ip = $request?->ip() ?? '127.0.0.1';
            $now = now();

            $lockedReport->update([
                'status' => DiscrepancyReport::STATUS_APPROVED,
                'approved_by_user_id' => $client->id,
                'approved_by_name' => $client->name,
                'approved_ip' => $ip,
                'approved_at' => $now,
            ]);

            $order->update([
                'order_status' => 'GATE_IN_VERIFIED',
                'warehouse_status' => 'IN_PROGRESS',
            ]);

            $billableQty = $lockedReport->actual_good_qty + $lockedReport->actual_dent_qty;
            $summary = "Berita Acara Selisih {$lockedReport->ba_number} disetujui (ACC) secara digital oleh {$client->name} (IP: {$ip}). Transaksi dibuka untuk proses produksi ({$billableQty} {$order->requested_unit} layak proses).";

            OrderStatusHistory::query()->create([
                'service_order_id' => $order->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => 'DISCREPANCY_PENDING',
                'to_status' => 'GATE_IN_VERIFIED',
                'changed_by_user_id' => $client->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'DISCREPANCY_REPORT_APPROVED',
                auditable: $lockedReport,
                oldValues: $oldValues,
                newValues: [
                    'order_number' => $order->order_number,
                    'ba_number' => $lockedReport->ba_number,
                    'status' => DiscrepancyReport::STATUS_APPROVED,
                    'approved_by_name' => $client->name,
                    'approved_ip' => $ip,
                    'approved_at' => $now->toIso8601String(),
                    'order_status' => 'GATE_IN_VERIFIED',
                    'summary' => $summary,
                ],
                actor: $client,
                request: $request,
            );

            $this->notifyRole(
                role: User::ROLE_KEPALA_GUDANG,
                order: $order,
                title: "BA Selisih {$lockedReport->ba_number} Di-ACC Client",
                message: "{$client->name} menyetujui BA Selisih untuk {$order->order_number}. Silakan lanjutkan proses produksi & dokumen kepatuhan.",
                tone: 'success',
                icon: 'check-circle',
                actionUrl: route('warehouse.orders.show', $order->order_slug, false),
            );

            return $lockedReport->fresh();
        });
    }

    /**
     * FASE 2 (3.1): Mencari tarif kontrak khusus per klien (client_contracts), fallback ke service_tariffs.
     *
     * @return array{unit_price: float, reject_disposal_fee: float, ppn_rate: float, pph23_rate: float, source: string, contract_id: int|null}
     */
    public function resolveContractPricing(ServiceOrder $order): array
    {
        $serviceName = $order->isWasteTransport()
            ? 'Pengangkutan Limbah'
            : ($order->reconditioningService?->name ?? $order->service_type ?? 'Laundry');

        $contract = ClientContract::query()
            ->active()
            ->where('client_company_id', $order->client_company_id)
            ->where(function ($q) use ($serviceName): void {
                $q->whereRaw('LOWER(service_type) = ?', [mb_strtolower($serviceName)])
                    ->orWhereRaw('LOWER(service_type) LIKE ?', ['%'.mb_strtolower($serviceName).'%']);
            })
            ->orderByRaw('CASE WHEN drum_product_id = ? THEN 0 ELSE 1 END', [(int) $order->drum_product_id])
            ->first();

        if ($contract) {
            return [
                'unit_price' => (float) $contract->unit_price,
                'reject_disposal_fee' => (float) $contract->reject_disposal_fee,
                'ppn_rate' => (float) $contract->ppn_rate,
                'pph23_rate' => (float) $contract->pph23_rate,
                'source' => 'Tarif Kontrak Client',
                'contract_id' => $contract->id,
            ];
        }

        $productName = $order->drumProduct?->name ?? $order->service_category ?? '';
        $tariff = ServiceTariff::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(service_type) = ?', [mb_strtolower($serviceName)])
            ->orderByRaw('CASE WHEN LOWER(COALESCE(service_category, \'\')) = ? THEN 0 ELSE 1 END', [mb_strtolower($productName)])
            ->first();

        return [
            'unit_price' => (float) ($tariff?->unit_price ?? 65000),
            'reject_disposal_fee' => 0.0,
            'ppn_rate' => 11.0,
            'pph23_rate' => 2.0,
            'source' => $tariff ? "Tarif Standar ({$tariff->tariff_code})" : 'Tarif Default',
            'contract_id' => null,
        ];
    }

    /**
     * FASE 2 (3.2): Unggah PDF e-Faktur oleh Keuangan pada record Invoice.
     */
    public function uploadEfaktur(
        User $keuangan,
        Invoice $invoice,
        ?string $efakturNumber,
        UploadedFile $file,
        ?Request $request = null,
    ): Invoice {
        Gate::forUser($keuangan)->authorize('update', $invoice);

        $path = $file->store('invoice-efaktur', 'local');

        return DB::transaction(function () use ($keuangan, $invoice, $efakturNumber, $path, $request): Invoice {
            $oldValues = [
                'efaktur_number' => $invoice->efaktur_number,
                'efaktur_path' => $invoice->efaktur_path,
            ];

            $invoice->update([
                'efaktur_number' => $efakturNumber ? trim($efakturNumber) : $invoice->efaktur_number,
                'efaktur_path' => $path,
                'efaktur_uploaded_at' => now(),
            ]);

            AuditLogger::record(
                action: 'INVOICE_EFAKTUR_UPLOADED',
                auditable: $invoice,
                oldValues: $oldValues,
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'efaktur_number' => $invoice->efaktur_number,
                    'efaktur_path' => $path,
                    'summary' => "Dokumen e-Faktur Pajak untuk {$invoice->invoice_number} diunggah oleh Keuangan.",
                ],
                actor: $keuangan,
                request: $request,
            );

            return $invoice->fresh();
        });
    }

    /**
     * FASE 4 (5.1): Penagihan Parsial (Split Billing) oleh Keuangan atau Owner/Super Admin.
     * Memecah tagihan untuk jumlah drum yang sudah lolos QC/disetujui tanpa menunggu sengketa selisih selesai.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createSplitInvoice(
        User $actor,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): Invoice {
        abort_unless($actor->is_active && $actor->hasRole(User::ROLE_KEUANGAN, User::ROLE_OWNER), 403);

        return DB::transaction(function () use ($actor, $order, $validated, $request): Invoice {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['latestGateIn', 'invoices', 'clientCompany', 'drumProduct', 'reconditioningService'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $splitQty = (int) $validated['split_qty'];
            $maxAllowed = $lockedOrder->latestGateIn
                ? $lockedOrder->latestGateIn->billableQuantity()
                : (int) ($lockedOrder->requested_quantity ?? 0);
            $alreadyBilled = (int) $lockedOrder->invoices()
                ->where('status', '!=', 'VOIDED')
                ->sum('billed_qty');

            $remaining = max(0, $maxAllowed - $alreadyBilled);
            if ($splitQty <= 0 || ($maxAllowed > 0 && $splitQty > $remaining)) {
                throw ValidationException::withMessages([
                    'split_qty' => "Kuantitas tagih parsial harus antara 1 dan {$remaining} {$lockedOrder->requested_unit} (sisa yang belum ditagihkan).",
                ]);
            }

            $pricing = $this->resolveContractPricing($lockedOrder);
            $unitPrice = isset($validated['unit_price']) && $validated['unit_price'] !== ''
                ? (float) $validated['unit_price']
                : $pricing['unit_price'];
            $additionalFees = isset($validated['additional_fees']) && $validated['additional_fees'] !== ''
                ? (float) $validated['additional_fees']
                : 0.0;
            $ppnRate = isset($validated['ppn_rate']) && $validated['ppn_rate'] !== ''
                ? (float) $validated['ppn_rate']
                : $pricing['ppn_rate'];
            $pph23Rate = isset($validated['pph23_rate']) && $validated['pph23_rate'] !== ''
                ? (float) $validated['pph23_rate']
                : $pricing['pph23_rate'];

            $lineTotal = round($splitQty * $unitPrice, 2);
            $subtotal = round($lineTotal + $additionalFees, 2);
            $ppnAmount = round($subtotal * ($ppnRate / 100), 2);
            $pph23Amount = round($subtotal * ($pph23Rate / 100), 2);
            $netReceivable = round($subtotal + $ppnAmount - $pph23Amount, 2);

            $splitIndex = $lockedOrder->invoices()->where('is_split_invoice', true)->count() + 1;
            $splitLabel = trim((string) ($validated['split_label'] ?? "Tagihan Parsial #{$splitIndex} ({$splitQty} {$lockedOrder->requested_unit})"));

            $ym = Carbon::now('Asia/Jakarta')->format('Y/m');
            $seq = Invoice::query()->lockForUpdate()->count() + 15;
            while (true) {
                $invoiceNumber = sprintf('INV/PKDI/%s/%04d-S%d', $ym, $seq, $splitIndex);
                $invoiceSlug = str_replace('/', '-', $invoiceNumber);
                if (! Invoice::query()->where('invoice_number', $invoiceNumber)->orWhere('invoice_slug', $invoiceSlug)->exists()) {
                    break;
                }
                $seq++;
            }

            $qrHash = hash('sha256', $invoiceNumber.'|'.$lockedOrder->order_number.'|'.Str::random(24));

            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'invoice_slug' => $invoiceSlug,
                'service_order_id' => $lockedOrder->id,
                'client_company_id' => $lockedOrder->client_company_id,
                'revision_number' => 0,
                'subtotal_amount' => $subtotal,
                'additional_fees' => $additionalFees,
                'adjustment_amount' => 0,
                'tax_amount' => $ppnAmount,
                'ppn_rate' => $ppnRate,
                'ppn_amount' => $ppnAmount,
                'pph23_rate' => $pph23Rate,
                'pph23_amount' => $pph23Amount,
                'net_receivable' => $netReceivable,
                'total_amount' => $netReceivable,
                'paid_amount' => 0,
                'balance_due' => $netReceivable,
                'status' => 'ISSUED',
                'issue_date' => now('Asia/Jakarta')->toDateString(),
                'due_date' => now('Asia/Jakarta')->addDays(14)->toDateString(),
                'payment_instructions' => 'Transfer Net Receivable ke Rekening Resmi PT Pulo Kencana Drum Industri sesuai nomor invoice parsial.',
                'notes' => "[Split Billing] {$splitLabel}. ".trim((string) ($validated['notes'] ?? '')),
                'is_split_invoice' => true,
                'split_label' => $splitLabel,
                'billed_qty' => $splitQty,
                'qr_hash' => $qrHash,
                'created_by_user_id' => $actor->id,
                'issued_at' => now(),
            ]);

            $svcLabel = $lockedOrder->isWasteTransport() ? 'Pengangkutan Limbah' : ($lockedOrder->reconditioningService?->name ?? $lockedOrder->service_type);
            $prodLabel = $lockedOrder->drumProduct?->name ?? $lockedOrder->service_category;

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'transaction_type' => $lockedOrder->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
                'description' => "{$splitLabel} — {$svcLabel} ({$prodLabel})",
                'quantity' => $splitQty,
                'unit' => $lockedOrder->requested_unit ?: 'Drum',
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            $lockedOrder->update([
                'billing_status' => 'INVOICED',
            ]);

            $summary = "Split Billing diterbitkan: {$invoiceNumber} untuk {$splitQty} {$lockedOrder->requested_unit} (Net Receivable Rp ".number_format($netReceivable, 0, ',', '.').').';

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'INVOICE',
                'from_status' => $lockedOrder->order_status,
                'to_status' => $lockedOrder->order_status,
                'changed_by_user_id' => $actor->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'SPLIT_BILLING_ISSUED',
                auditable: $invoice,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'invoice_number' => $invoiceNumber,
                    'split_label' => $splitLabel,
                    'billed_qty' => $splitQty,
                    'subtotal' => $subtotal,
                    'ppn_amount' => $ppnAmount,
                    'pph23_amount' => $pph23Amount,
                    'net_receivable' => $netReceivable,
                    'summary' => $summary,
                ],
                reason: $splitLabel,
                actor: $actor,
                request: $request,
            );

            $this->notifyClient(
                order: $lockedOrder,
                title: "Tagihan Parsial {$invoiceNumber} Terbit",
                message: "{$splitLabel} sebesar Rp ".number_format($netReceivable, 0, ',', '.')." telah diterbitkan ke Portal Client.",
                tone: 'info',
                icon: 'receipt',
                actionUrl: route('client.invoices.show', $invoice->invoice_slug, false),
            );

            return $invoice;
        });
    }

    /**
     * FASE 4 (5.1): Manager Override oleh Owner / Direktur untuk membuka kunci transaksi tertahan dokumen.
     * Wajib mengisi override_reason minimal 20 karakter dan tercatat permanen di audit_logs.
     */
    public function applyManagerOverride(
        User $owner,
        ServiceOrder $order,
        string $overrideReason,
        ?Request $request = null,
    ): ServiceOrder {
        abort_unless($owner->is_active && $owner->hasRole(User::ROLE_OWNER), 403, 'Hanya Direktur / Owner yang berwenang melakukan Manager Override.');

        $reason = trim($overrideReason);
        if (mb_strlen($reason) < 20) {
            throw ValidationException::withMessages([
                'override_reason' => 'Alasan Manager Override wajib diisi minimal 20 karakter sebagai pertanggungjawaban audit.',
            ]);
        }

        return DB::transaction(function () use ($owner, $order, $reason, $request): ServiceOrder {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['latestGateIn', 'result', 'billableTransaction', 'drumProduct', 'reconditioningService'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldValues = [
                'order_status' => $lockedOrder->order_status,
                'billing_status' => $lockedOrder->billing_status,
                'is_locked' => (bool) $lockedOrder->is_locked,
                'manager_override_at' => $lockedOrder->manager_override_at?->toIso8601String(),
            ];

            $lockedOrder->update([
                'manager_override_by_user_id' => $owner->id,
                'manager_override_at' => now(),
                'manager_override_reason' => $reason,
                'billing_status' => 'READY_TO_BILL',
                'warehouse_status' => 'COMPLETED',
            ]);

            $billableQty = $lockedOrder->latestGateIn
                ? $lockedOrder->latestGateIn->billableQuantity()
                : ($lockedOrder->result?->completed_quantity ?? (int) ($lockedOrder->requested_quantity ?? 1));
            $rejectQty = $lockedOrder->latestGateIn ? (int) $lockedOrder->latestGateIn->qty_reject : 0;
            $unit = $lockedOrder->requested_unit ?: 'Drum';
            $productSnapshot = $lockedOrder->drumProduct?->name ?? $lockedOrder->service_category ?? 'Drum';
            $serviceSnapshot = $lockedOrder->isWasteTransport() ? 'Pengangkutan Limbah' : ($lockedOrder->reconditioningService?->name ?? $lockedOrder->service_type ?? 'Laundry');

            ServiceResult::query()->firstOrCreate(
                ['service_order_id' => $lockedOrder->id],
                [
                    'reconditioning_service_id' => $lockedOrder->reconditioning_service_id,
                    'actual_service_type' => $serviceSnapshot,
                    'completed_quantity' => max(1, $billableQty),
                    'unprocessed_or_rejected_quantity' => $rejectQty,
                    'unit' => $unit,
                    'result_notes' => "[Manager Override oleh {$owner->name}] {$reason}",
                    'completed_by_user_id' => $owner->id,
                    'completed_at' => now(),
                ]
            );

            BillableTransaction::query()->firstOrCreate(
                ['service_order_id' => $lockedOrder->id],
                [
                    'transaction_code' => $this->generateUniqueCode(
                        BillableTransaction::class,
                        'transaction_code',
                        'BTX-'.Carbon::now('Asia/Jakarta')->format('Ymd').'-',
                        3
                    ),
                    'client_company_id' => $lockedOrder->client_company_id,
                    'transaction_type' => $lockedOrder->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
                    'drum_product_id' => $lockedOrder->drum_product_id,
                    'reconditioning_service_id' => $lockedOrder->reconditioning_service_id,
                    'product_name_snapshot' => $productSnapshot,
                    'service_name_snapshot' => $serviceSnapshot,
                    'billable_quantity' => max(1, $billableQty),
                    'rejected_quantity' => $rejectQty,
                    'unit' => $unit,
                    'billing_status' => BillableTransaction::STATUS_READY_TO_BILL,
                    'ready_at' => now(),
                    'created_by_user_id' => $owner->id,
                    'notes' => "[Manager Override] {$reason}",
                ]
            );

            $summary = "Manager Override diterapkan oleh {$owner->name} pada {$lockedOrder->order_number}. Kunci dokumen dibuka untuk penagihan. Alasan: {$reason}";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'ORDER',
                'from_status' => $oldValues['order_status'],
                'to_status' => $lockedOrder->order_status,
                'changed_by_user_id' => $owner->id,
                'notes' => $summary,
                'is_visible_to_client' => false,
            ]);

            AuditLogger::record(
                action: 'MANAGER_OVERRIDE_APPLIED',
                auditable: $lockedOrder,
                oldValues: $oldValues,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'manager_override_by' => $owner->name,
                    'manager_override_at' => now()->toIso8601String(),
                    'override_reason' => $reason,
                    'billing_status' => 'READY_TO_BILL',
                    'summary' => $summary,
                ],
                reason: $reason,
                actor: $owner,
                request: $request,
            );

            return $lockedOrder->fresh();
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function generateUniqueCode(string $modelClass, string $column, string $prefix, int $digits): string
    {
        $seq = $modelClass::query()->lockForUpdate()->count() + 1;

        while (true) {
            $code = $prefix.str_pad((string) $seq, $digits, '0', STR_PAD_LEFT);
            if (! $modelClass::query()->where($column, $code)->exists()) {
                return $code;
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
        if (! \Illuminate\Support\Facades\Schema::hasTable('app_notifications')) {
            return;
        }

        $recipients = User::query()
            ->where('role', $role)
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

    private function notifyClient(
        ServiceOrder $order,
        string $title,
        string $message,
        string $tone,
        string $icon,
        ?string $actionUrl = null,
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('app_notifications')) {
            return;
        }

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
                'action_url' => $actionUrl ?? route('client.orders.show', $order->order_slug, false),
            ]);
        }
    }
}
