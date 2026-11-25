<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseFilterRequest;
use App\Http\Requests\StoreOperationalExpenseRequest;
use App\Http\Requests\UpdateOperationalExpenseRequest;
use App\Models\OperationalExpense;
use App\Services\FinancialReportService;
use App\Services\OperationalExpenseService;
use App\Services\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalExpenseController extends Controller
{
    public function __construct(
        private readonly OperationalExpenseService $expenseService,
        private readonly FinancialReportService $reportService,
        private readonly ReportExportService $exportService,
    ) {}

    /**
     * Tampilkan halaman daftar & ringkasan biaya operasional cabang (GET /expenses).
     */
    public function index(ExpenseFilterRequest $request): Response
    {
        Gate::authorize('viewAny', OperationalExpense::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $report = $this->expenseService->getExpensesReport($actor, $filters);
        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        $canManage = $actor->can('create', OperationalExpense::class);

        return Inertia::render('Expenses/Index', [
            'summary' => $report['summary'],
            'categorySummaries' => $report['category_summaries'],
            'expenses' => $report['expenses'],
            'categories' => OperationalExpense::categoryOptions(),
            'drillDown' => $drillDown,
            'filters' => $filters,
            'permissions' => [
                'can_manage' => $canManage,
                'requires_branch_selection' => $actor->isAdminNasional(),
            ],
        ]);
    }

    /**
     * Simpan biaya operasional baru (POST /expenses).
     */
    public function store(StoreOperationalExpenseRequest $request): RedirectResponse
    {
        $this->expenseService->create($request->user(), $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Biaya operasional berhasil dicatat.');
    }

    /**
     * Perbarui catatan biaya operasional (PUT /expenses/{operationalExpense}).
     */
    public function update(
        UpdateOperationalExpenseRequest $request,
        OperationalExpense $operationalExpense
    ): RedirectResponse {
        $this->expenseService->update($request->user(), $operationalExpense, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Data biaya operasional berhasil diperbarui.');
    }

    /**
     * Hapus (soft-delete) catatan biaya operasional (DELETE /expenses/{operationalExpense}).
     */
    public function destroy(OperationalExpense $operationalExpense): RedirectResponse
    {
        Gate::authorize('delete', $operationalExpense);

        $this->expenseService->delete($operationalExpense);

        return redirect()
            ->back()
            ->with('success', 'Data biaya operasional berhasil dihapus.');
    }

    /**
     * Ekspor laporan biaya operasional ke format PDF atau Excel (.xlsx)
     * (GET /expenses/export/{format}).
     */
    public function export(ExpenseFilterRequest $request, string $format): HttpResponse|StreamedResponse
    {
        Gate::authorize('viewAny', OperationalExpense::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();
        $report = $this->expenseService->getExpensesReport($actor, $filters, limit: null);

        return match ($format) {
            'pdf' => $this->exportService->exportExpensesPdf($actor, $report, $filters),
            'excel' => $this->exportService->exportExpensesExcel($actor, $report, $filters),
            default => abort(404),
        };
    }
}
