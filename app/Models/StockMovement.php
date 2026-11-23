<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory, HasRegionScope;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id',
        'product_id',
        'user_id',
        'type',
        'qty_change',
        'qty_before',
        'qty_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'integer',
            'qty_before' => 'integer',
            'qty_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
