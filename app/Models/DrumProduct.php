<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrumProduct extends Model
{
    public const DEFAULT_PRODUCTS = [
        [
            'code' => 'PRD-DRUM-BESI',
            'name' => 'Drum besi',
            'capacity_label' => '200 Liter',
            'default_unit' => 'Drum',
            'description' => 'Drum besi industri standar PKDI.',
            'sort_order' => 1,
        ],
        [
            'code' => 'PRD-POLIGEN-150',
            'name' => 'Poligen 150 liter',
            'capacity_label' => '150 Liter',
            'default_unit' => 'Unit',
            'description' => 'Drum plastik poligen kapasitas 150 liter.',
            'sort_order' => 2,
        ],
        [
            'code' => 'PRD-POLIGEN-120',
            'name' => 'Poligen 120 liter',
            'capacity_label' => '120 Liter',
            'default_unit' => 'Unit',
            'description' => 'Drum plastik poligen kapasitas 120 liter.',
            'sort_order' => 3,
        ],
        [
            'code' => 'PRD-JERIGEN',
            'name' => 'Jerigen',
            'capacity_label' => 'Standar Industri',
            'default_unit' => 'Unit',
            'description' => 'Jerigen plastik industri.',
            'sort_order' => 4,
        ],
    ];

    protected $fillable = [
        'code',
        'name',
        'capacity_label',
        'default_unit',
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
        foreach (self::DEFAULT_PRODUCTS as $item) {
            self::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'capacity_label' => $item['capacity_label'],
                    'default_unit' => $item['default_unit'],
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
