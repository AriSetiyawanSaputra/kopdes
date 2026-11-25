<?php

use App\Http\Controllers\Auth\PosAuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OperationalExpenseController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosLookupController;
use App\Http\Controllers\PosShiftController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TransactionReturnController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    $user = $request->user();

    if ($user) {
        return $user->isCashier()
            ? redirect()->route('pos.index')
            : redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

// Autentikasi Kasir POS (Tap Kartu RFID + PIN 6 Digit)
Route::get('/pos/login', [PosAuthController::class, 'showLogin'])->name('pos.login');
Route::post('/pos/login', [PosAuthController::class, 'login'])->name('pos.login.store');

Route::middleware(['auth', 'role:kasir'])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');

    Route::post('/pos/lock', [PosAuthController::class, 'lock'])->name('pos.lock');
    Route::post('/pos/unlock', [PosAuthController::class, 'unlock'])->name('pos.unlock');
    Route::post('/pos/logout', [PosAuthController::class, 'logout'])->name('pos.logout');

    Route::middleware('pos.unlocked')->group(function () {
        Route::get('/pos/products/barcode/{barcode}', [PosLookupController::class, 'productByBarcode'])
            ->name('pos.products.barcode');
        Route::get('/pos/members/lookup', [PosLookupController::class, 'memberByCard'])
            ->name('pos.members.lookup');

        Route::post('/pos/shifts/open', [PosShiftController::class, 'open'])
            ->name('pos.shifts.open');
        Route::post('/pos/shifts/{shift}/cash-movements', [PosShiftController::class, 'recordCashMovement'])
            ->name('pos.shifts.cash-movements.store');
        Route::post('/pos/shifts/{shift}/close', [PosShiftController::class, 'close'])
            ->name('pos.shifts.close');
        Route::get('/pos/shifts/{shift}', [PosShiftController::class, 'summary'])
            ->name('pos.shifts.summary');

        Route::post('/pos/checkout', [PosController::class, 'checkout'])
            ->middleware('pos.shift')
            ->name('pos.checkout');
        Route::get('/pos/transactions/pending', [PosController::class, 'pendingTransactions'])
            ->name('pos.transactions.pending');
        Route::get('/pos/transactions/today', [PosController::class, 'todayTransactions'])
            ->name('pos.transactions.today');
        Route::post('/pos/transactions/{transaction}/check-status', [PosController::class, 'checkStatus'])
            ->name('pos.transactions.check-status');
        Route::post('/pos/transactions/{transaction}/cancel', [PosController::class, 'cancel'])
            ->name('pos.transactions.cancel');
    });
});

Route::middleware([
    'auth',
    'role:admin_nasional,admin_provinsi,admin_kabupaten,admin_cabang',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Laporan Keuangan Berjenjang & Rekap Transaksi Member (SHU)
    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/sales/export/{format}', [ReportController::class, 'exportSales'])
        ->whereIn('format', ['pdf', 'excel'])
        ->name('reports.sales.export');
    Route::get('/reports/members', [ReportController::class, 'members'])->name('reports.members');
    Route::get('/reports/members/export/{format}', [ReportController::class, 'exportMembers'])
        ->whereIn('format', ['pdf', 'excel'])
        ->name('reports.members.export');

    // Modul Biaya Operasional Cabang & Ekspor (PDF/Excel)
    Route::get('/expenses', [OperationalExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/export/{format}', [OperationalExpenseController::class, 'export'])
        ->whereIn('format', ['pdf', 'excel'])
        ->name('expenses.export');
    Route::post('/expenses', [OperationalExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{operationalExpense}', [OperationalExpenseController::class, 'update'])
        ->name('expenses.update');
    Route::delete('/expenses/{operationalExpense}', [OperationalExpenseController::class, 'destroy'])
        ->name('expenses.destroy');

    // Monitoring Shift Kasir & Force-Close (Dashboard Admin)
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/export/{format}', [ShiftController::class, 'export'])
        ->whereIn('format', ['pdf', 'excel'])
        ->name('shifts.export');
    Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::post('/shifts/{shift}/force-close', [ShiftController::class, 'forceClose'])
        ->name('shifts.force-close');

    // Retur Parsial & Full Void Transaksi Terpusat (Dashboard Admin)
    Route::get('/returns', [TransactionReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/export/{format}', [TransactionReturnController::class, 'export'])
        ->whereIn('format', ['pdf', 'excel'])
        ->name('returns.export');
    Route::get('/transactions/{transaction}/return', [TransactionReturnController::class, 'create'])
        ->name('returns.create');
    Route::post('/returns', [TransactionReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{transactionReturn}', [TransactionReturnController::class, 'show'])
        ->name('returns.show');

    // Referensi Wilayah Baku (Read-Only Dependent Dropdowns)
    Route::get('/regions/provinces', [RegionController::class, 'provinces'])->name('regions.provinces');
    Route::get('/regions/regencies', [RegionController::class, 'regencies'])->name('regions.regencies');
    Route::get('/regions/provinces/{provinceId}/regencies', [RegionController::class, 'regencies'])
        ->name('regions.provinces.regencies');
    Route::get('/regions/districts', [RegionController::class, 'districts'])->name('regions.districts');
    Route::get('/regions/regencies/{regencyId}/districts', [RegionController::class, 'districts'])
        ->name('regions.regencies.districts');
    Route::get('/regions/villages', [RegionController::class, 'villages'])->name('regions.villages');
    Route::get('/regions/districts/{districtId}/villages', [RegionController::class, 'villages'])
        ->name('regions.districts.villages');

    // Manajemen Cabang KOPDES (1 Desa = 1 KOPDES)
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::patch('/branches/{branch}/toggle-active', [BranchController::class, 'toggleActive'])
        ->name('branches.toggle-active');

    // Manajemen Pengguna Admin Berjenjang & Kasir (Binding RFID + PIN 6 Digit)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('users.toggle-active');

    // Manajemen Keanggotaan (Member) & Ganti Kartu RFID
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('/members', [MemberController::class, 'store'])->name('members.store');
    Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
    Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
    Route::patch('/members/{member}/replace-card', [MemberController::class, 'replaceCard'])
        ->name('members.replace-card');
    Route::patch('/members/{member}/toggle-status', [MemberController::class, 'toggleStatus'])
        ->name('members.toggle-status');

    // Master Kategori & Katalog Produk Global
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Manajemen Inventori Cabang & Audit Log Pergerakan Stok (Append-Only)
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
