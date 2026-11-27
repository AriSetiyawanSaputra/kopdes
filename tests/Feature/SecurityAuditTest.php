<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\AppSettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $adminNasional;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut;

    protected Member $memberNgunut;

    protected Product $product;

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
        $this->memberNgunut = Member::where('member_code', 'MBR-KOP-3502-001-00001')->firstOrFail();

        $this->memberNgunut->update([
            'phone' => '081234500001',
            'pin_hash' => Hash::make('123456'),
            'status' => 'active',
        ]);

        CashierShift::create([
            'shift_code' => 'SHF-SEC-TEST-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
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
            'sku' => 'SKU-SEC-001',
            'barcode' => '8999900000001',
            'name' => 'Beras Audit Keamanan 5kg',
            'unit' => 'sak',
            'cost_price' => 50000,
            'selling_price' => 65000,
            'member_price' => 60000,
            'is_active' => true,
        ]);

        BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);
    }

    /**
     * SEC-01: Empty MIDTRANS_SERVER_KEY must reject forged webhooks with HTTP 403 and route must be throttled.
     */
    public function test_sec01_midtrans_webhook_rejects_when_server_key_is_empty_and_has_rate_limit(): void
    {
        config(['services.midtrans.server_key' => '']);

        $transaction = $this->createPendingMidtransTransaction('INV-SEC-EMPTY-001', 65000, 1);

        // Attacker computes SHA-512 using empty string as server_key
        $forgedSignature = hash('sha512', $transaction->invoice_number.'200'.'65000.00'.'');

        $this->postJson('/api/webhooks/midtrans', [
            'order_id' => $transaction->invoice_number,
            'status_code' => '200',
            'gross_amount' => '65000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'qris',
            'signature_key' => $forgedSignature,
        ])->assertForbidden();

        $transaction->refresh();
        $this->assertSame('pending', $transaction->payment_status);

        $log = PaymentLog::where('external_order_id', $transaction->invoice_number)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->signature_verified);

        // Verify throttle:120,1 middleware is registered on webhook route
        $webhookRoute = Route::getRoutes()->getByName('api.webhooks.midtrans');
        $this->assertNotNull($webhookRoute);
        $this->assertContains('throttle:120,1', $webhookRoute->gatherMiddleware());
    }

    /**
     * SEC-02 & SEC-03: Excel formula injection payloads are neutralized as safe strings while internal SUM formulas remain intact;
     * DomPDF export succeeds with isPhpEnabled=false.
     */
    public function test_sec02_and_sec03_excel_formula_injection_is_neutralized_and_pdf_renders_without_php(): void
    {
        OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'category' => OperationalExpense::CATEGORY_LAINNYA,
            'amount' => 125000,
            'expense_date' => now()->toDateString(),
            'description' => '=CMD|\'/C calc\'!A0',
            'notes' => '+1+1',
        ]);

        OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'category' => OperationalExpense::CATEGORY_LAINNYA,
            'amount' => 75000,
            'expense_date' => now()->toDateString(),
            'description' => '@SUM(A1:A2)',
            'notes' => '-2+3',
        ]);

        // 1. Export Excel & inspect generated .xlsx cells
        $excelResponse = $this->actingAs($this->adminNgunut)
            ->get(route('expenses.export', [
                'format' => 'excel',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ]));

        $excelResponse->assertOk();

        $binary = $excelResponse->streamedContent();
        $tmpPath = tempnam(sys_get_temp_dir(), 'sec_xlsx_').'.xlsx';
        file_put_contents($tmpPath, $binary);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($tmpPath);
            $detailSheet = $spreadsheet->getSheetByName('Rincian Biaya Operasional');
            $this->assertNotNull($detailSheet);

            $foundCmdCell = null;
            $foundPlusCell = null;
            $foundAtCell = null;
            $foundMinusCell = null;
            $foundSumFormula = false;

            foreach ($detailSheet->getRowIterator() as $row) {
                $r = $row->getRowIndex();
                $descCell = $detailSheet->getCell("F{$r}");
                $notesCell = $detailSheet->getCell("G{$r}");
                $amountCell = $detailSheet->getCell("I{$r}");

                $descVal = (string) $descCell->getValue();
                $notesVal = (string) $notesCell->getValue();
                $amountVal = (string) $amountCell->getValue();

                if (str_contains($descVal, 'CMD|')) {
                    $foundCmdCell = $descCell;
                }
                if (str_contains($notesVal, '+1+1')) {
                    $foundPlusCell = $notesCell;
                }
                if (str_contains($descVal, '@SUM(A1:A2)')) {
                    $foundAtCell = $descCell;
                }
                if (str_contains($notesVal, '-2+3')) {
                    $foundMinusCell = $notesCell;
                }
                if (str_starts_with($amountVal, '=SUM(')) {
                    $foundSumFormula = true;
                    $this->assertSame(DataType::TYPE_FORMULA, $amountCell->getDataType());
                    $this->assertEquals(200000.0, (float) $amountCell->getCalculatedValue());
                }
            }

            $this->assertNotNull($foundCmdCell);
            $this->assertSame(DataType::TYPE_STRING, $foundCmdCell->getDataType());
            $this->assertStringStartsWith("'=CMD|", (string) $foundCmdCell->getValue());

            $this->assertNotNull($foundPlusCell);
            $this->assertSame(DataType::TYPE_STRING, $foundPlusCell->getDataType());
            $this->assertStringStartsWith("'+1+1", (string) $foundPlusCell->getValue());

            $this->assertNotNull($foundAtCell);
            $this->assertSame(DataType::TYPE_STRING, $foundAtCell->getDataType());
            $this->assertStringStartsWith("'@SUM(A1:A2)", (string) $foundAtCell->getValue());

            $this->assertNotNull($foundMinusCell);
            $this->assertSame(DataType::TYPE_STRING, $foundMinusCell->getDataType());
            $this->assertStringStartsWith("'-2+3", (string) $foundMinusCell->getValue());

            $this->assertTrue($foundSumFormula, 'Internal =SUM() total formula must remain active.');
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($tmpPath);
        }

        // 2. Export PDF (SEC-02: isPhpEnabled=false still renders valid PDF with page numbering)
        $pdfResponse = $this->actingAs($this->adminNgunut)
            ->get(route('expenses.export', [
                'format' => 'pdf',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ]));

        $pdfResponse->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    /**
     * SEC-04: Cashier blocked from /profile & /password, self-delete disabled, and seeded demo accounts protected when demo_mode=true.
     */
    public function test_sec04_profile_hardening_and_demo_account_protection(): void
    {
        // Cashier blocked from /profile & /password
        $this->actingAs($this->cashierNgunut)->get('/profile')->assertForbidden();
        $this->actingAs($this->cashierNgunut)->patch('/profile', [
            'name' => 'Kasir Edited',
            'email' => 'kasir@example.com',
        ])->assertForbidden();
        $this->actingAs($this->cashierNgunut)->delete('/profile', ['password' => 'password'])->assertForbidden();
        $this->actingAs($this->cashierNgunut)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertForbidden();

        // Admin Nasional self-deletion disabled even when demo_mode = false
        config(['kopdes.demo_mode' => false]);
        $this->actingAs($this->adminNasional)
            ->delete('/profile', ['password' => 'password'])
            ->assertForbidden();
        $this->assertNotNull($this->adminNasional->fresh());

        // When demo_mode = true, seeded demo admin, cashier, and member accounts cannot have credentials/status tampered
        config(['kopdes.demo_mode' => true]);

        $this->actingAs($this->adminNasional)
            ->patch('/profile', [
                'name' => 'Hijacked Admin',
                'email' => 'attacker@evil.test',
            ])
            ->assertForbidden();
        $this->assertSame('admin.nasional@kopdes.id', $this->adminNasional->fresh()->email);

        $this->actingAs($this->adminNasional)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertForbidden();

        $this->actingAs($this->adminNasional)
            ->patch(route('users.toggle-active', $this->adminNgunut))
            ->assertForbidden();
        $this->assertTrue((bool) $this->adminNgunut->fresh()->is_active);

        $this->actingAs($this->adminNgunut)
            ->post(route('members.reset-pin', $this->memberNgunut), [
                'pin' => '999888',
                'pin_confirmation' => '999888',
            ])
            ->assertForbidden();

        $this->actingAs($this->memberNgunut, 'member')
            ->put(route('member.security.pin.update'), [
                'current_pin' => '123456',
                'new_pin' => '654321',
                'new_pin_confirmation' => '654321',
            ])
            ->assertForbidden();
        $this->assertTrue(Hash::check('123456', $this->memberNgunut->fresh()->pin_hash));

        // When demo_mode = false, member PIN change works normally again
        config(['kopdes.demo_mode' => false]);
        $this->actingAs($this->memberNgunut, 'member')
            ->from(route('member.security'))
            ->put(route('member.security.pin.update'), [
                'current_pin' => '123456',
                'new_pin' => '654321',
                'new_pin_confirmation' => '654321',
            ])
            ->assertRedirect(route('member.security'));
        $this->assertTrue(Hash::check('654321', $this->memberNgunut->fresh()->pin_hash));
    }

    /**
     * SEC-05 & SEC-06: POS JSON responses never leak HPP/COGS fields or internal cashier_shift_id.
     */
    public function test_sec05_and_sec06_pos_json_does_not_leak_cogs_or_shift_id(): void
    {
        $checkoutResponse = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => null,
                'cash_received' => 100000,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
            ]);

        $checkoutResponse->assertOk();
        $payload = $checkoutResponse->json();

        $this->assertArrayNotHasKey('total_cogs', $payload);
        $this->assertArrayNotHasKey('cashier_shift_id', $payload);
        $this->assertArrayNotHasKey('total_cogs', $payload['transaction']);
        $this->assertArrayNotHasKey('cashier_shift_id', $payload['transaction']);
        $this->assertArrayNotHasKey('cost_price_snapshot', $payload['transaction']['items'][0]);
        $this->assertArrayNotHasKey('subtotal_cogs', $payload['transaction']['items'][0]);

        // Receipt fields remain intact
        $this->assertSame(65000, (int) $payload['transaction']['total_amount']);
        $this->assertSame('Beras Audit Keamanan 5kg', $payload['transaction']['items'][0]['product_name_snapshot']);

        // Also verify todayTransactions endpoint
        $todayResponse = $this->actingAs($this->cashierNgunut)
            ->getJson(route('pos.transactions.today'))
            ->assertOk();

        $firstToday = $todayResponse->json('transactions.0');
        $this->assertArrayNotHasKey('total_cogs', $firstToday);
        $this->assertArrayNotHasKey('cashier_shift_id', $firstToday);
        $this->assertArrayNotHasKey('cost_price_snapshot', $firstToday['items'][0]);
    }

    /**
     * SEC-07: Midtrans cancel API failure (412 settled or 503 timeout) must never blindly cancel locally;
     * only explicit HTTP 404 allows local cancellation fallback.
     */
    public function test_sec07_midtrans_cancel_reconciles_settled_on_412_and_blocks_on_5xx(): void
    {
        config([
            'services.midtrans.server_key' => 'SB-Mid-server-test-sec07',
            'services.midtrans.api_url' => 'https://api.sandbox.midtrans.com/v2',
        ]);

        // Case 1: Cancel returns 412 (already settled) -> checks status -> reconciles to 'paid' instead of cancelling!
        $txSettled = $this->createPendingMidtransTransaction('INV-SEC-412-001', 65000, 2);
        // Case 2: Cancel & Status both return 503 (outage) -> returns HTTP 502 and leaves transaction pending!
        $txOutage = $this->createPendingMidtransTransaction('INV-SEC-503-001', 65000, 3);
        // Case 3: Cancel returns 404 (Snap closed before payment channel selected) -> local cancel allowed!
        $txNotFound = $this->createPendingMidtransTransaction('INV-SEC-404-001', 65000, 1);

        Http::fake([
            "https://api.sandbox.midtrans.com/v2/{$txSettled->invoice_number}/cancel" => Http::response([
                'status_code' => '412',
                'status_message' => 'Merchant cannot modify the status of the transaction',
            ], 412),
            "https://api.sandbox.midtrans.com/v2/{$txSettled->invoice_number}/status" => Http::response([
                'status_code' => '200',
                'order_id' => $txSettled->invoice_number,
                'gross_amount' => '65000.00',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'payment_type' => 'qris',
            ], 200),
            "https://api.sandbox.midtrans.com/v2/{$txOutage->invoice_number}/cancel" => Http::response(['message' => 'Service Unavailable'], 503),
            "https://api.sandbox.midtrans.com/v2/{$txOutage->invoice_number}/status" => Http::response(['message' => 'Service Unavailable'], 503),
            "https://api.sandbox.midtrans.com/v2/{$txNotFound->invoice_number}/cancel" => Http::response([
                'status_code' => '404',
                'status_message' => "Transaction doesn't exist.",
            ], 404),
        ]);

        // Verify Case 1 (412 -> reconciled to paid)
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.transactions.cancel', $txSettled))
            ->assertOk()
            ->assertJsonPath('transaction.payment_status', 'paid');

        $txSettled->refresh();
        $this->assertSame('paid', $txSettled->payment_status);

        $stock = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
        $this->assertSame(28, $stock->quantity);
        $this->assertSame(4, $stock->reserved_qty); // 3 (txOutage) + 1 (txNotFound)

        // Verify Case 2 (503 -> HTTP 502, remains pending, reserved_qty intact)
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.transactions.cancel', $txOutage))
            ->assertStatus(502);

        $this->assertSame('pending', $txOutage->fresh()->payment_status);
        $this->assertSame(4, $stock->fresh()->reserved_qty);

        // Verify Case 3 (404 -> local cancel allowed, releases 1 reserved_qty)
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.transactions.cancel', $txNotFound))
            ->assertOk()
            ->assertJsonPath('transaction.payment_status', 'cancelled');

        $this->assertSame('cancelled', $txNotFound->fresh()->payment_status);
        $this->assertSame(3, $stock->fresh()->reserved_qty);
    }

    /**
     * SEC-08: Member portal login enforces unified errors + shared member_id throttle across member_code and phone.
     */
    public function test_sec08_member_login_prevents_enumeration_and_throttle_bypass_across_identifiers(): void
    {
        $codeKey = 'member-pin:'.strtolower($this->memberNgunut->member_code).'|127.0.0.1';
        $phoneKey = 'member-pin:081234500001|127.0.0.1';
        $idKey = 'member-pin-id:'.$this->memberNgunut->id.'|127.0.0.1';
        RateLimiter::clear($codeKey);
        RateLimiter::clear($phoneKey);
        RateLimiter::clear($idKey);

        // 3 failed attempts via member_code + 2 failed attempts via phone = 5 total hits on member-pin-id
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('member.login.store'), [
                'identifier' => $this->memberNgunut->member_code,
                'pin' => '000000',
            ])->assertStatus(422)->assertJsonValidationErrors(['identifier']);
        }

        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('member.login.store'), [
                'identifier' => '081234500001',
                'pin' => '000000',
            ])->assertStatus(422)->assertJsonValidationErrors(['identifier']);
        }

        // 6th attempt using phone (even with correct PIN!) MUST be blocked with HTTP 429
        $this->postJson(route('member.login.store'), [
            'identifier' => '081234500001',
            'pin' => '123456',
        ])->assertStatus(429)->assertHeader('Retry-After');

        $this->assertGuest('member');

        // Verify inactive member returns identical error message to non-existent member AND hits rate limiter
        $inactiveMember = Member::create([
            'member_code' => 'MBR-SEC-INACTIVE',
            'home_branch_id' => $this->branchNgunut->id,
            'card_uid' => null,
            'name' => 'Nonaktif Sec',
            'status' => 'inactive',
            'pin_hash' => Hash::make('123456'),
            'joined_at' => now()->toDateString(),
        ]);

        $inactiveResp = $this->postJson(route('member.login.store'), [
            'identifier' => $inactiveMember->member_code,
            'pin' => '123456',
        ])->assertStatus(422);

        $unknownResp = $this->postJson(route('member.login.store'), [
            'identifier' => 'MBR-DOES-NOT-EXIST',
            'pin' => '123456',
        ])->assertStatus(422);

        $this->assertSame(
            $unknownResp->json('errors.identifier.0'),
            $inactiveResp->json('errors.identifier.0')
        );
    }

    /**
     * SEC-09: BulkImport rejects disguised HTML/XML file masquerading as .xlsx via MIME validation.
     */
    public function test_sec09_bulk_import_rejects_disguised_html_or_xml_with_xlsx_extension(): void
    {
        $fakeHtmlAsXlsx = UploadedFile::fake()->createWithContent(
            'malicious.xlsx',
            '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'
        );

        $this->actingAs($this->adminNasional)
            ->post(route('imports.store', ['type' => 'products']), [
                'file' => $fakeHtmlAsXlsx,
            ])
            ->assertSessionHasErrors('file');

        // Also ensure valid .xlsx file still works cleanly via explicit Xlsx reader
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['category_name', 'sku', 'barcode', 'name', 'unit', 'cost_price', 'selling_price', 'member_price'],
            ['Sembako', 'SKU-SEC-IMP-01', '8999900009999', 'Gula Aman 1kg', 'kg', '12000', '15000', '14000'],
        ]);

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'valid_imp_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpXlsx);
        $spreadsheet->disconnectWorksheets();

        try {
            $validUpload = new UploadedFile(
                $tmpXlsx,
                'products.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $this->actingAs($this->adminNasional)
                ->post(route('imports.store', ['type' => 'products']), [
                    'file' => $validUpload,
                ])
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('products', ['sku' => 'SKU-SEC-IMP-01']);
        } finally {
            @unlink($tmpXlsx);
        }
    }

    /**
     * SEC-10, SEC-12, SEC-13, SEC-15, SEC-16, SEC-17:
     * Security headers, ActivityLog actor_id Anti-IDOR, BarcodeLabels pos_locked guard, member logout session regen, and timezone.
     */
    public function test_sec10_sec12_sec13_sec15_sec16_sec17_hardening_controls(): void
    {
        // SEC-10: Baseline security headers present; HSTS absent on plain HTTP and present on HTTPS
        $httpResponse = $this->get(route('login'));
        $httpResponse->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        $httpsResponse = $this->get('https://localhost/login');
        $httpsResponse->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // SEC-12: Admin Cabang Ngunut cannot filter ActivityLogs by actor_id from Cekok (HTTP 403)
        $this->actingAs($this->adminNgunut)
            ->get(route('activity-logs.index', ['actor_id' => $this->adminCekok->id]))
            ->assertForbidden();

        // Admin Cabang Ngunut CAN filter ActivityLogs by actor_id inside their own branch (HTTP 200)
        $this->actingAs($this->adminNgunut)
            ->get(route('activity-logs.index', ['actor_id' => $this->cashierNgunut->id]))
            ->assertOk();

        // SEC-13: Locked cashier cannot open /products/barcode-labels (HTTP 403)
        $this->actingAs($this->cashierNgunut)
            ->withSession(['pos_locked' => true])
            ->get(route('products.barcode-labels'))
            ->assertForbidden();

        $this->actingAs($this->cashierNgunut)
            ->withSession(['pos_locked' => false])
            ->get(route('products.barcode-labels'))
            ->assertOk();

        // SEC-15: Dead RegisteredUserController removed & member logout regenerates session ID
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Auth/RegisteredUserController.php'));

        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.dashboard'))
            ->assertOk();
        $sessionIdBeforeLogout = session()->getId();

        $this->actingAs($this->memberNgunut, 'member')
            ->post(route('member.logout'))
            ->assertRedirect(route('member.login'));

        $this->assertNotSame($sessionIdBeforeLogout, session()->getId());

        // SEC-16: Parameterized member transactions scope filters work cleanly
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.transactions.index', ['scope' => 'home']))
            ->assertOk();
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.transactions.index', ['scope' => 'cross']))
            ->assertOk();

        // SEC-17: Timezone defaults to Asia/Jakarta
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    private function createPendingMidtransTransaction(string $invoiceNumber, float $totalAmount, int $qty): Transaction
    {
        $stock = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();

        $stock->update([
            'reserved_qty' => (int) $stock->reserved_qty + $qty,
        ]);

        $transaction = Transaction::create([
            'invoice_number' => $invoiceNumber,
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => null,
            'member_home_branch_id' => null,
            'regular_subtotal' => $totalAmount,
            'member_discount_total' => 0,
            'subtotal' => $totalAmount,
            'total_amount' => $totalAmount,
            'total_cogs' => 50000 * $qty,
            'payment_method' => 'midtrans',
            'payment_status' => 'pending',
            'snap_token' => 'snap-token-test-'.$invoiceNumber,
            'expires_at' => now()->addMinutes(15),
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'barcode_snapshot' => $this->product->barcode,
            'unit_snapshot' => $this->product->unit,
            'qty' => $qty,
            'regular_price_snapshot' => $this->product->selling_price,
            'unit_price_snapshot' => $this->product->selling_price,
            'member_discount_snapshot' => 0,
            'is_member_price_applied' => false,
            'cost_price_snapshot' => $this->product->cost_price,
            'subtotal' => $totalAmount,
            'subtotal_cogs' => 50000 * $qty,
        ]);

        return $transaction;
    }
}
