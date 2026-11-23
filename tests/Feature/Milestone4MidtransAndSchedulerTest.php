<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone4MidtransAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected User $cashierNgunut;

    protected Product $productBeras;

    protected string $serverKey = 'SB-Mid-server-KOPDES-TEST-KEY-999';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        config([
            'services.midtrans.server_key' => $this->serverKey,
            'services.midtrans.client_key' => 'SB-Mid-client-KOPDES-TEST-KEY-999',
            'services.midtrans.snap_url' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
            'services.midtrans.api_url' => 'https://api.sandbox.midtrans.com/v2',
        ]);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->cashierNgunut = User::where('card_uid', 'KSR3502001A')->firstOrFail();

        $category = Category::firstOrCreate(
            ['slug' => 'sembako'],
            ['name' => 'Sembako']
        );

        $this->productBeras = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-MID-BRS01',
            'barcode' => '8998001001001',
            'name' => 'Beras Premium Midtrans 5kg',
            'unit' => 'karung',
            'cost_price' => 60000,
            'selling_price' => 68000,
            'is_active' => true,
        ]);
    }

    public function test_midtrans_checkout_reserves_stock_without_deducting_quantity_and_prevents_overselling(): void
    {
        Http::fake([
            '*snap/v1/transactions*' => Http::response([
                'token' => 'snap-token-ngunut-001',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-ngunut-001',
            ], 201),
        ]);

        // Initial branch stock: quantity = 5, reserved_qty = 0 (available_stock = 5)
        $stock = BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->productBeras->id,
            'quantity' => 5,
            'reserved_qty' => 0,
            'min_stock' => 2,
        ]);

        // 1. Cashier 1 creates a Midtrans transaction for qty = 4
        $response = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'member_id' => null,
                'items' => [
                    ['product_id' => $this->productBeras->id, 'qty' => 4],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'payment_status' => 'pending',
                'snap_token' => 'snap-token-ngunut-001',
            ]);

        // Verify quantity is STILL 5, reserved_qty is NOW 4, available_stock is 1, and NO stock_movements yet
        $stock->refresh();
        $this->assertSame(5, $stock->quantity);
        $this->assertSame(4, $stock->reserved_qty);
        $this->assertSame(1, $stock->available_stock);
        $this->assertSame(0, StockMovement::where('branch_id', $this->branchNgunut->id)->count());

        // 2. Another checkout attempt at the same branch for qty = 2 must be rejected (available_stock = 1 < 2)
        $oversellResponse = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 200000,
                'items' => [
                    ['product_id' => $this->productBeras->id, 'qty' => 2],
                ],
            ]);

        $oversellResponse->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $stock->refresh();
        $this->assertSame(5, $stock->quantity);
        $this->assertSame(4, $stock->reserved_qty);
    }

    public function test_webhook_with_invalid_signature_is_rejected_with_http_403_and_logged_in_payment_logs(): void
    {
        Http::fake([
            '*snap/v1/transactions*' => Http::response(['token' => 'snap-token-sig-test'], 201),
        ]);

        BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->productBeras->id,
            'quantity' => 10,
            'reserved_qty' => 0,
            'min_stock' => 2,
        ]);

        $checkoutRes = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [
                    ['product_id' => $this->productBeras->id, 'qty' => 2],
                ],
            ])->assertOk();

        $invoiceNumber = $checkoutRes->json('invoice_number');
        $grossAmount = '136000.00';

        // Send webhook with forged signature_key
        $webhookRes = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => $invoiceNumber,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'mid-tx-forged-001',
            'signature_key' => hash('sha512', 'FORGED-SIGNATURE'),
        ]);

        $webhookRes->assertForbidden();

        // Audit log must still be written with signature_verified = false
        $log = PaymentLog::where('external_order_id', $invoiceNumber)->firstOrFail();
        $this->assertFalse($log->signature_verified);
        $this->assertSame('webhook', $log->source);

        // Transaction status must remain pending and stock untouched
        $transaction = Transaction::where('invoice_number', $invoiceNumber)->firstOrFail();
        $this->assertSame('pending', $transaction->payment_status);

        $stock = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productBeras->id)
            ->firstOrFail();
        $this->assertSame(10, $stock->quantity);
        $this->assertSame(2, $stock->reserved_qty);
    }

    public function test_duplicate_settlement_webhook_is_idempotent_and_deducts_stock_only_once(): void
    {
        Http::fake([
            '*snap/v1/transactions*' => Http::response(['token' => 'snap-token-settle-01'], 201),
        ]);

        $stock = BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->productBeras->id,
            'quantity' => 10,
            'reserved_qty' => 0,
            'min_stock' => 2,
        ]);

        $checkoutRes = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [
                    ['product_id' => $this->productBeras->id, 'qty' => 3],
                ],
            ])->assertOk();

        $invoiceNumber = $checkoutRes->json('invoice_number');
        $statusCode = '200';
        $grossAmount = '204000.00';
        $validSignature = hash('sha512', $invoiceNumber.$statusCode.$grossAmount.$this->serverKey);

        $webhookPayload = [
            'order_id' => $invoiceNumber,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'qris',
            'transaction_id' => 'mid-tx-settle-001',
            'signature_key' => $validSignature,
        ];

        // 1st settlement webhook -> transitions pending -> paid, deducts quantity & reserved_qty, writes stock_movement
        $this->postJson('/api/webhooks/midtrans', $webhookPayload)
            ->assertOk()
            ->assertJson(['payment_status' => 'paid']);

        $stock->refresh();
        $this->assertSame(7, $stock->quantity);
        $this->assertSame(0, $stock->reserved_qty);
        $this->assertSame(1, StockMovement::where('branch_id', $this->branchNgunut->id)->where('type', 'sale')->count());

        // 2nd identical settlement webhook -> returns 200 OK without deducting stock again
        $this->postJson('/api/webhooks/midtrans', $webhookPayload)
            ->assertOk()
            ->assertJson(['payment_status' => 'paid']);

        $stock->refresh();
        $this->assertSame(7, $stock->quantity);
        $this->assertSame(0, $stock->reserved_qty);
        $this->assertSame(1, StockMovement::where('branch_id', $this->branchNgunut->id)->where('type', 'sale')->count());
    }

    public function test_webhook_expire_manual_cancel_and_scheduler_command_release_reserved_qty_back_to_zero(): void
    {
        Http::fake([
            '*snap/v1/transactions*' => Http::response(['token' => 'snap-token-release'], 201),
            '*/v2/*/cancel' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'cancel',
            ], 200),
            '*/v2/*/status' => Http::response([
                'status_code' => '407',
                'transaction_status' => 'expire',
            ], 200),
        ]);

        $stock = BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->productBeras->id,
            'quantity' => 15,
            'reserved_qty' => 0,
            'min_stock' => 2,
        ]);

        // --- A. Webhook Expire ---
        $res1 = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [['product_id' => $this->productBeras->id, 'qty' => 2]],
            ])->assertOk();

        $this->assertSame(2, $stock->fresh()->reserved_qty);

        $inv1 = $res1->json('invoice_number');
        $gross1 = '136000.00';
        $sig1 = hash('sha512', $inv1.'407'.$gross1.$this->serverKey);

        $this->postJson('/api/webhooks/midtrans', [
            'order_id' => $inv1,
            'status_code' => '407',
            'gross_amount' => $gross1,
            'transaction_status' => 'expire',
            'signature_key' => $sig1,
        ])->assertOk();

        $this->assertSame('expired', Transaction::where('invoice_number', $inv1)->value('payment_status'));
        $this->assertSame(0, $stock->fresh()->reserved_qty);
        $this->assertSame(15, $stock->fresh()->quantity);

        // --- B. Manual Cancel Action by Cashier ---
        $res2 = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [['product_id' => $this->productBeras->id, 'qty' => 3]],
            ])->assertOk();

        $this->assertSame(3, $stock->fresh()->reserved_qty);
        $tx2Id = $res2->json('transaction_id');

        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.transactions.cancel', $tx2Id))
            ->assertOk()
            ->assertJson(['payment_status' => 'cancelled']);

        $this->assertSame('cancelled', Transaction::findOrFail($tx2Id)->payment_status);
        $this->assertSame(0, $stock->fresh()->reserved_qty);
        $this->assertSame(15, $stock->fresh()->quantity);

        // --- C. Scheduler Command (kopdes:reconcile-expired-transactions) ---
        $res3 = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'midtrans',
                'items' => [['product_id' => $this->productBeras->id, 'qty' => 4]],
            ])->assertOk();

        $this->assertSame(4, $stock->fresh()->reserved_qty);
        $tx3 = Transaction::findOrFail($res3->json('transaction_id'));

        // Simulate 15-minute expiry window elapsed
        $tx3->update([
            'expires_at' => now()->subMinutes(1),
        ]);

        $this->artisan('kopdes:reconcile-expired-transactions')
            ->assertSuccessful();

        $this->assertSame('expired', $tx3->fresh()->payment_status);
        $this->assertSame(0, $stock->fresh()->reserved_qty);
        $this->assertSame(15, $stock->fresh()->quantity);
        $this->assertSame(0, StockMovement::where('branch_id', $this->branchNgunut->id)->count());
    }
}
