<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone5ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

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

        $this->adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $this->adminJatim = User::where('email', 'admin.jatim@kopdes.id')->firstOrFail();
        $this->adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $this->adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $this->adminCekok = User::where('email', 'admin.cekok@kopdes.id')->firstOrFail();

        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();
        $this->cashierCekok = User::where('card_uid', '0008305215')->firstOrFail();

        CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-RPT-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-002-RPT-001',
            'branch_id' => $this->branchCekok->id,
            'cashier_id' => $this->cashierCekok->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako'],
            ['name' => 'Sembako']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-RPT-001',
            'barcode' => '8999001001001',
            'name' => 'Beras Laporan Snapshot 5kg',
            'unit' => 'karung',
            'cost_price' => 50000,
            'selling_price' => 65000,
            'is_active' => true,
        ]);

        BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);

        BranchStock::create([
            'branch_id' => $this->branchCekok->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);
    }

    public function test_changing_product_cost_price_after_paid_transaction_does_not_alter_historical_gross_profit(): void
    {
        // 1. Complete a paid cash checkout at Ngunut (qty=2: revenue=130,000, cogs=100,000, gross_profit=30,000)
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 150000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        // 2. Admin Nasional drastically changes master product cost_price & selling_price AFTER transaction is paid
        $this->product->update([
            'cost_price' => 64000,
            'selling_price' => 80000,
        ]);

        // 3. Dashboard & Sales Report must still report historical snapshot gross profit = 30,000 (not 2,000 or 32,000)
        $dashboardResponse = $this->actingAs($this->adminNgunut)->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $dashboardProps = $dashboardResponse->viewData('page')['props'];

        $this->assertSame(130000, (int) $dashboardProps['kpis']['total_revenue']);
        $this->assertSame(100000, (int) $dashboardProps['kpis']['total_cogs']);
        $this->assertSame(30000, (int) $dashboardProps['kpis']['gross_profit']);
        $this->assertSame(1, $dashboardProps['kpis']['total_transactions']);

        $salesResponse = $this->actingAs($this->adminNasional)->get(route('reports.sales'));
        $salesResponse->assertOk();
        $salesProps = $salesResponse->viewData('page')['props'];

        $this->assertSame(130000, (int) $salesProps['summary']['total_revenue']);
        $this->assertSame(100000, (int) $salesProps['summary']['total_cogs']);
        $this->assertSame(30000, (int) $salesProps['summary']['gross_profit']);
        $this->assertCount(1, $salesProps['transactions']);
        $this->assertSame(30000, (int) $salesProps['transactions'][0]['gross_profit']);
    }

    public function test_pending_expired_failed_and_cancelled_transactions_are_excluded_from_revenue_and_profit(): void
    {
        // 1. Create 1 legitimate paid transaction (revenue=65,000, cogs=50,000, gross_profit=15,000)
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 70000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
            ])
            ->assertOk();

        // 2. Insert 4 non-paid transactions (pending, expired, failed, cancelled) in the same period
        foreach (['pending', 'expired', 'failed', 'cancelled'] as $idx => $status) {
            Transaction::create([
                'invoice_number' => "INV-KOP-3502-001-NONPAID-{$idx}",
                'idempotency_key' => (string) Str::uuid(),
                'branch_id' => $this->branchNgunut->id,
                'cashier_id' => $this->cashierNgunut->id,
                'member_id' => null,
                'member_home_branch_id' => null,
                'subtotal' => 999000,
                'total_amount' => 999000,
                'total_cogs' => 500000,
                'payment_method' => 'midtrans',
                'payment_status' => $status,
                'paid_at' => now(), // Even if paid_at were somehow populated, status != 'paid' must be excluded
            ]);
        }

        // 3. Dashboard & Sales Report must count ONLY the 1 paid transaction
        $dashboardResponse = $this->actingAs($this->adminNgunut)->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $kpis = $dashboardResponse->viewData('page')['props']['kpis'];

        $this->assertSame(1, $kpis['total_transactions']);
        $this->assertSame(65000, (int) $kpis['total_revenue']);
        $this->assertSame(50000, (int) $kpis['total_cogs']);
        $this->assertSame(15000, (int) $kpis['gross_profit']);

        $salesResponse = $this->actingAs($this->adminNgunut)->get(route('reports.sales'));
        $salesResponse->assertOk();
        $salesProps = $salesResponse->viewData('page')['props'];

        $this->assertSame(1, $salesProps['summary']['total_transactions']);
        $this->assertSame(65000, (int) $salesProps['summary']['total_revenue']);
        $this->assertCount(1, $salesProps['transactions']);
        $this->assertSame('paid', $salesProps['transactions'][0]['payment_status']);
    }

    public function test_admin_cabang_sees_home_members_spending_at_both_home_branch_and_other_branches(): void
    {
        // Siti Aminah belongs to KOPDES Desa Cekok (branchCekok)
        $memberCekok = Member::where('member_code', 'MBR-KOP-3502-002-00001')->firstOrFail();
        $memberNgunut = Member::where('member_code', 'MBR-KOP-3502-001-00001')->firstOrFail();

        // 1. Siti Aminah shops at her HOME branch (Cekok): qty=1 -> 65,000
        $this->actingAs($this->cashierCekok)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $memberCekok->id,
                'cash_received' => 70000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
            ])
            ->assertOk();

        // 2. Siti Aminah shops at OTHER village branch (Ngunut): qty=2 -> 130,000
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $memberCekok->id,
                'cash_received' => 150000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        // 3. Create an unpaid pending transaction for Siti Aminah at Ngunut (must be excluded!)
        Transaction::create([
            'invoice_number' => 'INV-KOP-3502-001-PENDING-MBR',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => $memberCekok->id,
            'member_home_branch_id' => $this->branchCekok->id,
            'subtotal' => 500000,
            'total_amount' => 500000,
            'total_cogs' => 400000,
            'payment_method' => 'midtrans',
            'payment_status' => 'pending',
            'paid_at' => null,
        ]);

        // 4. Admin Cabang Cekok views /reports/members
        $response = $this->actingAs($this->adminCekok)->get(route('reports.members'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $membersList = collect($props['members']);

        // Admin Cabang Cekok sees Siti Aminah (home_branch_id = Cekok) and NOT Budi Santoso (home_branch_id = Ngunut)
        $this->assertTrue($membersList->contains('id', $memberCekok->id));
        $this->assertFalse($membersList->contains('id', $memberNgunut->id));

        $sitiRow = $membersList->firstWhere('id', $memberCekok->id);
        $this->assertSame(2, $sitiRow['paid_transactions_count']);
        $this->assertSame(65000, (int) $sitiRow['home_branch_spend']);
        $this->assertSame(130000, (int) $sitiRow['other_branch_spend']);
        $this->assertSame(195000, (int) $sitiRow['total_spend']);
    }

    public function test_url_region_parameter_tampering_by_non_national_admins_is_rejected_with_http_403(): void
    {
        // Create branches in Kabupaten Madiun (3519, still Jatim 35) and Provinsi Jawa Tengah (33 / 3312)
        $branchMadiun = Branch::create([
            'code' => 'KOP-3519-001',
            'name' => 'KOPDES Desa Balerejo Madiun',
            'village_id' => '3519010001',
            'district_id' => '3519010',
            'regency_id' => '3519',
            'province_id' => '35',
            'address' => 'Jl. Madiun No. 1',
            'is_active' => true,
        ]);

        $branchWonogiri = Branch::create([
            'code' => 'KOP-3312-001',
            'name' => 'KOPDES Desa Biting Wonogiri',
            'village_id' => '3312010001',
            'district_id' => '3312010',
            'regency_id' => '3312',
            'province_id' => '33',
            'address' => 'Jl. Wonogiri No. 1',
            'is_active' => true,
        ]);

        $reportRoutes = ['dashboard', 'reports.sales', 'reports.members'];

        foreach ($reportRoutes as $routeName) {
            // 1. Admin Provinsi Jawa Timur (35) tampering to Jawa Tengah (33 / 3312 / Wonogiri) -> HTTP 403
            $this->actingAs($this->adminJatim)
                ->get(route($routeName, ['province_id' => '33']))
                ->assertForbidden();

            $this->actingAs($this->adminJatim)
                ->get(route($routeName, ['regency_id' => '3312']))
                ->assertForbidden();

            $this->actingAs($this->adminJatim)
                ->get(route($routeName, ['branch_id' => $branchWonogiri->id]))
                ->assertForbidden();

            // Valid drill-down within Jawa Timur -> HTTP 200
            $this->actingAs($this->adminJatim)
                ->get(route($routeName, ['regency_id' => '3502']))
                ->assertOk();

            // 2. Admin Kabupaten Ponorogo (3502) tampering to Kabupaten Madiun (3519) -> HTTP 403
            $this->actingAs($this->adminPonorogo)
                ->get(route($routeName, ['regency_id' => '3519']))
                ->assertForbidden();

            $this->actingAs($this->adminPonorogo)
                ->get(route($routeName, ['branch_id' => $branchMadiun->id]))
                ->assertForbidden();

            // Valid drill-down within Ponorogo -> HTTP 200
            $this->actingAs($this->adminPonorogo)
                ->get(route($routeName, ['branch_id' => $this->branchNgunut->id]))
                ->assertOk();

            // 3. Admin Cabang Ngunut tampering to Cabang Cekok -> HTTP 403
            $this->actingAs($this->adminNgunut)
                ->get(route($routeName, ['branch_id' => $this->branchCekok->id]))
                ->assertForbidden();

            // Valid access to own branch -> HTTP 200
            $this->actingAs($this->adminNgunut)
                ->get(route($routeName))
                ->assertOk();

            // 4. Admin Nasional can access any province/regency/branch -> HTTP 200
            $this->actingAs($this->adminNasional)
                ->get(route($routeName, ['branch_id' => $branchWonogiri->id]))
                ->assertOk();
        }
    }

    public function test_sales_and_member_reports_can_be_exported_as_pdf_and_excel_with_anti_idor_protection(): void
    {
        $memberCekok = Member::where('member_code', 'MBR-KOP-3502-002-00001')->firstOrFail();

        // Create a paid transaction at Ngunut for Cekok's member
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $memberCekok->id,
                'cash_received' => 150000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        // 1. Export Sales PDF
        $salesPdf = $this->actingAs($this->adminNasional)
            ->get(route('reports.sales.export', ['format' => 'pdf']));
        $salesPdf->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $salesPdf->getContent());

        // 2. Export Sales Excel (.xlsx) & verify 2 sheets readable by PhpSpreadsheet
        $salesExcel = $this->actingAs($this->adminNasional)
            ->get(route('reports.sales.export', ['format' => 'excel']));
        $salesExcel->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $salesBinary = $salesExcel->streamedContent();
        $this->assertStringStartsWith('PK', $salesBinary);

        $tmpSales = tempnam(sys_get_temp_dir(), 'kopdes_sales_').'.xlsx';
        file_put_contents($tmpSales, $salesBinary);
        $loadedSales = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpSales);
        $this->assertSame(['Ringkasan per Cabang', 'Rincian Transaksi'], $loadedSales->getSheetNames());
        @unlink($tmpSales);

        // 3. Export Members PDF
        $membersPdf = $this->actingAs($this->adminCekok)
            ->get(route('reports.members.export', ['format' => 'pdf']));
        $membersPdf->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $membersPdf->getContent());

        // 4. Export Members Excel (.xlsx) & verify worksheet readable by PhpSpreadsheet
        $membersExcel = $this->actingAs($this->adminCekok)
            ->get(route('reports.members.export', ['format' => 'excel']));
        $membersExcel->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $membersBinary = $membersExcel->streamedContent();
        $this->assertStringStartsWith('PK', $membersBinary);

        $tmpMembers = tempnam(sys_get_temp_dir(), 'kopdes_members_').'.xlsx';
        file_put_contents($tmpMembers, $membersBinary);
        $loadedMembers = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpMembers);
        $this->assertSame(['Rekap Member (SHU)'], $loadedMembers->getSheetNames());
        @unlink($tmpMembers);

        // 5. Anti-IDOR on export endpoints: Admin Cabang Ngunut requesting Cabang Cekok export gets HTTP 403
        $this->actingAs($this->adminNgunut)
            ->get(route('reports.sales.export', ['format' => 'pdf', 'branch_id' => $this->branchCekok->id]))
            ->assertForbidden();

        $this->actingAs($this->adminNgunut)
            ->get(route('reports.members.export', ['format' => 'excel', 'branch_id' => $this->branchCekok->id]))
            ->assertForbidden();

        // 6. Cashier is forbidden from exporting reports
        $this->actingAs($this->cashierNgunut)
            ->get(route('reports.sales.export', ['format' => 'pdf']))
            ->assertForbidden();
    }
}
