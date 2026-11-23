<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory, HasRegionScope;

    protected $fillable = [
        'member_code',
        'home_branch_id',
        'card_uid',
        'name',
        'phone',
        'nik',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
        ];
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
}
