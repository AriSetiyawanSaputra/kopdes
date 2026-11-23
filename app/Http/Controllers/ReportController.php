<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\FinancialReportService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService,
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
        ]);
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
            'members' => $report['members'],
            'drillDown' => $drillDown,
            'filters' => $filters,
        ]);
    }
}
