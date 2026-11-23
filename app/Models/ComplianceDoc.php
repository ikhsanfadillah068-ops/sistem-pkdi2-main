<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceDoc extends Model
{
    use HasFactory;

    public const TYPE_SURAT_JALAN = 'SURAT_JALAN';

    public const TYPE_MANIFEST_B3 = 'MANIFEST_B3';

    public const TYPE_BAST = 'BAST';

    public const TYPES = [
        self::TYPE_SURAT_JALAN,
        self::TYPE_MANIFEST_B3,
        self::TYPE_BAST,
    ];

    protected $fillable = [
        'service_order_id',
        'doc_type',
        'document_number',
        'file_path',
        'original_filename',
        'is_verified',
        'uploaded_by_user_id',
        'verified_by_user_id',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return match ($this->doc_type) {
            self::TYPE_SURAT_JALAN => 'Surat Jalan',
            self::TYPE_MANIFEST_B3 => 'Manifest B3 (Festronik)',
            self::TYPE_BAST => 'BAST Bertanda Tangan',
            default => $this->doc_type,
        };
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
