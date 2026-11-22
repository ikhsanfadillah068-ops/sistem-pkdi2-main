<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseSorting extends Model
{
    protected $fillable = [
        'service_order_id',
        'warehouse_receipt_id',
        'qty_passed',
        'qty_special_repair',
        'qty_rejected',
        'unit',
        'sorting_notes',
        'rejected_reason',
        'photo_path',
        'sorted_by_user_id',
        'sorted_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_passed' => 'integer',
            'qty_special_repair' => 'integer',
            'qty_rejected' => 'integer',
            'sorted_at' => 'datetime',
        ];
    }

    public function totalSorted(): int
    {
        return $this->qty_passed + $this->qty_special_repair + $this->qty_rejected;
    }

    public function processableQuantity(): int
    {
        return $this->qty_passed + $this->qty_special_repair;
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function warehouseReceipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class);
    }

    public function sortedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by_user_id');
    }
}
