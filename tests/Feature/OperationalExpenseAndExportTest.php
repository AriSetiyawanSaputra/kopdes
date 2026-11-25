<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\User;
use App\Services\FinancialReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OperationalExpenseAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected Branch $branchMadiun;

    protected Branch $branchWonogiri;

    protected User $adminNasional;

    protected User $adminJatim;

    protected User $adminPonorogo;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut;

    protected User $cashierCekok;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

        $this->branchMadiun = Branch::create([
            'code' => 'KOP-3519-001',
            'name' => 'KOPDES Desa Balerejo Madiun',
            'village_id' => '3519010001',
            'district_id' => '3519010',
            'regency_id' => '3519',
            'province_id' => '35',
            'address' => 'Jl. Madiun No. 1',
            'is_active' => true,
        ]);

        $this->branchWonogiri = Branch::create([
            'code' => 'KOP-3312-001',
            'name' => 'KOPDES Desa Biting Wonogiri',
            'village_id' => '3312010001',
            'district_id' => '3312010',
            'regency_id' => '3312',
            'province_id' => '33',
            'address' => 'Jl. Wonogiri No. 1',
            'is_active' => true,
        ]);

        $this->adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $this->adminJatim = User::where('email', 'admin.jatim@kopdes.id')->firstOrFail();
        $this->adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $this->adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $this->adminCekok = User::where('email', 'admin.cekok@kopdes.id')->firstOrFail();

        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();
        $this->cashierCekok = User::where('card_uid', '0008305215')->firstOrFail();

        CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-OPX-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako-opex'],
            ['name' => 'Sembako Opex']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-OPEX-001',
            'barcode' => '8999002002002',
            'name' => 'Minyak Goreng 2L',
            'unit' => 'pouch',
            'cost_price' => 30000,
            'selling_price' => 50000,
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
    }

    public function test_admin_cabang_can_crud_own_branch_expenses_and_gets_403_for_other_branch(): void
    {
        $today = now()->toDateString();

        // 1. Admin Cabang Ngunut mencatat biaya operasional di cabangnya sendiri -> 302 redirect success
        $this->actingAs($this->adminNgunut)
            ->post(route('expenses.store'), [
                'expense_date' => $today,
                'category' => OperationalExpense::CATEGORY_LISTRIK_AIR,
                'description' => 'Token Listrik PLN KOPDES Ngunut',
                'amount' => 350000,
                'notes' => 'Meteran utama toko',
            ])
            ->assertRedirect();

        $ownExpense = OperationalExpense::where('branch_id', $this->branchNgunut->id)->firstOrFail();
        $this->assertSame('Token Listrik PLN KOPDES Ngunut', $ownExpense->description);
        $this->assertSame(350000.0, (float) $ownExpense->amount);

        // 2. Admin Cabang Ngunut dapat mengedit biaya miliknya
        $this->actingAs($this->adminNgunut)
            ->put(route('expenses.update', $ownExpense), [
                'expense_date' => $today,
                'category' => OperationalExpense::CATEGORY_LISTRIK_AIR,
                'description' => 'Token Listrik & Air PDAM Ngunut',
                'amount' => 425000,
                'notes' => 'Revisi nominal tagihan',
            ])
            ->assertRedirect();

        $ownExpense->refresh();
        $this->assertSame('Token Listrik & Air PDAM Ngunut', $ownExpense->description);
        $this->assertSame(425000.0, (float) $ownExpense->amount);

        // 3. Buat biaya di Cabang Cekok
        $cekokExpense = OperationalExpense::create([
            'branch_id' => $this->branchCekok->id,
            'user_id' => $this->adminCekok->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_SEWA,
            'description' => 'Sewa Gudang Cekok',
            'amount' => 500000,
        ]);

        // 4. Anti-IDOR: Admin Cabang Ngunut ditolak HTTP 403 saat melihat filter cabang lain, create ke cabang lain, update, atau delete biaya Cekok
        $this->actingAs($this->adminNgunut)
            ->get(route('expenses.index', ['branch_id' => $this->branchCekok->id]))
            ->assertForbidden();

        $this->actingAs($this->adminNgunut)
            ->post(route('expenses.store'), [
                'branch_id' => $this->branchCekok->id,
                'expense_date' => $today,
                'category' => OperationalExpense::CATEGORY_ATK_KEMASAN,
                'description' => 'Coba injeksi cabang lain',
                'amount' => 50000,
            ])
            ->assertForbidden();

        $this->actingAs($this->adminNgunut)
            ->put(route('expenses.update', $cekokExpense), [
                'expense_date' => $today,
                'category' => OperationalExpense::CATEGORY_SEWA,
                'description' => 'Ubah biaya cabang lain',
                'amount' => 10000,
            ])
            ->assertForbidden();

        $this->actingAs($this->adminNgunut)
            ->delete(route('expenses.destroy', $cekokExpense))
            ->assertForbidden();

        // 5. Admin Cabang Ngunut dapat menghapus (soft-delete) biaya miliknya sendiri
        $this->actingAs($this->adminNgunut)
            ->delete(route('expenses.destroy', $ownExpense))
            ->assertRedirect();

        $this->assertSoftDeleted('operational_expenses', ['id' => $ownExpense->id]);
        $this->assertDatabaseHas('operational_expenses', ['id' => $cekokExpense->id, 'deleted_at' => null]);
    }

    public function test_admin_provinsi_and_kabupaten_have_read_only_scoped_access_and_403_on_write_or_out_of_scope(): void
    {
        $today = now()->toDateString();

        $expNgunut = OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_GAJI_HONOR,
            'description' => 'Honor Kasir Ngunut',
            'amount' => 1000000,
        ]);

        $expMadiun = OperationalExpense::create([
            'branch_id' => $this->branchMadiun->id,
            'user_id' => $this->adminNasional->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_TRANSPORTASI,
            'description' => 'BBM Distribusi Madiun',
            'amount' => 250000,
        ]);

        $expWonogiri = OperationalExpense::create([
            'branch_id' => $this->branchWonogiri->id,
            'user_id' => $this->adminNasional->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_PEMELIHARAAN,
            'description' => 'Servis AC Wonogiri',
            'amount' => 300000,
        ]);

        // Admin Provinsi Jatim (35): melihat Ngunut & Madiun (Jatim), TIDAK melihat Wonogiri (Jateng)
        $jatimResponse = $this->actingAs($this->adminJatim)->get(route('expenses.index'));
        $jatimResponse->assertOk();
        $jatimExpenses = collect($jatimResponse->viewData('page')['props']['expenses']);
        $this->assertTrue($jatimExpenses->contains('id', $expNgunut->id));
        $this->assertTrue($jatimExpenses->contains('id', $expMadiun->id));
        $this->assertFalse($jatimExpenses->contains('id', $expWonogiri->id));

        // Admin Kabupaten Ponorogo (3502): hanya melihat Ngunut (Ponorogo), TIDAK melihat Madiun/Wonogiri
        $ponorogoResponse = $this->actingAs($this->adminPonorogo)->get(route('expenses.index'));
        $ponorogoResponse->assertOk();
        $ponorogoExpenses = collect($ponorogoResponse->viewData('page')['props']['expenses']);
        $this->assertTrue($ponorogoExpenses->contains('id', $expNgunut->id));
        $this->assertFalse($ponorogoExpenses->contains('id', $expMadiun->id));
        $this->assertFalse($ponorogoExpenses->contains('id', $expWonogiri->id));

        // Manipulasi filter wilayah di luar scope -> HTTP 403
        $this->actingAs($this->adminJatim)
            ->get(route('expenses.index', ['province_id' => '33']))
            ->assertForbidden();

        $this->actingAs($this->adminJatim)
            ->get(route('expenses.index', ['branch_id' => $this->branchWonogiri->id]))
            ->assertForbidden();

        $this->actingAs($this->adminPonorogo)
            ->get(route('expenses.index', ['regency_id' => '3519']))
            ->assertForbidden();

        $this->actingAs($this->adminPonorogo)
            ->get(route('expenses.index', ['branch_id' => $this->branchMadiun->id]))
            ->assertForbidden();

        // Admin Provinsi & Kabupaten ditolak HTTP 403 saat mencoba create/update/delete
        foreach ([$this->adminJatim, $this->adminPonorogo] as $readonlyAdmin) {
            $this->actingAs($readonlyAdmin)
                ->post(route('expenses.store'), [
                    'branch_id' => $this->branchNgunut->id,
                    'expense_date' => $today,
                    'category' => OperationalExpense::CATEGORY_LAINNYA,
                    'description' => 'Percobaan input admin regional',
                    'amount' => 100000,
                ])
                ->assertForbidden();

            $this->actingAs($readonlyAdmin)
                ->put(route('expenses.update', $expNgunut), [
                    'expense_date' => $today,
                    'category' => OperationalExpense::CATEGORY_LAINNYA,
                    'description' => 'Percobaan edit admin regional',
                    'amount' => 100000,
                ])
                ->assertForbidden();

            $this->actingAs($readonlyAdmin)
                ->delete(route('expenses.destroy', $expNgunut))
                ->assertForbidden();
        }

        // Admin Nasional dapat membuat biaya dengan memilih branch_id secara eksplisit
        $this->actingAs($this->adminNasional)
            ->post(route('expenses.store'), [
                'branch_id' => $this->branchWonogiri->id,
                'expense_date' => $today,
                'category' => OperationalExpense::CATEGORY_ATK_KEMASAN,
                'description' => 'Kertas Struk Thermal Wonogiri',
                'amount' => 75000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('operational_expenses', [
            'branch_id' => $this->branchWonogiri->id,
            'description' => 'Kertas Struk Thermal Wonogiri',
        ]);
    }

    public function test_kasir_is_forbidden_from_all_operational_expense_and_report_export_routes(): void
    {
        $today = now()->toDateString();

        $expense = OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_LISTRIK_AIR,
            'description' => 'Listrik Ngunut',
            'amount' => 200000,
        ]);

        // Seluruh endpoint biaya operasional -> HTTP 403
        $this->actingAs($this->cashierNgunut)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->post(route('expenses.store'), [
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_LAINNYA,
            'description' => 'Kasir input biaya',
            'amount' => 50000,
        ])->assertForbidden();
        $this->actingAs($this->cashierNgunut)->put(route('expenses.update', $expense), [
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_LAINNYA,
            'description' => 'Kasir edit biaya',
            'amount' => 50000,
        ])->assertForbidden();
        $this->actingAs($this->cashierNgunut)->delete(route('expenses.destroy', $expense))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->get(route('expenses.export', ['format' => 'pdf']))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->get(route('expenses.export', ['format' => 'excel']))->assertForbidden();

        // Seluruh endpoint export laporan keuangan & SHU -> HTTP 403
        $this->actingAs($this->cashierNgunut)->get(route('reports.sales.export', ['format' => 'pdf']))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->get(route('reports.sales.export', ['format' => 'excel']))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->get(route('reports.members.export', ['format' => 'pdf']))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->get(route('reports.members.export', ['format' => 'excel']))->assertForbidden();
    }

    public function test_financial_report_service_calculates_net_profit_accurately_and_ignores_soft_deleted_expenses(): void
    {
        $today = now()->toDateString();

        // Checkout tunai di Ngunut: qty=10 x 50.000 = Omzet 500.000, HPP 300.000, Laba Kotor 200.000
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 500000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 10],
                ],
            ])
            ->assertOk();

        // Tambah 2 biaya operasional: 60.000 + 40.000 = 100.000 -> Laba Bersih = 200.000 - 100.000 = 100.000 (Net Margin 20%)
        $exp1 = OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_LISTRIK_AIR,
            'description' => 'Listrik Toko',
            'amount' => 60000,
        ]);

        $exp2 = OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_ATK_KEMASAN,
            'description' => 'Kantong Belanja',
            'amount' => 40000,
        ]);

        /** @var FinancialReportService $reportService */
        $reportService = app(FinancialReportService::class);
        $filters = [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'payment_method' => 'all',
            'branch_id' => $this->branchNgunut->id,
        ];

        $dashboard = $reportService->getDashboardSummary($this->adminNgunut, $filters);
        $this->assertSame(500000.0, $dashboard['kpis']['total_revenue']);
        $this->assertSame(300000.0, $dashboard['kpis']['total_cogs']);
        $this->assertSame(200000.0, $dashboard['kpis']['gross_profit']);
        $this->assertSame(100000.0, $dashboard['kpis']['total_operational_expenses']);
        $this->assertSame(100000.0, $dashboard['kpis']['net_profit']);
        $this->assertSame(20.0, $dashboard['kpis']['net_margin_percentage']);

        $sales = $reportService->getSalesReport($this->adminNgunut, $filters);
        $this->assertSame(100000.0, $sales['summary']['total_operational_expenses']);
        $this->assertSame(100000.0, $sales['summary']['net_profit']);
        $this->assertSame(20.0, $sales['summary']['net_margin_percentage']);
        $this->assertSame(100000.0, $sales['branch_summaries'][0]['operational_expenses']);
        $this->assertSame(100000.0, $sales['branch_summaries'][0]['net_profit']);

        // Soft-delete exp2 (40.000) -> Biaya Operasional jadi 60.000, Laba Bersih jadi 140.000 (Net Margin 28%)
        $exp2->delete();
        $this->assertSoftDeleted('operational_expenses', ['id' => $exp2->id]);

        $afterDelete = $reportService->getDashboardSummary($this->adminNgunut, $filters);
        $this->assertSame(60000.0, $afterDelete['kpis']['total_operational_expenses']);
        $this->assertSame(140000.0, $afterDelete['kpis']['net_profit']);
        $this->assertSame(28.0, $afterDelete['kpis']['net_margin_percentage']);

        // Pastikan exp1 masih aktif
        $this->assertNotNull($exp1->fresh());
    }

    public function test_shu_calculation_updates_dynamically_when_operational_expenses_change(): void
    {
        $today = now()->toDateString();
        $memberNgunut = Member::where('home_branch_id', $this->branchNgunut->id)->firstOrFail();

        // Transaksi member Ngunut: qty=10 x 50.000 = Omzet 500.000, HPP 300.000, Laba Kotor 200.000
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $memberNgunut->id,
                'cash_received' => 500000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 10],
                ],
            ])
            ->assertOk();

        // 1. Sebelum ada biaya operasional: Dasar SHU = 200.000, estimasi SHU member (100% belanja) = 200.000
        $beforeResp = $this->actingAs($this->adminNgunut)->get(route('reports.members'));
        $beforeResp->assertOk();
        $beforeProps = $beforeResp->viewData('page')['props'];
        $this->assertSame(200000.0, (float) $beforeProps['shuFinancials']['gross_profit']);
        $this->assertSame(0.0, (float) $beforeProps['shuFinancials']['total_operational_expenses']);
        $this->assertSame(200000.0, (float) $beforeProps['shuFinancials']['net_profit']);
        $beforeMemberRow = collect($beforeProps['members'])->firstWhere('id', $memberNgunut->id);
        $this->assertSame(200000.0, (float) $beforeMemberRow['estimated_shu_share']);

        // 2. Tambah biaya operasional 75.000 -> Dasar SHU turun menjadi 125.000
        $expense = OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_GAJI_HONOR,
            'description' => 'Honor Pengurus Bulanan',
            'amount' => 75000,
        ]);

        $afterAddResp = $this->actingAs($this->adminNgunut)->get(route('reports.members'));
        $afterAddResp->assertOk();
        $afterAddProps = $afterAddResp->viewData('page')['props'];
        $this->assertSame(75000.0, (float) $afterAddProps['shuFinancials']['total_operational_expenses']);
        $this->assertSame(125000.0, (float) $afterAddProps['shuFinancials']['net_profit']);
        $afterAddMemberRow = collect($afterAddProps['members'])->firstWhere('id', $memberNgunut->id);
        $this->assertSame(125000.0, (float) $afterAddMemberRow['estimated_shu_share']);

        // 3. Soft-delete biaya operasional -> Dasar SHU kembali naik ke 200.000
        $expense->delete();

        $afterDelResp = $this->actingAs($this->adminNgunut)->get(route('reports.members'));
        $afterDelResp->assertOk();
        $afterDelProps = $afterDelResp->viewData('page')['props'];
        $this->assertSame(0.0, (float) $afterDelProps['shuFinancials']['total_operational_expenses']);
        $this->assertSame(200000.0, (float) $afterDelProps['shuFinancials']['net_profit']);
        $afterDelMemberRow = collect($afterDelProps['members'])->firstWhere('id', $memberNgunut->id);
        $this->assertSame(200000.0, (float) $afterDelMemberRow['estimated_shu_share']);
    }

    public function test_all_pdf_and_excel_export_endpoints_succeed_with_valid_headers_and_enforce_anti_idor(): void
    {
        $today = now()->toDateString();
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $memberNgunut = Member::where('home_branch_id', $this->branchNgunut->id)->firstOrFail();

        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $memberNgunut->id,
                'cash_received' => 250000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 5],
                ],
            ])
            ->assertOk();

        OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'expense_date' => $today,
            'category' => OperationalExpense::CATEGORY_LISTRIK_AIR,
            'description' => 'Listrik & Air Ngunut',
            'amount' => 30000,
        ]);

        $dateQuery = ['start_date' => $startDate, 'end_date' => $endDate];

        // 1. Export Penjualan / Laba Rugi (PDF & Excel)
        $salesPdf = $this->actingAs($this->adminNasional)
            ->get(route('reports.sales.export', array_merge(['format' => 'pdf'], $dateQuery)));
        $salesPdf->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString("laporan-laba-rugi-kopdes-{$startDate}-sd-{$endDate}.pdf", (string) $salesPdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $salesPdf->getContent());

        $salesExcel = $this->actingAs($this->adminNasional)
            ->get(route('reports.sales.export', array_merge(['format' => 'excel'], $dateQuery)));
        $salesExcel->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString("laporan-laba-rugi-kopdes-{$startDate}-sd-{$endDate}.xlsx", (string) $salesExcel->headers->get('Content-Disposition'));
        $salesBinary = $salesExcel->streamedContent();
        $this->assertStringStartsWith('PK', $salesBinary);
        $tmpSales = tempnam(sys_get_temp_dir(), 'kopdes_pnl_').'.xlsx';
        file_put_contents($tmpSales, $salesBinary);
        $this->assertSame(['Ringkasan per Cabang', 'Rincian Transaksi'], IOFactory::load($tmpSales)->getSheetNames());
        @unlink($tmpSales);

        // 2. Export Biaya Operasional (PDF & Excel)
        $expensesPdf = $this->actingAs($this->adminNgunut)
            ->get(route('expenses.export', array_merge(['format' => 'pdf'], $dateQuery)));
        $expensesPdf->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString("laporan-biaya-operasional-kopdes-{$startDate}-sd-{$endDate}.pdf", (string) $expensesPdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $expensesPdf->getContent());

        $expensesExcel = $this->actingAs($this->adminNgunut)
            ->get(route('expenses.export', array_merge(['format' => 'excel'], $dateQuery)));
        $expensesExcel->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString("laporan-biaya-operasional-kopdes-{$startDate}-sd-{$endDate}.xlsx", (string) $expensesExcel->headers->get('Content-Disposition'));
        $expensesBinary = $expensesExcel->streamedContent();
        $this->assertStringStartsWith('PK', $expensesBinary);
        $tmpExpenses = tempnam(sys_get_temp_dir(), 'kopdes_opex_').'.xlsx';
        file_put_contents($tmpExpenses, $expensesBinary);
        $this->assertSame(['Rekap Kategori', 'Rincian Biaya Operasional'], IOFactory::load($tmpExpenses)->getSheetNames());
        @unlink($tmpExpenses);

        // 3. Export SHU & Rekap Member (PDF & Excel)
        $membersPdf = $this->actingAs($this->adminNgunut)
            ->get(route('reports.members.export', array_merge(['format' => 'pdf'], $dateQuery)));
        $membersPdf->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString("laporan-shu-member-kopdes-{$startDate}-sd-{$endDate}.pdf", (string) $membersPdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $membersPdf->getContent());

        $membersExcel = $this->actingAs($this->adminNgunut)
            ->get(route('reports.members.export', array_merge(['format' => 'excel'], $dateQuery)));
        $membersExcel->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString("laporan-shu-member-kopdes-{$startDate}-sd-{$endDate}.xlsx", (string) $membersExcel->headers->get('Content-Disposition'));
        $membersBinary = $membersExcel->streamedContent();
        $this->assertStringStartsWith('PK', $membersBinary);
        $tmpMembers = tempnam(sys_get_temp_dir(), 'kopdes_shu_').'.xlsx';
        file_put_contents($tmpMembers, $membersBinary);
        $this->assertSame(['Rekap Member (SHU)'], IOFactory::load($tmpMembers)->getSheetNames());
        @unlink($tmpMembers);

        // 4. Anti-IDOR pada SELURUH endpoint ekspor (PDF & Excel)
        $exportRoutes = ['reports.sales.export', 'expenses.export', 'reports.members.export'];
        foreach ($exportRoutes as $routeName) {
            foreach (['pdf', 'excel'] as $format) {
                // Admin Cabang Ngunut mencoba akses Cabang Cekok -> HTTP 403
                $this->actingAs($this->adminNgunut)
                    ->get(route($routeName, ['format' => $format, 'branch_id' => $this->branchCekok->id]))
                    ->assertForbidden();

                // Admin Kabupaten Ponorogo mencoba akses Kabupaten Madiun -> HTTP 403
                $this->actingAs($this->adminPonorogo)
                    ->get(route($routeName, ['format' => $format, 'regency_id' => '3519']))
                    ->assertForbidden();

                // Admin Provinsi Jatim mencoba akses Provinsi Jateng -> HTTP 403
                $this->actingAs($this->adminJatim)
                    ->get(route($routeName, ['format' => $format, 'province_id' => '33']))
                    ->assertForbidden();
            }
        }
    }
}
