<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of global product categories.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'permissions' => [
                'can_manage' => $user->isAdminNasional(),
            ],
        ]);
    }

    /**
     * Store a newly created category in storage (Admin Nasional only).
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', "Kategori {$category->name} berhasil ditambahkan.");
    }

    /**
     * Update the specified category in storage (Admin Nasional only).
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', "Kategori {$category->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified category if it has no associated products (Admin Nasional only).
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if (! $request->user()?->isAdminNasional()) {
            abort(403, 'Hanya Admin Nasional yang dapat menghapus kategori produk.');
        }

        if ($category->products()->withTrashed()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Kategori tidak dapat dihapus karena masih memiliki produk terkait.');
        }

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', "Kategori {$name} berhasil dihapus.");
    }
}
