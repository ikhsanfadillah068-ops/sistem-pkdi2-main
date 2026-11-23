<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconditionCert extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_id',
        'cert_number',
        'qr_hash',
        'qc_passed_qty',
        'completion_date',
        'pdf_path',
        'issued_by_user_id',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'qc_passed_qty' => 'integer',
            'completion_date' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
