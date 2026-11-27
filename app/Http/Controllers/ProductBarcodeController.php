<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductBarcodeController extends Controller
{
    /**
     * Halaman generator cetak stiker label barcode produk (SVG via jsbarcode).
     * Dapat diakses oleh seluruh role Admin maupun Kasir (FR-P3-05).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // SEC-13: Tolak akses cetak barcode untuk kasir ketika sesi layar POS sedang terkunci
        if ($user?->isCashier() && (bool) $request->session()->get('pos_locked', false)) {
            abort(403, 'Layar POS sedang terkunci. Silakan buka kunci terlebih dahulu menggunakan PIN kasir.');
        }

        $products = Product::query()
            ->where('is_active', true)
            ->with(['category:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'unit' => $p->unit,
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name ?? '-',
                'selling_price' => (float) $p->selling_price,
                'member_price' => $p->member_price !== null ? (float) $p->member_price : null,
            ])
            ->values()
            ->all();

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Products/BarcodeLabels', [
            'products' => $products,
            'categories' => $categories,
            'isCashier' => $request->user()?->isCashier() ?? false,
        ]);
    }
}
