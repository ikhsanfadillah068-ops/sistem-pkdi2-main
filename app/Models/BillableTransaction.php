<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillableTransaction extends Model
{
    public const STATUS_NOT_READY = 'NOT_READY';

    public const STATUS_READY_TO_BILL = 'READY_TO_BILL';

    public const STATUS_INVOICED = 'INVOICED';

    public const STATUS_VOIDED = 'VOIDED';

    protected $fillable = [
        'transaction_code',
        'service_order_id',
        'client_company_id',
        'transaction_type',
        'drum_product_id',
        'reconditioning_service_id',
        'product_name_snapshot',
        'service_name_snapshot',
        'billable_quantity',
        'rejected_quantity',
        'unit',
        'billing_status',
        'invoice_id',
        'ready_at',
        'invoiced_at',
        'created_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billable_quantity' => 'integer',
            'rejected_quantity' => 'integer',
            'ready_at' => 'datetime',
            'invoiced_at' => 'datetime',
        ];
    }

    public function scopeReadyToBill(Builder $query): Builder
    {
        return $query->where('billing_status', self::STATUS_READY_TO_BILL)
            ->whereNull('invoice_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function drumProduct(): BelongsTo
    {
        return $this->belongsTo(DrumProduct::class);
    }

    public function reconditioningService(): BelongsTo
    {
        return $this->belongsTo(ReconditioningService::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
