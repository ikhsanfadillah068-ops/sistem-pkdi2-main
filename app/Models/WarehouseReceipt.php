<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReceipt extends Model
{
    protected $fillable = [
        'service_order_id',
        'receipt_number',
        'received_at',
        'actual_received_quantity',
        'unit',
        'initial_condition',
        'discrepancy_notes',
        'photo_path',
        'received_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'actual_received_quantity' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function sortings(): HasMany
    {
        return $this->hasMany(WarehouseSorting::class);
    }
}
