<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Member extends Authenticatable
{
    use HasFactory, HasRegionScope;

    protected $table = 'members';

    protected $fillable = [
        'member_code',
        'home_branch_id',
        'card_uid',
        'name',
        'phone',
        'nik',
        'status',
        'pin_hash',
        'remember_token',
        'joined_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'pin_hash',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'has_rfid_card',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->pin_hash ?? '');
    }

    public function getAuthPasswordName(): string
    {
        return 'pin_hash';
    }

    public function getHasRfidCardAttribute(): bool
    {
        return ! empty($this->card_uid);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function regionBranchColumn(): string
    {
        return 'home_branch_id';
    }

    protected function regionBranchRelation(): string
    {
        return 'homeBranch';
    }

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'member_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(TransactionReturn::class, 'member_id');
    }
}
