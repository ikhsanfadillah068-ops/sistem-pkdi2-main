<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscrepancyReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_SPLIT_RESOLVED = 'SPLIT_RESOLVED';

    protected $fillable = [
        'service_order_id',
        'gate_log_id',
        'ba_number',
        'po_qty',
        'actual_good_qty',
        'actual_dent_qty',
        'actual_reject_qty',
        'total_physical_qty',
        'evidence_urls',
        'notes',
        'status',
        'magic_token',
        'approved_by_user_id',
        'approved_by_name',
        'approved_ip',
        'approved_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'po_qty' => 'integer',
            'actual_good_qty' => 'integer',
            'actual_dent_qty' => 'integer',
            'actual_reject_qty' => 'integer',
            'total_physical_qty' => 'integer',
            'evidence_urls' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SPLIT_RESOLVED], true);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function gateLog(): BelongsTo
    {
        return $this->belongsTo(GateLog::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
