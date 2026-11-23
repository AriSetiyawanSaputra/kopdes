<?php

use App\Http\Controllers\Auth\PosAuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Autentikasi Kasir POS (Tap Kartu RFID + PIN 6 Digit)
Route::get('/pos/login', [PosAuthController::class, 'showLogin'])->name('pos.login');
Route::post('/pos/login', [PosAuthController::class, 'login'])->name('pos.login.store');

Route::middleware(['auth', 'role:kasir'])->group(function () {
    Route::get('/pos', function () {
        return Inertia::render('Pos/Index');
    })->name('pos.index');

    Route::post('/pos/lock', [PosAuthController::class, 'lock'])->name('pos.lock');
    Route::post('/pos/unlock', [PosAuthController::class, 'unlock'])->name('pos.unlock');
    Route::post('/pos/logout', [PosAuthController::class, 'logout'])->name('pos.logout');
});

Route::middleware([
    'auth',
    'role:admin_nasional,admin_provinsi,admin_kabupaten,admin_cabang',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

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
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
