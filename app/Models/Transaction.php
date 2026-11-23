<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory, HasRegionScope;

    protected $fillable = [
        'invoice_number',
        'idempotency_key',
        'branch_id',
        'cashier_id',
        'member_id',
        'member_home_branch_id',
        'subtotal',
        'total_amount',
        'total_cogs',
        'payment_method',
        'payment_status',
        'cash_received',
        'change_amount',
        'snap_token',
        'midtrans_payment_type',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_cogs' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function memberHomeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'member_home_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class, 'transaction_id');
    }
}
