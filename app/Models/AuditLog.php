<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(static function (): bool {
            throw new \LogicException('AuditLog bersifat permanen (immutable) dan tidak dapat diubah.');
        });

        static::deleting(static function (): bool {
            throw new \LogicException('AuditLog bersifat permanen (immutable) dan tidak dapat dihapus.');
        });
    }

    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
