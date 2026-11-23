<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display the global product catalog (CRUD for admin_nasional, read-only for other admin roles).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $categoryFilter = $request->filled('category_id') ? (int) $request->query('category_id') : null;
        $statusFilter = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = Product::query()
            ->with(['category:id,name,slug'])
            ->orderBy('name');

        if ($categoryFilter !== null) {
            $query->where('category_id', $categoryFilter);
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->get()->map(fn (Product $product) => [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'unit' => $product->unit,
            'cost_price' => (float) $product->cost_price,
            'selling_price' => (float) $product->selling_price,
            'is_active' => (bool) $product->is_active,
            'category' => $product->category,
        ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'category_id' => $categoryFilter ?? '',
                'status' => $statusFilter ?? '',
            ],
            'permissions' => [
                'can_manage' => $user->isAdminNasional(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new global product (Admin Nasional only).
     */
    public function create(Request $request): Response
    {
        if (! $request->user()?->isAdminNasional()) {
            abort(403, 'Hanya Admin Nasional yang dapat menambah katalog produk global.');
        }

        return Inertia::render('Products/Form', [
            'product' => null,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created global product in storage (Admin Nasional only).
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return redirect()
            ->route('products.index')
            ->with('success', "Produk {$product->name} ({$product->sku}) berhasil ditambahkan ke katalog nasional.");
    }

    /**
     * Show the form for editing the specified global product (Admin Nasional only).
     */
    public function edit(Request $request, Product $product): Response
    {
        if (! $request->user()?->isAdminNasional()) {
            abort(403, 'Hanya Admin Nasional yang dapat mengubah katalog produk global.');
        }

        return Inertia::render('Products/Form', [
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'unit' => $product->unit,
                'cost_price' => (float) $product->cost_price,
                'selling_price' => (float) $product->selling_price,
                'is_active' => (bool) $product->is_active,
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified global product in storage (Admin Nasional only).
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('products.index')
            ->with('success', "Produk {$product->name} ({$product->sku}) berhasil diperbarui.");
    }

    /**
     * Soft-delete the specified global product so historical transaction_items remain intact (FR-07).
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        if (! $request->user()?->isAdminNasional()) {
            abort(403, 'Hanya Admin Nasional yang dapat menghapus produk.');
        }

        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', "Produk {$name} berhasil dihapus (soft delete).");
    }
}
