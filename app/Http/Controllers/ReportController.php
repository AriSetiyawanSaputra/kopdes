<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\FinancialReportService;
use App\Services\ReportExportService;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService,
        private readonly ReportExportService $exportService,
    ) {}

    /**
     * Render the detailed sales & gross profit report based strictly on HPP snapshots (GET /reports/sales).
     */
    public function sales(ReportFilterRequest $request): Response
    {
        Gate::authorize('view-sales-report');

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $report = $this->reportService->getSalesReport($actor, $filters);
        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        return Inertia::render('Reports/Sales', [
            'summary' => $report['summary'],
            'branchSummaries' => $report['branch_summaries'],
            'transactions' => $report['transactions'],
            'drillDown' => $drillDown,
            'filters' => $filters,
            'permissions' => [
                'can_process_return' => $actor->isAdminNasional() || $actor->isAdminCabang(),
            ],
        ]);
    }

    /**
     * Export the sales & gross profit report as a formatted PDF or Excel (.xlsx) file
     * (GET /reports/sales/export/{format}).
     */
    public function exportSales(ReportFilterRequest $request, string $format): HttpResponse|StreamedResponse
    {
        Gate::authorize('view-sales-report');

        $actor = $request->user();
        $filters = $request->resolvedFilters();
        $report = $this->reportService->getSalesReport($actor, $filters, transactionLimit: null);

        return match ($format) {
            'pdf' => $this->exportService->exportSalesPdf($actor, $report, $filters),
            'excel' => $this->exportService->exportSalesExcel($actor, $report, $filters),
            default => abort(404),
        };
    }

    /**
     * Render the cross-branch member spending recap report as SHU data foundation (GET /reports/members).
     */
    public function members(ReportFilterRequest $request): Response
    {
        Gate::authorize('view-member-report');

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $report = $this->reportService->getMemberReport($actor, $filters);
        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        return Inertia::render('Reports/Members', [
            'summary' => $report['summary'],
            'shuFinancials' => $report['shu_financials'],
            'members' => $report['members'],
            'drillDown' => $drillDown,
            'filters' => $filters,
        ]);
    }

    /**
     * Export the cross-branch member spending recap (SHU) report as PDF or Excel (.xlsx)
     * (GET /reports/members/export/{format}).
     */
    public function exportMembers(ReportFilterRequest $request, string $format): HttpResponse|StreamedResponse
    {
        Gate::authorize('view-member-report');

        $actor = $request->user();
        $filters = $request->resolvedFilters();
        $report = $this->reportService->getMemberReport($actor, $filters);

        return match ($format) {
            'pdf' => $this->exportService->exportMembersPdf($actor, $report, $filters),
            'excel' => $this->exportService->exportMembersExcel($actor, $report, $filters),
            default => abort(404),
        };
    }
}
