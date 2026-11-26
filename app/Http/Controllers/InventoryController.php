<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\RestockRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * Display branch inventory (quantity, reserved_qty, available_stock, min_stock)
     * scoped to the authenticated actor's region/branch.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        Gate::authorize('viewAny', BranchStock::class);

        $branchFilter = $request->filled('branch_id') ? (int) $request->query('branch_id') : null;
        $categoryFilter = $request->filled('category_id') ? (int) $request->query('category_id') : null;
        $lowStockFilter = $request->boolean('low_stock');
        $search = trim((string) $request->query('search', ''));

        Gate::authorize('filter-region', [
            null,
            null,
            $branchFilter,
        ]);

        $branches = Branch::forUser($actor)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'province_id', 'regency_id', 'is_active']);

        $selectedBranchId = $actor->isAdminCabang()
            ? (int) $actor->branch_id
            : ($branchFilter ?? ($branches->first()?->id ? (int) $branches->first()->id : null));

        $selectedBranch = $selectedBranchId !== null
            ? ($branches->firstWhere('id', $selectedBranchId) ?? Branch::forUser($actor)->find($selectedBranchId))
            : null;

        if ($selectedBranch) {
            Gate::authorize('viewAny', [BranchStock::class, $selectedBranch]);
        }

        $branchStocksByProduct = collect();

        if ($selectedBranch !== null) {
            $branchStocksByProduct = BranchStock::forUser($actor)
                ->where('branch_id', $selectedBranch->id)
                ->get()
                ->keyBy('product_id');
        }

        $productsQuery = Product::query()
            ->where('is_active', true)
            ->with(['category:id,name,slug'])
            ->orderBy('name');

        if ($categoryFilter !== null) {
            $productsQuery->where('category_id', $categoryFilter);
        }

        if ($search !== '') {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $stocks = $productsQuery->get()->map(function (Product $product) use ($branchStocksByProduct, $selectedBranch) {
            /** @var BranchStock|null $stock */
            $stock = $branchStocksByProduct->get($product->id);

            $quantity = (int) ($stock?->quantity ?? 0);
            $reservedQty = (int) ($stock?->reserved_qty ?? 0);
            $availableStock = $quantity - $reservedQty;
            $minStock = (int) ($stock?->min_stock ?? 5);

            return [
                'id' => $stock?->id,
                'branch_id' => $selectedBranch?->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'unit' => $product->unit,
                'cost_price' => (float) $product->cost_price,
                'selling_price' => (float) $product->selling_price,
                'category_id' => $product->category_id,
                'category' => $product->category,
                'product' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'cost_price' => (float) $product->cost_price,
                    'selling_price' => (float) $product->selling_price,
                    'category' => $product->category,
                ],
                'branch' => $selectedBranch ? [
                    'id' => $selectedBranch->id,
                    'code' => $selectedBranch->code,
                    'name' => $selectedBranch->name,
                ] : null,
                'quantity' => $quantity,
                'reserved_qty' => $reservedQty,
                'available_stock' => $availableStock,
                'min_stock' => $minStock,
                'is_low_stock' => $availableStock <= $minStock,
                'updated_at' => $stock?->updated_at?->toISOString(),
            ];
        });

        if ($lowStockFilter) {
            $stocks = $stocks->filter(fn (array $item) => $item['is_low_stock'])->values();
        }

        $canMutate = $selectedBranch
            ? $actor->can('mutate', [BranchStock::class, $selectedBranch])
            : $actor->can('mutate', BranchStock::class);

        return Inertia::render('Inventory/Index', [
            'stocks' => $stocks,
            'selectedBranch' => $selectedBranch ? [
                'id' => $selectedBranch->id,
                'code' => $selectedBranch->code,
                'name' => $selectedBranch->name,
            ] : null,
            'branches' => $branches,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'branch_id' => $selectedBranch?->id ?? '',
                'category_id' => $categoryFilter ?? '',
                'low_stock' => $lowStockFilter,
            ],
            'permissions' => [
                'can_restock' => $canMutate,
                'can_adjust' => $canMutate,
                'can_mutate' => $canMutate,
                'can_import' => $actor->isAdminNasional() || $actor->isAdminCabang(),
                'is_admin_nasional' => $actor->isAdminNasional(),
            ],
        ]);
    }

    /**
     * Record a restock (inbound goods) operation for the authenticated admin_cabang's branch.
     */
    public function restock(RestockRequest $request): RedirectResponse
    {
        $stock = $this->stockService->restock($request->user(), $request->validated());
        $qtyAdd = (int) $request->validated('qty_add');

        return redirect()
            ->back()
            ->with(
                'success',
                "Restock produk {$stock->product->name} (+{$qtyAdd} {$stock->product->unit}) berhasil disimpan."
            );
    }

    /**
     * Record a stock adjustment (stock opname) operation for the authenticated admin_cabang's branch.
     */
    public function adjust(AdjustStockRequest $request): RedirectResponse
    {
        $stock = $this->stockService->adjust($request->user(), $request->validated());

        return redirect()
            ->back()
            ->with(
                'success',
                "Penyesuaian stok produk {$stock->product->name} menjadi {$stock->quantity} {$stock->product->unit} berhasil disimpan."
            );
    }

    /**
     * Display the append-only audit log of stock movements scoped to the actor's region/branch.
     */
    public function movements(Request $request): Response
    {
        $actor = $request->user();

        Gate::authorize('viewAny', StockMovement::class);

        $branchFilter = $request->filled('branch_id') ? (int) $request->query('branch_id') : null;
        $productFilter = $request->filled('product_id') ? (int) $request->query('product_id') : null;
        $typeFilter = $request->query('type');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $search = trim((string) $request->query('search', ''));

        Gate::authorize('filter-region', [
            null,
            null,
            $branchFilter,
        ]);

        if ($branchFilter !== null) {
            $targetBranch = Branch::find($branchFilter);
            if ($targetBranch) {
                Gate::authorize('viewAny', [StockMovement::class, $targetBranch]);
            }
        }

        $query = StockMovement::forUser($actor)
            ->with([
                'branch:id,code,name',
                'product' => fn ($q) => $q->withTrashed()->select(['id', 'sku', 'barcode', 'name', 'unit']),
                'user:id,name,role',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($branchFilter !== null) {
            $query->where('branch_id', $branchFilter);
        }

        if ($productFilter !== null) {
            $query->where('product_id', $productFilter);
        }

        if (in_array($typeFilter, ['sale', 'restock', 'adjustment', 'void_return'], true)) {
            $query->where('type', $typeFilter);
        }

        if (is_string($startDate) && $startDate !== '') {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if (is_string($endDate) && $endDate !== '') {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->withTrashed()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        $movements = $query->get()->map(fn (StockMovement $movement) => [
            'id' => $movement->id,
            'branch_id' => $movement->branch_id,
            'product_id' => $movement->product_id,
            'user_id' => $movement->user_id,
            'type' => $movement->type,
            'qty_change' => (int) $movement->qty_change,
            'qty_before' => (int) $movement->qty_before,
            'qty_after' => (int) $movement->qty_after,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'notes' => $movement->notes,
            'created_at' => $movement->created_at?->format('Y-m-d H:i:s'),
            'branch' => $movement->branch,
            'product' => $movement->product,
            'user' => $movement->user,
        ]);

        $branches = Branch::forUser($actor)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $products = Product::withTrashed()
            ->orderBy('name')
            ->get(['id', 'sku', 'barcode', 'name', 'unit']);

        return Inertia::render('Inventory/Movements', [
            'movements' => $movements,
            'branches' => $branches,
            'products' => $products,
            'movementTypes' => [
                ['value' => 'restock', 'label' => 'Restock (Barang Masuk)'],
                ['value' => 'adjustment', 'label' => 'Penyesuaian Stok (Opname)'],
                ['value' => 'sale', 'label' => 'Penjualan POS'],
                ['value' => 'void_return', 'label' => 'Pembatalan / Retur'],
            ],
            'filters' => [
                'branch_id' => $branchFilter ?? ($actor->isAdminCabang() ? $actor->branch_id : ''),
                'product_id' => $productFilter ?? '',
                'type' => $typeFilter ?? '',
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
                'search' => $search,
            ],
        ]);
    }
}
