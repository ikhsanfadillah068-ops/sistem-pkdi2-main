<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceProcess extends Model
{
    protected $fillable = [
        'service_order_id',
        'process_stage',
        'progress_notes',
        'processed_qty',
        'photo_path',
        'is_visible_to_client',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_qty' => 'integer',
            'is_visible_to_client' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
