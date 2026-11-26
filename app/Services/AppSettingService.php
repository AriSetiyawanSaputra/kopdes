<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AppSettingService
{
    public const CACHE_KEY = 'kopdes.app_settings';

    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Canonical setting keys, types, groups, and MVP/Phase 2 backward-compatible defaults (PRD_PHASE3 §1.4 & FR-P3-07).
     *
     * @var array<string, array{default: mixed, type: string, group: string}>
     */
    private const DEFINITIONS = [
        'institution_name' => [
            'default' => 'Koperasi Desa Merah Putih',
            'type' => AppSetting::TYPE_STRING,
            'group' => 'general',
        ],
        'institution_logo_path' => [
            'default' => null,
            'type' => AppSetting::TYPE_STRING,
            'group' => 'general',
        ],
        'receipt_footer' => [
            'default' => 'Terima kasih telah berbelanja di KOPDES Merah Putih.',
            'type' => AppSetting::TYPE_STRING,
            'group' => 'receipt',
        ],
        'default_receipt_paper' => [
            'default' => '58mm',
            'type' => AppSetting::TYPE_STRING,
            'group' => 'receipt',
        ],
        'return_max_days' => [
            'default' => 7,
            'type' => AppSetting::TYPE_INTEGER,
            'group' => 'return',
        ],
        'shu_allocation_member_usaha' => [
            'default' => 100.0,
            'type' => AppSetting::TYPE_DECIMAL,
            'group' => 'shu',
        ],
        'shu_allocation_reserve' => [
            'default' => 0.0,
            'type' => AppSetting::TYPE_DECIMAL,
            'group' => 'shu',
        ],
        'shu_allocation_management' => [
            'default' => 0.0,
            'type' => AppSetting::TYPE_DECIMAL,
            'group' => 'shu',
        ],
        'shu_allocation_education' => [
            'default' => 0.0,
            'type' => AppSetting::TYPE_DECIMAL,
            'group' => 'shu',
        ],
    ];

    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Return all settings merged with MVP/Phase 2 fallbacks, cached under 'kopdes.app_settings'.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $resolved = $this->defaultValues();

            if (! Schema::hasTable('app_settings')) {
                return $resolved;
            }

            $rows = AppSetting::query()->get();
            foreach ($rows as $row) {
                $resolved[$row->key] = $row->castedValue();
            }

            return $resolved;
        });
    }

    /**
     * Get a single setting value with explicit fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all) && $all[$key] !== null) {
            return $all[$key];
        }

        if ($default !== null) {
            return $default;
        }

        return $this->defaultValues()[$key] ?? null;
    }

    /**
     * Return non-sensitive settings safe for Inertia shared props, receipts, and exports.
     *
     * @return array{
     *     institution_name: string,
     *     institution_logo_path: ?string,
     *     institution_logo_url: ?string,
     *     receipt_footer: string,
     *     default_receipt_paper: string
     * }
     */
    public function publicConfig(): array
    {
        $all = $this->all();
        $logoPath = ! empty($all['institution_logo_path']) ? (string) $all['institution_logo_path'] : null;

        return [
            'institution_name' => (string) ($all['institution_name'] ?? 'Koperasi Desa Merah Putih'),
            'institution_logo_path' => $logoPath,
            'institution_logo_url' => $logoPath ? '/storage/'.ltrim($logoPath, '/') : null,
            'receipt_footer' => (string) ($all['receipt_footer'] ?? 'Terima kasih telah berbelanja di KOPDES Merah Putih.'),
            'default_receipt_paper' => in_array($all['default_receipt_paper'] ?? '58mm', ['58mm', '80mm'], true)
                ? (string) $all['default_receipt_paper']
                : '58mm',
        ];
    }

    /**
     * Return an inline base64 data URI for the institution logo so DomPDF renders it reliably
     * with isRemoteEnabled = false.
     */
    public function logoDataUri(): ?string
    {
        $logoPath = $this->get('institution_logo_path');
        if (empty($logoPath) || ! is_string($logoPath)) {
            return null;
        }

        if (! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($logoPath);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return "data:{$mime};base64,".base64_encode($bytes);
    }

    /**
     * Return the 4 SHU allocation percentages (sum = 100%).
     *
     * @return array{
     *     member_usaha: float,
     *     reserve: float,
     *     management: float,
     *     education: float
     * }
     */
    public function shuAllocations(): array
    {
        return [
            'member_usaha' => round((float) $this->get('shu_allocation_member_usaha', 100.0), 2),
            'reserve' => round((float) $this->get('shu_allocation_reserve', 0.0), 2),
            'management' => round((float) $this->get('shu_allocation_management', 0.0), 2),
            'education' => round((float) $this->get('shu_allocation_education', 0.0), 2),
        ];
    }

    /**
     * Update multiple settings atomically, handle optional logo upload/removal, clear cache,
     * and write an immutable audit log entry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateMany(
        array $data,
        User $actor,
        ?UploadedFile $logo = null,
        bool $removeLogo = false
    ): array {
        $before = $this->all();
        $oldLogoPath = $before['institution_logo_path'] ?? null;
        $newLogoPath = $oldLogoPath;

        if ($logo !== null) {
            $newLogoPath = Storage::disk('public')->putFile('logos', $logo);
        } elseif ($removeLogo) {
            $newLogoPath = null;
        }

        $payload = [
            'institution_name' => trim((string) ($data['institution_name'] ?? $before['institution_name'])),
            'institution_logo_path' => $newLogoPath,
            'receipt_footer' => trim((string) ($data['receipt_footer'] ?? $before['receipt_footer'])),
            'default_receipt_paper' => (string) ($data['default_receipt_paper'] ?? $before['default_receipt_paper']),
            'return_max_days' => (int) ($data['return_max_days'] ?? $before['return_max_days']),
            'shu_allocation_member_usaha' => round((float) ($data['shu_allocation_member_usaha'] ?? $before['shu_allocation_member_usaha']), 2),
            'shu_allocation_reserve' => round((float) ($data['shu_allocation_reserve'] ?? $before['shu_allocation_reserve']), 2),
            'shu_allocation_management' => round((float) ($data['shu_allocation_management'] ?? $before['shu_allocation_management']), 2),
            'shu_allocation_education' => round((float) ($data['shu_allocation_education'] ?? $before['shu_allocation_education']), 2),
        ];

        DB::transaction(function () use ($payload, $actor): void {
            foreach ($payload as $key => $val) {
                $def = self::DEFINITIONS[$key];
                $storedValue = $val === null ? null : (string) $val;

                AppSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $storedValue,
                        'type' => $def['type'],
                        'group' => $def['group'],
                        'updated_by_user_id' => $actor->id,
                    ]
                );
            }
        });

        // Clean up superseded logo file on public disk after DB transaction succeeds
        if ($oldLogoPath && $oldLogoPath !== $newLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        $this->clearCache();
        $after = $this->all();

        $oldDiff = [];
        $newDiff = [];
        foreach (array_keys(self::DEFINITIONS) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $oldDiff[$key] = $before[$key] ?? null;
                $newDiff[$key] = $after[$key] ?? null;
            }
        }

        $this->activityLogService->record(
            action: ActivityLog::ACTION_SETTINGS_UPDATED,
            description: 'Memperbarui pengaturan sistem KOPDES ('.(empty($newDiff) ? 'tanpa perubahan nilai' : implode(', ', array_keys($newDiff))).')',
            actor: $actor,
            subject: null,
            oldValues: empty($oldDiff) ? $before : $oldDiff,
            newValues: empty($newDiff) ? $after : $newDiff,
            regionOverride: [
                'province_id' => null,
                'regency_id' => null,
                'branch_id' => null,
            ],
        );

        return $after;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultValues(): array
    {
        $defaults = [];
        foreach (self::DEFINITIONS as $key => $def) {
            $defaults[$key] = $def['default'];
        }

        $defaults['return_max_days'] = (int) config('kopdes.return_max_days', 7);

        return $defaults;
    }
}
