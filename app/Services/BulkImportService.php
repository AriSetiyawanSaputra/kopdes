<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\CardUidNormalizer;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class BulkImportService
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly MemberService $memberService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Import massal Master Produk (all-or-nothing, khusus Admin Nasional) (FR-P3-02).
     *
     * @return array{imported_count: int, type: string}
     */
    public function importProducts(UploadedFile $file, User $actor): array
    {
        $requiredHeaders = ['category_name', 'sku', 'barcode', 'name', 'unit', 'cost_price', 'selling_price'];
        $rows = $this->parseSpreadsheetRows($file, $requiredHeaders);

        $categoriesByLowerName = Category::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $c) => [mb_strtolower(trim($c->name)) => $c->id])
            ->all();

        $existingSkus = Product::withTrashed()
            ->pluck('sku')
            ->mapWithKeys(fn ($v) => [mb_strtolower(trim((string) $v)) => true])
            ->all();

        $existingBarcodes = Product::withTrashed()
            ->pluck('barcode')
            ->mapWithKeys(fn ($v) => [mb_strtolower(trim((string) $v)) => true])
            ->all();

        $seenSkus = [];
        $seenBarcodes = [];
        $prepared = [];
        $errors = [];

        foreach ($rows as $entry) {
            $rowNum = $entry['row_num'];
            $data = $entry['data'];

            $categoryName = trim((string) ($data['category_name'] ?? ''));
            $sku = trim((string) ($data['sku'] ?? ''));
            $barcode = trim((string) ($data['barcode'] ?? ''));
            $name = trim((string) ($data['name'] ?? ''));
            $unit = trim((string) ($data['unit'] ?? ''));
            $rawCost = trim((string) ($data['cost_price'] ?? ''));
            $rawSell = trim((string) ($data['selling_price'] ?? ''));
            $rawMemberPrice = trim((string) ($data['member_price'] ?? ''));

            $categoryId = $categoriesByLowerName[mb_strtolower($categoryName)] ?? null;
            if ($categoryName === '' || $categoryId === null) {
                $errors[] = [
                    'row' => $rowNum,
                    'field' => 'category_name',
                    'message' => "Kategori '{$categoryName}' tidak ditemukan di Master Kategori.",
                ];
            }

            if ($sku === '' || strlen($sku) > 50) {
                $errors[] = ['row' => $rowNum, 'field' => 'sku', 'message' => 'SKU wajib diisi (maks 50 karakter).'];
            } else {
                $skuKey = mb_strtolower($sku);
                if (isset($seenSkus[$skuKey])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'sku', 'message' => "SKU '{$sku}' duplikat dengan baris {$seenSkus[$skuKey]} pada file."];
                } elseif (isset($existingSkus[$skuKey])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'sku', 'message' => "SKU '{$sku}' sudah terdaftar di database."];
                } else {
                    $seenSkus[$skuKey] = $rowNum;
                }
            }

            if ($barcode === '' || strlen($barcode) > 64) {
                $errors[] = ['row' => $rowNum, 'field' => 'barcode', 'message' => 'Barcode wajib diisi (maks 64 karakter).'];
            } else {
                $barcodeKey = mb_strtolower($barcode);
                if (isset($seenBarcodes[$barcodeKey])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'barcode', 'message' => "Barcode '{$barcode}' duplikat dengan baris {$seenBarcodes[$barcodeKey]} pada file."];
                } elseif (isset($existingBarcodes[$barcodeKey])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'barcode', 'message' => "Barcode '{$barcode}' sudah terdaftar di database."];
                } else {
                    $seenBarcodes[$barcodeKey] = $rowNum;
                }
            }

            if ($name === '' || mb_strlen($name) > 200) {
                $errors[] = ['row' => $rowNum, 'field' => 'name', 'message' => 'Nama produk wajib diisi (maks 200 karakter).'];
            }

            if ($unit === '' || mb_strlen($unit) > 30) {
                $errors[] = ['row' => $rowNum, 'field' => 'unit', 'message' => 'Satuan produk wajib diisi (maks 30 karakter).'];
            }

            $costPrice = is_numeric($rawCost) ? round((float) $rawCost, 2) : -1;
            if ($rawCost === '' || ! is_numeric($rawCost) || $costPrice < 0) {
                $errors[] = ['row' => $rowNum, 'field' => 'cost_price', 'message' => 'HPP (cost_price) harus berupa angka >= 0.'];
            }

            $sellingPrice = is_numeric($rawSell) ? round((float) $rawSell, 2) : -1;
            if ($rawSell === '' || ! is_numeric($rawSell) || $sellingPrice < 0) {
                $errors[] = ['row' => $rowNum, 'field' => 'selling_price', 'message' => 'Harga jual (selling_price) harus berupa angka >= 0.'];
            }

            $memberPrice = null;
            if ($rawMemberPrice !== '') {
                if (! is_numeric($rawMemberPrice) || (float) $rawMemberPrice < 0) {
                    $errors[] = ['row' => $rowNum, 'field' => 'member_price', 'message' => 'Harga member (member_price) harus berupa angka >= 0.'];
                } else {
                    $memberPrice = round((float) $rawMemberPrice, 2);
                    if ($sellingPrice >= 0 && $memberPrice > $sellingPrice) {
                        $errors[] = ['row' => $rowNum, 'field' => 'member_price', 'message' => 'Harga member tidak boleh lebih tinggi dari harga jual normal (selling_price).'];
                    }
                }
            }

            $prepared[] = [
                'category_id' => $categoryId,
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $name,
                'unit' => $unit,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'member_price' => $memberPrice,
                'is_active' => true,
            ];
        }

        if (! empty($errors)) {
            $this->throwRowErrors($errors);
        }

        DB::transaction(function () use ($prepared, $actor): void {
            foreach ($prepared as $item) {
                Product::create($item);
            }

            $this->activityLogService->record(
                action: ActivityLog::ACTION_IMPORT_COMPLETED,
                description: 'Import massal Master Produk berhasil ('.count($prepared).' produk baru)',
                actor: $actor,
                newValues: [
                    'import_type' => 'products',
                    'imported_count' => count($prepared),
                ],
                regionOverride: [
                    'province_id' => null,
                    'regency_id' => null,
                    'branch_id' => null,
                ],
            );
        });

        return [
            'imported_count' => count($prepared),
            'type' => 'products',
        ];
    }

    /**
     * Import massal Stok Awal Cabang dengan lockForUpdate() & stock_movements (all-or-nothing) (FR-P3-03).
     *
     * @return array{imported_count: int, type: string, branch_name: string}
     */
    public function importInitialStocks(UploadedFile $file, User $actor, ?int $requestedBranchId = null): array
    {
        $branch = $this->resolveTargetBranch($actor, $requestedBranchId);

        $requiredHeaders = ['barcode', 'qty_add'];
        $rows = $this->parseSpreadsheetRows($file, $requiredHeaders);

        $productsByBarcode = Product::query()
            ->where('is_active', true)
            ->get(['id', 'barcode', 'name'])
            ->keyBy(fn (Product $p) => mb_strtolower(trim($p->barcode)));

        $seenBarcodes = [];
        $prepared = [];
        $errors = [];

        foreach ($rows as $entry) {
            $rowNum = $entry['row_num'];
            $data = $entry['data'];

            $barcode = trim((string) ($data['barcode'] ?? ''));
            $rawQty = trim((string) ($data['qty_add'] ?? ''));
            $rawMinStock = trim((string) ($data['min_stock'] ?? ''));
            $notes = trim((string) ($data['notes'] ?? ''));

            $barcodeKey = mb_strtolower($barcode);
            /** @var Product|null $product */
            $product = $barcode !== '' ? $productsByBarcode->get($barcodeKey) : null;

            if ($barcode === '' || ! $product) {
                $errors[] = [
                    'row' => $rowNum,
                    'field' => 'barcode',
                    'message' => "Produk aktif dengan barcode '{$barcode}' tidak ditemukan di Katalog Global.",
                ];
            } elseif (isset($seenBarcodes[$barcodeKey])) {
                $errors[] = [
                    'row' => $rowNum,
                    'field' => 'barcode',
                    'message' => "Barcode '{$barcode}' duplikat dengan baris {$seenBarcodes[$barcodeKey]} pada file.",
                ];
            } else {
                $seenBarcodes[$barcodeKey] = $rowNum;
            }

            if ($rawQty === '' || filter_var($rawQty, FILTER_VALIDATE_INT) === false || (int) $rawQty < 1) {
                $errors[] = ['row' => $rowNum, 'field' => 'qty_add', 'message' => 'Jumlah stok masuk (qty_add) wajib bilangan bulat minimal 1.'];
            }

            $minStock = null;
            if ($rawMinStock !== '') {
                if (filter_var($rawMinStock, FILTER_VALIDATE_INT) === false || (int) $rawMinStock < 0) {
                    $errors[] = ['row' => $rowNum, 'field' => 'min_stock', 'message' => 'Stok minimum (min_stock) harus berupa bilangan bulat >= 0.'];
                } else {
                    $minStock = (int) $rawMinStock;
                }
            }

            if ($product) {
                $prepared[] = [
                    'product_id' => $product->id,
                    'qty_add' => (int) $rawQty,
                    'min_stock' => $minStock,
                    'notes' => $notes !== '' ? $notes : 'Import Stok Awal',
                ];
            }
        }

        if (! empty($errors)) {
            $this->throwRowErrors($errors);
        }

        // Urutkan berdasarkan product_id menaik untuk mencegah deadlock saat lockForUpdate()
        usort($prepared, fn (array $a, array $b) => $a['product_id'] <=> $b['product_id']);
        $productIds = array_column($prepared, 'product_id');

        DB::transaction(function () use ($prepared, $productIds, $branch, $actor): void {
            $lockedStocks = BranchStock::query()
                ->where('branch_id', $branch->id)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($prepared as $line) {
                /** @var BranchStock|null $stock */
                $stock = $lockedStocks->get($line['product_id']);

                if ($stock === null) {
                    $qtyBefore = 0;
                    $qtyAfter = $line['qty_add'];

                    BranchStock::create([
                        'branch_id' => $branch->id,
                        'product_id' => $line['product_id'],
                        'quantity' => $qtyAfter,
                        'reserved_qty' => 0,
                        'min_stock' => $line['min_stock'] ?? 5,
                    ]);
                } else {
                    $qtyBefore = (int) $stock->quantity;
                    $qtyAfter = $qtyBefore + $line['qty_add'];

                    $updatePayload = ['quantity' => $qtyAfter];
                    if ($line['min_stock'] !== null) {
                        $updatePayload['min_stock'] = $line['min_stock'];
                    }
                    $stock->update($updatePayload);
                }

                StockMovement::create([
                    'branch_id' => $branch->id,
                    'product_id' => $line['product_id'],
                    'user_id' => $actor->id,
                    'type' => 'restock',
                    'qty_change' => $line['qty_add'],
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'reference_type' => 'bulk_import',
                    'reference_id' => null,
                    'notes' => $line['notes'],
                    'created_at' => now(),
                ]);
            }

            $this->activityLogService->record(
                action: ActivityLog::ACTION_IMPORT_COMPLETED,
                description: "Import massal Stok Awal di {$branch->name} ({$branch->code}) berhasil (".count($prepared).' produk)',
                actor: $actor,
                subject: $branch,
                newValues: [
                    'import_type' => 'stocks',
                    'branch_id' => $branch->id,
                    'imported_count' => count($prepared),
                ],
            );
        });

        return [
            'imported_count' => count($prepared),
            'type' => 'stocks',
            'branch_name' => $branch->name,
        ];
    }

    /**
     * Import massal Anggota (Member) dengan dukungan card_uid nullable (all-or-nothing) (FR-P3-04).
     *
     * @return array{imported_count: int, type: string, branch_name: string}
     */
    public function importMembers(UploadedFile $file, User $actor, ?int $requestedBranchId = null): array
    {
        $branch = $this->resolveTargetBranch($actor, $requestedBranchId);

        $requiredHeaders = ['name'];
        $rows = $this->parseSpreadsheetRows($file, $requiredHeaders);

        $existingNiks = Member::query()
            ->whereNotNull('nik')
            ->pluck('nik')
            ->mapWithKeys(fn ($v) => [trim((string) $v) => true])
            ->all();

        $existingUserCards = User::query()
            ->whereNotNull('card_uid')
            ->pluck('card_uid')
            ->mapWithKeys(fn ($v) => [CardUidNormalizer::normalize((string) $v) => true])
            ->all();

        $existingMemberCards = Member::query()
            ->whereNotNull('card_uid')
            ->pluck('card_uid')
            ->mapWithKeys(fn ($v) => [CardUidNormalizer::normalize((string) $v) => true])
            ->all();

        $seenNiks = [];
        $seenCards = [];
        $prepared = [];
        $errors = [];

        foreach ($rows as $entry) {
            $rowNum = $entry['row_num'];
            $data = $entry['data'];

            $name = trim((string) ($data['name'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? ''));
            $nik = trim((string) ($data['nik'] ?? ''));
            $rawCard = trim((string) ($data['card_uid'] ?? ''));
            $rawPin = trim((string) ($data['pin'] ?? ''));
            $rawJoinedAt = $data['joined_at'] ?? '';

            if ($name === '' || mb_strlen($name) > 150) {
                $errors[] = ['row' => $rowNum, 'field' => 'name', 'message' => 'Nama anggota wajib diisi (maks 150 karakter).'];
            }

            if ($phone !== '' && mb_strlen($phone) > 25) {
                $errors[] = ['row' => $rowNum, 'field' => 'phone', 'message' => 'Nomor HP maksimal 25 karakter.'];
            }

            if ($nik !== '') {
                if (mb_strlen($nik) > 20) {
                    $errors[] = ['row' => $rowNum, 'field' => 'nik', 'message' => 'NIK maksimal 20 karakter.'];
                } elseif (isset($seenNiks[$nik])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'nik', 'message' => "NIK '{$nik}' duplikat dengan baris {$seenNiks[$nik]} pada file."];
                } elseif (isset($existingNiks[$nik])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'nik', 'message' => "NIK '{$nik}' sudah terdaftar pada anggota lain."];
                } else {
                    $seenNiks[$nik] = $rowNum;
                }
            }

            $normalizedCard = null;
            if ($rawCard !== '') {
                $norm = CardUidNormalizer::normalize($rawCard);
                if ($norm === '' || strlen($norm) > 64) {
                    $errors[] = ['row' => $rowNum, 'field' => 'card_uid', 'message' => 'Format UID kartu RFID tidak valid (maks 64 karakter alfanumerik).'];
                } elseif (isset($seenCards[$norm])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'card_uid', 'message' => "UID kartu '{$norm}' duplikat dengan baris {$seenCards[$norm]} pada file."];
                } elseif (isset($existingUserCards[$norm])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'card_uid', 'message' => "UID kartu '{$norm}' sudah digunakan oleh akun Kasir."];
                } elseif (isset($existingMemberCards[$norm])) {
                    $errors[] = ['row' => $rowNum, 'field' => 'card_uid', 'message' => "UID kartu '{$norm}' sudah digunakan oleh Anggota lain."];
                } else {
                    $seenCards[$norm] = $rowNum;
                    $normalizedCard = $norm;
                }
            }

            $pinHash = null;
            if ($rawPin !== '') {
                if (! preg_match('/^\d{6}$/', $rawPin)) {
                    $errors[] = ['row' => $rowNum, 'field' => 'pin', 'message' => 'PIN Portal Member wajib tepat 6 digit angka (contoh: 123456).'];
                } else {
                    $pinHash = Hash::make($rawPin);
                }
            }

            $joinedAt = now()->toDateString();
            $joinedStr = trim((string) $rawJoinedAt);
            if ($joinedStr !== '') {
                $parsedDate = $this->parseDateValue($rawJoinedAt);
                if ($parsedDate === null) {
                    $errors[] = ['row' => $rowNum, 'field' => 'joined_at', 'message' => "Format tanggal bergabung '{$joinedStr}' tidak valid (gunakan YYYY-MM-DD)."];
                } else {
                    $joinedAt = $parsedDate;
                }
            }

            $prepared[] = [
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'nik' => $nik !== '' ? $nik : null,
                'card_uid' => $normalizedCard,
                'pin_hash' => $pinHash,
                'status' => 'active',
                'joined_at' => $joinedAt,
            ];
        }

        if (! empty($errors)) {
            $this->throwRowErrors($errors);
        }

        DB::transaction(function () use ($prepared, $branch, $actor): void {
            foreach ($prepared as $item) {
                $memberCode = $this->memberService->generateMemberCode($branch);
                Member::create(array_merge($item, [
                    'member_code' => $memberCode,
                    'home_branch_id' => $branch->id,
                ]));
            }

            $this->activityLogService->record(
                action: ActivityLog::ACTION_IMPORT_COMPLETED,
                description: "Import massal Anggota (Member) di {$branch->name} ({$branch->code}) berhasil (".count($prepared).' anggota baru)',
                actor: $actor,
                subject: $branch,
                newValues: [
                    'import_type' => 'members',
                    'branch_id' => $branch->id,
                    'imported_count' => count($prepared),
                ],
            );
        });

        return [
            'imported_count' => count($prepared),
            'type' => 'members',
            'branch_name' => $branch->name,
        ];
    }

    /**
     * Resolve target branch with strict Anti-IDOR enforcement for admin_cabang.
     */
    private function resolveTargetBranch(User $actor, ?int $requestedBranchId): Branch
    {
        if ($actor->isAdminCabang()) {
            if ($requestedBranchId !== null && (int) $requestedBranchId !== (int) $actor->branch_id) {
                abort(403, 'Anda tidak berwenang mengimpor data ke cabang KOPDES lain.');
            }

            return Branch::findOrFail((int) $actor->branch_id);
        }

        if (! $actor->isAdminNasional()) {
            abort(403, 'Anda tidak berwenang melakukan import massal ini.');
        }

        if ($requestedBranchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang KOPDES target wajib dipilih.',
            ]);
        }

        return Branch::findOrFail($requestedBranchId);
    }

    /**
     * Parse Excel (.xlsx) or CSV (.csv) rows into normalized associative arrays keyed by header name.
     *
     * @param  list<string>  $requiredHeaders
     * @return list<array{row_num: int, data: array<string, mixed>}>
     */
    private function parseSpreadsheetRows(UploadedFile $file, array $requiredHeaders): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'file' => 'File upload tidak dapat dibaca oleh server.',
            ]);
        }

        try {
            if ($ext === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $spreadsheet = $reader->load($path);
            } else {
                $spreadsheet = IOFactory::load($path);
            }
            $sheet = $spreadsheet->getActiveSheet();
            $rawRows = $sheet->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'file' => 'Gagal membaca file Excel/CSV. Pastikan file tidak rusak atau terproteksi password.',
            ]);
        }

        if (empty($rawRows)) {
            throw ValidationException::withMessages([
                'file' => 'File import kosong.',
            ]);
        }

        $headerRow = array_shift($rawRows);
        $headers = array_map(function ($cell) {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell);

            return strtolower(trim((string) $clean));
        }, $headerRow);

        $missingHeaders = array_values(array_diff($requiredHeaders, $headers));
        if (! empty($missingHeaders)) {
            throw ValidationException::withMessages([
                'file' => 'Header kolom wajib tidak lengkap: '.implode(', ', $missingHeaders).'. Gunakan template resmi yang tersedia.',
            ]);
        }

        $parsed = [];
        foreach ($rawRows as $idx => $cells) {
            $excelRowNum = $idx + 2; // Row 1 adalah header
            $hasContent = false;
            foreach ($cells as $val) {
                if (trim((string) $val) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (! $hasContent) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $colIdx => $colName) {
                if ($colName === '') {
                    continue;
                }
                $assoc[$colName] = $cells[$colIdx] ?? null;
            }

            $parsed[] = [
                'row_num' => $excelRowNum,
                'data' => $assoc,
            ];
        }

        if (empty($parsed)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ditemukan baris data di bawah header pada file yang diunggah.',
            ]);
        }

        if (count($parsed) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => 'Jumlah baris melebihi batas maksimal '.self::MAX_ROWS.' baris per sekali upload.',
            ]);
        }

        return $parsed;
    }

    private function parseDateValue(mixed $raw): ?string
    {
        if (is_numeric($raw) && (float) $raw > 20000 && (float) $raw < 100000) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        $str = trim((string) $raw);
        try {
            return Carbon::parse($str)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array{row: int, field: string, message: string}>  $errors
     */
    private function throwRowErrors(array $errors): never
    {
        throw ValidationException::withMessages([
            'file' => 'Import dibatalkan (all-or-nothing) karena ditemukan '.count($errors).' kesalahan validasi.',
            'import_errors' => $errors,
        ]);
    }
}
