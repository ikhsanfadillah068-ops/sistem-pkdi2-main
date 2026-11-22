<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashbookEntry extends Model
{
    protected $fillable = [
        'entry_number',
        'entry_type',
        'transaction_date',
        'source_type',
        'payment_id',
        'operational_expense_id',
        'description',
        'amount',
        'status',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function operationalExpense(): BelongsTo
    {
        return $this->belongsTo(OperationalExpense::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
