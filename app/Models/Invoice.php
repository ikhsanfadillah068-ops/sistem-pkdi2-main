<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoice_slug',
        'service_order_id',
        'client_company_id',
        'revision_number',
        'subtotal_amount',
        'adjustment_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'issue_date',
        'due_date',
        'payment_instructions',
        'notes',
        'pdf_path',
        'created_by_user_id',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'subtotal_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isClient()) {
            return $query->where('client_company_id', $user->client_company_id);
        }

        return $query;
    }

    public static function findByCodeOrSlug(string $code): ?self
    {
        return self::query()
            ->where('invoice_slug', $code)
            ->orWhere('invoice_number', $code)
            ->first();
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function billableTransactions(): HasMany
    {
        return $this->hasMany(BillableTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date');
    }

    public function deliveryOrder(): HasOne
    {
        return $this->hasOne(DeliveryOrder::class);
    }
}
