<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_company_id',
        'service_type',
        'drum_product_id',
        'unit_price',
        'reject_disposal_fee',
        'ppn_rate',
        'pph23_rate',
        'is_active',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'reject_disposal_fee' => 'decimal:2',
            'ppn_rate' => 'decimal:2',
            'pph23_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function drumProduct(): BelongsTo
    {
        return $this->belongsTo(DrumProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
