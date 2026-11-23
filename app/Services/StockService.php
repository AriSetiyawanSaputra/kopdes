<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Perform an atomic restock operation on a branch's product inventory.
     * Uses DB::transaction(), firstOrCreate() + lockForUpdate(), updates quantity,
     * and writes exactly 1 append-only record to stock_movements (type = 'restock').
     *
     * @param  array<string, mixed>|int  $dataOrProductId
     */
    public function restock(
        User $actor,
        array|int $dataOrProductId,
        ?int $qtyAdd = null,
        ?string $notes = null,
        ?int $branchId = null,
    ): BranchStock {
        if (is_array($dataOrProductId)) {
            $resolvedBranchId = isset($dataOrProductId['branch_id'])
                ? (int) $dataOrProductId['branch_id']
                : (int) $actor->branch_id;
            $productId = (int) $dataOrProductId['product_id'];
            $resolvedQtyAdd = (int) $dataOrProductId['qty_add'];
            $resolvedNotes = isset($dataOrProductId['notes']) ? (string) $dataOrProductId['notes'] : null;
        } else {
            $resolvedBranchId = $branchId ?? (int) $actor->branch_id;
            $productId = $dataOrProductId;
            $resolvedQtyAdd = (int) $qtyAdd;
            $resolvedNotes = $notes;
        }

        $branch = Branch::findOrFail($resolvedBranchId);
        Gate::forUser($actor)->authorize('restock', [BranchStock::class, $branch]);

        if ($resolvedQtyAdd < 1) {
            throw ValidationException::withMessages([
                'qty_add' => 'Jumlah penambahan stok minimal 1.',
            ]);
        }

        return DB::transaction(function () use ($actor, $branch, $productId, $resolvedQtyAdd, $resolvedNotes): BranchStock {
            $product = Product::query()
                ->where('id', $productId)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Produk tidak ditemukan atau sedang tidak aktif.',
                ]);
            }

            BranchStock::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => 0,
                    'reserved_qty' => 0,
                    'min_stock' => 5,
                ]
            );

            $branchStock = BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $qtyBefore = (int) $branchStock->quantity;
            $qtyAfter = $qtyBefore + $resolvedQtyAdd;
            $qtyChange = $resolvedQtyAdd;

            $branchStock->update([
                'quantity' => $qtyAfter,
            ]);

            $cleanNotes = $resolvedNotes !== null && trim($resolvedNotes) !== ''
                ? trim($resolvedNotes)
                : null;

            StockMovement::create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'type' => 'restock',
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'reference_type' => BranchStock::class,
                'reference_id' => $branchStock->id,
                'notes' => $cleanNotes,
                'created_at' => now(),
            ]);

            return $branchStock->refresh()->load([
                'branch:id,code,name',
                'product:id,sku,barcode,name,unit',
            ]);
        });
    }

    /**
     * Perform an atomic stock adjustment operation on a branch's product inventory.
     * Uses DB::transaction(), firstOrCreate() + lockForUpdate(), validates actual_quantity >= reserved_qty,
     * updates quantity, and writes exactly 1 append-only record to stock_movements (type = 'adjustment').
     *
     * @param  array<string, mixed>|int  $dataOrProductId
     */
    public function adjust(
        User $actor,
        array|int $dataOrProductId,
        ?int $actualQuantity = null,
        ?string $notes = null,
        ?int $branchId = null,
    ): BranchStock {
        if (is_array($dataOrProductId)) {
            $resolvedBranchId = isset($dataOrProductId['branch_id'])
                ? (int) $dataOrProductId['branch_id']
                : (int) $actor->branch_id;
            $productId = (int) $dataOrProductId['product_id'];
            $resolvedActualQty = (int) $dataOrProductId['actual_quantity'];
            $resolvedNotes = isset($dataOrProductId['notes']) ? (string) $dataOrProductId['notes'] : null;
        } else {
            $resolvedBranchId = $branchId ?? (int) $actor->branch_id;
            $productId = $dataOrProductId;
            $resolvedActualQty = (int) $actualQuantity;
            $resolvedNotes = $notes;
        }

        $branch = Branch::findOrFail($resolvedBranchId);
        Gate::forUser($actor)->authorize('adjust', [BranchStock::class, $branch]);

        if ($resolvedActualQty < 0) {
            throw ValidationException::withMessages([
                'actual_quantity' => 'Stok fisik aktual tidak boleh kurang dari 0.',
            ]);
        }

        $cleanNotes = $resolvedNotes !== null ? trim($resolvedNotes) : '';
        if ($cleanNotes === '') {
            throw ValidationException::withMessages([
                'notes' => 'Catatan alasan penyesuaian stok wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $branch, $productId, $resolvedActualQty, $cleanNotes): BranchStock {
            $product = Product::query()
                ->where('id', $productId)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Produk tidak ditemukan atau sedang tidak aktif.',
                ]);
            }

            BranchStock::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => 0,
                    'reserved_qty' => 0,
                    'min_stock' => 5,
                ]
            );

            $branchStock = BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedQty = (int) $branchStock->reserved_qty;

            if ($resolvedActualQty < $reservedQty) {
                throw ValidationException::withMessages([
                    'actual_quantity' => "Stok fisik aktual ({$resolvedActualQty}) tidak boleh lebih kecil dari stok yang sedang ditahan transaksi Midtrans ({$reservedQty}).",
                ]);
            }

            $qtyBefore = (int) $branchStock->quantity;
            $qtyAfter = $resolvedActualQty;
            $qtyChange = $qtyAfter - $qtyBefore;

            $branchStock->update([
                'quantity' => $qtyAfter,
            ]);

            StockMovement::create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'type' => 'adjustment',
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'reference_type' => BranchStock::class,
                'reference_id' => $branchStock->id,
                'notes' => $cleanNotes,
                'created_at' => now(),
            ]);

            return $branchStock->refresh()->load([
                'branch:id,code,name',
                'product:id,sku,barcode,name,unit',
            ]);
        });
    }
}
