<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInspection extends Model
{
    protected $fillable = [
        'service_order_id',
        'actual_cargo_type',
        'actual_quantity',
        'actual_unit',
        'cargo_condition',
        'discrepancy_notes',
        'decision',
        'decision_reason',
        'photo_path',
        'inspected_by_user_id',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'actual_quantity' => 'integer',
            'inspected_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }
}
