<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AppSettingService;
use App\Services\FinancialReportService;
use App\Services\TransactionReturnService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Milestone10SettingsAndActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected Branch $branchMadiun;

    protected User $adminNasional;

    protected User $adminJatim;

    protected User $adminPonorogo;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut;

    protected CashierShift $openShiftNgunut;

    protected Product $product;

    protected Member $memberNgunut;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(AppSettingService::CACHE_KEY);
        $this->seed(DatabaseSeeder::class);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();
        $this->branchMadiun = Branch::create([
            'code' => 'KOP-3519-001',
            'name' => 'KOPDES Balerejo Madiun',
            'village_id' => '3519010001',
            'district_id' => '3519010',
            'regency_id' => '3519',
            'province_id' => '35',
            'address' => 'Jl. Madiun Raya No. 10',
            'is_active' => true,
        ]);

        $this->adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $this->adminJatim = User::where('email', 'admin.jatim@kopdes.id')->firstOrFail();
        $this->adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $this->adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $this->adminCekok = User::where('email', 'admin.cekok@kopdes.id')->firstOrFail();
        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();

        $this->openShiftNgunut = CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-M10-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako-m10'],
            ['name' => 'Sembako M10']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-M10-001',
            'barcode' => '8991001001001',
            'name' => 'Beras M10 Premium 5kg',
            'unit' => 'karung',
            'cost_price' => 30000,
            'selling_price' => 50000,
            'member_price' => 45000,
            'is_active' => true,
        ]);

        BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);

        $this->memberNgunut = Member::where('home_branch_id', $this->branchNgunut->id)->firstOrFail();
    }

    /**
     * (a) Hanya admin_nasional yang dapat mengakses & mengubah app_settings (termasuk upload logo).
     * Role selain admin_nasional ditolak HTTP 403.
     */
    public function test_only_admin_nasional_can_access_and_update_app_settings_with_logo_upload(): void
    {
        Storage::fake('public');

        $payload = [
            'institution_name' => 'KOPDES Nusantara Sejahtera',
            'receipt_footer' => 'Belanja Hemat untuk Kesejahteraan Bersama',
            'default_receipt_paper' => '80mm',
            'return_max_days' => 14,
            'shu_allocation_member_usaha' => 40,
            'shu_allocation_reserve' => 30,
            'shu_allocation_management' => 20,
            'shu_allocation_education' => 10,
        ];

        // Role lain ditolak HTTP 403 pada GET & PUT /settings
        foreach ([$this->adminJatim, $this->adminPonorogo, $this->adminNgunut, $this->cashierNgunut] as $actor) {
            $this->actingAs($actor)->get(route('settings.index'))->assertForbidden();
            $this->actingAs($actor)->put(route('settings.update'), $payload)->assertForbidden();
        }

        // Admin Nasional dapat membuka /settings
        $this->actingAs($this->adminNasional)
            ->get(route('settings.index'))
            ->assertOk();

        // Admin Nasional dapat menyimpan pengaturan + upload logo valid (PNG)
        $fakePng = UploadedFile::fake()->create('logo-kopdes.png', 100, 'image/png');

        $this->actingAs($this->adminNasional)
            ->put(route('settings.update'), array_merge($payload, ['logo' => $fakePng]))
            ->assertRedirect(route('settings.index'));

        $service = app(AppSettingService::class);
        $this->assertSame('KOPDES Nusantara Sejahtera', $service->get('institution_name'));
        $this->assertSame('80mm', $service->get('default_receipt_paper'));
        $this->assertSame(14, $service->get('return_max_days'));
        $this->assertSame(40.0, $service->get('shu_allocation_member_usaha'));

        $logoPath = $service->get('institution_logo_path');
        $this->assertNotNull($logoPath);
        $this->assertStringStartsWith('logos/', (string) $logoPath);
        Storage::disk('public')->assertExists($logoPath);

        // Shared Inertia props app_settings langsung menampilkan nilai terbaru
        $dashRes = $this->actingAs($this->adminNasional)->get(route('dashboard'));
        $dashRes->assertOk();
        $sharedSettings = $dashRes->viewData('page')['props']['app_settings'];
        $this->assertSame('KOPDES Nusantara Sejahtera', $sharedSettings['institution_name']);
        $this->assertSame('80mm', $sharedSettings['default_receipt_paper']);
        $this->assertSame('Belanja Hemat untuk Kesejahteraan Bersama', $sharedSettings['receipt_footer']);
        $this->assertSame('/storage/'.$logoPath, $sharedSettings['institution_logo_url']);

        // Tercatat otomatis pada activity_logs sebagai settings.updated (global: province_id = null)
        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_SETTINGS_UPDATED,
            'actor_id' => $this->adminNasional->id,
            'province_id' => null,
            'branch_id' => null,
        ]);
    }

    /**
     * (b) Penjumlahan 4 persentase alokasi SHU selain 100% ditolak HTTP 422.
     */
    public function test_shu_allocation_percentages_not_summing_to_100_are_rejected_with_422(): void
    {
        $invalidPayload = [
            'institution_name' => 'Koperasi Desa Merah Putih',
            'receipt_footer' => 'Terima kasih.',
            'default_receipt_paper' => '58mm',
            'return_max_days' => 7,
            'shu_allocation_member_usaha' => 50,
            'shu_allocation_reserve' => 20,
            'shu_allocation_management' => 15,
            'shu_allocation_education' => 10, // Total = 95% != 100%
        ];

        $this->actingAs($this->adminNasional)
            ->putJson(route('settings.update'), $invalidPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shu_allocation_member_usaha']);

        // Fallback tetap 100% (tidak tersimpan)
        $this->assertSame(100.0, app(AppSettingService::class)->get('shu_allocation_member_usaha'));
    }

    /**
     * (c) Perubahan return_max_days langsung memengaruhi validasi retur di TransactionReturnService,
     * dan perubahan persentase SHU langsung memengaruhi perhitungan SHU di FinancialReportService.
     */
    public function test_dynamic_return_max_days_and_shu_allocations_immediately_affect_business_services(): void
    {
        // Buat transaksi tunai member: qty 4 x 45.000 = 180.000, HPP 120.000, Laba Kotor/Bersih = 60.000
        $res = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'cash_received' => 180000,
                'member_id' => $this->memberNgunut->id,
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 4],
                ],
            ]);
        $res->assertOk();

        $tx = Transaction::with('items')->findOrFail($res->json('transaction.id'));
        $txItem = $tx->items->firstOrFail();

        // Set tanggal paid_at menjadi 5 hari lalu
        $tx->forceFill(['paid_at' => now()->subDays(5)])->save();

        // 1. Ubah return_max_days menjadi 2 hari & SHU Jasa Usaha Anggota menjadi 40%
        $this->actingAs($this->adminNasional)
            ->put(route('settings.update'), [
                'institution_name' => 'Koperasi Desa Merah Putih',
                'receipt_footer' => 'Terima kasih.',
                'default_receipt_paper' => '58mm',
                'return_max_days' => 2,
                'shu_allocation_member_usaha' => 40,
                'shu_allocation_reserve' => 30,
                'shu_allocation_management' => 20,
                'shu_allocation_education' => 10,
            ])
            ->assertRedirect();

        // Cek laporan SHU: Dasar SHU = 60.000 -> Pool Jasa Usaha Anggota 40% = 24.000 -> Member (100% belanja) = 24.000
        $memberReport = app(FinancialReportService::class)->getMemberReport($this->adminNgunut, [
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->toDateString(),
            'payment_method' => 'all',
            'branch_id' => $this->branchNgunut->id,
        ]);
        $this->assertEquals(60000.0, $memberReport['shu_financials']['net_profit']);
        $this->assertEquals(24000.0, $memberReport['shu_financials']['member_usaha_pool']);
        $this->assertEquals(40.0, $memberReport['shu_financials']['shu_allocations']['member_usaha']['percent']);
        $this->assertEquals(18000.0, $memberReport['shu_financials']['shu_allocations']['reserve']['amount']);
        $memberRow = collect($memberReport['members'])->firstWhere('id', $this->memberNgunut->id);
        $this->assertEquals(24000.0, $memberRow['estimated_shu_share']);

        // Cek validasi retur: transaksi 5 hari lalu DITOLAK saat return_max_days = 2
        $returnService = app(TransactionReturnService::class);
        $rejected = false;
        try {
            $returnService->processReturn($this->adminNgunut, [
                'transaction_id' => $tx->id,
                'refund_method' => TransactionReturn::REFUND_CASH,
                'cash_source' => TransactionReturn::CASH_SOURCE_BRANCH,
                'reason' => 'Uji batas hari retur dinamis',
                'items' => [
                    [
                        'transaction_item_id' => $txItem->id,
                        'qty_returned' => 1,
                        'condition' => 'restockable',
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            $rejected = true;
            $this->assertArrayHasKey('transaction_id', $e->errors());
        }
        $this->assertTrue($rejected, 'Retur 5 hari lalu harus ditolak saat return_max_days = 2.');

        // 2. Perpanjang return_max_days menjadi 10 hari -> transaksi 5 hari lalu kini DITERIMA
        $this->actingAs($this->adminNasional)
            ->put(route('settings.update'), [
                'institution_name' => 'Koperasi Desa Merah Putih',
                'receipt_footer' => 'Terima kasih.',
                'default_receipt_paper' => '58mm',
                'return_max_days' => 10,
                'shu_allocation_member_usaha' => 40,
                'shu_allocation_reserve' => 30,
                'shu_allocation_management' => 20,
                'shu_allocation_education' => 10,
            ])
            ->assertRedirect();

        $createdReturn = $returnService->processReturn($this->adminNgunut, [
            'transaction_id' => $tx->id,
            'refund_method' => TransactionReturn::REFUND_CASH,
            'cash_source' => TransactionReturn::CASH_SOURCE_BRANCH,
            'reason' => 'Diterima setelah batas hari diperpanjang ke 10 hari',
            'items' => [
                [
                    'transaction_item_id' => $txItem->id,
                    'qty_returned' => 1,
                    'condition' => 'restockable',
                ],
            ],
        ]);
        $this->assertSame(TransactionReturn::STATUS_COMPLETED, $createdReturn->status);
    }

    /**
     * (d) Aksi sensitif otomatis menulis activity_logs dan menyensor field sensitif (password/pin_hash/server_key).
     */
    public function test_sensitive_actions_write_immutable_activity_logs_and_redact_secrets(): void
    {
        // 1. Update harga produk -> product.price_updated
        $this->actingAs($this->adminNasional)
            ->put(route('products.update', $this->product), [
                'category_id' => $this->product->category_id,
                'sku' => $this->product->sku,
                'barcode' => $this->product->barcode,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
                'cost_price' => 32000,
                'selling_price' => 52000,
                'member_price' => 46000,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_PRODUCT_PRICE_UPDATED,
            'subject_type' => Product::class,
            'subject_id' => $this->product->id,
            'province_id' => null,
        ]);

        // 2. Toggle status cabang -> branch.status_toggled
        $this->actingAs($this->adminNasional)
            ->patch(route('branches.toggle-active', $this->branchCekok))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_BRANCH_STATUS_TOGGLED,
            'subject_type' => Branch::class,
            'subject_id' => $this->branchCekok->id,
            'branch_id' => $this->branchCekok->id,
        ]);

        // 3. Toggle status user -> user.status_toggled
        $this->actingAs($this->adminNgunut)
            ->patch(route('users.toggle-active', $this->cashierNgunut))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_USER_STATUS_TOGGLED,
            'subject_type' => User::class,
            'subject_id' => $this->cashierNgunut->id,
            'branch_id' => $this->branchNgunut->id,
        ]);

        // Aktifkan kembali kasir agar bisa checkout untuk test retur & force-close
        $this->cashierNgunut->update(['is_active' => true]);

        // 4. Toggle status member & replace card -> member.status_toggled & member.card_replaced
        $this->actingAs($this->adminNgunut)
            ->patch(route('members.toggle-status', $this->memberNgunut))
            ->assertRedirect();

        $this->actingAs($this->adminNgunut)
            ->patch(route('members.replace-card', $this->memberNgunut), [
                'card_uid' => 'NEWCARD998877',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_MEMBER_STATUS_TOGGLED,
            'subject_type' => Member::class,
            'subject_id' => $this->memberNgunut->id,
            'branch_id' => $this->branchNgunut->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_MEMBER_CARD_REPLACED,
            'subject_type' => Member::class,
            'subject_id' => $this->memberNgunut->id,
            'branch_id' => $this->branchNgunut->id,
        ]);

        // 5. Force-close shift kasir -> shift.force_closed
        $this->actingAs($this->adminNgunut)
            ->post(route('shifts.force-close', $this->openShiftNgunut), [
                'actual_closing_cash' => 100000,
                'closing_notes' => 'Kasir lupa tutup shift',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ACTION_SHIFT_FORCE_CLOSED,
            'subject_type' => CashierShift::class,
            'subject_id' => $this->openShiftNgunut->id,
            'branch_id' => $this->branchNgunut->id,
        ]);

        // 6. Penyensoran field sensitif oleh ActivityLogService::record()
        $secretLog = app(ActivityLogService::class)->record(
            action: ActivityLog::ACTION_USER_STATUS_TOGGLED,
            description: 'Uji sensor field sensitif',
            actor: $this->adminNasional,
            subject: $this->cashierNgunut,
            oldValues: [
                'name' => 'Kasir Lama',
                'password' => 'SuperSecretPassword123',
                'pin_hash' => '$2y$12$SecretHashValue',
                'remember_token' => 'secret-remember-token',
                'server_key' => 'SB-Mid-server-SECRET',
            ],
            newValues: [
                'name' => 'Kasir Baru',
                'pin' => '654321',
                'nested' => [
                    'password' => 'NestedSecret',
                    'safe_flag' => true,
                ],
            ],
        );

        $serialized = json_encode([$secretLog->old_values, $secretLog->new_values]);
        $this->assertStringNotContainsString('SuperSecretPassword123', (string) $serialized);
        $this->assertStringNotContainsString('SecretHashValue', (string) $serialized);
        $this->assertStringNotContainsString('secret-remember-token', (string) $serialized);
        $this->assertStringNotContainsString('SB-Mid-server-SECRET', (string) $serialized);
        $this->assertStringNotContainsString('654321', (string) $serialized);
        $this->assertStringNotContainsString('NestedSecret', (string) $serialized);
        $this->assertArrayNotHasKey('password', $secretLog->old_values);
        $this->assertArrayNotHasKey('pin_hash', $secretLog->old_values);
        $this->assertArrayNotHasKey('remember_token', $secretLog->old_values);
        $this->assertArrayNotHasKey('server_key', $secretLog->old_values);
        $this->assertArrayNotHasKey('pin', $secretLog->new_values);
        $this->assertSame('Kasir Baru', $secretLog->new_values['name']);
        $this->assertTrue($secretLog->new_values['nested']['safe_flag']);
    }

    /**
     * (e) Scoping wilayah & anti-IDOR pada Audit Trail (GET /activity-logs):
     * admin_cabang & admin_kabupaten hanya melihat log pada scope wilayahnya, tidak melihat log global maupun wilayah lain.
     */
    public function test_activity_logs_enforce_region_scope_hide_global_logs_and_block_idor_with_403(): void
    {
        $logService = app(ActivityLogService::class);

        $globalLog = $logService->record(
            action: ActivityLog::ACTION_SETTINGS_UPDATED,
            description: 'Log Global Pengaturan Sistem',
            actor: $this->adminNasional,
            regionOverride: ['province_id' => null, 'regency_id' => null, 'branch_id' => null],
        );

        $ngunutLog = $logService->record(
            action: ActivityLog::ACTION_BRANCH_STATUS_TOGGLED,
            description: 'Log Cabang Ngunut Ponorogo',
            actor: $this->adminNgunut,
            subject: $this->branchNgunut,
        );

        $cekokLog = $logService->record(
            action: ActivityLog::ACTION_BRANCH_STATUS_TOGGLED,
            description: 'Log Cabang Cekok Ponorogo',
            actor: $this->adminCekok,
            subject: $this->branchCekok,
        );

        $madiunLog = $logService->record(
            action: ActivityLog::ACTION_BRANCH_STATUS_TOGGLED,
            description: 'Log Cabang Balerejo Madiun',
            actor: $this->adminNasional,
            subject: $this->branchMadiun,
        );

        // 1. Admin Nasional melihat semua (Global + Ngunut + Cekok + Madiun)
        $nasionalRes = $this->actingAs($this->adminNasional)->get(route('activity-logs.index'));
        $nasionalRes->assertOk();
        $nasionalIds = collect($nasionalRes->viewData('page')['props']['logs']['data'])->pluck('id');
        $this->assertTrue($nasionalIds->contains($globalLog->id));
        $this->assertTrue($nasionalIds->contains($ngunutLog->id));
        $this->assertTrue($nasionalIds->contains($cekokLog->id));
        $this->assertTrue($nasionalIds->contains($madiunLog->id));

        // 2. Admin Kabupaten Ponorogo melihat Ngunut & Cekok, TIDAK melihat Global maupun Madiun
        $ponorogoRes = $this->actingAs($this->adminPonorogo)->get(route('activity-logs.index'));
        $ponorogoRes->assertOk();
        $ponorogoIds = collect($ponorogoRes->viewData('page')['props']['logs']['data'])->pluck('id');
        $this->assertFalse($ponorogoIds->contains($globalLog->id));
        $this->assertTrue($ponorogoIds->contains($ngunutLog->id));
        $this->assertTrue($ponorogoIds->contains($cekokLog->id));
        $this->assertFalse($ponorogoIds->contains($madiunLog->id));

        // Manipulasi filter regency_id ke Madiun -> HTTP 403
        $this->actingAs($this->adminPonorogo)
            ->get(route('activity-logs.index', ['regency_id' => '3519']))
            ->assertForbidden();

        // 3. Admin Cabang Ngunut HANYA melihat Ngunut, TIDAK melihat Global, Cekok, maupun Madiun
        $ngunutRes = $this->actingAs($this->adminNgunut)->get(route('activity-logs.index'));
        $ngunutRes->assertOk();
        $ngunutIds = collect($ngunutRes->viewData('page')['props']['logs']['data'])->pluck('id');
        $this->assertFalse($ngunutIds->contains($globalLog->id));
        $this->assertTrue($ngunutIds->contains($ngunutLog->id));
        $this->assertFalse($ngunutIds->contains($cekokLog->id));
        $this->assertFalse($ngunutIds->contains($madiunLog->id));

        // Manipulasi filter branch_id ke Cekok -> HTTP 403
        $this->actingAs($this->adminNgunut)
            ->get(route('activity-logs.index', ['branch_id' => $this->branchCekok->id]))
            ->assertForbidden();

        // 4. Kasir ditolak HTTP 403
        $this->actingAs($this->cashierNgunut)
            ->get(route('activity-logs.index'))
            ->assertForbidden();
    }
}
