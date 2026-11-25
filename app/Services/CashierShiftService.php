<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\ShiftCashMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CashierShiftService
{
    /**
     * Ambil shift berstatus 'open' milik kasir pada cabangnya saat ini.
     */
    public function getActiveShiftForCashier(User $cashier): ?CashierShift
    {
        if (! $cashier->isCashier() || ! $cashier->branch_id) {
            return null;
        }

        return CashierShift::query()
            ->with(['branch:id,code,name,address,phone', 'cashier:id,name', 'cashMovements.user:id,name'])
            ->where('cashier_id', $cashier->id)
            ->where('branch_id', $cashier->branch_id)
            ->where('status', CashierShift::STATUS_OPEN)
            ->first();
    }

    /**
     * Daftar transaksi Midtrans yang masih berstatus 'pending' pada sebuah shift.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingTransactionsForShift(CashierShift $shift): array
    {
        return Transaction::query()
            ->where('cashier_shift_id', $shift->id)
            ->where('payment_status', 'pending')
            ->orderByDesc('created_at')
            ->get(['id', 'invoice_number', 'total_amount', 'created_at', 'expires_at'])
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'invoice_number' => $tx->invoice_number,
                'total_amount' => (float) $tx->total_amount,
                'created_at' => $tx->created_at?->format('Y-m-d H:i:s'),
                'expires_at' => $tx->expires_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Buka shift kasir baru secara atomik dengan pessimistic locking agar 1 kasir hanya
     * memiliki maksimal 1 shift 'open' (cabang tetap boleh paralel antar kasir berbeda).
     *
     * @param  array<string, mixed>  $data
     */
    public function openShift(User $cashier, array $data): CashierShift
    {
        if (! $cashier->isCashier() || ! $cashier->is_active || ! $cashier->branch_id) {
            abort(403, 'Hanya kasir aktif yang dapat membuka shift POS.');
        }

        $branch = Branch::findOrFail($cashier->branch_id);
        if (! $branch->is_active) {
            abort(403, 'Cabang KOPDES sedang nonaktif.');
        }

        return DB::transaction(function () use ($cashier, $branch, $data): CashierShift {
            // Kunci baris user kasir agar tidak bisa membuka 2 shift secara bersamaan (concurrency-safe)
            User::query()->where('id', $cashier->id)->lockForUpdate()->first();

            $existingOpenShift = CashierShift::query()
                ->where('cashier_id', $cashier->id)
                ->where('status', CashierShift::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($existingOpenShift) {
                throw ValidationException::withMessages([
                    'shift' => "Anda masih memiliki shift terbuka ({$existingOpenShift->shift_code}). Tutup shift tersebut sebelum membuka shift baru.",
                ]);
            }

            $shiftCode = $this->generateShiftCode($branch);

            $shift = CashierShift::create([
                'shift_code' => $shiftCode,
                'branch_id' => $branch->id,
                'cashier_id' => $cashier->id,
                'closed_by_user_id' => null,
                'status' => CashierShift::STATUS_OPEN,
                'is_force_closed' => false,
                'opening_cash' => round((float) ($data['opening_cash'] ?? 0), 2),
                'opening_notes' => isset($data['opening_notes']) && trim((string) $data['opening_notes']) !== ''
                    ? trim((string) $data['opening_notes'])
                    : null,
                'opened_at' => now(),
            ]);

            return $shift->fresh(['branch', 'cashier', 'cashMovements.user']);
        });
    }

    /**
     * Catat mutasi kas laci (cash_in / cash_out) secara append-only (FR-P2-02).
     * Tidak pernah menyentuh tabel operational_expenses maupun mengurangi Laba Bersih.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordCashMovement(CashierShift $shift, User $cashier, array $data): ShiftCashMovement
    {
        return DB::transaction(function () use ($shift, $cashier, $data): ShiftCashMovement {
            $lockedShift = CashierShift::query()
                ->where('id', $shift->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedShift->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => 'Mutasi kas laci tidak dapat dicatat karena shift ini sudah ditutup.',
                ]);
            }

            if ((int) $lockedShift->cashier_id !== (int) $cashier->id || (int) $lockedShift->branch_id !== (int) $cashier->branch_id) {
                abort(403, 'Anda hanya dapat mencatat mutasi kas pada shift milik Anda sendiri.');
            }

            return ShiftCashMovement::create([
                'cashier_shift_id' => $lockedShift->id,
                'branch_id' => $lockedShift->branch_id,
                'user_id' => $cashier->id,
                'type' => (string) $data['type'],
                'amount' => round((float) $data['amount'], 2),
                'notes' => trim((string) $data['notes']),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Tutup shift kasir (Blind Count oleh kasir atau Force-Close oleh Admin Cabang / Nasional)
     * di dalam DB::transaction() + lockForUpdate() (FR-P2-03 & FR-P2-04).
     *
     * Rumus: expected_closing_cash = opening_cash + cash_sales_total + cash_in_total - cash_out_total - cash_refund_total
     *        variance = actual_closing_cash - expected_closing_cash
     *
     * @param  array<string, mixed>  $data
     */
    public function closeShift(
        CashierShift $shift,
        User $actor,
        array $data,
        bool $isForceClose = false
    ): CashierShift {
        return DB::transaction(function () use ($shift, $actor, $data, $isForceClose): CashierShift {
            $lockedShift = CashierShift::query()
                ->where('id', $shift->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedShift->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => 'Shift kasir ini sudah berstatus tertutup (closed).',
                ]);
            }

            // Blokir tutup shift apabila masih ada transaksi Midtrans berstatus 'pending' pada shift ini
            $pendingInvoices = Transaction::query()
                ->where('cashier_shift_id', $lockedShift->id)
                ->where('payment_status', 'pending')
                ->lockForUpdate()
                ->pluck('invoice_number')
                ->values()
                ->all();

            if (! empty($pendingInvoices)) {
                throw ValidationException::withMessages([
                    'pending_transactions' => 'Tutup shift diblokir karena masih terdapat '.count($pendingInvoices).' transaksi Midtrans berstatus pending ('.implode(', ', $pendingInvoices).'). Selesaikan atau batalkan terlebih dahulu.',
                    'pending_invoices' => $pendingInvoices,
                ]);
            }

            $salesRow = Transaction::query()
                ->where('cashier_shift_id', $lockedShift->id)
                ->where('payment_status', 'paid')
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales_total")
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'midtrans' THEN total_amount ELSE 0 END), 0) as midtrans_sales_total")
                ->first();

            $movementRow = ShiftCashMovement::query()
                ->where('cashier_shift_id', $lockedShift->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'cash_in' THEN amount ELSE 0 END), 0) as cash_in_total")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'cash_out' THEN amount ELSE 0 END), 0) as cash_out_total")
                ->first();

            $cashRefundTotal = 0.0;
            if (Schema::hasTable('transaction_returns')) {
                $cashRefundTotal = round((float) DB::table('transaction_returns')
                    ->where('refund_method', 'cash_refund')
                    ->where('cash_source', 'active_shift')
                    ->where('refund_cashier_shift_id', $lockedShift->id)
                    ->where('status', 'completed')
                    ->sum('total_refund_amount'), 2);
            }

            $openingCash = round((float) $lockedShift->opening_cash, 2);
            $cashSalesTotal = round((float) ($salesRow->cash_sales_total ?? 0), 2);
            $midtransSalesTotal = round((float) ($salesRow->midtrans_sales_total ?? 0), 2);
            $cashInTotal = round((float) ($movementRow->cash_in_total ?? 0), 2);
            $cashOutTotal = round((float) ($movementRow->cash_out_total ?? 0), 2);

            $expectedClosingCash = round(
                $openingCash + $cashSalesTotal + $cashInTotal - $cashOutTotal - $cashRefundTotal,
                2
            );
            $actualClosingCash = round((float) $data['actual_closing_cash'], 2);
            $variance = round($actualClosingCash - $expectedClosingCash, 2);

            $closingNotes = isset($data['closing_notes']) && trim((string) $data['closing_notes']) !== ''
                ? trim((string) $data['closing_notes'])
                : null;

            $lockedShift->update([
                'status' => CashierShift::STATUS_CLOSED,
                'closed_by_user_id' => $actor->id,
                'is_force_closed' => $isForceClose,
                'cash_sales_total' => $cashSalesTotal,
                'midtrans_sales_total' => $midtransSalesTotal,
                'cash_in_total' => $cashInTotal,
                'cash_out_total' => $cashOutTotal,
                'cash_refund_total' => $cashRefundTotal,
                'expected_closing_cash' => $expectedClosingCash,
                'actual_closing_cash' => $actualClosingCash,
                'variance' => $variance,
                'closing_notes' => $closingNotes,
                'closed_at' => now(),
            ]);

            return $lockedShift->fresh(['branch', 'cashier', 'closedBy', 'cashMovements.user']);
        });
    }

    /**
     * Ambil daftar riwayat shift kasir dan ringkasan untuk Dashboard Admin (Shifts/Index.vue).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getShiftsReport(User $actor, array $filters, ?int $limit = 200): array
    {
        $startAt = Carbon::parse((string) ($filters['start_date'] ?? now()->startOfMonth()->toDateString()))->startOfDay();
        $endAt = Carbon::parse((string) ($filters['end_date'] ?? now()->endOfMonth()->toDateString()))->endOfDay();

        $query = CashierShift::forUser($actor)
            ->whereBetween('cashier_shifts.opened_at', [$startAt, $endAt]);

        if (! empty($filters['branch_id'])) {
            $query->where('cashier_shifts.branch_id', (int) $filters['branch_id']);
        } elseif (! empty($filters['regency_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('regency_id', $filters['regency_id']));
        } elseif (! empty($filters['province_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('province_id', $filters['province_id']));
        }

        $status = (string) ($filters['status'] ?? 'all');
        if (in_array($status, [CashierShift::STATUS_OPEN, CashierShift::STATUS_CLOSED], true)) {
            $query->where('cashier_shifts.status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('cashier_shifts.shift_code', 'like', "%{$search}%")
                    ->orWhereHas('cashier', fn (Builder $cq) => $cq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('branch', function (Builder $bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $summaryRow = (clone $query)
            ->selectRaw('COUNT(*) as total_shifts')
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_shifts")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_shifts")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'closed' THEN variance ELSE 0 END), 0) as total_variance")
            ->first();

        $shiftsQuery = (clone $query)
            ->with([
                'branch:id,code,name,regency_id,province_id',
                'cashier:id,name',
                'closedBy:id,name',
            ])
            ->withCount([
                'transactions as paid_transactions_count' => fn (Builder $q) => $q->where('payment_status', 'paid'),
                'transactions as pending_transactions_count' => fn (Builder $q) => $q->where('payment_status', 'pending'),
            ])
            ->orderByDesc('cashier_shifts.opened_at')
            ->orderByDesc('cashier_shifts.id');

        if ($limit !== null) {
            $shiftsQuery->limit($limit);
        }

        $shifts = $shiftsQuery->get()->map(fn (CashierShift $shift) => [
            'id' => $shift->id,
            'shift_code' => $shift->shift_code,
            'status' => $shift->status,
            'is_force_closed' => (bool) $shift->is_force_closed,
            'branch' => $shift->branch ? [
                'id' => $shift->branch->id,
                'code' => $shift->branch->code,
                'name' => $shift->branch->name,
            ] : null,
            'cashier' => $shift->cashier ? [
                'id' => $shift->cashier->id,
                'name' => $shift->cashier->name,
            ] : null,
            'closed_by' => $shift->closedBy ? [
                'id' => $shift->closedBy->id,
                'name' => $shift->closedBy->name,
            ] : null,
            'opening_cash' => (float) $shift->opening_cash,
            'cash_sales_total' => (float) $shift->cash_sales_total,
            'midtrans_sales_total' => (float) $shift->midtrans_sales_total,
            'cash_in_total' => (float) $shift->cash_in_total,
            'cash_out_total' => (float) $shift->cash_out_total,
            'cash_refund_total' => (float) $shift->cash_refund_total,
            'expected_closing_cash' => $shift->expected_closing_cash !== null ? (float) $shift->expected_closing_cash : null,
            'actual_closing_cash' => $shift->actual_closing_cash !== null ? (float) $shift->actual_closing_cash : null,
            'variance' => $shift->variance !== null ? (float) $shift->variance : null,
            'paid_transactions_count' => (int) $shift->paid_transactions_count,
            'pending_transactions_count' => (int) $shift->pending_transactions_count,
            'opened_at' => $shift->opened_at?->format('Y-m-d H:i:s'),
            'closed_at' => $shift->closed_at?->format('Y-m-d H:i:s'),
        ])->values()->all();

        return [
            'summary' => [
                'total_shifts' => (int) ($summaryRow->total_shifts ?? 0),
                'open_shifts' => (int) ($summaryRow->open_shifts ?? 0),
                'closed_shifts' => (int) ($summaryRow->closed_shifts ?? 0),
                'total_variance' => round((float) ($summaryRow->total_variance ?? 0), 2),
            ],
            'shifts' => $shifts,
        ];
    }

    /**
     * Generate kode shift unik: SHF-{BRANCH_CODE}-{YYYYMMDD}-{SEQ3}.
     */
    private function generateShiftCode(Branch $branch): string
    {
        $datePart = now()->format('Ymd');
        $prefix = "SHF-{$branch->code}-{$datePart}-";

        $existingCodes = CashierShift::query()
            ->where('shift_code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('shift_code');

        $maxSeq = 0;
        foreach ($existingCodes as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $code, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
            $exists = CashierShift::where('shift_code', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }
}
