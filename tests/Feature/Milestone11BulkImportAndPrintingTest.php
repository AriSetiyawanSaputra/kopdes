<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Province;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Milestone11BulkImportAndPrintingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $adminNasional;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut;

    protected Category $category;

    protected Product $existingProduct;

    protected Member $memberNgunut;

    protected Member $memberCekok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

        $this->adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $this->adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $this->adminCekok = User::where('email', 'admin.cekok@kopdes.id')->firstOrFail();
        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();

        $this->category = Category::firstOrCreate(
            ['slug' => 'sembako-m11'],
            ['name' => 'Sembako M11']
        );

        $this->existingProduct = Product::create([
            'category_id' => $this->category->id,
            'sku' => 'SKU-M11-EXIST',
            'barcode' => '8991100110011',
            'name' => 'Gula Pasir 1kg',
            'unit' => 'kg',
            'cost_price' => 14000,
            'selling_price' => 17000,
            'member_price' => 16000,
            'is_active' => true,
        ]);

        $this->memberNgunut = Member::where('home_branch_id', $this->branchNgunut->id)->firstOrFail();
        $this->memberCekok = Member::where('home_branch_id', $this->branchCekok->id)->firstOrFail();
    }

    /**
     * Helper to build an uploaded .xlsx file in temp storage from rows.
     *
     * @param  list<list<mixed>>  $rows
     */
    private function makeXlsxUpload(array $rows, string $filename = 'import.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');

        $tmpPath = tempnam(sys_get_temp_dir(), 'kopdes_m11_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $tmpPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * (a) Command kopdes:import-regions mengimpor CSV wilayah secara idempotent dan menolak header invalid.
     */
    public function test_import_regions_command_upserts_idempotently_and_rejects_invalid_headers(): void
    {
        $validCsv = tempnam(sys_get_temp_dir(), 'reg_ok_').'.csv';
        file_put_contents($validCsv, "id,name\n91,PAPUA BARAT TEST\n92,PAPUA TENGAH TEST\n");

        try {
            $this->artisan('kopdes:import-regions', ['file' => $validCsv, '--type' => 'provinces'])
                ->assertSuccessful();

            $this->assertDatabaseHas('provinces', ['id' => '91', 'name' => 'PAPUA BARAT TEST']);
            $this->assertDatabaseHas('provinces', ['id' => '92', 'name' => 'PAPUA TENGAH TEST']);

            // Jalankan ulang dengan perubahan nama -> idempotent (update nama tanpa duplikasi PK)
            file_put_contents($validCsv, "id,name\n91,PAPUA BARAT REVISI\n92,PAPUA TENGAH TEST\n");
            $this->artisan('kopdes:import-regions', ['file' => $validCsv, '--type' => 'provinces'])
                ->assertSuccessful();

            $this->assertSame('PAPUA BARAT REVISI', Province::find('91')?->name);
            $this->assertSame(1, Province::where('id', '91')->count());
        } finally {
            @unlink($validCsv);
        }

        // Header CSV tidak sesuai -> exit code non-zero
        $badCsv = tempnam(sys_get_temp_dir(), 'reg_bad_').'.csv';
        file_put_contents($badCsv, "kode,nama_prov\n99,INVALID\n");

        try {
            $this->artisan('kopdes:import-regions', ['file' => $badCsv, '--type' => 'provinces'])
                ->assertFailed();
        } finally {
            @unlink($badCsv);
        }
    }

    /**
     * (b) Import massal produk gagal total (all-or-nothing, HTTP 422 + nomor baris)
     * jika ada 1 baris invalid (member_price > selling_price atau duplikat internal), dan sukses jika semua valid.
     */
    public function test_bulk_product_import_is_all_or_nothing_and_logs_activity_on_success(): void
    {
        // Template download hanya untuk Admin Nasional
        $this->actingAs($this->adminNgunut)
            ->get(route('imports.template', 'products'))
            ->assertForbidden();

        $tplRes = $this->actingAs($this->adminNasional)
            ->get(route('imports.template', 'products'));
        $tplRes->assertOk();
        $this->assertStringStartsWith('PK', $tplRes->streamedContent());

        // File dengan 1 baris valid (baris 2) dan 2 baris invalid (baris 3: member_price > selling_price, baris 4: duplikat internal barcode)
        $badFile = $this->makeXlsxUpload([
            ['category_name', 'sku', 'barcode', 'name', 'unit', 'cost_price', 'selling_price', 'member_price'],
            ['Sembako M11', 'SKU-NEW-001', '8991111000001', 'Tepung Terigu 1kg', 'kg', 10000, 14000, 13000],
            ['Sembako M11', 'SKU-NEW-002', '8991111000002', 'Garam Dapur 500g', 'pcs', 3000, 5000, 6000],
            ['Sembako M11', 'SKU-NEW-003', '8991111000001', 'Duplikat Barcode', 'pcs', 4000, 6000, ''],
        ]);

        $failRes = $this->actingAs($this->adminNasional)
            ->postJson(route('imports.store', 'products'), ['file' => $badFile]);

        $failRes->assertStatus(422);
        $importErrors = $failRes->json('errors.import_errors');
        $this->assertIsArray($importErrors);
        $this->assertTrue(collect($importErrors)->contains(fn ($e) => $e['row'] === 3 && $e['field'] === 'member_price'));
        $this->assertTrue(collect($importErrors)->contains(fn ($e) => $e['row'] === 4 && $e['field'] === 'barcode'));

        // All-or-nothing: baris 2 yang valid TIDAK boleh tersimpan!
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-NEW-001']);

        // File 100% valid -> seluruh baris tersimpan + tercatat di activity_logs
        $goodFile = $this->makeXlsxUpload([
            ['category_name', 'sku', 'barcode', 'name', 'unit', 'cost_price', 'selling_price', 'member_price'],
            ['Sembako M11', 'SKU-NEW-001', '8991111000001', 'Tepung Terigu 1kg', 'kg', 10000, 14000, 13000],
            ['Sembako M11', 'SKU-NEW-002', '8991111000002', 'Garam Dapur 500g', 'pcs', 3000, 5000, ''],
        ]);

        $okRes = $this->actingAs($this->adminNasional)
            ->postJson(route('imports.store', 'products'), ['file' => $goodFile]);
        $okRes->assertOk();

        $this->assertDatabaseHas('products', ['sku' => 'SKU-NEW-001', 'member_price' => 13000]);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-NEW-002', 'member_price' => null]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_IMPORT_COMPLETED,
            'actor_id' => $this->adminNasional->id,
        ]);
    }

    /**
     * (c) Import member dengan banyak baris card_uid kosong tersimpan sebagai NULL tanpa konflik UNIQUE,
     * dan card_uid yang terisi tetap divalidasi unik lintas users & members.
     */
    public function test_bulk_member_import_allows_multiple_null_card_uids_and_enforces_cross_table_uniqueness(): void
    {
        // File gagal jika card_uid bentrok dengan kartu Kasir (0008493873)
        $conflictFile = $this->makeXlsxUpload([
            ['name', 'phone', 'nik', 'card_uid', 'pin', 'joined_at'],
            ['Warga Satu', '0811110001', '3502880101900001', '', '123456', '2026-02-01'],
            ['Warga Bentrok Kasir', '0811110002', '3502880101900002', '0008-4938-73', '654321', '2026-02-01'],
        ]);

        $this->actingAs($this->adminNgunut)
            ->postJson(route('imports.store', 'members'), ['file' => $conflictFile])
            ->assertStatus(422)
            ->assertJsonFragment(['row' => 3, 'field' => 'card_uid']);

        $this->assertDatabaseMissing('members', ['name' => 'Warga Satu']);

        // File valid: 2 baris dengan card_uid kosong/spasi (NULL) + 1 baris dengan card_uid baru + PIN 6 digit
        $validFile = $this->makeXlsxUpload([
            ['name', 'phone', 'nik', 'card_uid', 'pin', 'joined_at'],
            ['Warga Tanpa RFID 1', '0811110011', '3502880101900011', '', '112233', '2026-02-10'],
            ['Warga Tanpa RFID 2', '0811110012', '3502880101900012', '   ', '', '2026-02-11'],
            ['Warga Dengan RFID', '0811110013', '3502880101900013', 'rfid-m11-001', '654321', '2026-02-12'],
        ]);

        $this->actingAs($this->adminNgunut)
            ->postJson(route('imports.store', 'members'), ['file' => $validFile])
            ->assertOk();

        $m1 = Member::where('name', 'Warga Tanpa RFID 1')->firstOrFail();
        $m2 = Member::where('name', 'Warga Tanpa RFID 2')->firstOrFail();
        $m3 = Member::where('name', 'Warga Dengan RFID')->firstOrFail();

        $this->assertNull($m1->card_uid);
        $this->assertFalse($m1->has_rfid_card);
        $this->assertTrue(Hash::check('112233', (string) $m1->pin_hash));
        $this->assertArrayNotHasKey('pin_hash', $m1->toArray());

        $this->assertNull($m2->card_uid);
        $this->assertNull($m2->pin_hash);

        $this->assertSame('RFIDM11001', $m3->card_uid);
        $this->assertTrue($m3->has_rfid_card);
        $this->assertSame($this->branchNgunut->id, $m1->home_branch_id);

        // Anti-IDOR: admin_cabang Ngunut dilarang menyuntikkan branch_id Cekok
        $idorFile = $this->makeXlsxUpload([
            ['name', 'phone', 'nik', 'card_uid', 'pin', 'joined_at'],
            ['Warga Injeksi', '0811110099', '', '', '', ''],
        ]);

        $this->actingAs($this->adminNgunut)
            ->postJson(route('imports.store', 'members'), [
                'file' => $idorFile,
                'branch_id' => $this->branchCekok->id,
            ])
            ->assertForbidden();
    }

    /**
     * (d) Import stok awal menulis branch_stocks & stock_movements (restock) dan admin_cabang terkunci ke cabangnya.
     */
    public function test_bulk_initial_stock_import_writes_movements_and_blocks_cross_branch_idor(): void
    {
        $stockFile = $this->makeXlsxUpload([
            ['barcode', 'qty_add', 'min_stock', 'notes'],
            ['8991100110011', 45, 8, 'Stok perdana gudang Ngunut'],
        ]);

        // Anti-IDOR: adminNgunut mencoba mengimpor stok ke Cekok -> HTTP 403
        $this->actingAs($this->adminNgunut)
            ->postJson(route('imports.store', 'stocks'), [
                'file' => $stockFile,
                'branch_id' => $this->branchCekok->id,
            ])
            ->assertForbidden();

        // Import sah untuk cabang Ngunut sendiri
        $stockFileValid = $this->makeXlsxUpload([
            ['barcode', 'qty_add', 'min_stock', 'notes'],
            ['8991100110011', 45, 8, 'Stok perdana gudang Ngunut'],
        ]);

        $this->actingAs($this->adminNgunut)
            ->postJson(route('imports.store', 'stocks'), ['file' => $stockFileValid])
            ->assertOk();

        $stock = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->existingProduct->id)
            ->firstOrFail();

        $this->assertSame(45, (int) $stock->quantity);
        $this->assertSame(8, (int) $stock->min_stock);

        $movement = StockMovement::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->existingProduct->id)
            ->where('type', 'restock')
            ->firstOrFail();
        $this->assertSame(45, (int) $movement->qty_change);
        $this->assertSame('Stok perdana gudang Ngunut', $movement->notes);

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_IMPORT_COMPLETED,
            'branch_id' => $this->branchNgunut->id,
        ]);
    }

    /**
     * (e) Akses halaman cetak label barcode & cetak kartu member mengikuti RBAC dan menolak lintas cabang HTTP 403.
     */
    public function test_barcode_labels_and_member_card_printing_enforce_rbac_and_anti_idor(): void
    {
        // Cetak label barcode dapat diakses Admin maupun Kasir
        $this->actingAs($this->adminNasional)
            ->get(route('products.barcode-labels'))
            ->assertOk();

        $this->actingAs($this->adminNgunut)
            ->get(route('products.barcode-labels'))
            ->assertOk();

        $this->actingAs($this->cashierNgunut)
            ->get(route('products.barcode-labels'))
            ->assertOk();

        // Cetak kartu member satuan & batch: adminNgunut boleh mencetak member Ngunut
        $this->actingAs($this->adminNgunut)
            ->get(route('members.card', $this->memberNgunut))
            ->assertOk();

        $this->actingAs($this->adminNgunut)
            ->get(route('members.print-cards', ['ids' => [$this->memberNgunut->id]]))
            ->assertOk();

        // Anti-IDOR: adminNgunut ditolak HTTP 403 saat mencoba mencetak kartu member cabang Cekok (satuan maupun batch)
        $this->actingAs($this->adminNgunut)
            ->get(route('members.card', $this->memberCekok))
            ->assertForbidden();

        $this->actingAs($this->adminNgunut)
            ->get(route('members.print-cards', ['ids' => [$this->memberNgunut->id, $this->memberCekok->id]]))
            ->assertForbidden();

        // Kasir ditolak HTTP 403 mengakses cetak kartu member
        $this->actingAs($this->cashierNgunut)
            ->get(route('members.card', $this->memberNgunut))
            ->assertForbidden();
    }
}
