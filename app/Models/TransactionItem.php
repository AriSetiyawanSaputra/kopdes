<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'unit_price_snapshot',
        'cost_price_snapshot',
        'subtotal',
        'subtotal_cogs',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
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
}
