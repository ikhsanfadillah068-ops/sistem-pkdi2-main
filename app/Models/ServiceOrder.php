<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceOrder extends Model
{
    use HasFactory;

    public const TYPE_WASTE_TRANSPORT = 'WASTE_TRANSPORT';

    public const TYPE_RECONDITIONING = 'RECONDITIONING';

    // Alias kompatibilitas kode lama menuju jenis transaksi Pengangkutan Limbah PRD v2.1
    public const TYPE_DRUM_SALE = 'WASTE_TRANSPORT';

    public const TRANSACTION_TYPES = [
        self::TYPE_WASTE_TRANSPORT,
        self::TYPE_RECONDITIONING,
        'DRUM_SALE',
    ];

    protected $fillable = [
        'order_number',
        'order_slug',
        'client_company_id',
        'created_by_user_id',
        'transaction_type',
        'drum_product_id',
        'reconditioning_service_id',
        'service_category',
        'cargo_type',
        'service_type',
        'requested_quantity',
        'requested_unit',
        'po_number',
        'po_attachment_path',
        'needed_date',
        'scheduled_pickup_date',
        'origin_company_name',
        'transporter_name',
        'vehicle_type',
        'license_plate',
        'driver_name',
        'driver_phone',
        'client_notes',
        'specification_notes',
        'transport_notes',
        'attachment_path',
        'order_status',
        'gate_status',
        'warehouse_status',
        'billing_status',
        'payment_status',
        'delivery_method',
        'delivered_quantity',
        'delivered_at',
        'delivery_notes',
        'submitted_at',
        'in_transit_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'delivered_quantity' => 'integer',
            'needed_date' => 'date',
            'scheduled_pickup_date' => 'date',
            'delivered_at' => 'datetime',
            'submitted_at' => 'datetime',
            'in_transit_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isWasteTransport(): bool
    {
        return in_array($this->transaction_type, [self::TYPE_WASTE_TRANSPORT, 'DRUM_SALE'], true);
    }

    public function isDrumSale(): bool
    {
        return $this->isWasteTransport();
    }

    public function isReconditioning(): bool
    {
        return ! $this->isWasteTransport();
    }

    public function transactionTypeLabel(): string
    {
        return $this->isWasteTransport() ? 'Pengangkutan Limbah' : 'Rekondisi Drum';
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isClient()) {
            return $query->where('client_company_id', $user->client_company_id);
        }

        return $query;
    }

    public static function findByCodeOrSlug(string $code): ?self
    {
        return self::query()
            ->where('order_slug', $code)
            ->orWhere('order_number', $code)
            ->first();
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function drumProduct(): BelongsTo
    {
        return $this->belongsTo(DrumProduct::class);
    }

    public function reconditioningService(): BelongsTo
    {
        return $this->belongsTo(ReconditioningService::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class)->latest('received_at');
    }

    public function latestReceipt(): HasOne
    {
        return $this->hasOne(WarehouseReceipt::class)->latestOfMany();
    }

    public function sortings(): HasMany
    {
        return $this->hasMany(WarehouseSorting::class)->latest('sorted_at');
    }

    public function latestSorting(): HasOne
    {
        return $this->hasOne(WarehouseSorting::class)->latestOfMany();
    }

    public function billableTransaction(): HasOne
    {
        return $this->hasOne(BillableTransaction::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(VehicleGateLog::class)->latest('checked_at');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(WarehouseInspection::class)->latest('inspected_at');
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(WarehouseInspection::class)->latestOfMany();
    }

    public function processes(): HasMany
    {
        return $this->hasMany(ServiceProcess::class)->orderBy('recorded_at');
    }

    public function result(): HasOne
    {
        return $this->hasOne(ServiceResult::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
    }

    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class)->latest('issued_at');
    }

    public function latestDeliveryOrder(): HasOne
    {
        return $this->hasOne(DeliveryOrder::class)->latestOfMany();
    }
}
