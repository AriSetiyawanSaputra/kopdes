<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone8TransactionReturnTest extends TestCase
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

    protected CashierShift $openShiftNgunut;

    protected Product $productA;

    protected Product $productB;

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

        $this->openShiftNgunut = CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-M8-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako-m8'],
            ['name' => 'Sembako M8']
        );

        $this->productA = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-M8-001',
            'barcode' => '8998008008001',
            'name' => 'Beras Retur 5kg',
            'unit' => 'karung',
            'cost_price' => 50000,
            'selling_price' => 65000,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-M8-002',
            'barcode' => '8998008008002',
            'name' => 'Minyak Retur 1L',
            'unit' => 'pch',
            'cost_price' => 15000,
            'selling_price' => 20000,
            'is_active' => true,
        ]);

        foreach ([$this->productA, $this->productB] as $product) {
            BranchStock::create([
                'branch_id' => $this->branchNgunut->id,
                'product_id' => $product->id,
                'quantity' => 50,
                'reserved_qty' => 0,
                'min_stock' => 5,
            ]);
        }
    }

    private function createPaidCashTransaction(int $qtyA = 3, int $qtyB = 2): Transaction
    {
        $total = ($qtyA * 65000) + ($qtyB * 20000);

        $res = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => $total,
                'items' => [
                    ['product_id' => $this->productA->id, 'qty' => $qtyA],
                    ['product_id' => $this->productB->id, 'qty' => $qtyB],
                ],
            ])
            ->assertOk();

        return Transaction::with('items')->findOrFail($res->json('transaction_id'));
    }

    public function test_partial_then_full_return_updates_return_status_uses_snapshots_and_rejects_over_return(): void
    {
        // (a) Transaksi: 3x Beras (65.000 / HPP 50.000) + 2x Minyak (20.000 / HPP 15.000)
        $tx = $this->createPaidCashTransaction(3, 2);
        $this->assertSame('none', $tx->return_status);

        $itemA = $tx->items->firstWhere('product_id', $this->productA->id);
        $itemB = $tx->items->firstWhere('product_id', $this->productB->id);

        // Ubah harga master produk SETELAH transaksi -> retur WAJIB pakai snapshot asal (65.000 / 50.000)
        $this->productA->update(['selling_price' => 99000, 'cost_price' => 80000]);

        // 1. Retur parsial: 1x Beras (restockable)
        $this->actingAs($this->adminNgunut)
            ->post(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Pembeli mengurangi 1 karung beras',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    [
                        'transaction_item_id' => $itemA->id,
                        'qty_returned' => 1,
                        'condition' => 'restockable',
                    ],
                ],
            ])
            ->assertRedirect();

        $tx->refresh();
        $this->assertSame('partial', $tx->return_status);

        $ret1 = TransactionReturn::where('transaction_id', $tx->id)->firstOrFail();
        $this->assertSame('partial', $ret1->return_type);
        $this->assertSame(65000.0, (float) $ret1->total_refund_amount);
        $this->assertSame(50000.0, (float) $ret1->total_restocked_cogs);
        $this->assertSame(0.0, (float) $ret1->total_damaged_cogs);

        // 2. Coba over-return: minta retur 3x Beras padahal sisa tinggal 2 -> ditolak HTTP 422
        $this->actingAs($this->adminNgunut)
            ->postJson(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Coba retur melebihi sisa',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    [
                        'transaction_item_id' => $itemA->id,
                        'qty_returned' => 3,
                        'condition' => 'restockable',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // 3. Retur sisa penuh (2x Beras + 2x Minyak) -> return_status menjadi 'full'
        $this->actingAs($this->adminNgunut)
            ->post(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Pembatalan seluruh sisa item',
                'refund_method' => 'manual_transfer',
                'refund_reference' => 'TRF-BRI-998877',
                'items' => [
                    [
                        'transaction_item_id' => $itemA->id,
                        'qty_returned' => 2,
                        'condition' => 'restockable',
                    ],
                    [
                        'transaction_item_id' => $itemB->id,
                        'qty_returned' => 2,
                        'condition' => 'damaged',
                    ],
                ],
            ])
            ->assertRedirect();

        $tx->refresh();
        $this->assertSame('full', $tx->return_status);
        $this->assertSame('paid', $tx->payment_status); // Backward compatibility PRD 1.3 #2

        // 4. Upaya retur lagi setelah 'full' -> ditolak HTTP 422
        $this->actingAs($this->adminNgunut)
            ->postJson(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Coba retur setelah full',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    [
                        'transaction_item_id' => $itemA->id,
                        'qty_returned' => 1,
                        'condition' => 'restockable',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_returns_older_than_7_days_or_non_paid_transactions_are_rejected_with_http_422(): void
    {
        // (b1) Transaksi berusia 8 hari (melewati batas 7 hari) -> HTTP 422
        $oldTx = $this->createPaidCashTransaction(1, 0 + 1);
        $oldTx->update(['paid_at' => now()->subDays(8)]);
        $oldItem = $oldTx->items->firstOrFail();

        $this->actingAs($this->adminNgunut)
            ->postJson(route('returns.store'), [
                'transaction_id' => $oldTx->id,
                'reason' => 'Retur terlambat 8 hari',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    [
                        'transaction_item_id' => $oldItem->id,
                        'qty_returned' => 1,
                        'condition' => 'restockable',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);

        // (b2) Transaksi berstatus pending -> HTTP 422
        $pendingTx = $this->createPaidCashTransaction(1, 1);
        $pendingTx->update(['payment_status' => 'pending', 'paid_at' => null]);
        $pendingItem = $pendingTx->items->firstOrFail();

        $this->actingAs($this->adminNgunut)
            ->postJson(route('returns.store'), [
                'transaction_id' => $pendingTx->id,
                'reason' => 'Retur transaksi belum bayar',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    [
                        'transaction_item_id' => $pendingItem->id,
                        'qty_returned' => 1,
                        'condition' => 'restockable',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_restockable_items_add_stock_and_write_void_return_movement_while_damaged_items_do_not_restock(): void
    {
        // (c) Awal stok: A=50, B=50. Beli A=2, B=2 -> stok A=48, B=48
        $tx = $this->createPaidCashTransaction(2, 2);
        $itemA = $tx->items->firstWhere('product_id', $this->productA->id);
        $itemB = $tx->items->firstWhere('product_id', $this->productB->id);

        $stockA = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productA->id)->firstOrFail();
        $stockB = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productB->id)->firstOrFail();

        $this->assertSame(48, $stockA->quantity);
        $this->assertSame(48, $stockB->quantity);

        // Retur A=2 (restockable) & B=2 (damaged)
        $this->actingAs($this->adminNgunut)
            ->post(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Beras utuh layak jual, minyak kemasan pecah',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    [
                        'transaction_item_id' => $itemA->id,
                        'qty_returned' => 2,
                        'condition' => TransactionReturnItem::CONDITION_RESTOCKABLE,
                    ],
                    [
                        'transaction_item_id' => $itemB->id,
                        'qty_returned' => 2,
                        'condition' => TransactionReturnItem::CONDITION_DAMAGED,
                    ],
                ],
            ])
            ->assertRedirect();

        $return = TransactionReturn::where('transaction_id', $tx->id)->firstOrFail();
        $this->assertSame(100000.0, (float) $return->total_restocked_cogs); // 2 * 50.000
        $this->assertSame(30000.0, (float) $return->total_damaged_cogs);    // 2 * 15.000
        $this->assertSame(170000.0, (float) $return->total_refund_amount);  // 2*65.000 + 2*20.000

        // Produk A (restockable): stok naik dari 48 -> 50 dan ada log stock_movements 'void_return'
        $this->assertSame(50, $stockA->fresh()->quantity);
        $voidMovement = StockMovement::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productA->id)
            ->where('type', 'void_return')
            ->firstOrFail();
        $this->assertSame(2, $voidMovement->qty_change);
        $this->assertSame(48, $voidMovement->qty_before);
        $this->assertSame(50, $voidMovement->qty_after);
        $this->assertSame(TransactionReturn::class, $voidMovement->reference_type);
        $this->assertSame($return->id, $voidMovement->reference_id);

        // Produk B (damaged): stok TETAP 48 dan TIDAK ada stock_movements 'void_return'
        $this->assertSame(48, $stockB->fresh()->quantity);
        $this->assertSame(
            0,
            StockMovement::where('branch_id', $this->branchNgunut->id)
                ->where('product_id', $this->productB->id)
                ->where('type', 'void_return')
                ->count()
        );
    }

    public function test_active_shift_cash_refund_reduces_shift_expected_cash_while_branch_cash_and_transfer_do_not(): void
    {
        // (d) Shift opening_cash = 100.000
        // Penjualan tunai: 3x Beras (195.000) + 2x Minyak (40.000) = 235.000
        $tx = $this->createPaidCashTransaction(3, 2);
        $itemA = $tx->items->firstWhere('product_id', $this->productA->id);
        $itemB = $tx->items->firstWhere('product_id', $this->productB->id);

        // 1. Refund 1x Beras (65.000) dari active_shift -> HARUS mengurangi expected_closing_cash shift
        $this->actingAs($this->adminNgunut)
            ->post(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Refund dari laci shift aktif',
                'refund_method' => 'cash_refund',
                'cash_source' => 'active_shift',
                'refund_cashier_shift_id' => $this->openShiftNgunut->id,
                'items' => [
                    ['transaction_item_id' => $itemA->id, 'qty_returned' => 1, 'condition' => 'restockable'],
                ],
            ])
            ->assertRedirect();

        // 2. Refund 1x Beras (65.000) dari branch_cash -> TIDAK mengurangi laci shift
        $this->actingAs($this->adminNgunut)
            ->post(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Refund dari kas brankas cabang',
                'refund_method' => 'cash_refund',
                'cash_source' => 'branch_cash',
                'items' => [
                    ['transaction_item_id' => $itemA->id, 'qty_returned' => 1, 'condition' => 'restockable'],
                ],
            ])
            ->assertRedirect();

        // 3. Refund 1x Minyak (20.000) via manual_transfer -> TIDAK mengurangi laci shift
        $this->actingAs($this->adminNgunut)
            ->post(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Refund transfer bank',
                'refund_method' => 'manual_transfer',
                'refund_reference' => 'TRF-BCA-001',
                'items' => [
                    ['transaction_item_id' => $itemB->id, 'qty_returned' => 1, 'condition' => 'damaged'],
                ],
            ])
            ->assertRedirect();

        // Tutup shift: expected = 100.000 (opening) + 235.000 (cash sales) - 65.000 (active_shift refund) = 270.000
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.shifts.close', $this->openShiftNgunut->id), [
                'actual_closing_cash' => 270000,
            ])
            ->assertOk();

        $closedShift = $this->openShiftNgunut->fresh();
        $this->assertSame(65000.0, (float) $closedShift->cash_refund_total);
        $this->assertSame(270000.0, (float) $closedShift->expected_closing_cash);
        $this->assertSame(0.0, (float) $closedShift->variance);

        // (e) Refund ke shift yang sudah berstatus 'closed' ditolak HTTP 422
        $this->actingAs($this->adminNgunut)
            ->postJson(route('returns.store'), [
                'transaction_id' => $tx->id,
                'reason' => 'Coba refund dari shift tertutup',
                'refund_method' => 'cash_refund',
                'cash_source' => 'active_shift',
                'refund_cashier_shift_id' => $closedShift->id,
                'items' => [
                    ['transaction_item_id' => $itemA->id, 'qty_returned' => 1, 'condition' => 'restockable'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['refund_cashier_shift_id']);
    }

    public function test_kasir_other_branch_admin_and_regional_admins_are_forbidden_from_processing_returns(): void
    {
        // (f) Transaksi milik Cabang Ngunut
        $tx = $this->createPaidCashTransaction(1, 1);
        $itemA = $tx->items->firstWhere('product_id', $this->productA->id);

        $payload = [
            'transaction_id' => $tx->id,
            'reason' => 'Percobaan akses tidak sah',
            'refund_method' => 'cash_refund',
            'cash_source' => 'branch_cash',
            'items' => [
                ['transaction_item_id' => $itemA->id, 'qty_returned' => 1, 'condition' => 'restockable'],
            ],
        ];

        // Kasir -> HTTP 403 pada semua endpoint retur
        $this->actingAs($this->cashierNgunut)->get(route('returns.index'))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->get(route('returns.create', $tx))->assertForbidden();
        $this->actingAs($this->cashierNgunut)->postJson(route('returns.store'), $payload)->assertForbidden();

        // Admin Cabang Cekok (cabang lain) -> HTTP 403
        $this->actingAs($this->adminCekok)->get(route('returns.create', $tx))->assertForbidden();
        $this->actingAs($this->adminCekok)->postJson(route('returns.store'), $payload)->assertForbidden();

        // Admin Provinsi & Admin Kabupaten -> read-only, ditolak HTTP 403 saat create/store
        foreach ([$this->adminJatim, $this->adminPonorogo] as $regionalAdmin) {
            $this->actingAs($regionalAdmin)->get(route('returns.index'))->assertOk();
            $this->actingAs($regionalAdmin)->get(route('returns.create', $tx))->assertForbidden();
            $this->actingAs($regionalAdmin)->postJson(route('returns.store'), $payload)->assertForbidden();
        }
    }
}
