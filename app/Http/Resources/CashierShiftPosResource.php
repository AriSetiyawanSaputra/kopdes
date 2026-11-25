<?php

namespace App\Http\Resources;

use App\Models\CashierShift;
use App\Models\ShiftCashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource khusus layar POS yang menegakkan keamanan Blind Count (FR-P2-03 & PRD_PHASE2 6.3 #4).
 * Selama shift berstatus 'open', kolom cash_sales_total, midtrans_sales_total, cash_in_total,
 * cash_out_total, cash_refund_total, expected_closing_cash, dan variance DILARANG disertakan.
 *
 * @mixin CashierShift
 */
class CashierShiftPosResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CashierShift $shift */
        $shift = $this->resource;
        $shift->loadMissing(['branch:id,code,name,address,phone', 'cashier:id,name', 'cashMovements.user:id,name']);

        $movements = $shift->cashMovements
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (ShiftCashMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'amount' => (float) $m->amount,
                'notes' => $m->notes,
                'user_name' => $m->user?->name ?? '-',
                'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
            ])
            ->all();

        $base = [
            'id' => $shift->id,
            'shift_code' => $shift->shift_code,
            'branch_id' => $shift->branch_id,
            'cashier_id' => $shift->cashier_id,
            'status' => $shift->status,
            'opening_cash' => (float) $shift->opening_cash,
            'opening_notes' => $shift->opening_notes,
            'opened_at' => $shift->opened_at?->format('Y-m-d H:i:s'),
            'branch' => $shift->branch ? [
                'id' => $shift->branch->id,
                'code' => $shift->branch->code,
                'name' => $shift->branch->name,
                'address' => $shift->branch->address,
                'phone' => $shift->branch->phone,
            ] : null,
            'cashier' => $shift->cashier ? [
                'id' => $shift->cashier->id,
                'name' => $shift->cashier->name,
            ] : null,
            'cash_movements' => $movements,
        ];

        // Blind Count Enforcement: saat shift masih 'open', JANGAN bocorkan ekspektasi / penjualan / selisih
        if ($shift->isOpen()) {
            return $base;
        }

        $shift->loadMissing(['closedBy:id,name']);

        return array_merge($base, [
            'is_force_closed' => (bool) $shift->is_force_closed,
            'closed_by_user_id' => $shift->closed_by_user_id,
            'closed_by' => $shift->closedBy ? [
                'id' => $shift->closedBy->id,
                'name' => $shift->closedBy->name,
            ] : null,
            'cash_sales_total' => (float) $shift->cash_sales_total,
            'midtrans_sales_total' => (float) $shift->midtrans_sales_total,
            'cash_in_total' => (float) $shift->cash_in_total,
            'cash_out_total' => (float) $shift->cash_out_total,
            'cash_refund_total' => (float) $shift->cash_refund_total,
            'expected_closing_cash' => (float) $shift->expected_closing_cash,
            'actual_closing_cash' => (float) $shift->actual_closing_cash,
            'variance' => (float) $shift->variance,
            'closing_notes' => $shift->closing_notes,
            'closed_at' => $shift->closed_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
