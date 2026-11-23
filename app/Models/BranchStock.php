<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchStock extends Model
{
    use HasFactory, HasRegionScope;

    protected $fillable = [
        'branch_id',
        'product_id',
        'quantity',
        'reserved_qty',
        'min_stock',
    ];

    protected $appends = [
        'available_stock',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_qty' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    protected function availableStock(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) $this->quantity - (int) $this->reserved_qty,
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
