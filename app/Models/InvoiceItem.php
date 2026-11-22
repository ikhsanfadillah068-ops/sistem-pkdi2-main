<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'service_tariff_id',
        'billable_transaction_id',
        'transaction_type',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function serviceTariff(): BelongsTo
    {
        return $this->belongsTo(ServiceTariff::class);
    }

    public function billableTransaction(): BelongsTo
    {
        return $this->belongsTo(BillableTransaction::class);
    }
}
