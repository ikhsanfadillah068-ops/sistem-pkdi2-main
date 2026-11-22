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
        'npwp',
        'nib_number',
        'nib_file_path',
        'documents_status',
        'verification_status',
    ];

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
