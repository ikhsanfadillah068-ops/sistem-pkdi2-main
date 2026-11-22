<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BillableTransaction;
use App\Models\DrumProduct;
use App\Models\OrderStatusHistory;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\ServiceProcess;
use App\Models\ServiceResult;
use App\Models\User;
use App\Models\VehicleGateLog;
use App\Models\WarehouseInspection;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseSorting;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    /**
     * Client membuat pesanan Penjualan Drum atau Jasa Rekondisi Drum dan langsung diteruskan ke Kepala Gudang.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createOrder(
        User $client,
        array $validated,
        ?UploadedFile $attachment = null,
        ?UploadedFile $poAttachment = null,
        ?Request $request = null,
    ): ServiceOrder {
        Gate::forUser($client)->authorize('create', ServiceOrder::class);

        $transactionType = strtoupper(trim((string) ($validated['transaction_type'] ?? ServiceOrder::TYPE_RECONDITIONING)));
        if ($transactionType === 'DRUM_SALE') {
            $transactionType = ServiceOrder::TYPE_WASTE_TRANSPORT;
        }
        if (! in_array($transactionType, ServiceOrder::TRANSACTION_TYPES, true)) {
            $transactionType = ServiceOrder::TYPE_RECONDITIONING;
        }

        $product = isset($validated['drum_product_id']) && $validated['drum_product_id'] !== ''
            ? DrumProduct::query()->find((int) $validated['drum_product_id'])
            : null;

        $reconditioningService = null;
        if ($transactionType === ServiceOrder::TYPE_RECONDITIONING
            && isset($validated['reconditioning_service_id'])
            && $validated['reconditioning_service_id'] !== '') {
            $reconditioningService = ReconditioningService::query()->find((int) $validated['reconditioning_service_id']);
        }

        $serviceCategory = $product?->name ?? trim((string) ($validated['service_category'] ?? 'Drum besi'));
        $serviceType = $transactionType === ServiceOrder::TYPE_WASTE_TRANSPORT
            ? 'Pengangkutan Limbah'
            : ($reconditioningService?->name ?? trim((string) ($validated['service_type'] ?? 'Laundry')));

        $cargoType = isset($validated['cargo_type']) && trim((string) $validated['cargo_type']) !== ''
            ? trim((string) $validated['cargo_type'])
            : ($transactionType === ServiceOrder::TYPE_WASTE_TRANSPORT ? 'Limbah & Kemasan Industri' : null);

        $originCompany = isset($validated['origin_company_name']) && trim((string) $validated['origin_company_name']) !== ''
            ? trim((string) $validated['origin_company_name'])
            : ($client->clientCompany?->company_name ?? $client->companyDisplay());

        $licensePlate = isset($validated['license_plate']) && trim((string) $validated['license_plate']) !== ''
            ? strtoupper(preg_replace('/\s+/', ' ', trim((string) $validated['license_plate'])) ?? '')
            : null;
        $vehicleType = isset($validated['vehicle_type']) && trim((string) $validated['vehicle_type']) !== ''
            ? trim((string) $validated['vehicle_type'])
            : null;
        $driverName = isset($validated['driver_name']) && trim((string) $validated['driver_name']) !== ''
            ? trim((string) $validated['driver_name'])
            : null;

        $requestedQty = isset($validated['requested_quantity']) && $validated['requested_quantity'] !== ''
            ? (int) $validated['requested_quantity']
            : null;
        $requestedUnit = isset($validated['requested_unit']) && trim((string) $validated['requested_unit']) !== ''
            ? trim((string) $validated['requested_unit'])
            : ($product?->default_unit ?? 'Drum');

        $poNumber = isset($validated['po_number']) && trim((string) $validated['po_number']) !== ''
            ? trim((string) $validated['po_number'])
            : null;
        $neededDate = isset($validated['needed_date']) && trim((string) $validated['needed_date']) !== ''
            ? trim((string) $validated['needed_date'])
            : null;
        $specificationNotes = isset($validated['specification_notes']) && trim((string) $validated['specification_notes']) !== ''
            ? trim((string) $validated['specification_notes'])
            : null;
        $clientNotes = isset($validated['client_notes']) && trim((string) $validated['client_notes']) !== ''
            ? trim((string) $validated['client_notes'])
            : null;

        // Cegah pengiriman ganda dalam 5 detik untuk kombinasi pesanan identik dari akun yang sama.
        $recentDuplicate = ServiceOrder::query()
            ->where('created_by_user_id', $client->id)
            ->where('transaction_type', $transactionType)
            ->where('service_category', $serviceCategory)
            ->where('service_type', $serviceType)
            ->where('requested_quantity', $requestedQty)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->first();

        if ($recentDuplicate) {
            return $recentDuplicate;
        }

        $attachmentPath = $attachment ? $attachment->store('order-attachments', 'local') : null;
        $poAttachmentPath = $poAttachment ? $poAttachment->store('order-po-documents', 'local') : null;

        return DB::transaction(function () use (
            $client,
            $transactionType,
            $product,
            $reconditioningService,
            $serviceCategory,
            $cargoType,
            $serviceType,
            $requestedQty,
            $requestedUnit,
            $poNumber,
            $poAttachmentPath,
            $neededDate,
            $originCompany,
            $vehicleType,
            $licensePlate,
            $driverName,
            $specificationNotes,
            $clientNotes,
            $attachmentPath,
            $request
        ): ServiceOrder {
            [$orderNumber, $orderSlug] = $this->generateUniqueOrderNumber();

            $order = ServiceOrder::query()->create([
                'order_number' => $orderNumber,
                'order_slug' => $orderSlug,
                'client_company_id' => $client->client_company_id,
                'created_by_user_id' => $client->id,
                'transaction_type' => $transactionType,
                'drum_product_id' => $product?->id,
                'reconditioning_service_id' => $reconditioningService?->id,
                'service_category' => $serviceCategory,
                'cargo_type' => $cargoType,
                'service_type' => $serviceType,
                'requested_quantity' => $requestedQty,
                'requested_unit' => $requestedUnit,
                'po_number' => $poNumber,
                'po_attachment_path' => $poAttachmentPath,
                'needed_date' => $neededDate,
                'origin_company_name' => $originCompany,
                'vehicle_type' => $vehicleType,
                'license_plate' => $licensePlate,
                'driver_name' => $driverName,
                'specification_notes' => $specificationNotes,
                'client_notes' => $clientNotes,
                'attachment_path' => $attachmentPath,
                'order_status' => 'UNDER_REVIEW',
                'gate_status' => 'NOT_REQUIRED',
                'warehouse_status' => 'PENDING_REVIEW',
                'billing_status' => 'NOT_READY',
                'payment_status' => 'UNPAID',
                'submitted_at' => now(),
            ]);

            $typeLabel = $order->transactionTypeLabel();
            $historyNote = "Pesanan {$order->order_number} ({$typeLabel}: {$serviceCategory}, {$serviceType}, {$requestedQty} {$requestedUnit}) diajukan oleh {$originCompany}. Langsung masuk antrean peninjauan Kepala Gudang.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $order->id,
                'track_type' => 'ORDER',
                'from_status' => 'SUBMITTED',
                'to_status' => 'UNDER_REVIEW',
                'changed_by_user_id' => $client->id,
                'notes' => $historyNote,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'ORDER_CREATED',
                auditable: $order,
                newValues: [
                    'order_number' => $order->order_number,
                    'transaction_type' => $order->transaction_type,
                    'product_name' => $serviceCategory,
                    'service_type' => $serviceType,
                    'order_status' => $order->order_status,
                    'warehouse_status' => $order->warehouse_status,
                    'billing_status' => $order->billing_status,
                    'po_number' => $poNumber,
                    'summary' => $historyNote,
                ],
                actor: $client,
                request: $request,
            );

            $this->notifyRole(
                role: User::ROLE_KEPALA_GUDANG,
                order: $order,
                title: "Pesanan {$typeLabel} baru masuk antrean Gudang",
                message: "{$order->order_number} · {$originCompany} ({$requestedQty} {$requestedUnit} {$serviceCategory})",
                tone: 'info',
                icon: 'clipboard-check',
                actionUrl: route('warehouse.orders.show', $order->order_slug, false),
            );

            return $order;
        });
    }

    /**
     * Pemeriksaan gerbang Satpam telah dikeluarkan dari alur digital PRD 2.0.
     *
     * @param  array<string, mixed>  $validated
     */
    public function inspectGateIn(
        User $satpam,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): VehicleGateLog {
        Gate::forUser($satpam)->authorize('inspectGate', $order);

        throw ValidationException::withMessages([
            'gate_status' => 'Alur pemeriksaan gerbang Satpam tidak lagi aktif pada sistem PKDI versi 2.0.',
        ]);
    }

    /**
     * Tinjauan operasional awal oleh Kepala Gudang:
     * - ACCEPT: Rekondisi -> WAITING_RECEIPT, Penjualan Drum -> PREPARING
     * - NEEDS_CLARIFICATION: memerlukan alasan minimal 5 karakter
     * - REJECT: membatalkan pesanan dengan alasan minimal 5 karakter
     *
     * @param  array<string, mixed>  $validated
     */
    public function reviewOrder(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): ServiceOrder {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $validated, $request): ServiceOrder {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->order_status, ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Pesanan ini tidak berada pada tahap peninjauan awal Gudang.',
                ]);
            }

            $decision = strtoupper(trim((string) ($validated['decision'] ?? 'ACCEPT')));
            if (! in_array($decision, ['ACCEPT', 'NEEDS_CLARIFICATION', 'REJECT'], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Keputusan peninjauan pesanan tidak valid.',
                ]);
            }

            $notes = isset($validated['review_notes']) ? trim((string) $validated['review_notes']) : '';
            $reason = isset($validated['decision_reason']) && trim((string) $validated['decision_reason']) !== ''
                ? trim((string) $validated['decision_reason'])
                : $notes;

            if (in_array($decision, ['NEEDS_CLARIFICATION', 'REJECT'], true) && mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'decision_reason' => 'Alasan klarifikasi atau penolakan wajib diisi minimal 5 karakter.',
                ]);
            }

            $oldStatus = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
            ];

            if ($decision === 'ACCEPT') {
                $nextOrderStatus = $lockedOrder->isWasteTransport() ? 'PREPARING' : 'WAITING_RECEIPT';
                $nextWarehouseStatus = $lockedOrder->isWasteTransport() ? 'PREPARING' : 'WAITING_RECEIPT';

                $lockedOrder->update([
                    'order_status' => $nextOrderStatus,
                    'warehouse_status' => $nextWarehouseStatus,
                ]);

                $summary = $lockedOrder->isWasteTransport()
                    ? 'Permintaan pengangkutan limbah disetujui Kepala Gudang dan siap dijadwalkan/diterima.'
                    : 'Permintaan rekondisi drum disetujui Kepala Gudang. Menunggu penerimaan fisik di Gudang.';

                if ($notes !== '') {
                    $summary .= " Catatan: {$notes}";
                }

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldStatus['order_status'],
                    'to_status' => $nextOrderStatus,
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_ORDER_ACCEPTED',
                    auditable: $lockedOrder,
                    oldValues: $oldStatus,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'order_status' => $nextOrderStatus,
                        'warehouse_status' => $nextWarehouseStatus,
                        'summary' => $summary,
                    ],
                    reason: $notes !== '' ? $notes : null,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: $lockedOrder->isDrumSale() ? 'Pesanan penjualan sedang disiapkan' : 'Pesanan rekondisi diterima Gudang',
                    message: "{$lockedOrder->order_number}: {$summary}",
                    tone: 'success',
                    icon: 'clipboard-check',
                );
            } elseif ($decision === 'NEEDS_CLARIFICATION') {
                $lockedOrder->update([
                    'order_status' => 'NEEDS_CLARIFICATION',
                    'warehouse_status' => 'CLARIFICATION_NEEDED',
                ]);

                $summary = "Kepala Gudang meminta klarifikasi pesanan: {$reason}";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldStatus['order_status'],
                    'to_status' => 'NEEDS_CLARIFICATION',
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_NEEDS_CLARIFICATION',
                    auditable: $lockedOrder,
                    oldValues: $oldStatus,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'order_status' => 'NEEDS_CLARIFICATION',
                        'warehouse_status' => 'CLARIFICATION_NEEDED',
                        'summary' => $summary,
                    ],
                    reason: $reason,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: 'Pesanan membutuhkan klarifikasi',
                    message: "{$lockedOrder->order_number}: {$reason}",
                    tone: 'warning',
                    icon: 'alert-triangle',
                );
            } else {
                $lockedOrder->update([
                    'order_status' => 'CANCELLED',
                    'warehouse_status' => 'REJECTED',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);

                $summary = "Pesanan ditolak Kepala Gudang. Alasan: {$reason}";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldStatus['order_status'],
                    'to_status' => 'CANCELLED',
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_ORDER_REJECTED',
                    auditable: $lockedOrder,
                    oldValues: $oldStatus,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'order_status' => 'CANCELLED',
                        'warehouse_status' => 'REJECTED',
                        'summary' => $summary,
                    ],
                    reason: $reason,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: 'Pesanan ditolak Kepala Gudang',
                    message: "{$lockedOrder->order_number}: {$reason}",
                    tone: 'danger',
                    icon: 'alert-triangle',
                );
            }

            return $lockedOrder->fresh();
        });
    }

    /**
     * Koordinasi pengangkutan dan perjalanan limbah/kemasan menuju PKDI (PRD v2.1 Bab 8.4):
     * - TRANSPORT_SCHEDULED: jadwal dan transporter/armada ditetapkan
     * - IN_TRANSIT: armada pengangkutan sedang dalam perjalanan menuju PKDI
     *
     * @param  array<string, mixed>  $validated
     */
    public function scheduleOrUpdateTransport(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): ServiceOrder {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $validated, $request): ServiceOrder {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->order_status, ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Koordinasi pengangkutan hanya dapat diperbarui sebelum barang diterima fisik di Gudang PKDI.',
                ]);
            }

            $transportStatus = strtoupper(trim((string) ($validated['transport_status'] ?? 'TRANSPORT_SCHEDULED')));
            if (! in_array($transportStatus, ['TRANSPORT_SCHEDULED', 'IN_TRANSIT'], true)) {
                throw ValidationException::withMessages([
                    'transport_status' => 'Status pengangkutan harus TRANSPORT_SCHEDULED atau IN_TRANSIT.',
                ]);
            }

            $scheduledDate = isset($validated['scheduled_pickup_date']) && trim((string) $validated['scheduled_pickup_date']) !== ''
                ? trim((string) $validated['scheduled_pickup_date'])
                : ($lockedOrder->scheduled_pickup_date?->toDateString() ?? now('Asia/Jakarta')->toDateString());
            $transporterName = isset($validated['transporter_name']) && trim((string) $validated['transporter_name']) !== ''
                ? trim((string) $validated['transporter_name'])
                : $lockedOrder->transporter_name;
            $vehicleType = isset($validated['vehicle_type']) && trim((string) $validated['vehicle_type']) !== ''
                ? trim((string) $validated['vehicle_type'])
                : $lockedOrder->vehicle_type;
            $licensePlate = isset($validated['license_plate']) && trim((string) $validated['license_plate']) !== ''
                ? strtoupper(preg_replace('/\s+/', ' ', trim((string) $validated['license_plate'])) ?? '')
                : $lockedOrder->license_plate;
            $driverName = isset($validated['driver_name']) && trim((string) $validated['driver_name']) !== ''
                ? trim((string) $validated['driver_name'])
                : $lockedOrder->driver_name;
            $driverPhone = isset($validated['driver_phone']) && trim((string) $validated['driver_phone']) !== ''
                ? trim((string) $validated['driver_phone'])
                : $lockedOrder->driver_phone;
            $transportNotes = isset($validated['transport_notes']) && trim((string) $validated['transport_notes']) !== ''
                ? trim((string) $validated['transport_notes'])
                : $lockedOrder->transport_notes;

            $oldStatus = $lockedOrder->order_status;

            $lockedOrder->update([
                'order_status' => $transportStatus,
                'warehouse_status' => $transportStatus,
                'scheduled_pickup_date' => $scheduledDate,
                'transporter_name' => $transporterName,
                'vehicle_type' => $vehicleType,
                'license_plate' => $licensePlate,
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
                'transport_notes' => $transportNotes,
                'in_transit_at' => $transportStatus === 'IN_TRANSIT' ? ($lockedOrder->in_transit_at ?? now()) : $lockedOrder->in_transit_at,
            ]);

            $plateNote = $licensePlate ? " (Armada {$licensePlate})" : '';
            $summary = $transportStatus === 'IN_TRANSIT'
                ? "Armada pengangkutan sedang dalam perjalanan menuju PKDI{$plateNote}."
                : "Pengangkutan limbah/drum dijadwalkan pada {$scheduledDate}{$plateNote}.";

            if ($transportNotes) {
                $summary .= " Catatan: {$transportNotes}";
            }

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'TRANSPORT',
                'from_status' => $oldStatus,
                'to_status' => $transportStatus,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'WAREHOUSE_TRANSPORT_UPDATED',
                auditable: $lockedOrder,
                oldValues: ['order_status' => $oldStatus],
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'order_status' => $transportStatus,
                    'scheduled_pickup_date' => $scheduledDate,
                    'transporter_name' => $transporterName,
                    'license_plate' => $licensePlate,
                    'driver_name' => $driverName,
                    'summary' => $summary,
                ],
                reason: $transportNotes,
                actor: $gudang,
                request: $request,
            );

            $this->notifyClient(
                order: $lockedOrder,
                title: $transportStatus === 'IN_TRANSIT' ? 'Armada pengangkutan dalam perjalanan' : 'Jadwal pengangkutan telah ditetapkan',
                message: "{$lockedOrder->order_number}: {$summary}",
                tone: 'info',
                icon: 'truck',
            );

            return $lockedOrder->fresh();
        });
    }

    /**
     * Pencatatan penerimaan fisik limbah / drum oleh Kepala Gudang.
     * Tidak menimpa kuantitas atau informasi pengajuan awal Client.
     *
     * @param  array<string, mixed>  $validated
     */
    public function recordReceipt(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?UploadedFile $photo = null,
        ?Request $request = null,
    ): WarehouseReceipt {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        $photoPath = $photo ? $photo->store('warehouse-receipts', 'local') : null;

        return DB::transaction(function () use ($gudang, $order, $validated, $photoPath, $request): WarehouseReceipt {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->order_status, ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Pesanan ini sudah melewati tahap penerimaan fisik di Gudang.',
                ]);
            }

            $actualQty = (int) $validated['actual_received_quantity'];
            $unit = isset($validated['unit']) && trim((string) $validated['unit']) !== ''
                ? trim((string) $validated['unit'])
                : $lockedOrder->requested_unit;
            $initialCondition = trim((string) $validated['initial_condition']);
            $discrepancyNotes = isset($validated['discrepancy_notes']) && trim((string) $validated['discrepancy_notes']) !== ''
                ? trim((string) $validated['discrepancy_notes'])
                : null;
            $receivedAt = isset($validated['received_at']) && trim((string) $validated['received_at']) !== ''
                ? Carbon::parse((string) $validated['received_at'], 'Asia/Jakarta')
                : now();

            $oldStatus = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
            ];

            $receipt = WarehouseReceipt::query()->create([
                'service_order_id' => $lockedOrder->id,
                'receipt_number' => $this->generateUniqueReceiptNumber(),
                'received_at' => $receivedAt,
                'actual_received_quantity' => $actualQty,
                'unit' => $unit,
                'initial_condition' => $initialCondition,
                'discrepancy_notes' => $discrepancyNotes,
                'photo_path' => $photoPath,
                'received_by_user_id' => $gudang->id,
            ]);

            $lockedOrder->update([
                'order_status' => 'RECEIVED',
                'warehouse_status' => 'RECEIVED',
            ]);

            $diffNote = ($lockedOrder->requested_quantity !== null && $actualQty !== $lockedOrder->requested_quantity)
                ? " (pengajuan awal: {$lockedOrder->requested_quantity} {$lockedOrder->requested_unit})"
                : '';
            $summary = "Penerimaan fisik {$receipt->receipt_number}: diterima {$actualQty} {$unit}{$diffNote}.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $oldStatus['order_status'],
                'to_status' => 'RECEIVED',
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary.($discrepancyNotes ? " Selisih: {$discrepancyNotes}" : ''),
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'WAREHOUSE_RECEIPT_RECORDED',
                auditable: $receipt,
                oldValues: $oldStatus,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'receipt_number' => $receipt->receipt_number,
                    'requested_quantity' => $lockedOrder->requested_quantity,
                    'actual_received_quantity' => $actualQty,
                    'order_status' => 'RECEIVED',
                    'summary' => $summary,
                ],
                reason: $discrepancyNotes,
                actor: $gudang,
                request: $request,
            );

            $this->notifyClient(
                order: $lockedOrder,
                title: 'Drum diterima di Gudang PKDI',
                message: "Gudang mencatat penerimaan fisik {$actualQty} {$unit} untuk pesanan {$lockedOrder->order_number}.",
                tone: 'info',
                icon: 'package',
            );

            return $receipt;
        });
    }

    /**
     * Pemeriksaan kondisi dan penyortiran drum seimbang untuk Jasa Rekondisi.
     * Menegakkan aturan: qty_passed + qty_special_repair + qty_rejected == jumlah fisik diterima.
     *
     * @param  array<string, mixed>  $validated
     * @return array{inspection: WarehouseInspection, sorting: WarehouseSorting}
     */
    public function recordSortingAndInspection(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?UploadedFile $photo = null,
        ?Request $request = null,
    ): array {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        $photoPath = $photo ? $photo->store('warehouse-sortings', 'local') : null;

        return DB::transaction(function () use ($gudang, $order, $validated, $photoPath, $request): array {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with('latestReceipt')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOrder->isReconditioning()) {
                throw ValidationException::withMessages([
                    'transaction_type' => 'Penyortiran drum hanya berlaku untuk transaksi Jasa Rekondisi Drum.',
                ]);
            }

            if (! in_array($lockedOrder->order_status, ['RECEIVED', 'WAITING_RECEIPT', 'UNDER_REVIEW', 'ACCEPTED', 'NEEDS_CLARIFICATION'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Pesanan ini tidak berada pada tahap pemeriksaan dan penyortiran drum.',
                ]);
            }

            $receipt = $lockedOrder->latestReceipt;
            $expectedTotal = $receipt?->actual_received_quantity ?? (int) ($validated['actual_quantity'] ?? $lockedOrder->requested_quantity ?? 0);
            $unit = $receipt?->unit ?? $lockedOrder->requested_unit;

            $qtyPassed = (int) ($validated['qty_passed'] ?? 0);
            $qtySpecial = (int) ($validated['qty_special_repair'] ?? 0);
            $qtyRejected = (int) ($validated['qty_rejected'] ?? 0);
            $totalSorted = $qtyPassed + $qtySpecial + $qtyRejected;

            if ($expectedTotal <= 0 || $totalSorted !== $expectedTotal) {
                throw ValidationException::withMessages([
                    'qty_passed' => "Total penyortiran ({$totalSorted} {$unit}) harus sama persis dengan jumlah aktual diterima ({$expectedTotal} {$unit}).",
                ]);
            }

            $rejectedReason = isset($validated['rejected_reason']) && trim((string) $validated['rejected_reason']) !== ''
                ? trim((string) $validated['rejected_reason'])
                : null;

            if ($qtyRejected > 0 && ($rejectedReason === null || mb_strlen($rejectedReason) < 5)) {
                throw ValidationException::withMessages([
                    'rejected_reason' => 'Alasan drum tidak layak wajib diisi minimal 5 karakter apabila terdapat jumlah ditolak.',
                ]);
            }

            if (($qtyPassed + $qtySpecial) <= 0) {
                throw ValidationException::withMessages([
                    'qty_passed' => 'Tidak ada drum layak proses. Jika seluruh drum tidak layak, gunakan aksi Tolak Pesanan disertai alasan.',
                ]);
            }

            $cargoCondition = trim((string) ($validated['cargo_condition'] ?? ($receipt?->initial_condition ?? 'Kondisi hasil pemeriksaan dan sortir Gudang.')));
            $sortingNotes = isset($validated['sorting_notes']) && trim((string) $validated['sorting_notes']) !== ''
                ? trim((string) $validated['sorting_notes'])
                : null;
            $actualCargoType = trim((string) ($validated['actual_cargo_type'] ?? $lockedOrder->drumProduct?->name ?? $lockedOrder->service_category ?? 'Drum'));

            $oldStatus = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
            ];

            $inspection = WarehouseInspection::query()->create([
                'service_order_id' => $lockedOrder->id,
                'actual_cargo_type' => $actualCargoType,
                'actual_quantity' => $expectedTotal,
                'actual_unit' => $unit,
                'cargo_condition' => $cargoCondition,
                'discrepancy_notes' => $receipt?->discrepancy_notes ?? $sortingNotes,
                'decision' => 'APPROVED',
                'decision_reason' => null,
                'photo_path' => $photoPath,
                'inspected_by_user_id' => $gudang->id,
                'inspected_at' => now(),
            ]);

            $sorting = WarehouseSorting::query()->create([
                'service_order_id' => $lockedOrder->id,
                'warehouse_receipt_id' => $receipt?->id,
                'qty_passed' => $qtyPassed,
                'qty_special_repair' => $qtySpecial,
                'qty_rejected' => $qtyRejected,
                'unit' => $unit,
                'sorting_notes' => $sortingNotes,
                'rejected_reason' => $rejectedReason,
                'photo_path' => $photoPath,
                'sorted_by_user_id' => $gudang->id,
                'sorted_at' => now(),
            ]);

            $lockedOrder->update([
                'order_status' => 'IN_PROGRESS',
                'warehouse_status' => 'IN_PROGRESS',
            ]);

            $processable = $qtyPassed + $qtySpecial;
            $summary = "Penyortiran selesai: {$qtyPassed} {$unit} layak proses, {$qtySpecial} {$unit} penanganan khusus, {$qtyRejected} {$unit} tidak layak. {$processable} {$unit} masuk proses rekondisi.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $oldStatus['order_status'],
                'to_status' => 'IN_PROGRESS',
                'changed_by_user_id' => $gudang->id,
                'notes' => "Pemeriksaan dan penyortiran selesai ({$processable} {$unit} diproses".($qtyRejected > 0 ? ", {$qtyRejected} {$unit} tidak layak" : '').').',
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'WAREHOUSE_SORTING_RECORDED',
                auditable: $sorting,
                oldValues: $oldStatus,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'qty_passed' => $qtyPassed,
                    'qty_special_repair' => $qtySpecial,
                    'qty_rejected' => $qtyRejected,
                    'order_status' => 'IN_PROGRESS',
                    'summary' => $summary,
                ],
                reason: $rejectedReason ?? $sortingNotes,
                actor: $gudang,
                request: $request,
            );

            $this->notifyClient(
                order: $lockedOrder,
                title: 'Drum masuk tahap proses rekondisi',
                message: "Hasil pemeriksaan {$lockedOrder->order_number}: {$processable} {$unit} masuk proses {$lockedOrder->service_type}.",
                tone: 'success',
                icon: 'sliders',
            );

            return [
                'inspection' => $inspection,
                'sorting' => $sorting,
            ];
        });
    }

    /**
     * Pemeriksaan langsung muatan aktual dan keputusan Kepala Gudang (mendukung alur cepat & kompatibilitas).
     *
     * @param  array<string, mixed>  $validated
     */
    public function inspectWarehouse(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?UploadedFile $photo = null,
        ?Request $request = null,
    ): WarehouseInspection {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        $photoPath = $photo ? $photo->store('warehouse-inspections', 'local') : null;

        return DB::transaction(function () use ($gudang, $order, $validated, $photoPath, $request): WarehouseInspection {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->order_status, ['SUBMITTED', 'UNDER_REVIEW', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW', 'NEEDS_CLARIFICATION', 'ACCEPTED', 'WAITING_RECEIPT', 'RECEIVED'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Pesanan ini tidak berada pada tahap peninjauan atau pemeriksaan Gudang.',
                ]);
            }

            $decision = strtoupper(trim((string) ($validated['decision'] ?? 'APPROVED')));
            if (! in_array($decision, ['APPROVED', 'NEEDS_CLARIFICATION', 'REJECTED'], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Keputusan pemeriksaan gudang tidak valid.',
                ]);
            }

            $decisionReason = isset($validated['decision_reason']) ? trim((string) $validated['decision_reason']) : '';
            if (in_array($decision, ['NEEDS_CLARIFICATION', 'REJECTED'], true) && mb_strlen($decisionReason) < 5) {
                throw ValidationException::withMessages([
                    'decision_reason' => 'Alasan klarifikasi atau penolakan pekerjaan gudang wajib diisi minimal 5 karakter.',
                ]);
            }

            $actualCargoType = trim((string) ($validated['actual_cargo_type'] ?? $lockedOrder->cargo_type ?? $lockedOrder->service_category ?? 'Drum'));
            $actualQty = (int) ($validated['actual_quantity'] ?? $lockedOrder->requested_quantity ?? 1);
            $actualUnit = trim((string) ($validated['actual_unit'] ?? $lockedOrder->requested_unit));
            $cargoCondition = trim((string) ($validated['cargo_condition'] ?? 'Kondisi standar siap proses.'));
            $discrepancyNotes = isset($validated['discrepancy_notes']) && trim((string) $validated['discrepancy_notes']) !== ''
                ? trim((string) $validated['discrepancy_notes'])
                : null;

            $oldStatus = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
            ];

            $inspection = WarehouseInspection::query()->create([
                'service_order_id' => $lockedOrder->id,
                'actual_cargo_type' => $actualCargoType,
                'actual_quantity' => $actualQty,
                'actual_unit' => $actualUnit,
                'cargo_condition' => $cargoCondition,
                'discrepancy_notes' => $discrepancyNotes,
                'decision' => $decision,
                'decision_reason' => $decisionReason !== '' ? $decisionReason : null,
                'photo_path' => $photoPath,
                'inspected_by_user_id' => $gudang->id,
                'inspected_at' => now(),
            ]);

            if ($decision === 'APPROVED') {
                $nextStatus = $lockedOrder->isDrumSale() ? 'PREPARING' : 'IN_PROGRESS';

                $lockedOrder->update([
                    'order_status' => $nextStatus,
                    'warehouse_status' => $nextStatus,
                ]);

                $summary = "Pemeriksaan muatan disetujui Kepala Gudang. Aktual diterima: {$actualQty} {$actualUnit} ({$actualCargoType}). Pengerjaan dimulai.";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldStatus['order_status'],
                    'to_status' => $nextStatus,
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary.($discrepancyNotes ? " Catatan selisih: {$discrepancyNotes}" : ''),
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_INSPECTION_APPROVED',
                    auditable: $lockedOrder,
                    oldValues: $oldStatus,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'order_status' => $nextStatus,
                        'warehouse_status' => $nextStatus,
                        'actual_cargo_type' => $actualCargoType,
                        'actual_quantity' => $actualQty,
                        'discrepancy_notes' => $discrepancyNotes,
                        'summary' => $summary,
                    ],
                    reason: $discrepancyNotes,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: 'Pemeriksaan muatan disetujui & sedang diproses',
                    message: "Gudang menerima {$actualQty} {$actualUnit} untuk pesanan {$lockedOrder->order_number}.",
                    tone: 'success',
                    icon: 'sliders',
                );
            } elseif ($decision === 'NEEDS_CLARIFICATION') {
                $lockedOrder->update([
                    'order_status' => 'NEEDS_CLARIFICATION',
                    'warehouse_status' => 'CLARIFICATION_NEEDED',
                ]);

                $summary = "Kepala Gudang meminta klarifikasi muatan: {$decisionReason}";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldStatus['order_status'],
                    'to_status' => 'NEEDS_CLARIFICATION',
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_NEEDS_CLARIFICATION',
                    auditable: $lockedOrder,
                    oldValues: $oldStatus,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'order_status' => 'NEEDS_CLARIFICATION',
                        'warehouse_status' => 'CLARIFICATION_NEEDED',
                        'summary' => $summary,
                    ],
                    reason: $decisionReason,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: 'Muatan membutuhkan klarifikasi',
                    message: "{$lockedOrder->order_number}: {$decisionReason}",
                    tone: 'warning',
                    icon: 'alert-triangle',
                );
            } else {
                $lockedOrder->update([
                    'order_status' => 'CANCELLED',
                    'warehouse_status' => 'REJECTED',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $decisionReason,
                ]);

                $summary = "Pekerjaan ditolak oleh Kepala Gudang. Alasan: {$decisionReason}";

                OrderStatusHistory::query()->create([
                    'service_order_id' => $lockedOrder->id,
                    'track_type' => 'WAREHOUSE',
                    'from_status' => $oldStatus['order_status'],
                    'to_status' => 'CANCELLED',
                    'changed_by_user_id' => $gudang->id,
                    'notes' => $summary,
                    'is_visible_to_client' => true,
                ]);

                AuditLogger::record(
                    action: 'WAREHOUSE_INSPECTION_REJECTED',
                    auditable: $lockedOrder,
                    oldValues: $oldStatus,
                    newValues: [
                        'order_number' => $lockedOrder->order_number,
                        'order_status' => 'CANCELLED',
                        'warehouse_status' => 'REJECTED',
                        'summary' => $summary,
                    ],
                    reason: $decisionReason,
                    actor: $gudang,
                    request: $request,
                );

                $this->notifyClient(
                    order: $lockedOrder,
                    title: 'Pekerjaan gudang ditolak',
                    message: "{$lockedOrder->order_number}: {$decisionReason}",
                    tone: 'danger',
                    icon: 'alert-triangle',
                );
            }

            return $inspection;
        });
    }

    /**
     * Kepala Gudang mencatat perkembangan pengerjaan rekondisi atau persiapan produk (ServiceProcess).
     *
     * @param  array<string, mixed>  $validated
     */
    public function recordProcess(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?UploadedFile $photo = null,
        ?Request $request = null,
    ): ServiceProcess {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        $photoPath = $photo ? $photo->store('warehouse-processes', 'local') : null;

        return DB::transaction(function () use ($gudang, $order, $validated, $photoPath, $request): ServiceProcess {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['latestSorting', 'latestInspection'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->order_status, ['IN_PROGRESS', 'PREPARING'], true)
                || ! in_array($lockedOrder->warehouse_status, ['IN_PROGRESS', 'PREPARING'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Pembaruan progres hanya dapat dicatat ketika pesanan sedang diproses atau dipersiapkan di Gudang.',
                ]);
            }

            $stage = trim((string) $validated['process_stage']);
            $notes = trim((string) $validated['progress_notes']);
            $processedQty = isset($validated['processed_qty']) && $validated['processed_qty'] !== ''
                ? (int) $validated['processed_qty']
                : ($lockedOrder->latestSorting?->processableQuantity()
                    ?? $lockedOrder->latestInspection?->actual_quantity
                    ?? $lockedOrder->requested_quantity);
            $isVisibleToClient = array_key_exists('is_visible_to_client', $validated)
                ? filter_var($validated['is_visible_to_client'], FILTER_VALIDATE_BOOLEAN)
                : true;

            $recentDuplicate = ServiceProcess::query()
                ->where('service_order_id', $lockedOrder->id)
                ->where('process_stage', $stage)
                ->where('progress_notes', $notes)
                ->where('created_at', '>=', now()->subSeconds(5))
                ->first();

            if ($recentDuplicate) {
                return $recentDuplicate;
            }

            $process = ServiceProcess::query()->create([
                'service_order_id' => $lockedOrder->id,
                'process_stage' => $stage,
                'progress_notes' => $notes,
                'processed_qty' => $processedQty,
                'photo_path' => $photoPath,
                'is_visible_to_client' => $isVisibleToClient,
                'recorded_by_user_id' => $gudang->id,
                'recorded_at' => now(),
            ]);

            $lockedOrder->touch();

            $summary = "Progres gudang ({$stage}): {$notes}";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $lockedOrder->order_status,
                'to_status' => $lockedOrder->order_status,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => $isVisibleToClient,
            ]);

            AuditLogger::record(
                action: 'WAREHOUSE_PROCESS_UPDATED',
                auditable: $lockedOrder,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'process_stage' => $stage,
                    'processed_qty' => $processedQty,
                    'is_visible_to_client' => $isVisibleToClient,
                    'summary' => $summary,
                ],
                actor: $gudang,
                request: $request,
            );

            return $process;
        });
    }

    /**
     * Kepala Gudang mencatat hasil akhir pekerjaan rekondisi atau persiapan penjualan drum
     * dan secara atomik membentuk satu BillableTransaction (READY_TO_BILL) untuk antrean Keuangan.
     *
     * @param  array<string, mixed>  $validated
     */
    public function completeService(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?UploadedFile $photo = null,
        ?Request $request = null,
    ): ServiceResult {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        $photoPath = $photo ? $photo->store('warehouse-results', 'local') : null;

        return DB::transaction(function () use ($gudang, $order, $validated, $photoPath, $request): ServiceResult {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with(['drumProduct', 'reconditioningService', 'latestReceipt', 'latestInspection', 'billableTransaction'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedCompleteStatuses = $lockedOrder->isWasteTransport()
                ? ['RECEIVED', 'SORTING', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING', 'IN_PROGRESS', 'PROCESSING']
                : ['IN_PROGRESS', 'PROCESSING', 'PREPARING'];

            if (! in_array($lockedOrder->order_status, $allowedCompleteStatuses, true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Penyelesaian hasil kerja hanya dapat dilakukan setelah tahap penerimaan atau proses Gudang yang disyaratkan.',
                ]);
            }

            if ($lockedOrder->billableTransaction && $lockedOrder->billableTransaction->billing_status === BillableTransaction::STATUS_INVOICED) {
                throw ValidationException::withMessages([
                    'billing_status' => 'Transaksi ini sudah masuk ke dalam invoice resmi sehingga hasil kerja tidak dapat diubah langsung.',
                ]);
            }

            $reconditioningService = null;
            if ($lockedOrder->isReconditioning()) {
                if (isset($validated['reconditioning_service_id']) && $validated['reconditioning_service_id'] !== '') {
                    $reconditioningService = ReconditioningService::query()->find((int) $validated['reconditioning_service_id']);
                }
                $reconditioningService ??= $lockedOrder->reconditioningService;
            }

            $actualServiceType = $lockedOrder->isWasteTransport()
                ? 'Pengangkutan Limbah'
                : ($reconditioningService?->name
                    ?? (isset($validated['actual_service_type']) && trim((string) $validated['actual_service_type']) !== ''
                        ? trim((string) $validated['actual_service_type'])
                        : ($lockedOrder->service_type ?? 'Laundry')));

            $completedQty = (int) $validated['completed_qty'];
            $rejectedQty = isset($validated['rejected_qty']) && $validated['rejected_qty'] !== ''
                ? (int) $validated['rejected_qty']
                : 0;
            $resultNotes = isset($validated['result_notes']) && trim((string) $validated['result_notes']) !== ''
                ? trim((string) $validated['result_notes'])
                : null;
            $unit = $lockedOrder->latestReceipt?->unit
                ?? $lockedOrder->latestInspection?->actual_unit
                ?? $lockedOrder->requested_unit;

            if ($completedQty <= 0 && $rejectedQty <= 0) {
                throw ValidationException::withMessages([
                    'completed_qty' => 'Kuantitas hasil pekerjaan harus lebih besar dari 0.',
                ]);
            }

            $oldStatus = [
                'order_status' => $lockedOrder->order_status,
                'warehouse_status' => $lockedOrder->warehouse_status,
                'billing_status' => $lockedOrder->billing_status,
            ];

            $result = ServiceResult::query()->updateOrCreate(
                ['service_order_id' => $lockedOrder->id],
                [
                    'reconditioning_service_id' => $reconditioningService?->id,
                    'actual_service_type' => $actualServiceType,
                    'completed_quantity' => $completedQty,
                    'unprocessed_or_rejected_quantity' => $rejectedQty,
                    'unit' => $unit,
                    'result_notes' => $resultNotes,
                    'photo_path' => $photoPath ?? $lockedOrder->result?->photo_path,
                    'completed_by_user_id' => $gudang->id,
                    'completed_at' => now(),
                ]
            );

            $productSnapshot = $lockedOrder->drumProduct?->name ?? $lockedOrder->service_category ?? 'Drum';
            $billingStatus = $completedQty > 0 ? BillableTransaction::STATUS_READY_TO_BILL : BillableTransaction::STATUS_NOT_READY;
            $billable = null;

            if ($completedQty > 0) {
                $existingBillable = $lockedOrder->billableTransaction;
                $billable = BillableTransaction::query()->updateOrCreate(
                    ['service_order_id' => $lockedOrder->id],
                    [
                        'transaction_code' => $existingBillable?->transaction_code ?? $this->generateUniqueBillableCode(),
                        'client_company_id' => $lockedOrder->client_company_id,
                        'transaction_type' => $lockedOrder->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
                        'drum_product_id' => $lockedOrder->drum_product_id,
                        'reconditioning_service_id' => $reconditioningService?->id,
                        'product_name_snapshot' => $productSnapshot,
                        'service_name_snapshot' => $actualServiceType,
                        'billable_quantity' => $completedQty,
                        'rejected_quantity' => $rejectedQty,
                        'unit' => $unit,
                        'billing_status' => BillableTransaction::STATUS_READY_TO_BILL,
                        'ready_at' => now(),
                        'created_by_user_id' => $gudang->id,
                        'notes' => $resultNotes,
                    ]
                );
            }

            $lockedOrder->update([
                'order_status' => 'READY',
                'warehouse_status' => 'COMPLETED',
                'billing_status' => $billingStatus,
            ]);

            $summary = $lockedOrder->isWasteTransport()
                ? "Layanan pengangkutan & penerimaan limbah selesai: {$completedQty} {$unit} ({$productSnapshot}) diterima dan masuk antrean penagihan."
                : "Hasil rekondisi selesai: {$completedQty} {$unit} ({$actualServiceType}) layak, {$rejectedQty} {$unit} tidak layak. Siap diserahkan dan masuk antrean penagihan.";

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'WAREHOUSE',
                'from_status' => $oldStatus['order_status'],
                'to_status' => 'READY',
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: 'SERVICE_COMPLETED',
                auditable: $lockedOrder,
                oldValues: $oldStatus,
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'order_status' => 'READY',
                    'warehouse_status' => 'COMPLETED',
                    'billing_status' => $billingStatus,
                    'billable_transaction_code' => $billable?->transaction_code,
                    'actual_service_type' => $actualServiceType,
                    'completed_quantity' => $completedQty,
                    'unprocessed_or_rejected_quantity' => $rejectedQty,
                    'summary' => $summary,
                ],
                reason: $resultNotes,
                actor: $gudang,
                request: $request,
            );

            if ($billable) {
                $this->notifyRole(
                    role: User::ROLE_KEUANGAN,
                    order: $lockedOrder,
                    title: 'Transaksi baru siap ditagihkan',
                    message: "{$billable->transaction_code} ({$lockedOrder->order_number}) · {$completedQty} {$unit} {$productSnapshot} ({$actualServiceType})",
                    tone: 'info',
                    icon: 'clipboard-check',
                    actionUrl: route('finance.billable.index', [], false),
                );
            }

            $this->notifyClient(
                order: $lockedOrder,
                title: $lockedOrder->isDrumSale() ? 'Produk pesanan siap diserahkan' : 'Pekerjaan rekondisi selesai & siap diserahkan',
                message: "Pesanan {$lockedOrder->order_number} ({$completedQty} {$unit} selesai) telah siap diserahkan atau dikirim.",
                tone: 'success',
                icon: 'check-circle',
            );

            return $result;
        });
    }

    /**
     * Pencatatan penyerahan atau pengiriman barang oleh Kepala Gudang (READY -> IN_DELIVERY / COMPLETED).
     * Informasi armada/kendaraan dan pengemudi bersifat opsional dan dicatat oleh pihak internal.
     *
     * @param  array<string, mixed>  $validated
     */
    public function recordDelivery(
        User $gudang,
        ServiceOrder $order,
        array $validated,
        ?Request $request = null,
    ): ServiceOrder {
        Gate::forUser($gudang)->authorize('manageWarehouse', $order);

        return DB::transaction(function () use ($gudang, $order, $validated, $request): ServiceOrder {
            /** @var ServiceOrder $lockedOrder */
            $lockedOrder = ServiceOrder::query()
                ->with('result')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->order_status, ['READY', 'IN_DELIVERY', 'DELIVERED', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Penyerahan atau pengiriman hanya dapat dicatat setelah hasil pekerjaan Gudang selesai (Siap Diserahkan).',
                ]);
            }

            $action = strtoupper(trim((string) ($validated['delivery_action'] ?? 'MARK_COMPLETED')));
            if (! in_array($action, ['START_DELIVERY', 'MARK_COMPLETED'], true)) {
                throw ValidationException::withMessages([
                    'delivery_action' => 'Aksi penyerahan/pengiriman tidak valid.',
                ]);
            }

            $method = strtoupper(trim((string) ($validated['delivery_method'] ?? 'DIANTAR_PKDI')));
            if (! in_array($method, ['DIANTAR_PKDI', 'DIAMBIL_CLIENT'], true)) {
                throw ValidationException::withMessages([
                    'delivery_method' => 'Metode penyerahan harus DIANTAR_PKDI atau DIAMBIL_CLIENT.',
                ]);
            }

            $deliveredQty = isset($validated['delivered_quantity']) && $validated['delivered_quantity'] !== ''
                ? (int) $validated['delivered_quantity']
                : ($lockedOrder->result?->completed_quantity ?? $lockedOrder->requested_quantity ?? 1);

            if ($deliveredQty <= 0) {
                throw ValidationException::withMessages([
                    'delivered_quantity' => 'Kuantitas diserahkan/dikirim minimal 1.',
                ]);
            }

            $vehicleType = isset($validated['vehicle_type']) && trim((string) $validated['vehicle_type']) !== ''
                ? trim((string) $validated['vehicle_type'])
                : $lockedOrder->vehicle_type;
            $licensePlate = isset($validated['license_plate']) && trim((string) $validated['license_plate']) !== ''
                ? strtoupper(preg_replace('/\s+/', ' ', trim((string) $validated['license_plate'])) ?? '')
                : $lockedOrder->license_plate;
            $driverName = isset($validated['driver_name']) && trim((string) $validated['driver_name']) !== ''
                ? trim((string) $validated['driver_name'])
                : $lockedOrder->driver_name;
            $deliveryNotes = isset($validated['delivery_notes']) && trim((string) $validated['delivery_notes']) !== ''
                ? trim((string) $validated['delivery_notes'])
                : null;

            $oldStatus = $lockedOrder->order_status;
            $nextStatus = $action === 'START_DELIVERY' ? 'IN_DELIVERY' : 'COMPLETED';
            $unit = $lockedOrder->result?->unit ?? $lockedOrder->requested_unit;
            $methodLabel = config("pkdi.delivery_methods.{$method}", $method === 'DIANTAR_PKDI' ? 'Diantar Armada PKDI' : 'Diambil oleh Client');

            $lockedOrder->update([
                'order_status' => $nextStatus,
                'delivery_method' => $method,
                'delivered_quantity' => $deliveredQty,
                'delivered_at' => $nextStatus === 'COMPLETED' ? now() : $lockedOrder->delivered_at,
                'completed_at' => $nextStatus === 'COMPLETED' ? now() : $lockedOrder->completed_at,
                'vehicle_type' => $vehicleType,
                'license_plate' => $licensePlate,
                'driver_name' => $driverName,
                'delivery_notes' => $deliveryNotes,
            ]);

            $plateSummary = $licensePlate ? " (Armada {$licensePlate})" : '';
            $summary = $nextStatus === 'IN_DELIVERY'
                ? "Pengiriman dimulai ({$methodLabel}): {$deliveredQty} {$unit}{$plateSummary}."
                : "Serah terima selesai ({$methodLabel}): {$deliveredQty} {$unit} telah diserahkan kepada Client{$plateSummary}.";

            if ($deliveryNotes) {
                $summary .= " Catatan: {$deliveryNotes}";
            }

            OrderStatusHistory::query()->create([
                'service_order_id' => $lockedOrder->id,
                'track_type' => 'DELIVERY',
                'from_status' => $oldStatus,
                'to_status' => $nextStatus,
                'changed_by_user_id' => $gudang->id,
                'notes' => $summary,
                'is_visible_to_client' => true,
            ]);

            AuditLogger::record(
                action: $nextStatus === 'IN_DELIVERY' ? 'WAREHOUSE_DELIVERY_STARTED' : 'WAREHOUSE_DELIVERY_COMPLETED',
                auditable: $lockedOrder,
                oldValues: ['order_status' => $oldStatus],
                newValues: [
                    'order_number' => $lockedOrder->order_number,
                    'order_status' => $nextStatus,
                    'delivery_method' => $method,
                    'delivered_quantity' => $deliveredQty,
                    'license_plate' => $licensePlate,
                    'summary' => $summary,
                ],
                reason: $deliveryNotes,
                actor: $gudang,
                request: $request,
            );

            $this->notifyClient(
                order: $lockedOrder,
                title: $nextStatus === 'IN_DELIVERY' ? 'Barang dalam proses pengiriman' : 'Barang telah diserahkan & pesanan selesai',
                message: "{$lockedOrder->order_number}: {$summary}",
                tone: $nextStatus === 'IN_DELIVERY' ? 'info' : 'success',
                icon: $nextStatus === 'IN_DELIVERY' ? 'truck' : 'check-circle',
            );

            return $lockedOrder->fresh();
        });
    }

    /**
     * Menghasilkan nomor pesanan unik berformat `hari-DD/MM/YY-N` beserta slug URL.
     *
     * @return array{0: string, 1: string}
     */
    private function generateUniqueOrderNumber(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $dayMap = [
            0 => 'minggu',
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
        ];
        $dayName = $dayMap[$now->dayOfWeek] ?? 'senin';
        $datePart = $now->format('d/m/y');

        $seq = ServiceOrder::query()->lockForUpdate()->count() + 1;

        while (true) {
            $orderNumber = "{$dayName}-{$datePart}-{$seq}";
            $orderSlug = str_replace('/', '-', $orderNumber);

            $exists = ServiceOrder::query()
                ->where('order_number', $orderNumber)
                ->orWhere('order_slug', $orderSlug)
                ->exists();

            if (! $exists) {
                return [$orderNumber, $orderSlug];
            }

            $seq++;
        }
    }

    private function generateUniqueReceiptNumber(): string
    {
        $datePart = Carbon::now('Asia/Jakarta')->format('Ymd');
        $seq = WarehouseReceipt::query()->lockForUpdate()->count() + 1;

        while (true) {
            $code = sprintf('RCV-%s-%03d', $datePart, $seq);
            if (! WarehouseReceipt::query()->where('receipt_number', $code)->exists()) {
                return $code;
            }
            $seq++;
        }
    }

    private function generateUniqueBillableCode(): string
    {
        $datePart = Carbon::now('Asia/Jakarta')->format('Ymd');
        $seq = BillableTransaction::query()->lockForUpdate()->count() + 1;

        while (true) {
            $code = sprintf('BTX-%s-%03d', $datePart, $seq);
            if (! BillableTransaction::query()->where('transaction_code', $code)->exists()) {
                return $code;
            }
            $seq++;
        }
    }

    private function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plate) ?? '');
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
                'action_url' => route('client.orders.show', $order->order_slug, false),
            ]);
        }
    }
}
