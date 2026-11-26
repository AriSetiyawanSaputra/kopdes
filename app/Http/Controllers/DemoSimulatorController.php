<?php

namespace App\Http\Controllers;

use App\Models\BranchStock;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoSimulatorController extends Controller
{
    /**
     * Provide sample stocked products (mix of member_price & regular) and sample RFID members
     * (at least 1 home-branch + 1 cross-branch) for the POS Hardware Simulator Popover (FR-P3-13).
     * Strictly gated behind middleware ['auth:web', 'role:kasir', 'demo.mode'].
     */
    public function posSamples(Request $request): JsonResponse
    {
        /** @var User $cashier */
        $cashier = $request->user('web');
        $branchId = (int) $cashier->branch_id;

        $stocks = BranchStock::query()
            ->where('branch_id', $branchId)
            ->whereRaw('(quantity - reserved_qty) > 0')
            ->with(['product' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn (BranchStock $stock) => $stock->product !== null);

        $withMemberPrice = $stocks
            ->filter(fn (BranchStock $stock) => $stock->product->member_price !== null)
            ->take(4);

        $withoutMemberPrice = $stocks
            ->filter(fn (BranchStock $stock) => $stock->product->member_price === null)
            ->take(4);

        $combinedStocks = $withMemberPrice->concat($withoutMemberPrice)->unique('product_id');

        // Fill up to 8 products if one bucket had fewer than 4
        if ($combinedStocks->count() < 8) {
            $remaining = $stocks
                ->reject(fn (BranchStock $stock) => $combinedStocks->contains('product_id', $stock->product_id))
                ->take(8 - $combinedStocks->count());
            $combinedStocks = $combinedStocks->concat($remaining);
        }

        $products = $combinedStocks
            ->take(8)
            ->map(function (BranchStock $stock): array {
                $p = $stock->product;

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'name' => $p->name,
                    'unit' => $p->unit,
                    'selling_price' => round((float) $p->selling_price, 2),
                    'member_price' => $p->member_price !== null ? round((float) $p->member_price, 2) : null,
                    'available_stock' => max(0, (int) $stock->quantity - (int) $stock->reserved_qty),
                ];
            })
            ->values()
            ->all();

        // Sample RFID members: prioritize 1 home branch + up to 2 cross-branch (max 3 total)
        $homeMember = Member::query()
            ->where('status', 'active')
            ->whereNotNull('card_uid')
            ->where('home_branch_id', $branchId)
            ->with('homeBranch:id,code,name')
            ->orderBy('id')
            ->first();

        $crossMembers = Member::query()
            ->where('status', 'active')
            ->whereNotNull('card_uid')
            ->where('home_branch_id', '!=', $branchId)
            ->with('homeBranch:id,code,name')
            ->orderBy('id')
            ->limit(2)
            ->get();

        $memberCollection = collect();
        if ($homeMember) {
            $memberCollection->push($homeMember);
        }
        foreach ($crossMembers as $cm) {
            if ($memberCollection->count() < 3) {
                $memberCollection->push($cm);
            }
        }
        if ($memberCollection->count() < 3) {
            $extraHome = Member::query()
                ->where('status', 'active')
                ->whereNotNull('card_uid')
                ->whereNotIn('id', $memberCollection->pluck('id')->all())
                ->with('homeBranch:id,code,name')
                ->orderBy('id')
                ->limit(3 - $memberCollection->count())
                ->get();
            $memberCollection = $memberCollection->concat($extraHome);
        }

        $members = $memberCollection
            ->map(fn (Member $m): array => [
                'id' => $m->id,
                'member_code' => $m->member_code,
                'name' => $m->name,
                'card_uid' => $m->card_uid,
                'home_branch_id' => $m->home_branch_id,
                'home_branch_name' => $m->homeBranch?->name ?? '-',
                'is_cross_branch' => (int) $m->home_branch_id !== $branchId,
            ])
            ->values()
            ->all();

        return response()->json([
            'products' => $products,
            'members' => $members,
        ]);
    }
}
