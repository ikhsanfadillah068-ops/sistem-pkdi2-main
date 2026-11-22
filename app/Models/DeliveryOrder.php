<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'delivery_order_number',
        'service_order_id',
        'invoice_id',
        'client_company_id',
        'cargo_summary',
        'quantity_released',
        'unit',
        'vehicle_type',
        'license_plate',
        'driver_name',
        'status',
        'notes',
        'pdf_path',
        'issued_by_user_id',
        'issued_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_released' => 'integer',
            'issued_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(VehicleGateLog::class);
    }
}
