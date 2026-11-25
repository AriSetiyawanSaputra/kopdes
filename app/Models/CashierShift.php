<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    use HasFactory, HasRegionScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'shift_code',
        'branch_id',
        'cashier_id',
        'closed_by_user_id',
        'status',
        'is_force_closed',
        'opening_cash',
        'cash_sales_total',
        'midtrans_sales_total',
        'cash_in_total',
        'cash_out_total',
        'cash_refund_total',
        'expected_closing_cash',
        'actual_closing_cash',
        'variance',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_force_closed' => 'boolean',
            'opening_cash' => 'decimal:2',
            'cash_sales_total' => 'decimal:2',
            'midtrans_sales_total' => 'decimal:2',
            'cash_in_total' => 'decimal:2',
            'cash_out_total' => 'decimal:2',
            'cash_refund_total' => 'decimal:2',
            'expected_closing_cash' => 'decimal:2',
            'actual_closing_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(ShiftCashMovement::class, 'cashier_shift_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'cashier_shift_id');
    }

    public function cashReturns(): HasMany
    {
        return $this->hasMany(TransactionReturn::class, 'refund_cashier_shift_id');
    }
}
