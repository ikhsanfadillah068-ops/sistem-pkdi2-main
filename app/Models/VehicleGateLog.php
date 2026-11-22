<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleGateLog extends Model
{
    protected $fillable = [
        'service_order_id',
        'delivery_order_id',
        'check_type',
        'actual_license_plate',
        'actual_vehicle_type',
        'actual_driver_name',
        'is_plate_matched',
        'is_vehicle_matched',
        'is_cargo_matched',
        'decision',
        'hold_or_note_reason',
        'checked_by_user_id',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_plate_matched' => 'boolean',
            'is_vehicle_matched' => 'boolean',
            'is_cargo_matched' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }
}
