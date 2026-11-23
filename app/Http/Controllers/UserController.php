<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {}

    /**
     * Display a listing of users & cashiers within the actor's region/branch scope.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        Gate::authorize('viewAny', User::class);

        $roleFilter = $request->query('role');
        $branchFilter = $request->filled('branch_id') ? (int) $request->query('branch_id') : null;
        $search = trim((string) $request->query('search', ''));

        Gate::authorize('filter-region', [
            null,
            null,
            $branchFilter,
        ]);

        $query = User::forUser($actor)
            ->with([
                'province:id,name',
                'regency:id,name',
                'branch:id,code,name',
            ])
            ->orderBy('role')
            ->orderBy('name');

        if ($actor->isAdminProvinsi()) {
            $query->whereIn('role', [
                User::ROLE_ADMIN_KABUPATEN,
                User::ROLE_ADMIN_CABANG,
                User::ROLE_KASIR,
            ]);
        } elseif ($actor->isAdminKabupaten()) {
            $query->whereIn('role', [
                User::ROLE_ADMIN_CABANG,
                User::ROLE_KASIR,
            ]);
        } elseif ($actor->isAdminCabang()) {
            $query->where('role', User::ROLE_KASIR);
        }

        if ($roleFilter) {
            $query->where('role', $roleFilter);
        }

        if ($branchFilter !== null) {
            $query->where('branch_id', $branchFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('card_uid', 'like', "%{$search}%");
            });
        }

        $users = $query->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'province_id' => $u->province_id,
            'regency_id' => $u->regency_id,
            'branch_id' => $u->branch_id,
            'card_uid' => $u->card_uid,
            'is_active' => (bool) $u->is_active,
            'province' => $u->province,
            'regency' => $u->regency,
            'branch' => $u->branch,
            'can_update' => $actor->can('update', $u),
            'can_toggle_active' => $actor->can('toggleActive', $u),
        ]);

        $branches = Branch::forUser($actor)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'branches' => $branches,
            'creatableRoles' => $this->creatableRolesFor($actor),
            'filters' => [
                'search' => $search,
                'role' => $roleFilter ?? '',
                'branch_id' => $branchFilter ?? '',
            ],
        ]);
    }

    /**
     * Show the form for creating a new Admin or Cashier user.
     */
    public function create(Request $request): Response
    {
        $actor = $request->user();

        Gate::authorize('create', User::class);

        return Inertia::render('Users/Form', [
            'targetUser' => null,
            'creatableRoles' => $this->creatableRolesFor($actor),
            'provinces' => $actor->isAdminNasional()
                ? Province::orderBy('name')->get(['id', 'name'])
                : Province::where('id', $actor->province_id)->get(['id', 'name']),
            'regencies' => $this->accessibleRegenciesFor($actor),
            'branches' => Branch::forUser($actor)->orderBy('code')->get(['id', 'code', 'name', 'regency_id', 'province_id']),
        ]);
    }

    /**
     * Store a newly created user or cashier in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $created = $this->userManagementService->create($request->user(), $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', "Pengguna {$created->name} ({$created->role}) berhasil ditambahkan.");
    }

    /**
     * Show the form for editing the specified user or cashier.
     */
    public function edit(Request $request, User $user): Response
    {
        $actor = $request->user();

        Gate::authorize('update', $user);

        return Inertia::render('Users/Form', [
            'targetUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?? '',
                'role' => $user->role,
                'province_id' => $user->province_id ?? '',
                'regency_id' => $user->regency_id ?? '',
                'branch_id' => $user->branch_id ?? '',
                'card_uid' => $user->card_uid ?? '',
                'is_active' => (bool) $user->is_active,
            ],
            'creatableRoles' => $this->creatableRolesFor($actor),
            'provinces' => $actor->isAdminNasional()
                ? Province::orderBy('name')->get(['id', 'name'])
                : Province::where('id', $actor->province_id)->get(['id', 'name']),
            'regencies' => $this->accessibleRegenciesFor($actor),
            'branches' => Branch::forUser($actor)->orderBy('code')->get(['id', 'code', 'name', 'regency_id', 'province_id']),
        ]);
    }

    /**
     * Update the specified user or cashier in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $updated = $this->userManagementService->update($request->user(), $user, $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', "Data pengguna {$updated->name} berhasil diperbarui.");
    }

    /**
     * Toggle active status of a user account.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $updated = $this->userManagementService->toggleActive($user);
        $statusText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "Akun {$updated->name} berhasil {$statusText}.");
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function creatableRolesFor(User $actor): array
    {
        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => [
                ['value' => User::ROLE_ADMIN_NASIONAL, 'label' => 'Admin Nasional'],
                ['value' => User::ROLE_ADMIN_PROVINSI, 'label' => 'Admin Provinsi'],
                ['value' => User::ROLE_ADMIN_KABUPATEN, 'label' => 'Admin Kabupaten/Kota'],
                ['value' => User::ROLE_ADMIN_CABANG, 'label' => 'Admin Cabang KOPDES'],
                ['value' => User::ROLE_KASIR, 'label' => 'Kasir POS (RFID + PIN)'],
            ],
            User::ROLE_ADMIN_PROVINSI => [
                ['value' => User::ROLE_ADMIN_KABUPATEN, 'label' => 'Admin Kabupaten/Kota'],
            ],
            User::ROLE_ADMIN_KABUPATEN => [
                ['value' => User::ROLE_ADMIN_CABANG, 'label' => 'Admin Cabang KOPDES'],
            ],
            User::ROLE_ADMIN_CABANG => [
                ['value' => User::ROLE_KASIR, 'label' => 'Kasir POS (RFID + PIN)'],
            ],
            default => [],
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Regency>
     */
    private function accessibleRegenciesFor(User $actor)
    {
        $query = Regency::orderBy('name');

        if ($actor->isAdminProvinsi()) {
            $query->where('province_id', $actor->province_id);
        } elseif ($actor->isAdminKabupaten() || $actor->isAdminCabang()) {
            $query->where('id', $actor->regency_id);
        }

        return $query->get(['id', 'province_id', 'name']);
    }
}
