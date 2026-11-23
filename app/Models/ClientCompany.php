<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_code',
        'company_name',
        'pic_name',
        'email',
        'phone',
        'address',
        'latitude',
        'longitude',
        'google_maps_url',
        'npwp',
        'nib_number',
        'nib_file_path',
        'documents_status',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public static function buildGoogleMapsUrl(mixed $lat, mixed $lng, ?string $fallbackAddress = null): ?string
    {
        if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
            return sprintf('https://www.google.com/maps?q=%.6F,%.6F', (float) $lat, (float) $lng);
        }

        if ($fallbackAddress !== null && trim($fallbackAddress) !== '' && trim($fallbackAddress) !== '-') {
            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(trim($fallbackAddress));
        }

        return null;
    }

    public function resolvedGoogleMapsUrl(): ?string
    {
        if (filled($this->google_maps_url)) {
            return $this->google_maps_url;
        }

        return self::buildGoogleMapsUrl($this->latitude, $this->longitude, $this->address);
    }

    public function hasCompleteDocuments(): bool
    {
        return filled($this->npwp) && (filled($this->nib_number) || filled($this->nib_file_path));
    }

    public function billableTransactions(): HasMany
    {
        return $this->hasMany(BillableTransaction::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }
}
