<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Requests\StoreShiftCashMovementRequest;
use App\Http\Resources\CashierShiftPosResource;
use App\Models\CashierShift;
use App\Services\CashierShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PosShiftController extends Controller
{
    public function __construct(
        private readonly CashierShiftService $shiftService,
    ) {}

    /**
     * Buka shift kasir baru di layar POS (POST /pos/shifts/open).
     */
    public function open(OpenShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftService->openShift(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'shift' => (new CashierShiftPosResource($shift))->resolve($request),
            'pending_transactions_for_shift' => [],
            'message' => "Shift kasir {$shift->shift_code} berhasil dibuka.",
        ], 201);
    }

    /**
     * Catat mutasi kas laci (cash_in / cash_out) selama shift terbuka (POST /pos/shifts/{shift}/cash-movements).
     */
    public function recordCashMovement(StoreShiftCashMovementRequest $request, CashierShift $shift): JsonResponse
    {
        $movement = $this->shiftService->recordCashMovement(
            $shift,
            $request->user(),
            $request->validated()
        );

        $freshShift = $shift->fresh(['branch', 'cashier', 'cashMovements.user']);

        return response()->json([
            'movement' => [
                'id' => $movement->id,
                'type' => $movement->type,
                'amount' => (float) $movement->amount,
                'notes' => $movement->notes,
                'created_at' => $movement->created_at?->format('Y-m-d H:i:s'),
            ],
            'shift' => (new CashierShiftPosResource($freshShift))->resolve($request),
            'message' => $movement->type === 'cash_in'
                ? 'Kas masuk laci berhasil dicatat.'
                : 'Kas keluar laci berhasil dicatat.',
        ], 201);
    }

    /**
     * Tutup shift kasir dengan metode Blind Count (POST /pos/shifts/{shift}/close).
     */
    public function close(CloseShiftRequest $request, CashierShift $shift): JsonResponse
    {
        $closedShift = $this->shiftService->closeShift(
            $shift,
            $request->user(),
            $request->validated(),
            isForceClose: false
        );

        return response()->json([
            'shift' => (new CashierShiftPosResource($closedShift))->resolve($request),
            'message' => "Shift kasir {$closedShift->shift_code} berhasil ditutup.",
        ]);
    }

    /**
     * Ambil ringkasan shift untuk kasir (GET /pos/shifts/{shift}).
     * Blind Count tetap ditegakkan: selama shift 'open', ekspektasi & penjualan TIDAK dikembalikan.
     */
    public function summary(Request $request, CashierShift $shift): JsonResponse
    {
        Gate::authorize('view', $shift);

        $pending = $shift->isOpen()
            ? $this->shiftService->getPendingTransactionsForShift($shift)
            : [];

        return response()->json([
            'shift' => (new CashierShiftPosResource($shift))->resolve($request),
            'pending_transactions_for_shift' => $pending,
        ]);
    }
}
