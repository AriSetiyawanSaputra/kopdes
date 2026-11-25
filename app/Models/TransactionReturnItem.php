<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionReturnItem extends Model
{
    use HasFactory;

    public const CONDITION_RESTOCKABLE = 'restockable';

    public const CONDITION_DAMAGED = 'damaged';

    protected $fillable = [
        'transaction_return_id',
        'transaction_item_id',
        'product_id',
        'product_name_snapshot',
        'qty_returned',
        'condition',
        'unit_price_snapshot',
        'cost_price_snapshot',
        'subtotal_refund',
        'subtotal_cogs',
    ];

    protected function casts(): array
    {
        return [
            'qty_returned' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
            'cost_price_snapshot' => 'decimal:2',
            'subtotal_refund' => 'decimal:2',
            'subtotal_cogs' => 'decimal:2',
        ];
    }

    public function transactionReturn(): BelongsTo
    {
        return $this->belongsTo(TransactionReturn::class, 'transaction_return_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }
}
