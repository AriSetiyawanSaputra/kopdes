<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\ShiftCashMovement;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\User;
use App\Services\AppSettingService;
use App\Support\CardUidNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Rich, idempotent 3-month KOPDES portfolio dataset (FR-P3-14):
     * - 2 Provinces, 3 Regencies, 4 Active Branches
     * - 16 Products (10 with member_price, 6 regular)
     * - Branch Stocks & Initial Stock Movements
     * - 12 Members (8 with RFID + Portal PIN, 4 without RFID binding + Portal PIN)
     * - 64 Historical Transactions over 90 days (cash & midtrans, home/cross-branch/guest)
     * - 6 Closed Cashier Shifts (balanced, surplus, minus & force-closed) + Cash Movements
     * - 4 Completed Transaction Returns (restockable & damaged, partial & full void)
     * - 16 Operational Expenses across all categories
     * - Dynamic app_settings & sample activity_logs
     */
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            RoleAndAdminSeeder::class,
        ]);

        // Hash once outside loops for speed (< 3 seconds total execution)
        $passwordHash = Hash::make('password');
        $pinHash = Hash::make('123456');

        DB::transaction(function () use ($passwordHash, $pinHash): void {
            // 1. Ensure 4 Active KOPDES Branches across 2 Provinces & 3 Regencies
            $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
            $branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

            $branchBalerejo = Branch::updateOrCreate(
                ['village_id' => '3519010001'],
                [
                    'code' => 'KOP-3519-001',
                    'name' => 'KOPDES Desa Balerejo',
                    'district_id' => '3519010',
                    'regency_id' => '3519',
                    'province_id' => '35',
                    'address' => 'Jl. Raya Balerejo No. 18, Kec. Kebonsari, Kab. Madiun',
                    'phone' => '0351-482101',
                    'is_active' => true,
                ]
            );

            $branchBiting = Branch::updateOrCreate(
                ['village_id' => '3312010001'],
                [
                    'code' => 'KOP-3312-001',
                    'name' => 'KOPDES Desa Biting',
                    'district_id' => '3312010',
                    'regency_id' => '3312',
                    'province_id' => '33',
                    'address' => 'Jl. Raya Purwantoro - Ponorogo KM 3, Desa Biting, Kab. Wonogiri',
                    'phone' => '0273-415201',
                    'is_active' => true,
                ]
            );

            $branches = [$branchNgunut, $branchCekok, $branchBalerejo, $branchBiting];

            // 2. Additional Admins & Cashiers for Branches 3 & 4
            $adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
            $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
            $adminCekok = User::where('email', 'admin.cekok@kopdes.id')->firstOrFail();

            $adminBalerejo = User::updateOrCreate(
                ['email' => 'admin.balerejo@kopdes.id'],
                [
                    'name' => 'Admin Cabang KOPDES Desa Balerejo',
                    'password' => $passwordHash,
                    'role' => User::ROLE_ADMIN_CABANG,
                    'province_id' => '35',
                    'regency_id' => '3519',
                    'branch_id' => $branchBalerejo->id,
                    'card_uid' => null,
                    'pin_hash' => null,
                    'is_active' => true,
                ]
            );

            $adminBiting = User::updateOrCreate(
                ['email' => 'admin.biting@kopdes.id'],
                [
                    'name' => 'Admin Cabang KOPDES Desa Biting',
                    'password' => $passwordHash,
                    'role' => User::ROLE_ADMIN_CABANG,
                    'province_id' => '33',
                    'regency_id' => '3312',
                    'branch_id' => $branchBiting->id,
                    'card_uid' => null,
                    'pin_hash' => null,
                    'is_active' => true,
                ]
            );

            $cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();
            $cashierCekok = User::where('card_uid', '0008305215')->firstOrFail();

            $cashierBalerejo = User::updateOrCreate(
                ['name' => 'Kasir Balerejo 1', 'role' => User::ROLE_KASIR],
                [
                    'email' => null,
                    'password' => null,
                    'province_id' => '35',
                    'regency_id' => '3519',
                    'branch_id' => $branchBalerejo->id,
                    'card_uid' => CardUidNormalizer::normalize('0008510003'),
                    'pin_hash' => $pinHash,
                    'is_active' => true,
                ]
            );

            $cashierBiting = User::updateOrCreate(
                ['name' => 'Kasir Biting 1', 'role' => User::ROLE_KASIR],
                [
                    'email' => null,
                    'password' => null,
                    'province_id' => '33',
                    'regency_id' => '3312',
                    'branch_id' => $branchBiting->id,
                    'card_uid' => CardUidNormalizer::normalize('0008510004'),
                    'pin_hash' => $pinHash,
                    'is_active' => true,
                ]
            );

            $branchAdmins = [
                $branchNgunut->id => $adminNgunut,
                $branchCekok->id => $adminCekok,
                $branchBalerejo->id => $adminBalerejo,
                $branchBiting->id => $adminBiting,
            ];

            $branchCashiers = [
                $branchNgunut->id => $cashierNgunut,
                $branchCekok->id => $cashierCekok,
                $branchBalerejo->id => $cashierBalerejo,
                $branchBiting->id => $cashierBiting,
            ];

            // 3. Categories & 16 Realistic Sembako / UMKM Products (10 with member_price, 6 regular)
            $catSembako = Category::updateOrCreate(['slug' => 'sembako'], ['name' => 'Sembako']);
            $catMinuman = Category::updateOrCreate(['slug' => 'minuman-susu'], ['name' => 'Minuman & Susu']);
            $catRumah = Category::updateOrCreate(['slug' => 'kebutuhan-rumah-tangga'], ['name' => 'Kebutuhan Rumah Tangga']);
            $catUmkm = Category::updateOrCreate(['slug' => 'produk-umkm-desa'], ['name' => 'Produk UMKM Desa']);

            $productSpecs = [
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-001', 'barcode' => '8991001000011', 'name' => 'Beras Premium Merah Putih 5kg', 'unit' => 'sak', 'cost' => 62000, 'sell' => 74000, 'member' => 70000],
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-002', 'barcode' => '8991001000028', 'name' => 'Minyak Goreng Kemasan 2L', 'unit' => 'pouch', 'cost' => 29500, 'sell' => 35000, 'member' => 33000],
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-003', 'barcode' => '8991001000035', 'name' => 'Gula Pasir Kristal 1kg', 'unit' => 'kg', 'cost' => 14500, 'sell' => 17500, 'member' => 16500],
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-004', 'barcode' => '8991001000042', 'name' => 'Telur Ayam Negeri 1kg', 'unit' => 'kg', 'cost' => 24000, 'sell' => 28500, 'member' => 27000],
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-005', 'barcode' => '8991001000059', 'name' => 'Tepung Terigu Serbaguna 1kg', 'unit' => 'pcs', 'cost' => 10500, 'sell' => 13000, 'member' => 12000],
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-006', 'barcode' => '8991001000066', 'name' => 'Mie Instan Goreng (Isi 5)', 'unit' => 'pack', 'cost' => 13000, 'sell' => 16000, 'member' => null],
                ['cat' => $catSembako->id, 'sku' => 'KOP-SMB-007', 'barcode' => '8991001000073', 'name' => 'Kecap Manis Botol 520ml', 'unit' => 'botol', 'cost' => 17000, 'sell' => 21000, 'member' => 19500],
                ['cat' => $catMinuman->id, 'sku' => 'KOP-MNM-001', 'barcode' => '8991002000018', 'name' => 'Kopi Bubuk Robusta Desa 200g', 'unit' => 'pack', 'cost' => 16000, 'sell' => 22000, 'member' => 20000],
                ['cat' => $catMinuman->id, 'sku' => 'KOP-MNM-002', 'barcode' => '8991002000025', 'name' => 'Teh Celup Melati Isi 25', 'unit' => 'box', 'cost' => 6500, 'sell' => 9000, 'member' => null],
                ['cat' => $catMinuman->id, 'sku' => 'KOP-MNM-003', 'barcode' => '8991002000032', 'name' => 'Susu UHT Full Cream 1L', 'unit' => 'kotak', 'cost' => 16500, 'sell' => 20500, 'member' => 19000],
                ['cat' => $catMinuman->id, 'sku' => 'KOP-MNM-004', 'barcode' => '8991002000049', 'name' => 'Air Mineral Gelas 1 Dus', 'unit' => 'dus', 'cost' => 18000, 'sell' => 23000, 'member' => null],
                ['cat' => $catRumah->id, 'sku' => 'KOP-RMT-001', 'barcode' => '8991003000015', 'name' => 'Sabun Cuci Piring Jeruk 780ml', 'unit' => 'pouch', 'cost' => 12500, 'sell' => 16000, 'member' => 15000],
                ['cat' => $catRumah->id, 'sku' => 'KOP-RMT-002', 'barcode' => '8991003000022', 'name' => 'Deterjen Bubuk 800g', 'unit' => 'pack', 'cost' => 16000, 'sell' => 20000, 'member' => null],
                ['cat' => $catRumah->id, 'sku' => 'KOP-RMT-003', 'barcode' => '8991003000039', 'name' => 'Gas LPG 3kg Refill Koperasi', 'unit' => 'tabung', 'cost' => 16500, 'sell' => 19000, 'member' => 18000],
                ['cat' => $catUmkm->id, 'sku' => 'KOP-UMK-001', 'barcode' => '8991004000012', 'name' => 'Keripik Tempe UMKM Ngunut 250g', 'unit' => 'pack', 'cost' => 9000, 'sell' => 14000, 'member' => null],
                ['cat' => $catUmkm->id, 'sku' => 'KOP-UMK-002', 'barcode' => '8991004000029', 'name' => 'Madu Hutan Asli Ponorogo 350ml', 'unit' => 'botol', 'cost' => 45000, 'sell' => 60000, 'member' => null],
            ];

            /** @var list<Product> $products */
            $products = [];
            foreach ($productSpecs as $spec) {
                $products[] = Product::updateOrCreate(
                    ['sku' => $spec['sku']],
                    [
                        'category_id' => $spec['cat'],
                        'barcode' => $spec['barcode'],
                        'name' => $spec['name'],
                        'unit' => $spec['unit'],
                        'cost_price' => $spec['cost'],
                        'selling_price' => $spec['sell'],
                        'member_price' => $spec['member'],
                        'is_active' => true,
                    ]
                );
            }

            // 4. Branch Stocks & Idempotent Initial Restock Movements
            foreach ($branches as $bIdx => $branch) {
                foreach ($products as $pIdx => $product) {
                    $initialQty = 85 + (($bIdx * 7 + $pIdx * 3) % 45);

                    BranchStock::firstOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'quantity' => $initialQty,
                            'reserved_qty' => 0,
                            'min_stock' => 12,
                        ]
                    );

                    $movementNote = 'Stok Awal Demo KOPDES';
                    $hasInitialMovement = StockMovement::query()
                        ->where('branch_id', $branch->id)
                        ->where('product_id', $product->id)
                        ->where('type', 'restock')
                        ->where('notes', $movementNote)
                        ->exists();

                    if (! $hasInitialMovement) {
                        StockMovement::create([
                            'branch_id' => $branch->id,
                            'product_id' => $product->id,
                            'user_id' => $branchAdmins[$branch->id]->id,
                            'type' => 'restock',
                            'qty_change' => $initialQty,
                            'qty_before' => 0,
                            'qty_after' => $initialQty,
                            'notes' => $movementNote,
                            'created_at' => now()->subDays(92),
                        ]);
                    }
                }
            }

            // 5. 12 Members across 4 Branches (8 with RFID + Portal PIN, 4 without RFID binding + Portal PIN)
            // Update the 2 seeded members to ensure Portal Member PIN 123456 is active
            $memberAri = Member::where('member_code', 'MBR-KOP-3502-001-00001')->firstOrFail();
            $memberAri->update(['pin_hash' => $pinHash, 'status' => 'active']);

            $memberCintya = Member::where('member_code', 'MBR-KOP-3502-002-00001')->firstOrFail();
            $memberCintya->update(['pin_hash' => $pinHash, 'status' => 'active']);

            $extraMembersData = [
                ['code' => 'MBR-KOP-3502-001-00002', 'branch' => $branchNgunut->id, 'uid' => '0009500102', 'name' => 'Budi Santoso', 'phone' => '081234560102', 'nik' => '3502090303880003'],
                ['code' => 'MBR-KOP-3502-001-00003', 'branch' => $branchNgunut->id, 'uid' => null, 'name' => 'Siti Aminah', 'phone' => '081234560103', 'nik' => '3502090404910004'],
                ['code' => 'MBR-KOP-3502-001-00004', 'branch' => $branchNgunut->id, 'uid' => '0009500104', 'name' => 'Hendra Wijaya', 'phone' => '081234560104', 'nik' => '3502090505850005'],
                ['code' => 'MBR-KOP-3502-002-00002', 'branch' => $branchCekok->id, 'uid' => '0009500202', 'name' => 'Dewi Lestari', 'phone' => '081234560202', 'nik' => '3502090606930006'],
                ['code' => 'MBR-KOP-3502-002-00003', 'branch' => $branchCekok->id, 'uid' => null, 'name' => 'Agus Prasetyo', 'phone' => '081234560203', 'nik' => '3502090707870007'],
                ['code' => 'MBR-KOP-3519-001-00001', 'branch' => $branchBalerejo->id, 'uid' => '0009500301', 'name' => 'Rina Kartika', 'phone' => '081234560301', 'nik' => '3519010101900001'],
                ['code' => 'MBR-KOP-3519-001-00002', 'branch' => $branchBalerejo->id, 'uid' => '0009500302', 'name' => 'Slamet Riyadi', 'phone' => '081234560302', 'nik' => '3519010202860002'],
                ['code' => 'MBR-KOP-3519-001-00003', 'branch' => $branchBalerejo->id, 'uid' => null, 'name' => 'Yuni Astuti', 'phone' => '081234560303', 'nik' => '3519010303940003'],
                ['code' => 'MBR-KOP-3312-001-00001', 'branch' => $branchBiting->id, 'uid' => '0009500401', 'name' => 'Joko Susilo', 'phone' => '081234560401', 'nik' => '3312010101890001'],
                ['code' => 'MBR-KOP-3312-001-00002', 'branch' => $branchBiting->id, 'uid' => null, 'name' => 'Sri Wahyuni', 'phone' => '081234560402', 'nik' => '3312010202920002'],
            ];

            /** @var list<Member> $allMembers */
            $allMembers = [$memberAri, $memberCintya];
            foreach ($extraMembersData as $idx => $md) {
                $allMembers[] = Member::updateOrCreate(
                    ['member_code' => $md['code']],
                    [
                        'home_branch_id' => $md['branch'],
                        'card_uid' => $md['uid'] ? CardUidNormalizer::normalize($md['uid']) : null,
                        'name' => $md['name'],
                        'phone' => $md['phone'],
                        'nik' => $md['nik'],
                        'status' => 'active',
                        'pin_hash' => $pinHash,
                        'joined_at' => now()->subDays(110 - ($idx * 4))->toDateString(),
                    ]
                );
            }

            // 6. 6 Closed Cashier Shifts (Balanced, Surplus, Minus & Force-Closed) + Cash Movements
            $shiftSpecs = [
                ['code' => 'SHF-DEMO-001', 'branch' => $branchNgunut, 'days_ago' => 45, 'opening' => 300000, 'cash_in' => 50000, 'cash_out' => 25000, 'variance' => 0, 'force' => false, 'note' => 'Rekonsiliasi laci seimbang sempurna.'],
                ['code' => 'SHF-DEMO-002', 'branch' => $branchNgunut, 'days_ago' => 14, 'opening' => 300000, 'cash_in' => 0, 'cash_out' => 30000, 'variance' => 10000, 'force' => false, 'note' => 'Surplus kecil Rp 10.000 dari pembulatan uang receh.'],
                ['code' => 'SHF-DEMO-003', 'branch' => $branchCekok, 'days_ago' => 30, 'opening' => 250000, 'cash_in' => 100000, 'cash_out' => 20000, 'variance' => -5000, 'force' => false, 'note' => 'Selisih kurang Rp 5.000 terverifikasi admin cabang.'],
                ['code' => 'SHF-DEMO-004', 'branch' => $branchCekok, 'days_ago' => 7, 'opening' => 250000, 'cash_in' => 0, 'cash_out' => 0, 'variance' => 0, 'force' => false, 'note' => 'Tutup shift normal, laci sesuai.'],
                ['code' => 'SHF-DEMO-005', 'branch' => $branchBalerejo, 'days_ago' => 20, 'opening' => 300000, 'cash_in' => 50000, 'cash_out' => 15000, 'variance' => 5000, 'force' => false, 'note' => 'Surplus Rp 5.000 tercatat di audit shift.'],
                ['code' => 'SHF-DEMO-006', 'branch' => $branchBiting, 'days_ago' => 10, 'opening' => 200000, 'cash_in' => 0, 'cash_out' => 40000, 'variance' => -15000, 'force' => true, 'note' => 'Force-close oleh Admin Cabang karena kasir lupa tutup shift.'],
            ];

            /** @var array<int, CashierShift> $demoShiftsByBranch */
            $demoShifts = [];
            foreach ($shiftSpecs as $sSpec) {
                $branch = $sSpec['branch'];
                $cashier = $branchCashiers[$branch->id];
                $admin = $branchAdmins[$branch->id];

                $openedAt = now()->subDays($sSpec['days_ago'])->setTime(8, 0);
                $closedAt = (clone $openedAt)->setTime(17, 0);

                $shift = CashierShift::firstOrCreate(
                    ['shift_code' => $sSpec['code']],
                    [
                        'branch_id' => $branch->id,
                        'cashier_id' => $cashier->id,
                        'closed_by_user_id' => $sSpec['force'] ? $admin->id : $cashier->id,
                        'status' => CashierShift::STATUS_CLOSED,
                        'is_force_closed' => $sSpec['force'],
                        'opening_cash' => $sSpec['opening'],
                        'cash_sales_total' => 0,
                        'midtrans_sales_total' => 0,
                        'cash_in_total' => $sSpec['cash_in'],
                        'cash_out_total' => $sSpec['cash_out'],
                        'cash_refund_total' => 0,
                        'expected_closing_cash' => $sSpec['opening'] + $sSpec['cash_in'] - $sSpec['cash_out'],
                        'actual_closing_cash' => $sSpec['opening'] + $sSpec['cash_in'] - $sSpec['cash_out'] + $sSpec['variance'],
                        'variance' => $sSpec['variance'],
                        'opening_notes' => 'Modal awal laci shift demo',
                        'closing_notes' => $sSpec['note'],
                        'opened_at' => $openedAt,
                        'closed_at' => $closedAt,
                    ]
                );

                if ($sSpec['cash_in'] > 0 && ! ShiftCashMovement::where('cashier_shift_id', $shift->id)->where('type', ShiftCashMovement::TYPE_CASH_IN)->exists()) {
                    ShiftCashMovement::create([
                        'cashier_shift_id' => $shift->id,
                        'branch_id' => $branch->id,
                        'user_id' => $cashier->id,
                        'type' => ShiftCashMovement::TYPE_CASH_IN,
                        'amount' => $sSpec['cash_in'],
                        'notes' => 'Tambahan uang receh kembalian',
                        'created_at' => (clone $openedAt)->addHours(2),
                    ]);
                }

                if ($sSpec['cash_out'] > 0 && ! ShiftCashMovement::where('cashier_shift_id', $shift->id)->where('type', ShiftCashMovement::TYPE_CASH_OUT)->exists()) {
                    ShiftCashMovement::create([
                        'cashier_shift_id' => $shift->id,
                        'branch_id' => $branch->id,
                        'user_id' => $cashier->id,
                        'type' => ShiftCashMovement::TYPE_CASH_OUT,
                        'amount' => $sSpec['cash_out'],
                        'notes' => 'Pembelian kantong plastik & air minum kasir',
                        'created_at' => (clone $openedAt)->addHours(5),
                    ]);
                }

                $demoShifts[$sSpec['code']] = [
                    'shift' => $shift,
                    'spec' => $sSpec,
                ];
            }

            // 7. 64 Realistic Historical Transactions Spread Over 3 Months (90 Days)
            // Deterministic invoice numbers make re-seeding 100% idempotent.
            $midtransTypes = ['qris', 'gopay', 'bank_transfer'];
            $shiftCodesByBranch = [
                $branchNgunut->id => ['SHF-DEMO-001', 'SHF-DEMO-002'],
                $branchCekok->id => ['SHF-DEMO-003', 'SHF-DEMO-004'],
                $branchBalerejo->id => ['SHF-DEMO-005'],
                $branchBiting->id => ['SHF-DEMO-006'],
            ];

            /** @var array<string, Transaction> $createdTransactions */
            $createdTransactions = [];

            for ($i = 1; $i <= 64; $i++) {
                $invoiceNumber = sprintf('INV-DEMO-%04d', $i);
                $existingTx = Transaction::where('invoice_number', $invoiceNumber)->first();
                if ($existingTx) {
                    $createdTransactions[$invoiceNumber] = $existingTx;

                    continue;
                }

                $branch = $branches[($i - 1) % 4];
                $cashier = $branchCashiers[$branch->id];

                // Spread transactions from 88 days ago up to today (last 4 within 3 days for realistic returns)
                $daysAgo = $i >= 61 ? (65 - $i) : max(1, 89 - (int) floor(($i - 1) * 1.4));
                $paidAt = now()->subDays($daysAgo)->setTime(9 + ($i % 8), ($i * 7) % 60);

                // Member assignment: ~55% home branch, ~25% cross-branch, ~20% guest (non-member)
                $member = null;
                if ($i % 5 !== 0) {
                    if ($i % 4 === 0) {
                        // Cross-branch member (e.g., Ari at Cekok/Balerejo or Cintya at Ngunut)
                        $crossCandidates = array_values(array_filter(
                            $allMembers,
                            fn (Member $m) => (int) $m->home_branch_id !== (int) $branch->id
                        ));
                        $member = $crossCandidates[$i % count($crossCandidates)];
                    } else {
                        $homeCandidates = array_values(array_filter(
                            $allMembers,
                            fn (Member $m) => (int) $m->home_branch_id === (int) $branch->id
                        ));
                        $member = $homeCandidates[$i % count($homeCandidates)];
                    }
                }

                $paymentMethod = ($i % 3 === 0) ? 'midtrans' : 'cash';
                $midtransType = $paymentMethod === 'midtrans' ? $midtransTypes[$i % count($midtransTypes)] : null;

                // Link earlier transactions to demo closed shifts for realistic shift totals
                $branchShiftCodes = $shiftCodesByBranch[$branch->id];
                $linkedShiftCode = $branchShiftCodes[$i % count($branchShiftCodes)];
                $linkedShift = $i <= 48 ? $demoShifts[$linkedShiftCode]['shift'] : null;

                // Pick 2 or 3 distinct products deterministically
                $itemCount = ($i % 2 === 0) ? 3 : 2;
                $itemsPayload = [];
                $regularSubtotal = 0.0;
                $memberDiscountTotal = 0.0;
                $totalAmount = 0.0;
                $totalCogs = 0.0;

                for ($k = 0; $k < $itemCount; $k++) {
                    $prod = $products[($i * 3 + $k * 5) % count($products)];
                    $qty = (($i + $k) % 3) + 1;
                    $regPrice = (float) $prod->selling_price;
                    $hasMemberPrice = $member !== null && $prod->member_price !== null;
                    $unitPrice = $hasMemberPrice ? (float) $prod->member_price : $regPrice;
                    $costPrice = (float) $prod->cost_price;

                    $itemRegular = round($regPrice * $qty, 2);
                    $itemSubtotal = round($unitPrice * $qty, 2);
                    $itemDiscount = round(max(0.0, $itemRegular - $itemSubtotal), 2);
                    $itemCogs = round($costPrice * $qty, 2);

                    $regularSubtotal += $itemRegular;
                    $memberDiscountTotal += $itemDiscount;
                    $totalAmount += $itemSubtotal;
                    $totalCogs += $itemCogs;

                    $itemsPayload[] = [
                        'product' => $prod,
                        'qty' => $qty,
                        'regular_price_snapshot' => $regPrice,
                        'unit_price_snapshot' => $unitPrice,
                        'member_discount_snapshot' => $itemDiscount,
                        'is_member_price_applied' => $hasMemberPrice,
                        'cost_price_snapshot' => $costPrice,
                        'subtotal' => $itemSubtotal,
                        'subtotal_cogs' => $itemCogs,
                    ];
                }

                $cashReceived = $paymentMethod === 'cash' ? ceil($totalAmount / 10000) * 10000 : null;
                $changeAmount = $paymentMethod === 'cash' ? round($cashReceived - $totalAmount, 2) : null;

                $tx = Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'idempotency_key' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'cashier_id' => $cashier->id,
                    'cashier_shift_id' => $linkedShift?->id,
                    'member_id' => $member?->id,
                    'member_home_branch_id' => $member?->home_branch_id,
                    'regular_subtotal' => round($regularSubtotal, 2),
                    'member_discount_total' => round($memberDiscountTotal, 2),
                    'subtotal' => round($totalAmount, 2),
                    'total_amount' => round($totalAmount, 2),
                    'total_cogs' => round($totalCogs, 2),
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'return_status' => 'none',
                    'cash_received' => $cashReceived,
                    'change_amount' => $changeAmount,
                    'midtrans_payment_type' => $midtransType,
                    'paid_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);

                foreach ($itemsPayload as $row) {
                    TransactionItem::create([
                        'transaction_id' => $tx->id,
                        'product_id' => $row['product']->id,
                        'product_name_snapshot' => $row['product']->name,
                        'barcode_snapshot' => $row['product']->barcode,
                        'unit_snapshot' => $row['product']->unit,
                        'qty' => $row['qty'],
                        'regular_price_snapshot' => $row['regular_price_snapshot'],
                        'unit_price_snapshot' => $row['unit_price_snapshot'],
                        'member_discount_snapshot' => $row['member_discount_snapshot'],
                        'is_member_price_applied' => $row['is_member_price_applied'],
                        'cost_price_snapshot' => $row['cost_price_snapshot'],
                        'subtotal' => $row['subtotal'],
                        'subtotal_cogs' => $row['subtotal_cogs'],
                    ]);
                }

                $createdTransactions[$invoiceNumber] = $tx;
            }

            // Recompute exact shift sales totals & expected/actual cash for the 6 demo shifts
            foreach ($demoShifts as $entry) {
                /** @var CashierShift $shift */
                $shift = $entry['shift'];
                $spec = $entry['spec'];

                $cashSales = (float) Transaction::where('cashier_shift_id', $shift->id)
                    ->where('payment_status', 'paid')
                    ->where('payment_method', 'cash')
                    ->sum('total_amount');
                $midtransSales = (float) Transaction::where('cashier_shift_id', $shift->id)
                    ->where('payment_status', 'paid')
                    ->where('payment_method', 'midtrans')
                    ->sum('total_amount');

                $expected = round((float) $shift->opening_cash + $cashSales + (float) $shift->cash_in_total - (float) $shift->cash_out_total, 2);
                $actual = round($expected + (float) $spec['variance'], 2);

                $shift->update([
                    'cash_sales_total' => round($cashSales, 2),
                    'midtrans_sales_total' => round($midtransSales, 2),
                    'expected_closing_cash' => $expected,
                    'actual_closing_cash' => $actual,
                    'variance' => (float) $spec['variance'],
                ]);
            }

            // 8. 4 Completed Transaction Returns (Restockable & Damaged, Partial & Full Void)
            $returnSpecs = [
                ['ret_no' => 'RET-DEMO-001', 'inv' => 'INV-DEMO-0061', 'type' => TransactionReturn::TYPE_PARTIAL, 'cond' => TransactionReturnItem::CONDITION_RESTOCKABLE, 'reason' => 'Anggota salah ambil ukuran kemasan (layak jual kembali)'],
                ['ret_no' => 'RET-DEMO-002', 'inv' => 'INV-DEMO-0062', 'type' => TransactionReturn::TYPE_PARTIAL, 'cond' => TransactionReturnItem::CONDITION_DAMAGED, 'reason' => 'Kemasan bocor saat diantar (barang rusak/susut)'],
                ['ret_no' => 'RET-DEMO-003', 'inv' => 'INV-DEMO-0063', 'type' => TransactionReturn::TYPE_PARTIAL, 'cond' => TransactionReturnItem::CONDITION_RESTOCKABLE, 'reason' => 'Pengurangan kuantitas belanja anggota'],
                ['ret_no' => 'RET-DEMO-004', 'inv' => 'INV-DEMO-0064', 'type' => TransactionReturn::TYPE_FULL, 'cond' => TransactionReturnItem::CONDITION_RESTOCKABLE, 'reason' => 'Pembatalan penuh transaksi atas permintaan anggota'],
            ];

            foreach ($returnSpecs as $rSpec) {
                if (TransactionReturn::where('return_number', $rSpec['ret_no'])->exists()) {
                    continue;
                }

                $tx = $createdTransactions[$rSpec['inv']] ?? Transaction::where('invoice_number', $rSpec['inv'])->first();
                if (! $tx) {
                    continue;
                }

                $tx->load('items');
                if ($tx->items->isEmpty()) {
                    continue;
                }

                $targetItems = $rSpec['type'] === TransactionReturn::TYPE_FULL
                    ? $tx->items
                    : $tx->items->take(1);

                $totalRefund = 0.0;
                $restockedCogs = 0.0;
                $damagedCogs = 0.0;
                $retItemsPayload = [];

                foreach ($targetItems as $txItem) {
                    $qtyRet = $rSpec['type'] === TransactionReturn::TYPE_FULL
                        ? (int) $txItem->qty
                        : 1;
                    $subRefund = round((float) $txItem->unit_price_snapshot * $qtyRet, 2);
                    $subCogs = round((float) $txItem->cost_price_snapshot * $qtyRet, 2);

                    $totalRefund += $subRefund;
                    if ($rSpec['cond'] === TransactionReturnItem::CONDITION_RESTOCKABLE) {
                        $restockedCogs += $subCogs;
                    } else {
                        $damagedCogs += $subCogs;
                    }

                    $retItemsPayload[] = [
                        'tx_item' => $txItem,
                        'qty' => $qtyRet,
                        'sub_refund' => $subRefund,
                        'sub_cogs' => $subCogs,
                    ];
                }

                $returnedAt = $tx->paid_at ? (clone $tx->paid_at)->addHours(3) : now()->subHours(2);

                $ret = TransactionReturn::create([
                    'return_number' => $rSpec['ret_no'],
                    'transaction_id' => $tx->id,
                    'branch_id' => $tx->branch_id,
                    'processed_by_user_id' => $branchAdmins[$tx->branch_id]->id,
                    'member_id' => $tx->member_id,
                    'member_home_branch_id' => $tx->member_home_branch_id,
                    'return_type' => $rSpec['type'],
                    'refund_method' => TransactionReturn::REFUND_CASH,
                    'cash_source' => TransactionReturn::CASH_SOURCE_BRANCH,
                    'total_refund_amount' => round($totalRefund, 2),
                    'total_restocked_cogs' => round($restockedCogs, 2),
                    'total_damaged_cogs' => round($damagedCogs, 2),
                    'reason' => $rSpec['reason'],
                    'status' => TransactionReturn::STATUS_COMPLETED,
                    'returned_at' => $returnedAt,
                ]);

                foreach ($retItemsPayload as $rp) {
                    TransactionReturnItem::create([
                        'transaction_return_id' => $ret->id,
                        'transaction_item_id' => $rp['tx_item']->id,
                        'product_id' => $rp['tx_item']->product_id,
                        'product_name_snapshot' => $rp['tx_item']->product_name_snapshot,
                        'qty_returned' => $rp['qty'],
                        'condition' => $rSpec['cond'],
                        'unit_price_snapshot' => $rp['tx_item']->unit_price_snapshot,
                        'cost_price_snapshot' => $rp['tx_item']->cost_price_snapshot,
                        'subtotal_refund' => $rp['sub_refund'],
                        'subtotal_cogs' => $rp['sub_cogs'],
                    ]);
                }

                $tx->update([
                    'return_status' => $rSpec['type'] === TransactionReturn::TYPE_FULL ? 'full' : 'partial',
                ]);
            }

            // 9. 16 Operational Expenses across all 4 branches & categories over 3 months
            $expenseTemplates = [
                ['cat' => OperationalExpense::CATEGORY_LISTRIK_AIR, 'amount' => 185000, 'desc' => 'Tagihan listrik & air bulanan toko KOPDES'],
                ['cat' => OperationalExpense::CATEGORY_ATK_KEMASAN, 'amount' => 95000, 'desc' => 'Kertas struk thermal 58mm & kantong belanja'],
                ['cat' => OperationalExpense::CATEGORY_TRANSPORTASI, 'amount' => 120000, 'desc' => 'Ongkos angkut kulakan beras & sembako'],
                ['cat' => OperationalExpense::CATEGORY_PEMELIHARAAN, 'amount' => 75000, 'desc' => 'Perawatan rak display & scanner barcode'],
            ];

            foreach ($branches as $bIdx => $branch) {
                foreach ($expenseTemplates as $eIdx => $tpl) {
                    $expDate = now()->subDays(12 + ($eIdx * 18) + ($bIdx * 2))->toDateString();
                    OperationalExpense::firstOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'category' => $tpl['cat'],
                            'description' => $tpl['desc'].' ('.$branch->code.')',
                        ],
                        [
                            'user_id' => $branchAdmins[$branch->id]->id,
                            'expense_date' => $expDate,
                            'amount' => $tpl['amount'] + ($bIdx * 10000),
                            'notes' => 'Data operasional demo KOPDES',
                        ]
                    );
                }
            }

            // 10. Initial Dynamic app_settings (SHU = 40% + 30% + 20% + 10% = 100%)
            $defaultSettings = [
                ['key' => 'institution_name', 'value' => 'Koperasi Desa Merah Putih', 'type' => AppSetting::TYPE_STRING, 'group' => 'general'],
                ['key' => 'receipt_footer', 'value' => 'Terima kasih telah berbelanja di KOPDES Merah Putih. Belanja di KOPDES, SHU kembali untuk anggota!', 'type' => AppSetting::TYPE_STRING, 'group' => 'receipt'],
                ['key' => 'default_receipt_paper', 'value' => '58mm', 'type' => AppSetting::TYPE_STRING, 'group' => 'receipt'],
                ['key' => 'return_max_days', 'value' => '7', 'type' => AppSetting::TYPE_INTEGER, 'group' => 'return'],
                ['key' => 'shu_allocation_member_usaha', 'value' => '40', 'type' => AppSetting::TYPE_DECIMAL, 'group' => 'shu'],
                ['key' => 'shu_allocation_reserve', 'value' => '30', 'type' => AppSetting::TYPE_DECIMAL, 'group' => 'shu'],
                ['key' => 'shu_allocation_management', 'value' => '20', 'type' => AppSetting::TYPE_DECIMAL, 'group' => 'shu'],
                ['key' => 'shu_allocation_education', 'value' => '10', 'type' => AppSetting::TYPE_DECIMAL, 'group' => 'shu'],
            ];

            foreach ($defaultSettings as $st) {
                AppSetting::updateOrCreate(
                    ['key' => $st['key']],
                    [
                        'value' => $st['value'],
                        'type' => $st['type'],
                        'group' => $st['group'],
                        'updated_by_user_id' => $adminNasional->id,
                    ]
                );
            }
            Cache::forget(AppSettingService::CACHE_KEY);

            // 11. Sample Immutable Activity Logs (Idempotent by action + description)
            $sampleLogs = [
                [
                    'actor' => $adminNasional,
                    'action' => ActivityLog::ACTION_SETTINGS_UPDATED,
                    'description' => '[Demo] Menetapkan komposisi alokasi SHU koperasi 40/30/20/10',
                    'subject_type' => null,
                    'subject_id' => null,
                    'province_id' => null,
                    'regency_id' => null,
                    'branch_id' => null,
                    'old_values' => ['shu_allocation_member_usaha' => 100],
                    'new_values' => ['shu_allocation_member_usaha' => 40, 'shu_allocation_reserve' => 30],
                ],
                [
                    'actor' => $adminNasional,
                    'action' => ActivityLog::ACTION_PRODUCT_PRICE_UPDATED,
                    'description' => '[Demo] Menetapkan harga khusus anggota untuk Beras Premium Merah Putih 5kg',
                    'subject_type' => Product::class,
                    'subject_id' => $products[0]->id,
                    'province_id' => null,
                    'regency_id' => null,
                    'branch_id' => null,
                    'old_values' => ['member_price' => null],
                    'new_values' => ['member_price' => 70000],
                ],
                [
                    'actor' => $adminNgunut,
                    'action' => ActivityLog::ACTION_MEMBER_PIN_RESET,
                    'description' => '[Demo] Mengaktifkan PIN Portal Member Ari Setiyawan (MBR-KOP-3502-001-00001)',
                    'subject_type' => Member::class,
                    'subject_id' => $memberAri->id,
                    'province_id' => $branchNgunut->province_id,
                    'regency_id' => $branchNgunut->regency_id,
                    'branch_id' => $branchNgunut->id,
                    'old_values' => ['has_portal_pin' => false],
                    'new_values' => ['has_portal_pin' => true],
                ],
                [
                    'actor' => $adminNgunut,
                    'action' => ActivityLog::ACTION_TRANSACTION_RETURNED,
                    'description' => '[Demo] Memproses retur parsial RET-DEMO-001 (restockable)',
                    'subject_type' => null,
                    'subject_id' => null,
                    'province_id' => $branchNgunut->province_id,
                    'regency_id' => $branchNgunut->regency_id,
                    'branch_id' => $branchNgunut->id,
                    'old_values' => ['return_status' => 'none'],
                    'new_values' => ['return_status' => 'partial'],
                ],
                [
                    'actor' => $adminBiting,
                    'action' => ActivityLog::ACTION_SHIFT_FORCE_CLOSED,
                    'description' => '[Demo] Force-close shift SHF-DEMO-006 di KOPDES Desa Biting',
                    'subject_type' => CashierShift::class,
                    'subject_id' => $demoShifts['SHF-DEMO-006']['shift']->id,
                    'province_id' => $branchBiting->province_id,
                    'regency_id' => $branchBiting->regency_id,
                    'branch_id' => $branchBiting->id,
                    'old_values' => ['status' => 'open'],
                    'new_values' => ['status' => 'closed', 'is_force_closed' => true],
                ],
                [
                    'actor' => $adminNasional,
                    'action' => ActivityLog::ACTION_IMPORT_COMPLETED,
                    'description' => '[Demo] Onboarding awal 16 produk & stok awal 4 cabang KOPDES',
                    'subject_type' => null,
                    'subject_id' => null,
                    'province_id' => null,
                    'regency_id' => null,
                    'branch_id' => null,
                    'old_values' => null,
                    'new_values' => ['products_imported' => 16, 'branches_seeded' => 4],
                ],
            ];

            foreach ($sampleLogs as $idx => $sl) {
                $exists = ActivityLog::query()
                    ->where('action', $sl['action'])
                    ->where('description', $sl['description'])
                    ->exists();

                if (! $exists) {
                    ActivityLog::create([
                        'actor_id' => $sl['actor']->id,
                        'actor_role' => $sl['actor']->role,
                        'action' => $sl['action'],
                        'description' => $sl['description'],
                        'subject_type' => $sl['subject_type'],
                        'subject_id' => $sl['subject_id'],
                        'province_id' => $sl['province_id'],
                        'regency_id' => $sl['regency_id'],
                        'branch_id' => $sl['branch_id'],
                        'old_values' => $sl['old_values'],
                        'new_values' => $sl['new_values'],
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'DemoDataSeeder/1.0',
                        'created_at' => now()->subDays(15 - ($idx * 2)),
                    ]);
                }
            }
        });
    }
}
