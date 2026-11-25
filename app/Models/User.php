<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'province_id',
    'regency_id',
    'branch_id',
    'card_uid',
    'pin_hash',
    'is_active',
])]
#[Hidden([
    'password',
    'remember_token',
    'pin_hash',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRegionScope, Notifiable;

    public const ROLE_ADMIN_NASIONAL = 'admin_nasional';

    public const ROLE_ADMIN_PROVINSI = 'admin_provinsi';

    public const ROLE_ADMIN_KABUPATEN = 'admin_kabupaten';

    public const ROLE_ADMIN_CABANG = 'admin_cabang';

    public const ROLE_KASIR = 'kasir';

    public const ADMIN_ROLES = [
        self::ROLE_ADMIN_NASIONAL,
        self::ROLE_ADMIN_PROVINSI,
        self::ROLE_ADMIN_KABUPATEN,
        self::ROLE_ADMIN_CABANG,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_KASIR;
    }

    public function isAdminNasional(): bool
    {
        return $this->role === self::ROLE_ADMIN_NASIONAL;
    }

    public function isAdminProvinsi(): bool
    {
        return $this->role === self::ROLE_ADMIN_PROVINSI;
    }

    public function isAdminKabupaten(): bool
    {
        return $this->role === self::ROLE_ADMIN_KABUPATEN;
    }

    public function isAdminCabang(): bool
    {
        return $this->role === self::ROLE_ADMIN_CABANG;
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'cashier_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'user_id');
    }

    public function operationalExpenses(): HasMany
    {
        return $this->hasMany(OperationalExpense::class, 'user_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashierShift::class, 'cashier_id');
    }
}
