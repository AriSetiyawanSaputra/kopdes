<?php

namespace App\Models;

use App\Models\Concerns\HasRegionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalExpense extends Model
{
    use HasFactory, HasRegionScope, SoftDeletes;

    public const CATEGORY_LISTRIK_AIR = 'listrik_air';

    public const CATEGORY_GAJI_HONOR = 'gaji_honor';

    public const CATEGORY_SEWA = 'sewa';

    public const CATEGORY_ATK_KEMASAN = 'atk_kemasan';

    public const CATEGORY_TRANSPORTASI = 'transportasi';

    public const CATEGORY_PEMELIHARAAN = 'pemeliharaan';

    public const CATEGORY_LAINNYA = 'lainnya';

    /**
     * Daftar kategori biaya operasional resmi beserta label Bahasa Indonesia.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_LISTRIK_AIR => 'Listrik & Air',
        self::CATEGORY_GAJI_HONOR => 'Gaji & Honor',
        self::CATEGORY_SEWA => 'Sewa Tempat',
        self::CATEGORY_ATK_KEMASAN => 'ATK & Kemasan',
        self::CATEGORY_TRANSPORTASI => 'Transportasi & Distribusi',
        self::CATEGORY_PEMELIHARAAN => 'Pemeliharaan & Perbaikan',
        self::CATEGORY_LAINNYA => 'Lainnya',
    ];

    protected $fillable = [
        'branch_id',
        'user_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', (string) $this->category));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function categoryOptions(): array
    {
        $options = [];
        foreach (self::CATEGORIES as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }
}
