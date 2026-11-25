<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\ShiftCashMovement;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\FinancialReportService;
use App\Services\TransactionReturnService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class Milestone9Phase2ReportsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $adminNasional;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut;

    protected User $cashierCekok;

    protected CashierShift $shiftNgunut;

    protected CashierShift $shiftCekok;

    protected Product $product;

    protected Member $memberA;

    protected Member $memberB;

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
        $this->cashierCekok = User::where('card_uid', '0008305215')->firstOrFail();

        $this->shiftNgunut = CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-M9-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $this->shiftCekok = CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-002-M9-001',
            'branch_id' => $this->branchCekok->id,
            'cashier_id' => $this->cashierCekok->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako-m9'],
            ['name' => 'Sembako M9']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-M9-001',
            'barcode' => '8999009009001',
            'name' => 'Beras KOPDES M9 5kg',
            'unit' => 'karung',
            'cost_price' => 30000,
            'selling_price' => 50000,
            'member_price' => 45000,
            'is_active' => true,
        ]);

        foreach ([$this->branchNgunut, $this->branchCekok] as $branch) {
            BranchStock::create([
                'branch_id' => $branch->id,
                'product_id' => $this->product->id,
                'quantity' => 100,
                'reserved_qty' => 0,
                'min_stock' => 5,
            ]);
        }

        $this->memberA = Member::create([
            'home_branch_id' => $this->branchNgunut->id,
            'member_code' => 'MBR-M9-0001',
            'card_uid' => 'M9CARD0001',
            'name' => 'Anggota Alpha M9',
            'nik' => '3502990101990001',
            'phone' => '081234560001',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $this->memberB = Member::create([
            'home_branch_id' => $this->branchNgunut->id,
            'member_code' => 'MBR-M9-0002',
            'card_uid' => 'M9CARD0002',
            'name' => 'Anggota Beta M9',
            'nik' => '3502990101990002',
            'phone' => '081234560002',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);
    }

    private function checkoutCash(User $cashier, int $qty, ?Member $member = null): Transaction
    {
        $unitPrice = $member ? 45000 : 50000;
        $total = $qty * $unitPrice;

        $res = $this->actingAs($cashier)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => $total,
                'member_id' => $member?->id,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => $qty],
                ],
            ]);

        $res->assertOk();

        return Transaction::with('items')->findOrFail($res->json('transaction.id'));
    }

    /**
     * (a) Retur berkondisi restockable menurunkan Omzet Bersih dan HPP Bersih sesuai snapshot transaksi asal.
     */
    public function test_restockable_return_lowers_net_revenue_and_net_cogs_using_original_snapshots(): void
    {
        $tx = $this->checkoutCash($this->cashierNgunut, qty: 4, member: $this->memberA);
        $txItem = $tx->items->firstOrFail();

        // Ubah harga & HPP katalog setelah transaksi untuk membuktikan laporan murni memakai snapshot
        $this->product->update([
            'cost_price' => 99000,
            'selling_price' => 120000,
            'member_price' => 110000,
        ]);

        // Retur parsial 1 unit berkondisi restockable (refund 45.000, restocked_cogs 30.000)
        app(TransactionReturnService::class)->processReturn($this->adminNgunut, [
            'transaction_id' => $tx->id,
            'refund_method' => TransactionReturn::REFUND_CASH,
            'cash_source' => TransactionReturn::CASH_SOURCE_BRANCH,
            'reason' => 'Pelanggan kelebihan beli 1 karung (kemasan utuh)',
            'items' => [
                [
                    'transaction_item_id' => $txItem->id,
                    'qty_returned' => 1,
                    'condition' => 'restockable',
                ],
            ],
        ]);

        $filters = [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'payment_method' => 'all',
            'branch_id' => $this->branchNgunut->id,
        ];

        $reportService = app(FinancialReportService::class);
        $dashboard = $reportService->getDashboardSummary($this->adminNgunut, $filters);
        $sales = $reportService->getSalesReport($this->adminNgunut, $filters);
        $members = $reportService->getMemberReport($this->adminNgunut, $filters);

        // Gross: 4 * 45.000 = 180.000; Retur: 45.000; Net Revenue: 135.000
        // Gross COGS: 4 * 30.000 = 120.000; Restocked COGS: 30.000; Net COGS: 90.000
        // Gross Profit: 135.000 - 90.000 = 45.000; Member Discount: 4 * 5.000 = 20.000
        foreach ([$dashboard['kpis'], $sales['summary'], $members['shu_financials']] as $metrics) {
            $this->assertEquals(180000.0, $metrics['gross_revenue']);
            $this->assertEquals(45000.0, $metrics['total_returns']);
            $this->assertEquals(135000.0, $metrics['net_revenue']);
            $this->assertEquals(135000.0, $metrics['total_revenue']);
            $this->assertEquals(120000.0, $metrics['gross_cogs']);
            $this->assertEquals(30000.0, $metrics['restocked_cogs']);
            $this->assertEquals(0.0, $metrics['damaged_cogs']);
            $this->assertEquals(90000.0, $metrics['net_cogs']);
            $this->assertEquals(90000.0, $metrics['total_cogs']);
            $this->assertEquals(45000.0, $metrics['gross_profit']);
            $this->assertEquals(45000.0, $metrics['net_profit']);
            $this->assertEquals(20000.0, $metrics['total_member_discount']);
        }
    }

    /**
     * (b) Retur berkondisi damaged menurunkan Omzet Bersih TANPA menurunkan HPP Bersih
     * sehingga Laba Kotor berkurang sebesar nilai barang rusak dan tercatat pada damaged_cogs.
     */
    public function test_damaged_return_lowers_net_revenue_without_lowering_net_cogs(): void
    {
        // Pembeli umum: 3 * 50.000 = 150.000; Gross COGS = 3 * 30.000 = 90.000; Laba Kotor awal = 60.000
        $tx = $this->checkoutCash($this->cashierNgunut, qty: 3, member: null);
        $txItem = $tx->items->firstOrFail();

        // Retur 1 unit berkondisi damaged (refund 50.000, restocked_cogs 0, damaged_cogs 30.000)
        app(TransactionReturnService::class)->processReturn($this->adminNgunut, [
            'transaction_id' => $tx->id,
            'refund_method' => TransactionReturn::REFUND_TRANSFER,
            'refund_reference' => 'TRF-M9-DMG-001',
            'reason' => 'Karung robek saat diterima pelanggan',
            'items' => [
                [
                    'transaction_item_id' => $txItem->id,
                    'qty_returned' => 1,
                    'condition' => 'damaged',
                ],
            ],
        ]);

        $filters = [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'payment_method' => 'all',
            'branch_id' => $this->branchNgunut->id,
        ];

        $sales = app(FinancialReportService::class)->getSalesReport($this->adminNgunut, $filters);
        $summary = $sales['summary'];

        // Net Revenue = 150.000 - 50.000 = 100.000
        // Net COGS = 90.000 - 0 = 90.000 (HPP rusak TIDAK dikurangi)
        // Gross Profit = 100.000 - 90.000 = 10.000 (margin 2 unit laku 40.000 - rugi modal rusak 30.000 = 10.000)
        $this->assertEquals(150000.0, $summary['gross_revenue']);
        $this->assertEquals(50000.0, $summary['total_returns']);
        $this->assertEquals(100000.0, $summary['net_revenue']);
        $this->assertEquals(90000.0, $summary['gross_cogs']);
        $this->assertEquals(0.0, $summary['restocked_cogs']);
        $this->assertEquals(30000.0, $summary['damaged_cogs']);
        $this->assertEquals(90000.0, $summary['net_cogs']);
        $this->assertEquals(10000.0, $summary['gross_profit']);
        $this->assertEquals(10000.0, $summary['net_profit']);
    }

    /**
     * (c) Laba Bersih, dasar pembagian SHU, dan akumulasi belanja member (KOPDES Asal & Lintas Cabang)
     * otomatis terkoreksi oleh retur, serta shift_cash_movements tidak dihitung sebagai biaya operasional.
     */
    public function test_shu_basis_and_cross_branch_member_spending_are_corrected_by_returns_and_exclude_shift_movements(): void
    {
        // Member A belanja 2 unit di KOPDES Asal (Ngunut): 90.000 (HPP 60.000, hemat 10.000)
        $this->checkoutCash($this->cashierNgunut, qty: 2, member: $this->memberA);

        // Member A belanja 2 unit Lintas Cabang (Cekok): 90.000 (HPP 60.000, hemat 10.000)
        $txCross = $this->checkoutCash($this->cashierCekok, qty: 2, member: $this->memberA);
        $txCrossItem = $txCross->items->firstOrFail();

        // Member B belanja 2 unit di KOPDES Asal (Ngunut): 90.000 (HPP 60.000, hemat 10.000)
        $this->checkoutCash($this->cashierNgunut, qty: 2, member: $this->memberB);

        // Catat kas keluar laci shift 50.000 -> TIDAK boleh masuk Biaya Operasional
        ShiftCashMovement::create([
            'cashier_shift_id' => $this->shiftNgunut->id,
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->cashierNgunut->id,
            'type' => ShiftCashMovement::TYPE_CASH_OUT,
            'amount' => 50000,
            'notes' => 'Setor tunai sebagian ke brankas cabang',
        ]);

        // Catat Biaya Operasional cabang Ngunut sebesar 25.000
        OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => now()->toDateString(),
            'category' => 'listrik_air',
            'description' => 'Token listrik bulanan',
            'amount' => 25000,
        ]);

        // Retur 1 unit restockable atas transaksi lintas cabang Member A di Cekok (refund 45.000, restocked COGS 30.000)
        app(TransactionReturnService::class)->processReturn($this->adminCekok, [
            'transaction_id' => $txCross->id,
            'refund_method' => TransactionReturn::REFUND_CASH,
            'cash_source' => TransactionReturn::CASH_SOURCE_BRANCH,
            'reason' => 'Retur parsial belanja lintas cabang',
            'items' => [
                [
                    'transaction_item_id' => $txCrossItem->id,
                    'qty_returned' => 1,
                    'condition' => 'restockable',
                ],
            ],
        ]);

        $filters = [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'payment_method' => 'all',
        ];

        $report = app(FinancialReportService::class)->getMemberReport($this->adminNasional, $filters);
        $shu = $report['shu_financials'];

        // Omzet Bruto: 270.000 - Retur 45.000 = Omzet Bersih 225.000
        // HPP Bruto: 180.000 - Restock 30.000 = HPP Bersih 150.000
        // Laba Kotor: 75.000 - Biaya Operasional 25.000 (mutasi shift 50.000 diabaikan) = Dasar SHU 50.000
        $this->assertEquals(270000.0, $shu['gross_revenue']);
        $this->assertEquals(45000.0, $shu['total_returns']);
        $this->assertEquals(225000.0, $shu['net_revenue']);
        $this->assertEquals(150000.0, $shu['net_cogs']);
        $this->assertEquals(75000.0, $shu['gross_profit']);
        $this->assertEquals(25000.0, $shu['total_operational_expenses']);
        $this->assertEquals(50000.0, $shu['net_profit']);
        $this->assertEquals(50000.0, $shu['shu_basis']);
        $this->assertEquals(30000.0, $shu['total_member_discount']);

        $membersById = collect($report['members'])->keyBy('id');
        $rowA = $membersById->get($this->memberA->id);
        $rowB = $membersById->get($this->memberB->id);

        // Member A: KOPDES Asal 90.000, Lintas Cabang 90.000 - 45.000 = 45.000, Total 135.000 (60% -> SHU 30.000)
        $this->assertEquals(90000.0, $rowA['home_branch_spend']);
        $this->assertEquals(45000.0, $rowA['other_branch_spend']);
        $this->assertEquals(45000.0, $rowA['returned_spend']);
        $this->assertEquals(135000.0, $rowA['total_spend']);
        $this->assertEquals(60.0, $rowA['spend_share_percent']);
        $this->assertEquals(30000.0, $rowA['estimated_shu_share']);

        // Member B: KOPDES Asal 90.000, Lintas Cabang 0, Total 90.000 (40% -> SHU 20.000)
        $this->assertEquals(90000.0, $rowB['home_branch_spend']);
        $this->assertEquals(0.0, $rowB['other_branch_spend']);
        $this->assertEquals(90000.0, $rowB['total_spend']);
        $this->assertEquals(40.0, $rowB['spend_share_percent']);
        $this->assertEquals(20000.0, $rowB['estimated_shu_share']);
    }

    /**
     * (d) Seluruh endpoint ekspor Excel & PDF (Laba Rugi, SHU, Shift, Retur) mengembalikan HTTP 200
     * dengan Content-Type valid untuk scope sah, dan HTTP 403 pada manipulasi wilayah lintas scope atau akses kasir.
     */
    public function test_all_phase2_exports_return_valid_files_and_enforce_anti_idor_403(): void
    {
        $tx = $this->checkoutCash($this->cashierNgunut, qty: 2, member: $this->memberA);
        $txItem = $tx->items->firstOrFail();

        $return = app(TransactionReturnService::class)->processReturn($this->adminNgunut, [
            'transaction_id' => $tx->id,
            'refund_method' => TransactionReturn::REFUND_CASH,
            'cash_source' => TransactionReturn::CASH_SOURCE_ACTIVE_SHIFT,
            'refund_cashier_shift_id' => $this->shiftNgunut->id,
            'reason' => 'Uji ekspor retur Milestone 9',
            'items' => [
                [
                    'transaction_item_id' => $txItem->id,
                    'qty_returned' => 1,
                    'condition' => 'restockable',
                ],
            ],
        ]);

        // Tutup shift agar seluruh kolom rekonsiliasi shift terisi pada file ekspor
        app(CashierShiftService::class)->closeShift($this->shiftNgunut, $this->cashierNgunut, [
            'actual_closing_cash' => 145000, // 100.000 + 90.000 - 45.000 = 145.000
            'closing_notes' => 'Tutup shift untuk uji ekspor M9',
        ]);

        $validParams = [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        // 1. Valid PDF exports (200 OK + application/pdf + %PDF- signature)
        $pdfRoutes = [
            route('reports.sales.export', array_merge(['format' => 'pdf'], $validParams)),
            route('reports.members.export', array_merge(['format' => 'pdf'], $validParams)),
            route('shifts.export', array_merge(['format' => 'pdf'], $validParams)),
            route('returns.export', array_merge(['format' => 'pdf'], $validParams)),
        ];

        foreach ($pdfRoutes as $url) {
            $res = $this->actingAs($this->adminNgunut)->get($url);
            $res->assertOk();
            $res->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $res->getContent());
        }

        // 2. Valid Excel exports (200 OK + xlsx Content-Type + readable spreadsheet & expected sheet names)
        $excelSpecs = [
            [
                'url' => route('reports.sales.export', array_merge(['format' => 'excel'], $validParams)),
                'sheets' => ['Ringkasan per Cabang', 'Rincian Transaksi'],
                'needle' => null,
            ],
            [
                'url' => route('reports.members.export', array_merge(['format' => 'excel'], $validParams)),
                'sheets' => ['Rekap Member (SHU)'],
                'needle' => 'MBR-M9-0001',
            ],
            [
                'url' => route('shifts.export', array_merge(['format' => 'excel'], $validParams)),
                'sheets' => ['Riwayat Shift Kasir'],
                'needle' => $this->shiftNgunut->shift_code,
            ],
            [
                'url' => route('returns.export', array_merge(['format' => 'excel'], $validParams)),
                'sheets' => ['Riwayat Retur & Void'],
                'needle' => $return->return_number,
            ],
        ];

        foreach ($excelSpecs as $spec) {
            $res = $this->actingAs($this->adminNgunut)->get($spec['url']);
            $res->assertOk();
            $res->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $binary = $res->streamedContent();
            $this->assertStringStartsWith('PK', $binary);

            $tmp = tempnam(sys_get_temp_dir(), 'kopdes_m9_').'.xlsx';
            file_put_contents($tmp, $binary);
            try {
                $spreadsheet = IOFactory::load($tmp);
                $this->assertSame($spec['sheets'], $spreadsheet->getSheetNames());

                if ($spec['needle'] !== null) {
                    $rows = $spreadsheet->getActiveSheet()->toArray();
                    $flatText = implode(' ', array_map(fn ($r) => implode(' ', array_map('strval', $r)), $rows));
                    $this->assertStringContainsString($spec['needle'], $flatText);
                }
            } finally {
                @unlink($tmp);
            }
        }

        // 3. Anti-IDOR: adminNgunut mencoba mengunduh laporan cabang Cekok -> WAJIB HTTP 403
        $tamperedParams = array_merge($validParams, ['branch_id' => $this->branchCekok->id]);
        $tamperedUrls = [
            route('reports.sales.export', array_merge(['format' => 'pdf'], $tamperedParams)),
            route('reports.sales.export', array_merge(['format' => 'excel'], $tamperedParams)),
            route('reports.members.export', array_merge(['format' => 'pdf'], $tamperedParams)),
            route('reports.members.export', array_merge(['format' => 'excel'], $tamperedParams)),
            route('shifts.export', array_merge(['format' => 'pdf'], $tamperedParams)),
            route('shifts.export', array_merge(['format' => 'excel'], $tamperedParams)),
            route('returns.export', array_merge(['format' => 'pdf'], $tamperedParams)),
            route('returns.export', array_merge(['format' => 'excel'], $tamperedParams)),
        ];

        foreach ($tamperedUrls as $url) {
            $this->actingAs($this->adminNgunut)->get($url)->assertForbidden();
        }

        // 4. Kasir dilarang mengakses endpoint ekspor shift & retur -> HTTP 403
        $this->actingAs($this->cashierNgunut)
            ->get(route('shifts.export', array_merge(['format' => 'pdf'], $validParams)))
            ->assertForbidden();
        $this->actingAs($this->cashierNgunut)
            ->get(route('returns.export', array_merge(['format' => 'excel'], $validParams)))
            ->assertForbidden();
    }
}
