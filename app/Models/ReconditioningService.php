<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconditioningService extends Model
{
    public const DEFAULT_SERVICES = [
        [
            'code' => 'REC-LAUNDRY',
            'name' => 'Laundry',
            'description' => 'Pencucian dan pembersihan drum.',
            'sort_order' => 1,
        ],
        [
            'code' => 'REC-LAUNDRY-CAT',
            'name' => 'Laundry + Cat',
            'description' => 'Pencucian dan pengecatan ulang drum.',
            'sort_order' => 2,
        ],
        [
            'code' => 'REC-LAUNDRY-CAT-SABLON',
            'name' => 'Laundry + Cat + Sablon',
            'description' => 'Pencucian, pengecatan ulang, dan penyablonan drum.',
            'sort_order' => 3,
        ],
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULT_SERVICES as $item) {
            self::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                ]
            );
        }
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }
}
