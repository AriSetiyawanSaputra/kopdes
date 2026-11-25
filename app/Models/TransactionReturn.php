<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionReturn extends Model
{
    use HasFactory, HasRegionScope;

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_FULL = 'full';

    public const REFUND_CASH = 'cash_refund';

    public const REFUND_TRANSFER = 'manual_transfer';

    public const CASH_SOURCE_BRANCH = 'branch_cash';

    public const CASH_SOURCE_ACTIVE_SHIFT = 'active_shift';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'return_number',
        'transaction_id',
        'branch_id',
        'processed_by_user_id',
        'member_id',
        'member_home_branch_id',
        'return_type',
        'refund_method',
        'cash_source',
        'refund_cashier_shift_id',
        'refund_reference',
        'total_refund_amount',
        'total_restocked_cogs',
        'total_damaged_cogs',
        'reason',
        'status',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'total_refund_amount' => 'decimal:2',
            'total_restocked_cogs' => 'decimal:2',
            'total_damaged_cogs' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function memberHomeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'member_home_branch_id');
    }

    public function refundShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'refund_cashier_shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionReturnItem::class, 'transaction_return_id');
    }
}
