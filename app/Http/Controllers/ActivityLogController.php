<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService,
    ) {}

    /**
     * Tampilkan daftar Audit Trail Admin (read-only, terproteksi HasRegionScope) (GET /activity-logs).
     */
    public function index(ActivityLogFilterRequest $request): Response
    {
        Gate::authorize('viewAny', ActivityLog::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $startAt = Carbon::parse($filters['start_date'])->startOfDay();
        $endAt = Carbon::parse($filters['end_date'])->endOfDay();

        $query = ActivityLog::forUser($actor)
            ->with([
                'actor:id,name,email,role',
                'branch:id,code,name',
                'regency:id,name',
                'province:id,name',
            ])
            ->whereBetween('activity_logs.created_at', [$startAt, $endAt]);

        if (! empty($filters['branch_id'])) {
            $query->where('activity_logs.branch_id', (int) $filters['branch_id']);
        } elseif (! empty($filters['regency_id'])) {
            $query->where('activity_logs.regency_id', $filters['regency_id']);
        } elseif (! empty($filters['province_id'])) {
            $query->where('activity_logs.province_id', $filters['province_id']);
        }

        if ($filters['action'] !== '') {
            $query->where('activity_logs.action', $filters['action']);
        }

        if ($filters['actor_id'] !== null) {
            $query->where('activity_logs.actor_id', $filters['actor_id']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('activity_logs.description', 'like', "%{$search}%")
                    ->orWhere('activity_logs.action', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn (Builder $aq) => $aq->where('name', 'like', "%{$search}%"));
            });
        }

        $logs = $query
            ->orderByDesc('activity_logs.created_at')
            ->orderByDesc('activity_logs.id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => ActivityLog::ACTION_LABELS[$log->action] ?? $log->action,
                'description' => $log->description,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'role' => $log->actor->role,
                ] : null,
                'actor_role' => $log->actor_role,
                'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
                'subject_id' => $log->subject_id,
                'province_id' => $log->province_id,
                'regency_id' => $log->regency_id,
                'branch_id' => $log->branch_id,
                'branch' => $log->branch ? [
                    'id' => $log->branch->id,
                    'code' => $log->branch->code,
                    'name' => $log->branch->name,
                ] : null,
                'regency_name' => $log->regency?->name,
                'province_name' => $log->province?->name,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ]);

        $actors = User::forUser($actor)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        $actionOptions = collect(ActivityLog::ACTION_LABELS)
            ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
            ->values()
            ->all();

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'actors' => $actors,
            'actionOptions' => $actionOptions,
            'drillDown' => $drillDown,
            'filters' => $filters,
        ]);
    }
}
