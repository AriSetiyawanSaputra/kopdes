<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Province;
use App\Models\Regency;
use App\Services\ActivityLogService;
use App\Services\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Display a listing of KOPDES branches scoped to the authenticated user's region.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', Branch::class);

        $provinceFilter = $request->query('province_id');
        $regencyFilter = $request->query('regency_id');
        $statusFilter = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        Gate::authorize('filter-region', [
            $provinceFilter ?: null,
            $regencyFilter ?: null,
            null,
        ]);

        $query = Branch::forUser($user)
            ->with([
                'province:id,name',
                'regency:id,name',
                'district:id,name',
                'village:id,name',
            ])
            ->orderBy('code');

        if ($provinceFilter) {
            $query->where('province_id', $provinceFilter);
        }

        if ($regencyFilter) {
            $query->where('regency_id', $regencyFilter);
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $provinces = $user->isAdminNasional()
            ? Province::orderBy('name')->get(['id', 'name'])
            : Province::where('id', $user->province_id)->get(['id', 'name']);

        $regenciesQuery = Regency::orderBy('name');
        if ($user->isAdminNasional()) {
            if ($provinceFilter) {
                $regenciesQuery->where('province_id', $provinceFilter);
            }
        } elseif ($user->isAdminProvinsi()) {
            $regenciesQuery->where('province_id', $user->province_id);
        } else {
            $regenciesQuery->where('id', $user->regency_id);
        }

        $branches = $query->get()->map(fn (Branch $branch) => [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
            'village_id' => $branch->village_id,
            'district_id' => $branch->district_id,
            'regency_id' => $branch->regency_id,
            'province_id' => $branch->province_id,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'is_active' => (bool) $branch->is_active,
            'province' => $branch->province,
            'regency' => $branch->regency,
            'district' => $branch->district,
            'village' => $branch->village,
            'can_update' => $user->can('update', $branch),
        ]);

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'provinces' => $provinces,
            'regencies' => $regenciesQuery->get(['id', 'province_id', 'name']),
            'filters' => [
                'search' => $search,
                'province_id' => $provinceFilter ?? '',
                'regency_id' => $regencyFilter ?? '',
                'status' => $statusFilter ?? '',
            ],
            'permissions' => [
                'can_create' => $user->can('create', Branch::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new KOPDES branch.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        Gate::authorize('create', Branch::class);

        $provinces = $user->isAdminNasional()
            ? Province::orderBy('name')->get(['id', 'name'])
            : Province::where('id', $user->province_id)->get(['id', 'name']);

        return Inertia::render('Branches/Form', [
            'branch' => null,
            'provinces' => $provinces,
            'lockedScope' => [
                'province_id' => $user->isAdminNasional() ? null : $user->province_id,
                'regency_id' => $user->isAdminKabupaten() ? $user->regency_id : null,
            ],
        ]);
    }

    /**
     * Store a newly created KOPDES branch in storage.
     */
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $branch = $this->branchService->create($request->validated());

        return redirect()
            ->route('branches.index')
            ->with('success', "Cabang {$branch->name} ({$branch->code}) berhasil didaftarkan.");
    }

    /**
     * Show the form for editing the specified KOPDES branch.
     */
    public function edit(Request $request, Branch $branch): Response
    {
        $user = $request->user();

        Gate::authorize('update', $branch);

        $branch->load([
            'province:id,name',
            'regency:id,name',
            'district:id,name',
            'village:id,name',
        ]);

        $provinces = $user->isAdminNasional()
            ? Province::orderBy('name')->get(['id', 'name'])
            : Province::where('id', $user->province_id)->get(['id', 'name']);

        return Inertia::render('Branches/Form', [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'province_id' => $branch->province_id,
                'regency_id' => $branch->regency_id,
                'district_id' => $branch->district_id,
                'village_id' => $branch->village_id,
                'address' => $branch->address,
                'phone' => $branch->phone ?? '',
                'is_active' => (bool) $branch->is_active,
            ],
            'provinces' => $provinces,
            'lockedScope' => [
                'province_id' => $user->isAdminNasional() ? null : $user->province_id,
                'regency_id' => $user->isAdminKabupaten() ? $user->regency_id : null,
            ],
        ]);
    }

    /**
     * Update the specified KOPDES branch in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $updated = $this->branchService->update($branch, $request->validated());

        return redirect()
            ->route('branches.index')
            ->with('success', "Data cabang {$updated->name} ({$updated->code}) berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status of the specified KOPDES branch.
     */
    public function toggleActive(Request $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('toggleActive', $branch);

        $oldActive = (bool) $branch->is_active;
        $updated = $this->branchService->toggleActive($branch);
        $statusText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->activityLogService->record(
            action: ActivityLog::ACTION_BRANCH_STATUS_TOGGLED,
            description: "Cabang {$updated->name} ({$updated->code}) {$statusText}",
            actor: $request->user(),
            subject: $updated,
            oldValues: ['is_active' => $oldActive],
            newValues: ['is_active' => (bool) $updated->is_active],
        );

        return redirect()
            ->back()
            ->with('success', "Cabang {$updated->name} ({$updated->code}) berhasil {$statusText}.");
    }
}
