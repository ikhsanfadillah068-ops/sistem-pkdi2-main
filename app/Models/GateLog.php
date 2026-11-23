<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GateLog extends Model
{
    use HasFactory;

    public const TYPE_IN = 'IN';

    public const TYPE_OUT = 'OUT';

    protected $fillable = [
        'service_order_id',
        'type',
        'plate_number',
        'driver_name',
        'qty_good',
        'qty_dent',
        'qty_reject',
        'total_physical_qty',
        'evidence_photos',
        'notes',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_good' => 'integer',
            'qty_dent' => 'integer',
            'qty_reject' => 'integer',
            'total_physical_qty' => 'integer',
            'evidence_photos' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function billableQuantity(): int
    {
        return (int) $this->qty_good + (int) $this->qty_dent;
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function discrepancyReport(): HasOne
    {
        return $this->hasOne(DiscrepancyReport::class);
    }
}
