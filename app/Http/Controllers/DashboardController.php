<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\FinancialReportService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService,
    ) {}

    /**
     * Render the hierarchical financial KPI dashboard with region drill-down (GET /dashboard).
     */
    public function index(ReportFilterRequest $request): Response
    {
        Gate::authorize('view-dashboard');

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $summary = $this->reportService->getDashboardSummary($actor, $filters);
        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        return Inertia::render('Dashboard/Index', [
            'kpis' => $summary['kpis'],
            'paymentBreakdown' => $summary['payment_breakdown'],
            'branchSummaries' => $summary['branch_summaries'],
            'drillDown' => $drillDown,
            'filters' => $filters,
        ]);
    }
}
