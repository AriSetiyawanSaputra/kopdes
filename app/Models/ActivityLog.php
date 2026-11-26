<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory, HasRegionScope;

    public const UPDATED_AT = null;

    public const ACTION_PRODUCT_PRICE_UPDATED = 'product.price_updated';

    public const ACTION_BRANCH_STATUS_TOGGLED = 'branch.status_toggled';

    public const ACTION_USER_STATUS_TOGGLED = 'user.status_toggled';

    public const ACTION_MEMBER_STATUS_TOGGLED = 'member.status_toggled';

    public const ACTION_MEMBER_CARD_REPLACED = 'member.card_replaced';

    public const ACTION_MEMBER_PIN_RESET = 'member.pin_reset';

    public const ACTION_SHIFT_FORCE_CLOSED = 'shift.force_closed';

    public const ACTION_TRANSACTION_RETURNED = 'transaction.returned';

    public const ACTION_IMPORT_COMPLETED = 'import.completed';

    public const ACTION_SETTINGS_UPDATED = 'settings.updated';

    public const ACTION_LABELS = [
        self::ACTION_PRODUCT_PRICE_UPDATED => 'Perubahan Harga Produk',
        self::ACTION_BRANCH_STATUS_TOGGLED => 'Aktivasi / Nonaktivasi Cabang',
        self::ACTION_USER_STATUS_TOGGLED => 'Aktivasi / Nonaktivasi Pengguna',
        self::ACTION_MEMBER_STATUS_TOGGLED => 'Aktivasi / Nonaktivasi Member',
        self::ACTION_MEMBER_CARD_REPLACED => 'Penggantian Kartu RFID Member',
        self::ACTION_MEMBER_PIN_RESET => 'Set / Reset PIN Member',
        self::ACTION_SHIFT_FORCE_CLOSED => 'Force-Close Shift Kasir',
        self::ACTION_TRANSACTION_RETURNED => 'Retur / Void Transaksi',
        self::ACTION_IMPORT_COMPLETED => 'Import Massal Excel/CSV',
        self::ACTION_SETTINGS_UPDATED => 'Perubahan Pengaturan Sistem',
    ];

    protected $table = 'activity_logs';

    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'province_id',
        'regency_id',
        'branch_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * ActivityLog stores direct province_id, regency_id, and branch_id columns.
     * Global logs (province_id = NULL) are automatically hidden from roles below admin_nasional.
     */
    protected function regionScopeMode(): string
    {
        return 'direct';
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
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
}
