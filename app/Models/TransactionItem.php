<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_name_snapshot',
        'barcode_snapshot',
        'unit_snapshot',
        'qty',
        'regular_price_snapshot',
        'unit_price_snapshot',
        'member_discount_snapshot',
        'is_member_price_applied',
        'cost_price_snapshot',
        'subtotal',
        'subtotal_cogs',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'regular_price_snapshot' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'member_discount_snapshot' => 'decimal:2',
            'is_member_price_applied' => 'boolean',
            'cost_price_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'subtotal_cogs' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(TransactionReturnItem::class, 'transaction_item_id');
    }

    protected function returnedQty(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if ($this->relationLoaded('returnItems')) {
                    return (int) $this->returnItems->sum('qty_returned');
                }

                return (int) $this->returnItems()->sum('qty_returned');
            },
        );
    }

    protected function remainingReturnableQty(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, (int) $this->qty - $this->returned_qty),
        );
    }
}
