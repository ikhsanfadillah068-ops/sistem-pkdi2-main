<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'service_order_id',
        'track_type',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'notes',
        'is_visible_to_client',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_client' => 'boolean',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
