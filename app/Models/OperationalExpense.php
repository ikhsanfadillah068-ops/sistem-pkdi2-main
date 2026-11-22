<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OperationalExpense extends Model
{
    protected $fillable = [
        'expense_number',
        'expense_category',
        'expense_date',
        'description',
        'amount',
        'recipient_or_vendor',
        'payment_method',
        'receipt_file_path',
        'status',
        'void_reason',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function cashbookEntry(): HasOne
    {
        return $this->hasOne(CashbookEntry::class);
    }
}
