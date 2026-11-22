<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceResult extends Model
{
    protected $fillable = [
        'service_order_id',
        'reconditioning_service_id',
        'actual_service_type',
        'completed_quantity',
        'unprocessed_or_rejected_quantity',
        'unit',
        'result_notes',
        'photo_path',
        'completed_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_quantity' => 'integer',
            'unprocessed_or_rejected_quantity' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function reconditioningService(): BelongsTo
    {
        return $this->belongsTo(ReconditioningService::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
