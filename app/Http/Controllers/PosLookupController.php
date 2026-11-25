<?php

namespace App\Http\Controllers;

use App\Models\BranchStock;
use App\Models\Member;
use App\Models\Product;
use App\Support\CardUidNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosLookupController extends Controller
{
    /**
     * Lookup an active global product by physical barcode and return its current branch stock availability.
     * Endpoint: GET /pos/products/barcode/{barcode}
     */
    public function productByBarcode(Request $request, string $barcode): JsonResponse
    {
        $cashier = $request->user();

        if (! $cashier || ! $cashier->is_active || ! $cashier->branch_id || ! $cashier->branch?->is_active) {
            abort(403, 'Akun kasir atau cabang KOPDES sedang tidak aktif.');
        }

        $cleanBarcode = trim($barcode);

        $product = Product::query()
            ->where('barcode', $cleanBarcode)
            ->where('is_active', true)
            ->with(['category:id,name'])
            ->first();

        if (! $product) {
            return response()->json([
                'message' => 'Barcode tidak ditemukan. Jika Anda memindai Kartu Member, tekan F2 terlebih dahulu.',
            ], 404);
        }

        $stock = BranchStock::query()
            ->where('branch_id', $cashier->branch_id)
            ->where('product_id', $product->id)
            ->first();

        $quantity = (int) ($stock?->quantity ?? 0);
        $reservedQty = (int) ($stock?->reserved_qty ?? 0);
        $availableStock = max(0, $quantity - $reservedQty);

        return response()->json([
            'id' => $product->id,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'unit' => $product->unit,
            'selling_price' => (float) $product->selling_price,
            'member_price' => $product->member_price !== null ? (float) $product->member_price : null,
            'quantity' => $quantity,
            'reserved_qty' => $reservedQty,
            'available_stock' => $availableStock,
        ]);
    }

    /**
     * Controlled cross-branch member lookup by RFID card_uid at POS (PRD 2.3 & FR-06).
     * Reads across all home_branch_id records for active members and returns only the 5 minimal non-sensitive fields.
     * Endpoint: GET /pos/members/lookup?card_uid=...
     */
    public function memberByCard(Request $request): JsonResponse
    {
        $cashier = $request->user();

        if (! $cashier || ! $cashier->is_active || ! $cashier->branch_id || ! $cashier->branch?->is_active) {
            abort(403, 'Akun kasir atau cabang KOPDES sedang tidak aktif.');
        }

        $normalizedUid = CardUidNormalizer::normalize((string) $request->query('card_uid', ''));

        if ($normalizedUid === '') {
            return response()->json([
                'message' => 'UID kartu member wajib dipindai atau diisi.',
            ], 422);
        }

        $member = Member::query()
            ->with(['homeBranch:id,code,name'])
            ->where('card_uid', $normalizedUid)
            ->first();

        if (! $member) {
            return response()->json([
                'message' => 'Kartu member tidak terdaftar dalam sistem KOPDES.',
            ], 404);
        }

        if ($member->status !== 'active') {
            return response()->json([
                'message' => 'Kartu member tidak aktif.',
            ], 422);
        }

        return response()->json([
            'id' => $member->id,
            'member_code' => $member->member_code,
            'name' => $member->name,
            'home_branch_id' => $member->home_branch_id,
            'home_branch_name' => $member->homeBranch?->name,
        ]);
    }
}
