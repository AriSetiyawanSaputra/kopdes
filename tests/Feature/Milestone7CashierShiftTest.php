<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinancialReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone7CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $adminNasional;

    protected User $adminJatim;

    protected User $adminPonorogo;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut1;

    protected User $cashierNgunut2;

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

        $this->cashierNgunut1 = User::where('card_uid', '0008493873')->firstOrFail();
        $this->cashierCekok = User::where('card_uid', '0008305215')->firstOrFail();

        // Buat Kasir kedua di Cabang Ngunut untuk menguji multi-shift paralel di cabang yang sama
        $this->cashierNgunut2 = User::create([
            'name' => 'Kasir Ngunut 2',
            'role' => User::ROLE_KASIR,
            'province_id' => $this->branchNgunut->province_id,
            'regency_id' => $this->branchNgunut->regency_id,
            'branch_id' => $this->branchNgunut->id,
            'card_uid' => '0009998887',
            'pin_hash' => Hash::make('654321'),
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako-m7'],
            ['name' => 'Sembako M7']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-M7-001',
            'barcode' => '8997007007007',
            'name' => 'Beras Shift 5kg',
            'unit' => 'karung',
            'cost_price' => 50000,
            'selling_price' => 60000,
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

    public function test_checkout_is_rejected_with_http_422_when_cashier_has_no_open_shift(): void
    {
        // (a) Belum buka shift -> checkout ditolak HTTP 422
        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 100000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shift']);

        $this->assertSame(0, Transaction::count());

        // Setelah buka shift -> checkout berhasil dan tertaut ke cashier_shift_id
        $openRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 200000,
                'opening_notes' => 'Shift Pagi',
            ])
            ->assertCreated();

        $shiftId = $openRes->json('shift.id');

        $checkoutRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 100000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
            ])
            ->assertOk();

        $tx = Transaction::findOrFail($checkoutRes->json('transaction_id'));
        $this->assertSame($shiftId, (int) $tx->cashier_shift_id);
    }

    public function test_single_cashier_cannot_open_two_shifts_but_two_cashiers_in_same_branch_can_run_parallel_shifts(): void
    {
        // (b) Kasir 1 buka shift pertama -> berhasil (201)
        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 150000,
            ])
            ->assertCreated();

        // Kasir 1 mencoba buka shift kedua saat masih open -> ditolak HTTP 422
        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 150000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shift']);

        // Kasir 2 di cabang yang SAMA (Ngunut) membuka shift paralel -> berhasil (201)
        $this->actingAs($this->cashierNgunut2)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 250000,
            ])
            ->assertCreated();

        $this->assertSame(
            2,
            CashierShift::where('branch_id', $this->branchNgunut->id)
                ->where('status', CashierShift::STATUS_OPEN)
                ->count()
        );
    }

    public function test_pos_payload_during_open_shift_enforces_blind_count_without_exposing_expected_cash_or_sales(): void
    {
        // (c) Buka shift & lakukan penjualan tunai
        $openRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 200000,
            ])
            ->assertCreated();

        $shiftId = $openRes->json('shift.id');

        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 120000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        $forbiddenKeys = [
            'expected_closing_cash',
            'cash_sales_total',
            'midtrans_sales_total',
            'cash_in_total',
            'cash_out_total',
            'cash_refund_total',
            'variance',
        ];

        // 1. Cek payload Inertia GET /pos
        $posResponse = $this->actingAs($this->cashierNgunut1)->get(route('pos.index'));
        $posResponse->assertOk();
        $activeShiftProp = $posResponse->viewData('page')['props']['activeShift'];
        $this->assertNotNull($activeShiftProp);
        $this->assertSame('open', $activeShiftProp['status']);

        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $activeShiftProp, "Blind Count violation: key '{$key}' leaked in GET /pos.");
        }

        // 2. Cek payload JSON GET /pos/shifts/{shift}
        $summaryResponse = $this->actingAs($this->cashierNgunut1)
            ->getJson(route('pos.shifts.summary', $shiftId))
            ->assertOk();

        $shiftJson = $summaryResponse->json('shift');
        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $shiftJson, "Blind Count violation: key '{$key}' leaked in GET /pos/shifts/{shift}.");
        }
    }

    public function test_closing_shift_is_blocked_with_http_422_when_pending_midtrans_transaction_exists(): void
    {
        Http::fake([
            '*snap/v1/transactions*' => Http::response(['token' => 'snap-shift-pending-01'], 201),
        ]);

        // (d) Buka shift dan buat transaksi Midtrans berstatus pending
        $openRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 100000,
            ])
            ->assertCreated();
        $shiftId = $openRes->json('shift.id');

        $checkoutRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
            ])
            ->assertOk();

        $invoiceNumber = $checkoutRes->json('invoice_number');

        // Coba tutup shift saat masih ada transaksi pending -> ditolak HTTP 422
        $closeRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.close', $shiftId), [
                'actual_closing_cash' => 100000,
            ]);

        $closeRes->assertStatus(422)
            ->assertJsonValidationErrors(['pending_transactions']);
        $this->assertStringContainsString($invoiceNumber, (string) $closeRes->json('errors.pending_transactions.0'));

        // Pastikan shift masih berstatus open
        $this->assertSame(CashierShift::STATUS_OPEN, CashierShift::findOrFail($shiftId)->status);
    }

    public function test_close_shift_reconciliation_formula_is_accurate_and_cash_movements_do_not_affect_operational_expenses(): void
    {
        Http::fake([
            '*snap/v1/transactions*' => Http::response(['token' => 'snap-shift-paid-01'], 201),
        ]);

        // (e & f) Buka shift modal awal = 200.000
        $openRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.open'), [
                'opening_cash' => 200000,
            ])
            ->assertCreated();
        $shiftId = $openRes->json('shift.id');

        // 1. Penjualan Tunai (paid): 3 x 60.000 = 180.000
        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 200000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 3],
                ],
            ])
            ->assertOk();

        // 2. Penjualan Midtrans lalu ubah menjadi paid: 2 x 60.000 = 120.000 (TIDAK boleh masuk ekspektasi laci tunai!)
        $midtransRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        Transaction::findOrFail($midtransRes->json('transaction_id'))->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        // 3. Mutasi Kas Masuk = 50.000 (tambah receh) & Kas Keluar = 100.000 (setor ke brankas admin)
        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.cash-movements.store', $shiftId), [
                'type' => 'cash_in',
                'amount' => 50000,
                'notes' => 'Tambah uang receh kembalian',
            ])
            ->assertCreated();

        $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.cash-movements.store', $shiftId), [
                'type' => 'cash_out',
                'amount' => 100000,
                'notes' => 'Setor sebagian uang tunai ke brankas admin',
            ])
            ->assertCreated();

        // Ekspektasi laci = 200.000 + 180.000 + 50.000 - 100.000 = 330.000
        // Kasir hitung fisik = 325.000 -> variance = -5.000 (kurang 5.000)
        $closeRes = $this->actingAs($this->cashierNgunut1)
            ->postJson(route('pos.shifts.close', $shiftId), [
                'actual_closing_cash' => 325000,
                'closing_notes' => 'Kurang receh Rp 5.000',
            ])
            ->assertOk();

        $closedShift = CashierShift::findOrFail($shiftId);
        $this->assertSame(CashierShift::STATUS_CLOSED, $closedShift->status);
        $this->assertFalse($closedShift->is_force_closed);
        $this->assertSame($this->cashierNgunut1->id, (int) $closedShift->closed_by_user_id);
        $this->assertSame(180000.0, (float) $closedShift->cash_sales_total);
        $this->assertSame(120000.0, (float) $closedShift->midtrans_sales_total);
        $this->assertSame(50000.0, (float) $closedShift->cash_in_total);
        $this->assertSame(100000.0, (float) $closedShift->cash_out_total);
        $this->assertSame(330000.0, (float) $closedShift->expected_closing_cash);
        $this->assertSame(325000.0, (float) $closedShift->actual_closing_cash);
        $this->assertSame(-5000.0, (float) $closedShift->variance);

        // Response setelah closed kini boleh mengembalikan hasil rekonsiliasi lengkap
        $closeRes->assertJsonPath('shift.expected_closing_cash', 330000)
            ->assertJsonPath('shift.actual_closing_cash', 325000)
            ->assertJsonPath('shift.variance', -5000);

        // (f) Mutasi kas laci TIDAK masuk ke operational_expenses dan TIDAK mengurangi Laba Bersih
        $this->assertSame(0, OperationalExpense::count());
        /** @var FinancialReportService $reportService */
        $reportService = app(FinancialReportService::class);
        $dashboard = $reportService->getDashboardSummary($this->adminNgunut, [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'branch_id' => $this->branchNgunut->id,
        ]);

        // Omzet = 180.000 + 120.000 = 300.000 | HPP = 5 x 50.000 = 250.000 | Laba Kotor = 50.000 | Opex = 0 | Laba Bersih = 50.000
        $this->assertSame(0.0, $dashboard['kpis']['total_operational_expenses']);
        $this->assertSame(50000.0, $dashboard['kpis']['gross_profit']);
        $this->assertSame(50000.0, $dashboard['kpis']['net_profit']);
    }

    public function test_admin_cabang_can_force_close_own_branch_shift_and_rbac_blocks_other_branches_and_readonly_roles(): void
    {
        // (g) Kasir Ngunut & Kasir Cekok masing-masing membuka shift
        $shiftNgunut = CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-FC-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut1->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $shiftCekok = CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-002-FC-001',
            'branch_id' => $this->branchCekok->id,
            'cashier_id' => $this->cashierCekok->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 150000,
            'opened_at' => now(),
        ]);

        // Admin Cabang Ngunut ditolak HTTP 403 saat melihat atau force-close shift Cabang Cekok
        $this->actingAs($this->adminNgunut)
            ->get(route('shifts.show', $shiftCekok))
            ->assertForbidden();

        $this->actingAs($this->adminNgunut)
            ->post(route('shifts.force-close', $shiftCekok), [
                'actual_closing_cash' => 150000,
                'closing_notes' => 'Coba force close cabang lain',
            ])
            ->assertForbidden();

        // Admin Provinsi & Admin Kabupaten read-only -> boleh lihat, ditolak HTTP 403 saat force-close
        foreach ([$this->adminJatim, $this->adminPonorogo] as $readonlyAdmin) {
            $this->actingAs($readonlyAdmin)
                ->get(route('shifts.show', $shiftNgunut))
                ->assertOk();

            $this->actingAs($readonlyAdmin)
                ->post(route('shifts.force-close', $shiftNgunut), [
                    'actual_closing_cash' => 100000,
                    'closing_notes' => 'Admin regional coba force close',
                ])
                ->assertForbidden();
        }

        // Kasir dilarang mengakses halaman monitoring shift admin
        $this->actingAs($this->cashierNgunut1)
            ->get(route('shifts.index'))
            ->assertForbidden();

        // Admin Cabang Ngunut BERHASIL force-close shift cabangnya sendiri
        $this->actingAs($this->adminNgunut)
            ->post(route('shifts.force-close', $shiftNgunut), [
                'actual_closing_cash' => 100000,
                'closing_notes' => 'Kasir lupa tutup shift, dihitung oleh Admin Cabang',
            ])
            ->assertRedirect(route('shifts.show', $shiftNgunut));

        $shiftNgunut->refresh();
        $this->assertSame(CashierShift::STATUS_CLOSED, $shiftNgunut->status);
        $this->assertTrue($shiftNgunut->is_force_closed);
        $this->assertSame($this->adminNgunut->id, (int) $shiftNgunut->closed_by_user_id);
        $this->assertSame(0.0, (float) $shiftNgunut->variance);
    }
}
