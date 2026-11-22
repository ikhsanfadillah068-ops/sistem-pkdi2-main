<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentAccount extends Model
{
    protected $fillable = [
        'payment_method',
        'category',
        'provider_name',
        'account_number',
        'account_holder',
        'instructions',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('category')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function categoryForMethod(string $method): string
    {
        foreach (config('pkdi.payment_methods', []) as $group => $methods) {
            if (in_array($method, $methods, true)) {
                return (string) $group;
            }
        }

        return 'Lainnya';
    }
}
