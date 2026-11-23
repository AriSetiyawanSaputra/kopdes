<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'gateway',
        'source',
        'external_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'transaction_status',
        'fraud_status',
        'gross_amount',
        'signature_verified',
        'raw_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'signature_verified' => 'boolean',
            'raw_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
