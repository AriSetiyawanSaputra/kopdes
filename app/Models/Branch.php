<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory, HasRegionScope;

    protected $fillable = [
        'code',
        'name',
        'village_id',
        'district_id',
        'regency_id',
        'province_id',
        'address',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'home_branch_id');
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(BranchStock::class, 'branch_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'branch_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'branch_id');
    }

    public function operationalExpenses(): HasMany
    {
        return $this->hasMany(OperationalExpense::class, 'branch_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashierShift::class, 'branch_id');
    }
}
